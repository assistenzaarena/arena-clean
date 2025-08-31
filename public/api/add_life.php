<?php
// public/api/add_life.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // generate_unique_code8()

// Deve essere loggato
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'bad_method']); exit; }

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }

// Parametri
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($tournament_id <= 0 || $user_id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
    // 1) Torneo deve essere OPEN e prima del lock, ricavo anche costi e limiti
    $tq = $pdo->prepare("
        SELECT status, lock_at, cost_per_life, max_lives_per_user
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

    $cost  = (int)$t['cost_per_life'];
    $limit = (int)$t['max_lives_per_user'];

    // 2) L’utente deve essere iscritto; controllo anche quante vite ha già
    $eq = $pdo->prepare("
        SELECT lives
        FROM tournament_enrollments
        WHERE user_id = :u AND tournament_id = :t
        LIMIT 1
    ");
    $eq->execute([':u'=>$user_id, ':t'=>$tournament_id]);
    $enroll = $eq->fetch(PDO::FETCH_ASSOC);
    if (!$enroll) { echo json_encode(['ok'=>false,'error'=>'not_enrolled']); exit; }

    $currentLives = (int)$enroll['lives'];
    if ($limit > 0 && $currentLives >= $limit) {
        echo json_encode(['ok'=>false,'error'=>'lives_limit']); exit;
    }

    // 3) Transazione: addebito crediti, incremento vite, log movimento
    $pdo->beginTransaction();

    // 3.1) Addebito crediti (solo se saldo sufficiente)
    $upd = $pdo->prepare("
        UPDATE utenti
        SET crediti = crediti - :c
        WHERE id = :u AND crediti >= :c
    ");
    $upd->execute([':c'=>$cost, ':u'=>$user_id]);
    if ($upd->rowCount() !== 1) {
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'error'=>'insufficient_funds']); exit;
    }

    // 3.2) Incremento vite
    $upLives = $pdo->prepare("
        UPDATE tournament_enrollments
        SET lives = lives + 1
        WHERE user_id = :u AND tournament_id = :t
        LIMIT 1
    ");
    $upLives->execute([':u'=>$user_id, ':t'=>$tournament_id]);

    // 3.3) Log movimento credito (addebito: importo negativo)
    $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
    $mov = $pdo->prepare("
        INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
        VALUES (:m, :u, :t, 'buy_life', :a, NOW())
    ");
    $mov->execute([
        ':m' => $movCode,
        ':u' => $user_id,
        ':t' => $tournament_id,
        ':a' => -$cost
    ]);

    // Nuovo numero vite da ritornare
    $newLives = $currentLives + 1;

    $pdo->commit();

    echo json_encode(['ok'=>true, 'lives'=>$newLives]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    // Per debug lato client puoi temporaneamente esporre il messaggio:
    // echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]);
    echo json_encode(['ok'=>false,'error'=>'exception']);
}
