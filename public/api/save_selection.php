<?php
// public/api/save_selection.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // per generate_unique_code8()

// ---------- helper risposta ----------
function respond(array $js, int $http = 200) {
    http_response_code($http);
    echo json_encode($js);
    exit;
}

// ---------- guard ----------
if (empty($_SESSION['user_id'])) {
    respond(['ok'=>false,'error'=>'not_logged'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok'=>false,'error'=>'bad_method'], 405);
}
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    respond(['ok'=>false,'error'=>'bad_csrf'], 400);
}

// ---------- input ----------
$user_id      = (int)$_SESSION['user_id'];
$tournament_id= isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$event_id     = isset($_POST['event_id'])      ? (int)$_POST['event_id']      : 0;
$life_index   = isset($_POST['life_index'])    ? (int)$_POST['life_index']    : -1;
$side         = isset($_POST['side'])          ? strtolower((string)$_POST['side']) : '';

if ($tournament_id <= 0 || $event_id <= 0 || $life_index < 0 || !in_array($side, ['home','away'], true)) {
    respond(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
    // 1) torneo deve essere OPEN e prima del lock (+ controllo choices_locked)
    $st = $pdo->prepare("SELECT status, lock_at, max_lives_per_user, choices_locked FROM tournaments WHERE id = ? LIMIT 1");
    $st->execute([$tournament_id]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t)                    respond(['ok'=>false,'error'=>'not_found'], 404);
    if ((int)($t['choices_locked'] ?? 0) === 1) {
        respond(['ok'=>false,'error'=>'locked'], 400);
    }
    if ($t['status']!=='open')  respond(['ok'=>false,'error'=>'not_open'], 400);
    if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
        respond(['ok'=>false,'error'=>'locked'], 400);
    }

    // 2) iscrizione esistente e life_index valido
    $se = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id = ? AND tournament_id = ? LIMIT 1");
    $se->execute([$user_id, $tournament_id]);
    $enr = $se->fetch(PDO::FETCH_ASSOC);
    if (!$enr) respond(['ok'=>false,'error'=>'not_enrolled'], 400);

    $lives = (int)$enr['lives'];
    if ($life_index < 0 || $life_index >= $lives) {
        respond(['ok'=>false,'error'=>'life_out_of_range'], 400);
    }

    // 3) evento valido e attivo nel torneo
    $sv = $pdo->prepare("SELECT id FROM tournament_events WHERE id=? AND tournament_id=? AND is_active=1 LIMIT 1");
    $sv->execute([$event_id, $tournament_id]);
    if (!$sv->fetchColumn()) {
        respond(['ok'=>false,'error'=>'event_invalid'], 400);
    }

    // 4) upsert selezione
    $pdo->beginTransaction();

    $sx = $pdo->prepare("SELECT id, selection_code FROM tournament_selections
                         WHERE user_id=? AND tournament_id=? AND life_index=? LIMIT 1");
    $sx->execute([$user_id, $tournament_id, $life_index]);
    $row = $sx->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $up = $pdo->prepare("UPDATE tournament_selections
                             SET event_id=?, side=?, locked_at=NULL, finalized_at=NULL
                             WHERE id=?");
        $up->execute([$event_id, $side, (int)$row['id']]);
        $selCode = $row['selection_code'] ?: generate_unique_code8($pdo, 'tournament_selections','selection_code', 8);
        if (!$row['selection_code']) {
            $up2 = $pdo->prepare("UPDATE tournament_selections SET selection_code=? WHERE id=?");
            $up2->execute([$selCode, (int)$row['id']]);
        }
    } else {
        $selCode = generate_unique_code8($pdo, 'tournament_selections','selection_code', 8);
        $ins = $pdo->prepare("INSERT INTO tournament_selections
              (tournament_id, user_id, life_index, event_id, side, selection_code, created_at, locked_at, finalized_at)
              VALUES (?,?,?,?,?,?, NOW(), NULL, NULL)");
        $ins->execute([$tournament_id, $user_id, $life_index, $event_id, $side, $selCode]);
    }

    $pdo->commit();

    respond(['ok'=>true, 'selection_code'=>$selCode]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[save_selection] '.$e->getMessage());
    respond(['ok'=>false,'error'=>'exception'], 500);
}
