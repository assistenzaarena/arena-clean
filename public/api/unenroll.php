<?php
// public/api/unenroll.php
// Disiscrizione torneo: rimborsa i crediti all’utente, elimina l’enrollment
// [AGGIUNTA] Controllo su choices_locked: se =1 → blocco disiscrizione

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // generate_unique_code8

// helper: JSON oppure redirect (se arriva redirect=1)
$wantRedirect = !empty($_POST['redirect']);
$redirectUrl  = '/lobby.php';
$flashOk      = 'Disiscrizione effettuata.';
$flashErr     = 'Operazione non riuscita.';

/**
 * Risponde in JSON (default) oppure fa redirect alla lobby se $wantRedirect.
 */
function respond(bool $ok, array $payload = [], ?string $err = null) {
    global $wantRedirect, $redirectUrl, $flashOk, $flashErr;

    if ($wantRedirect) {
        if ($ok) {
            $_SESSION['flash'] = $flashOk;
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $_SESSION['flash'] = $flashErr;
            $_SESSION['flash_type'] = 'error';
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    // modalità JSON (AJAX/fetch)
    if ($ok) {
        echo json_encode(['ok' => true] + $payload);
    } else {
        echo json_encode(['ok' => false, 'error' => ($err ?? 'error')]);
    }
    exit;
}

// Deve essere loggato
if (empty($_SESSION['user_id'])) { respond(false, [], 'not_logged'); }

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(false, [], 'bad_method'); }

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { respond(false, [], 'bad_csrf'); }

// Parametri
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id = (int)$_SESSION['user_id'];
if ($tournament_id <= 0) { respond(false, [], 'bad_params'); }

try {
    // 1) Torneo deve essere OPEN, non oltre lock e non con choices_locked=1
    $tq = $pdo->prepare("
        SELECT status, lock_at, cost_per_life, choices_locked
        FROM tournaments
        WHERE id = ?
        LIMIT 1
    ");
    $tq->execute([$tournament_id]);
    $t = $tq->fetch(PDO::FETCH_ASSOC);

    if (!$t) { respond(false, [], 'not_found'); }
    if ($t['status'] !== 'open') { respond(false, [], 'not_open'); }
    if ((int)$t['choices_locked'] === 1) {
        respond(false, [], 'choices_locked'); // blocco manuale da admin
    }
    if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
        respond(false, [], 'locked');
    }

    // 2) Recupero l'iscrizione per sapere quante vite rimborsare
    $eq = $pdo->prepare("
        SELECT lives
        FROM tournament_enrollments
        WHERE user_id = ? AND tournament_id = ?
        LIMIT 1
    ");
    $eq->execute([$user_id, $tournament_id]);
    $enroll = $eq->fetch(PDO::FETCH_ASSOC);

    if (!$enroll) { respond(false, [], 'not_enrolled'); }

    $lives = (int)$enroll['lives'];
    if ($lives < 1) { $lives = 1; } // safety

    $refund = $lives * (int)$t['cost_per_life']; // rimborso totale in crediti

    // 3) Transazione: elimino iscrizione, accredito, log movimento
    $pdo->beginTransaction();

    // 3.1) Delete iscrizione
    $del = $pdo->prepare("
        DELETE FROM tournament_enrollments
        WHERE user_id = ? AND tournament_id = ?
        LIMIT 1
    ");
    $del->execute([$user_id, $tournament_id]);

    // 3.2) Accredito crediti all'utente
    $up = $pdo->prepare("
        UPDATE utenti
        SET crediti = crediti + ?
        WHERE id = ?
    ");
    $up->execute([$refund, $user_id]);

    // 3.3) Log movimento (accredito: importo positivo)
    $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
    $mov = $pdo->prepare("
        INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
        VALUES (?, ?, ?, 'unenroll', ?, NOW())
    ");
    $mov->execute([$movCode, $user_id, $tournament_id, $refund]);

    $pdo->commit();

    // Successo: JSON oppure redirect
    respond(true, ['redirect' => '/lobby.php']);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    // se vuoi loggare: error_log($e->getMessage());
    respond(false, [], 'exception');
}
