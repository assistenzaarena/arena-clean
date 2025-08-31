<?php
// public/api/unenroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // generate_unique_code8

// Deve essere loggato
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'bad_method']); exit; }

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }

// Parametri
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id = (int)$_SESSION['user_id'];
if ($tournament_id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
    // 1) Torneo deve essere OPEN e non oltre lock primo round
    $tq = $pdo->prepare("
        SELECT status, lock_at, cost_per_life
        FROM tournaments
        WHERE id = :id
        LIMIT 1
    ");
    $tq->execute([':id'=>$tournament_id]);
    $t = $tq->fetch(PDO::FETCH_ASSOC);

    if (!$t) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
    if ($t['status'] !== 'open') { echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }
    if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
        echo json_encode(['ok'=>false,'error'=>'locked']); exit;
    }

    // 2) Recupero l'iscrizione per sapere quante vite rimborsare
    $eq = $pdo->prepare("
        SELECT lives
        FROM tournament_enrollments
        WHERE user_id = :u AND tournament_id = :t
        LIMIT 1
    ");
    $eq->execute([':u'=>$user_id, ':t'=>$tournament_id]);
    $enroll = $eq->fetch(PDO::FETCH_ASSOC);

    if (!$enroll) { echo json_encode(['ok'=>false,'error'=>'not_enrolled']); exit; }

    $lives = (int)$enroll['lives'];
    if ($lives < 1) { $lives = 1; } // safety

    $refund = $lives * (int)$t['cost_per_life']; // rimborso totale in crediti

    // 3) Transazione: elimino iscrizione, accredito, log movimento
    $pdo->beginTransaction();

    // 3.1) Delete iscrizione
    $del = $pdo->prepare("
        DELETE FROM tournament_enrollments
        WHERE user_id = :u AND tournament_id = :t
        LIMIT 1
    ");
    $del->execute([':u'=>$user_id, ':t'=>$tournament_id]);

    // 3.2) Accredito crediti all'utente
    $up = $pdo->prepare("
        UPDATE utenti
        SET crediti = crediti + :r
        WHERE id = :u
    ");
    $up->execute([':r'=>$refund, ':u'=>$user_id]);

    // 3.3) Log movimento (accredito: importo positivo)
    $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
    $mov = $pdo->prepare("
        INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
        VALUES (:mcode, :uid, :tid, 'unenroll', :amount, NOW())
    ");
    $mov->execute([
        ':mcode'  => $movCode,
        ':uid'    => $user_id,
        ':tid'    => $tournament_id,
        ':amount' => $refund,   // accredito
    ]);

    $pdo->commit();

    echo json_encode(['ok'=>true, 'redirect'=>'/lobby.php']); exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]); exit;
    // echo json_encode(['ok'=>false,'error'=>'exception']); exit;
}
