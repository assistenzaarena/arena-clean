<?php
// public/api/enroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // per generate_unique_code8 (movements)

if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'error'=>'not_logged']); exit;
}
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
  // 1) Torneo deve essere OPEN e prima del lock
  $tq = $pdo->prepare("
    SELECT status, lock_at, cost_per_life
    FROM tournaments
    WHERE id = :id
    LIMIT 1
  ");
  $tq->execute([':id' => $tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);

  if (!$t) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
  if ($t['status'] !== 'open') { echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    echo json_encode(['ok'=>false,'error'=>'locked']); exit;
  }

  $cost = (int)$t['cost_per_life']; // in crediti (interi)

  // 2) Evita doppia iscrizione
  $ck = $pdo->prepare("
    SELECT id, lives
    FROM tournament_enrollments
    WHERE user_id = :u AND tournament_id = :t
    LIMIT 1
  ");
  $ck->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  if ($ck->fetch()) {
    echo json_encode(['ok'=>false,'error'=>'already_enrolled']); exit;
  }

  // 3) Transazione: addebito, enroll, movimento
  $pdo->beginTransaction();

  // 3.1) addebito crediti (solo se saldo sufficiente)
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

  // 3.2) iscrizione (lives = 1); created_at ha default CURRENT_TIMESTAMP
  $ins = $pdo->prepare("
    INSERT INTO tournament_enrollments (user_id, tournament_id, lives)
    VALUES (:u, :t, 1)
  ");
  $ins->execute([
    ':u' => $user_id,
    ':t' => $tournament_id
  ]);

  // 3.3) log movimento su credit_movements
  //    Tabella attuale: id, movement_code, user_id, tournament_id, type, amount, created_at
  //    (niente 'sign' → usiamo amount negativo per l’addebito)
  $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
  $mov = $pdo->prepare("
    INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount)
    VALUES (:mc, :u, :t, 'enroll', :amt)
  ");
  $mov->execute([
    ':mc'  => $movCode,
    ':u'   => $user_id,
    ':t'   => $tournament_id,
    ':amt' => -$cost        // addebito = negativo
  ]);

  $pdo->commit();
  echo json_encode(['ok'=>true, 'redirect'=>'/torneo.php?id='.(int)$tournament_id]); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  // DEBUG: lascia attivo finché testi, poi rimetti la riga "cieca"
  echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]); exit;
  // echo json_encode(['ok'=>false,'error'=>'exception']); exit;
}
