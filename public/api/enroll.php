<?php
// public/api/enroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // per generate_unique_code(), generate_unique_code8()

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
  // 1) Leggo torneo e vincoli: deve essere OPEN e prima del lock (primo round)
  $tq = $pdo->prepare("SELECT status, lock_at, cost_per_life FROM tournaments WHERE id=:id LIMIT 1");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
  if ($t['status'] !== 'open') { echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    echo json_encode(['ok'=>false,'error'=>'locked']); exit;
  }

  $cost = (int)$t['cost_per_life']; // in crediti (interi)

  // 2) Evito doppia iscrizione
  $ck = $pdo->prepare("SELECT 1 FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $ck->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  if ($ck->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'already_enrolled']); exit; }

  // 3) Transazione: addebito, insert enrollment, log movimento
  $pdo->beginTransaction();

  // 3.1) saldo sufficiente + addebito
  $upd = $pdo->prepare("UPDATE utenti SET crediti = crediti - :c WHERE id=:u AND crediti >= :c");
  $upd->execute([':c'=>$cost, ':u'=>$user_id]);
  if ($upd->rowCount() !== 1) {
    $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'insufficient_funds']); exit;
  }

  // 3.2) iscrizione (lives=1) con registration_code a 5 cifre
  $regCode = generate_unique_code($pdo, 'tournament_enrollments', 'registration_code'); // 5 cifre univoco
  $ins = $pdo->prepare("
    INSERT INTO tournament_enrollments (user_id, tournament_id, registration_code, lives)
    VALUES (:u, :t, :code, 1)
  ");
  $ins->execute([':u'=>$user_id, ':t'=>$tournament_id, ':code'=>$regCode]);

  // 3.3) log movimento (addebito) — amount NEGATIVO, nessuna colonna 'sign'
  $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
  $mov = $pdo->prepare("
    INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
    VALUES (:m, :u, :t, 'enroll', :a, NOW())
  ");
  $mov->execute([
    ':m'=>$movCode,
    ':u'=>$user_id,
    ':t'=>$tournament_id,
    ':a'=> -1 * $cost   // addebito → negativo
  ]);

  $pdo->commit();

  echo json_encode(['ok'=>true, 'redirect'=>'/torneo.php?id='.$tournament_id]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
