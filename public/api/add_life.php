<?php
// public/api/add_life.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // generate_unique_code8 per il log

// must login
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }
// only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'bad_method']); exit; }
// csrf
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }

// params
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id = (int)$_SESSION['user_id'];
if ($tournament_id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
  // torneo deve essere open e prima del lock
  $tq = $pdo->prepare("
    SELECT status, lock_at, cost_per_life, max_lives_per_user
    FROM tournaments
    WHERE id = :id
    LIMIT 1
  ");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t)               { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
  if ($t['status']!=='open'){ echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()){
    echo json_encode(['ok'=>false,'error'=>'locked']); exit;
  }

  // deve essere già iscritto
  $eq = $pdo->prepare("
    SELECT lives
    FROM tournament_enrollments
    WHERE user_id = :u AND tournament_id = :t
    LIMIT 1
  ");
  $eq->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $enroll = $eq->fetch(PDO::FETCH_ASSOC);
  if (!$enroll) { echo json_encode(['ok'=>false,'error'=>'not_enrolled']); exit; }

  $lives   = (int)$enroll['lives'];
  $max     = (int)$t['max_lives_per_user'];
  $cost    = (int)$t['cost_per_life'];

  // limite vite per utente
  if ($max > 0 && $lives >= $max) {
    echo json_encode(['ok'=>false,'error'=>'lives_limit']); exit;
  }

  // transazione: addebito + lives++ + log
  $pdo->beginTransaction();

  // addebito saldo (solo se sufficiente)
  $upd = $pdo->prepare("UPDATE utenti SET crediti = crediti - :c WHERE id=:u AND crediti >= :c");
  $upd->execute([':c'=>$cost, ':u'=>$user_id]);
  if ($upd->rowCount() !== 1) {
    $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'insufficient_funds']); exit;
  }

  // incrementa vite
  $upL = $pdo->prepare("
    UPDATE tournament_enrollments
    SET lives = lives + 1
    WHERE user_id = :u AND tournament_id = :t
    LIMIT 1
  ");
  $upL->execute([':u'=>$user_id, ':t'=>$tournament_id]);

  // log movimento (addebito)
  $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
  $mov = $pdo->prepare("
    INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
    VALUES (:mcode, :uid, :tid, 'buy_life', :amount, NOW())
  ");
  $mov->execute([
    ':mcode'=>$movCode, ':uid'=>$user_id, ':tid'=>$tournament_id, ':amount'=>-$cost
  ]);

  // nuovo lives e nuovo saldo per risposta
  $curL = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $curL->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $newLives = (int)$curL->fetchColumn();

  $curC = $pdo->prepare("SELECT crediti FROM utenti WHERE id=:u LIMIT 1");
  $curC->execute([':u'=>$user_id]);
  $newCrediti = (int)$curC->fetchColumn();

  $pdo->commit();

  echo json_encode(['ok'=>true, 'lives'=>$newLives, 'crediti'=>$newCrediti]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
