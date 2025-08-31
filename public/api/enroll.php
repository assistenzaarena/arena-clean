<?php
// public/api/enroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // generate_unique_code(), generate_unique_code8()

// Deve essere loggato
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'bad_method']); exit; }

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }

// Parametri
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id       = (int)($_SESSION['user_id'] ?? 0);
if ($tournament_id <= 0 || $user_id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
  // 1) Torneo deve essere OPEN e prima del lock (inizio 1° round)
  $tq = $pdo->prepare("
    SELECT status, lock_at, cost_per_life
    FROM tournaments
    WHERE id = :id
    LIMIT 1
  ");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);

  if (!$t)                     { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
  if ($t['status'] !== 'open') { echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    echo json_encode(['ok'=>false,'error'=>'locked']); exit;
  }

  $cost = (int)$t['cost_per_life']; // crediti interi

  // 2) Evita doppia iscrizione
  $ck = $pdo->prepare("
    SELECT 1
    FROM tournament_enrollments
    WHERE user_id = :u AND tournament_id = :t
    LIMIT 1
  ");
  $ck->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  if ($ck->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'already_enrolled']); exit; }

  // 3) Transazione: addebito → iscrizione (registration_code 5 cifre) → log movimento
  $pdo->beginTransaction();

  // 3.1) Addebito con controllo saldo
  $upd = $pdo->prepare("
    UPDATE utenti
    SET crediti = crediti - :c
    WHERE id = :u AND crediti >= :c
    LIMIT 1
  ");
  $upd->execute([':c'=>$cost, ':u'=>$user_id]);
  if ($upd->rowCount() !== 1) {
    $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'insufficient_funds']); exit;
  }

  // 3.2) Iscrizione: lives=1 + registration_code (5 cifre)
  $regCode = generate_unique_code($pdo, 'tournament_enrollments', 'registration_code');
  $ins = $pdo->prepare("
    INSERT INTO tournament_enrollments (user_id, tournament_id, registration_code, lives, created_at)
    VALUES (:u, :t, :rc, 1, NOW())
  ");
  $ins->execute([
    ':u'  => $user_id,
    ':t'  => $tournament_id,
    ':rc' => $regCode
  ]);

  // 3.3) Log movimento: addebito (amount NEGATIVO) su credit_movements
  $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
  $mov = $pdo->prepare("
    INSERT INTO credit_movements
      (user_id, tournament_id, movement_code, type, amount, created_at)
    VALUES
      (:u, :t, :mc, 'enroll', :amount, NOW())
  ");
  $mov->execute([
    ':u'      => $user_id,
    ':t'      => $tournament_id,
    ':mc'     => $movCode,
    ':amount' => -1 * $cost
  ]);

  $pdo->commit();

  echo json_encode(['ok'=>true, 'redirect'=>'/torneo.php?id='.$tournament_id]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
