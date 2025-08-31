<?php
// public/api/enroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // generate_unique_code(), generate_unique_code8()

function jexit(array $p){ echo json_encode($p); exit; }

// Utente loggato
if (empty($_SESSION['user_id'])) { jexit(['ok'=>false,'error'=>'not_logged']); }
// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jexit(['ok'=>false,'error'=>'bad_method']); }
// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { jexit(['ok'=>false,'error'=>'bad_csrf']); }

// Parametri
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id       = (int)($_SESSION['user_id'] ?? 0);
if ($tournament_id <= 0 || $user_id <= 0) { jexit(['ok'=>false,'error'=>'bad_params']); }

try {
  // STAGE: torneo
  $tq = $pdo->prepare("
    SELECT status, lock_at, cost_per_life
    FROM tournaments
    WHERE id = :id
    LIMIT 1
  ");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t)                         { jexit(['ok'=>false,'error'=>'not_found','stage'=>'load_tournament']); }
  if (($t['status'] ?? '')!=='open'){ jexit(['ok'=>false,'error'=>'not_open','stage'=>'status']); }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    jexit(['ok'=>false,'error'=>'locked','stage'=>'lock_at']);
  }
  $cost = (int)$t['cost_per_life'];

  // STAGE: doppia iscrizione
  $ck = $pdo->prepare("
    SELECT 1 FROM tournament_enrollments
    WHERE user_id = :u AND tournament_id = :t
    LIMIT 1
  ");
  $ck->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  if ($ck->fetchColumn()) { jexit(['ok'=>false,'error'=>'already_enrolled','stage'=>'already_enrolled']); }

  // STAGE: schema check (registration_code presente?)
  $hasRegCode = false;
  $colQ = $pdo->prepare("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tournament_enrollments'
      AND COLUMN_NAME = 'registration_code'
  ");
  $colQ->execute();
  $hasRegCode = ((int)$colQ->fetchColumn() > 0);

  // STAGE: transazione
  $pdo->beginTransaction();

  // STAGE: addebito saldo (con controllo)
  $upd = $pdo->prepare("
    UPDATE utenti
    SET crediti = crediti - :c
    WHERE id = :u AND crediti >= :c
    LIMIT 1
  ");
  $upd->execute([':c'=>$cost, ':u'=>$user_id]);
  if ($upd->rowCount() !== 1) {
    $pdo->rollBack();
    jexit(['ok'=>false,'error'=>'insufficient_funds','stage'=>'debit']);
  }

  // STAGE: insert enrollment (con/senza registration_code)
  if ($hasRegCode) {
    $regCode = generate_unique_code($pdo, 'tournament_enrollments', 'registration_code');
    $ins = $pdo->prepare("
      INSERT INTO tournament_enrollments (user_id, tournament_id, registration_code, lives, created_at)
      VALUES (:u, :t, :rc, 1, NOW())
    ");
    $ins->execute([':u'=>$user_id, ':t'=>$tournament_id, ':rc'=>$regCode]);
  } else {
    $ins = $pdo->prepare("
      INSERT INTO tournament_enrollments (user_id, tournament_id, lives, created_at)
      VALUES (:u, :t, 1, NOW())
    ");
    $ins->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  }

  // STAGE: movimento crediti (amount negativo = addebito)
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

  jexit(['ok'=>true, 'redirect'=>'/torneo.php?id='.$tournament_id]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  // ritorna SEMPRE JSON con messaggio e stage (se impostato prima)
  jexit([
    'ok'    => false,
    'error' => 'exception',
    'msg'   => $e->getMessage()
  ]);
}
