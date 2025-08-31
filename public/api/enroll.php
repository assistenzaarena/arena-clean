<?php
// public/api/enroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // generate_unique_code8()

// Deve essere loggato
if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'error'=>'not_logged']); exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'bad_method']); exit;
}

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
}

// Parametri
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($tournament_id <= 0 || $user_id <= 0) {
  echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

try {
  // 1) Torneo deve essere OPEN e prima del lock (round 1)
  $tq = $pdo->prepare("
    SELECT status, lock_at, cost_per_life
    FROM tournaments
    WHERE id = :id
    LIMIT 1
  ");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);

  if (!$t)                  { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
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
  if ($ck->fetchColumn()) {
    echo json_encode(['ok'=>false,'error'=>'already_enrolled']); exit;
  }

  // 3) Transazione: addebito, insert enrollment, log movimento
  $pdo->beginTransaction();

  // 3.1) saldo sufficiente + addebito
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

  // 3.2) iscrizione (lives = 1)
  // se la colonna registration_code esiste: genera un codice a 5 cifre; se non esiste, questo campo viene ignorato dalla query
  $regCode = null;
  $hasReg  = false;

  // rileva dinamicamente se la colonna esiste (così non esplode in ambienti dove non l'hai ancora aggiunta)
  $colq = $pdo->query("SHOW COLUMNS FROM tournament_enrollments LIKE 'registration_code'");
  if ($colq && $colq->fetch(PDO::FETCH_ASSOC)) {
    $hasReg  = true;
    // se vuoi tenerlo NULL lasciarlo così; se vuoi generarlo:
    $regCode = generate_unique_code($pdo, 'tournament_enrollments', 'registration_code');
  }

  if ($hasReg) {
    $ins = $pdo->prepare("
      INSERT INTO tournament_enrollments (user_id, tournament_id, registration_code, lives, created_at)
      VALUES (:u, :t, :rc, 1, NOW())
    ");
    $ins->execute([
      ':u'  => $user_id,
      ':t'  => $tournament_id,
      ':rc' => $regCode
    ]);
  } else {
    $ins = $pdo->prepare("
      INSERT INTO tournament_enrollments (user_id, tournament_id, lives, created_at)
      VALUES (:u, :t, 1, NOW())
    ");
    $ins->execute([
      ':u' => $user_id,
      ':t' => $tournament_id
    ]);
  }

  // 3.3) log movimento (addebito) — NIENTE colonna 'sign'
  $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
  $mov = $pdo->prepare("
    INSERT INTO credit_movements
      (user_id, tournament_id, movement_code, type, amount, created_at)
    VALUES
      (:u, :t, :m, 'enroll', :a, NOW())
  ");
  // amount negativo = addebito
  $mov->execute([
    ':u' => $user_id,
    ':t' => $tournament_id,
    ':m' => $movCode,
    ':a' => -$cost
  ]);

  $pdo->commit();

  echo json_encode(['ok'=>true, 'redirect'=>'/torneo.php?id='.$tournament_id]); exit;

}} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  // DEBUG TEMPORANEO: mostra anche il messaggio SQL
  echo json_encode([
    'ok'    => false,
    'error' => 'exception',
    'msg'   => $e->getMessage()
  ]);
}
  echo json_encode(['ok'=>false,'error'=>'exception']); exit;
}
