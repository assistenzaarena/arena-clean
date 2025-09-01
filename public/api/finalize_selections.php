<?php
// public/api/finalize_selections.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // per generate_unique_code8()

// (a) Permessi minimi: serve una sessione valida (utente loggato)
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'not_logged']); exit;
}

// (b) Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'bad_method']); exit;
}

// (c) CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    echo json_encode(['ok' => false, 'error' => 'bad_csrf']); exit;
}

// (d) Parametri
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
if ($tournament_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'bad_params']); exit;
}

try {
    // 1) Verifica torneo: deve essere OPEN e oltre il lock
    $tq = $pdo->prepare("SELECT status, lock_at FROM tournaments WHERE id = ? LIMIT 1");
    $tq->execute([$tournament_id]);
    $t = $tq->fetch(PDO::FETCH_ASSOC);

    if (!$t) {
        echo json_encode(['ok' => false, 'error' => 'not_found']); exit;
    }
    if (($t['status'] ?? '') !== 'open') {
        echo json_encode(['ok' => false, 'error' => 'not_open']); exit;
    }
    if (empty($t['lock_at']) || strtotime($t['lock_at']) > time()) {
        // non ancora scaduto → non si può finalizzare
        echo json_encode(['ok' => false, 'error' => 'not_locked']); exit;
    }

    // 2) Finalizza SOLO le selezioni non finalizzate
    $pdo->beginTransaction();

    // Blocchiamo logicamente le righe da finalizzare
    $sel = $pdo->prepare("SELECT id, selection_code FROM tournament_selections WHERE tournament_id = ? AND finalized_at IS NULL");
    $sel->execute([$tournament_id]);
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;

    if ($rows) {
        // aggiorniamo ogni selezione, assegnando selection_code se mancante
        $upd = $pdo->prepare("UPDATE tournament_selections
                              SET selection_code = ?, 
                                  locked_at = COALESCE(locked_at, NOW()),
                                  finalized_at = NOW()
                              WHERE id = ?");
        foreach ($rows as $r) {
            $code = $r['selection_code'];
            if ($code === null || $code === '') {
                // 8 char alfanumerico univoco su colonna selection_code
                $code = generate_unique_code8($pdo, 'tournament_selections', 'selection_code', 8);
            }
            $upd->execute([$code, (int)$r['id']]);
            $updated += $upd->rowCount();
        }
    }

    $pdo->commit();

    echo json_encode([
        'ok'        => true,
        'finalized' => $updated
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    // Se vuoi loggare: error_log('[finalize] '.$e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'exception']);
}
