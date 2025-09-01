<?php
// public/api/add_life.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // generate_unique_code8

// must be logged in
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }

// only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'bad_method']); exit; }

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }

// params
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id       = (int)($_SESSION['user_id'] ?? 0);
if ($tournament_id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
    // 1) torneo: deve essere open + prima del lock
    $tq = $pdo->prepare("
        SELECT status, lock_at, cost_per_life, max_lives_per_user
        FROM tournaments
        WHERE id = :tid
        LIMIT 1
    ");
    $tq->execute([':tid' => $tournament_id]);
    $t = $tq->fetch(PDO::FETCH_ASSOC);
    if (!$t) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
    if ($t['status'] !== 'open') { echo json_encode(['ok'=>false,'error'=>'locked']); exit; }
    if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) { echo json_encode(['ok'=>false,'error'=>'locked']); exit; }

    $cost = (int)$t['cost_per_life'];
    $maxL = (int)$t['max_lives_per_user'];

    // 2) deve esistere l’iscrizione
    $eq = $pdo->prepare("
        SELECT lives
        FROM tournament_enrollments
        WHERE user_id = :uid AND tournament_id = :tid
        LIMIT 1
    ");
    $eq->execute([':uid' => $user_id, ':tid' => $tournament_id]);
    $enroll = $eq->fetch(PDO::FETCH_ASSOC);
    if (!$enroll) { echo json_encode(['ok'=>false,'error'=>'not_enrolled']); exit; }

    $curLives = (int)$enroll['lives'];
    if ($maxL > 0 && $curLives >= $maxL) {
        echo json_encode(['ok'=>false,'error'=>'lives_limit']); exit;
    }

    // 3) transazione: addebito crediti, +1 vita, log movimento
    $pdo->beginTransaction();

    // 3.1) addebito: crediti sufficienti?
    $deb = $pdo->prepare("
        UPDATE utenti
        SET crediti = crediti - :c
        WHERE id = :uid AND crediti >= :c
    ");
    $deb->execute([':c' => $cost, ':uid' => $user_id]);
    if ($deb->rowCount() !== 1) {
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'error'=>'insufficient_funds']); exit;
    }

    // 3.2) +1 vita
    $up = $pdo->prepare("
        UPDATE tournament_enrollments
        SET lives = lives + 1
        WHERE user_id = :uid AND tournament_id = :tid
    ");
    $up->execute([':uid'=>$user_id, ':tid'=>$tournament_id]);

    // 3.3) log movimento
    $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
    $log = $pdo->prepare("
        INSERT INTO credit_movements
            (movement_code, user_id, tournament_id, type, amount, created_at)
        VALUES
            (:mcode, :uid, :tid, 'buy_life', :amount, NOW())
    ");
    $log->execute([
        ':mcode'  => $movCode,
        ':uid'    => $user_id,
        ':tid'    => $tournament_id,
        // amount: importo “speso” -> negativo
        ':amount' => -$cost,
    ]);

    // 3.4) leggo vite aggiornate e saldo aggiornato per la risposta
    $lv = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:uid AND tournament_id=:tid LIMIT 1");
    $lv->execute([':uid'=>$user_id, ':tid'=>$tournament_id]);
    $newLives = (int)$lv->fetchColumn();

    $hc = $pdo->prepare("SELECT crediti FROM utenti WHERE id=:uid LIMIT 1");
    $hc->execute([':uid'=>$user_id]);
    $headerCredits = (int)$hc->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'ok'             => true,
        'lives'          => $newLives,
        'header_credits' => $headerCredits
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    // se vuoi vedere il messaggio esatto per debugging:
    // echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]);
    echo json_encode(['ok'=>false,'error'=>'exception']);
}
