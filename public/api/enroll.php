<?php
// public/api/enroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // generate_unique_code8()

// Requisiti base
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'bad_method']); exit; }

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }

// Parametri
$tid  = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$uid  = (int)($_SESSION['user_id'] ?? 0);
if ($tid <= 0 || $uid <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
  // 1) Torneo: deve essere OPEN e prima del lock
  $tq = $pdo->prepare("
    SELECT status, lock_at, cost_per_life
    FROM tournaments
    WHERE id = :tid
    LIMIT 1
  ");
  $tq->execute(['tid' => $tid]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);

  if (!$t) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
  if ($t['status'] !== 'open') { echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    echo json_encode(['ok'=>false,'error'=>'locked']); exit;
  }

  $cost = (int)$t['cost_per_life']; // crediti interi

  // 2) Evita doppia iscrizione
  $ck = $pdo->prepare("
    SELECT 1
    FROM tournament_enrollments
    WHERE user_id = :uid AND tournament_id = :tid
    LIMIT 1
  ");
  $ck->execute(['uid' => $uid, 'tid' => $tid]);
  if ($ck->fetchColumn()) {
    echo json_encode(['ok'=>false,'error'=>'already_enrolled']); exit;
  }

  // 3) Transazione: addebito, enroll, movimento
  $pdo->beginTransaction();

  // 3.1) addebito crediti (solo se saldo sufficiente)
  $upd = $pdo->prepare("
    UPDATE utenti
    SET crediti = crediti - :cost
    WHERE id = :uid AND crediti >= :cost
  ");
  $upd->execute(['cost' => $cost, 'uid' => $uid]);
  if ($upd->rowCount() !== 1) {
    $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'insufficient_funds']); exit;
  }

  // 3.2) iscrizione (lives = 1) — qui NON usiamo registration_code per escludere ogni ambiguità
  $ins = $pdo->prepare("
    INSERT INTO tournament_enrollments (user_id, tournament_id, lives, created_at)
    VALUES (:uid, :tid, 1, NOW())
  ");
  $ins->execute(['uid' => $uid, 'tid' => $tid]);

  // 3.3) log movimento (amount negativo = addebito) su credit_movements
  $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
  $mov = $pdo->prepare("
    INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
    VALUES (:mcode, :uid, :tid, 'enroll', :amount, NOW())
  ");
  $mov->execute([
    'mcode'  => $movCode,
    'uid'    => $uid,
    'tid'    => $tid,
    'amount' => -$cost
  ]);

  $pdo->commit();
  echo json_encode(['ok'=>true,'redirect'=>'/torneo.php?id=' . $tid]); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  // per debug: scommenta la riga sotto per vedere l'errore esatto, poi rimettila com'era
  // echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]); exit;
  echo json_encode(['ok'=>false,'error'=>'exception']); exit;
}
