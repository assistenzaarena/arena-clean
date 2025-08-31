<?php
// public/api/enroll.php (versione con diagnostica)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';

if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'bad_method']); exit; }

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }

$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($tournament_id <= 0 || $user_id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
  // 1) torneo
  try {
    $tq = $pdo->prepare("SELECT status, lock_at, cost_per_life FROM tournaments WHERE id = :id LIMIT 1");
    $tq->execute([':id' => $tournament_id]);
    $t = $tq->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'exception','stage'=>'sel_tournament','msg'=>$e->getMessage()]); exit;
  }

  if (!$t) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
  if ($t['status'] !== 'open') { echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) { echo json_encode(['ok'=>false,'error'=>'locked']); exit; }

  $cost = (int)$t['cost_per_life'];

  // 2) già iscritto?
  try {
    $ck = $pdo->prepare("SELECT id FROM tournament_enrollments WHERE user_id = :u AND tournament_id = :t LIMIT 1");
    $ck->execute([':u'=>$user_id, ':t'=>$tournament_id]);
    $already = $ck->fetchColumn();
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'exception','stage'=>'ck_enroll','msg'=>$e->getMessage()]); exit;
  }
  if ($already) { echo json_encode(['ok'=>false,'error'=>'already_enrolled']); exit; }

  // 3) transazione
  $pdo->beginTransaction();

  // 3.1) addebito
  try {
    $upd = $pdo->prepare("UPDATE utenti SET crediti = crediti - :c WHERE id = :u AND crediti >= :c");
    $upd->execute([':c'=>$cost, ':u'=>$user_id]);
    if ($upd->rowCount() !== 1) {
      $pdo->rollBack();
      echo json_encode(['ok'=>false,'error'=>'insufficient_funds']); exit;
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'exception','stage'=>'upd_user','msg'=>$e->getMessage()]); exit;
  }

  // 3.2) insert enrollment (lives=1)
  try {
    $ins = $pdo->prepare("INSERT INTO tournament_enrollments (user_id, tournament_id, lives) VALUES (:u, :t, 1)");
    $ins->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'exception','stage'=>'ins_enroll','msg'=>$e->getMessage()]); exit;
  }

  // 3.3) movimento crediti (amount NEGATIVO, no colonna 'sign')
  try {
    $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
    $mov = $pdo->prepare("
      INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount)
      VALUES (:mc, :u, :t, 'enroll', :amt)
    ");
    $mov->execute([
      ':mc'  => $movCode,
      ':u'   => $user_id,
      ':t'   => $tournament_id,
      ':amt' => -$cost
    ]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'exception','stage'=>'ins_movement','msg'=>$e->getMessage()]); exit;
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'redirect'=>'/torneo.php?id='.(int)$tournament_id]); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false,'error'=>'exception','stage'=>'outer','msg'=>$e->getMessage()]); exit;
}
