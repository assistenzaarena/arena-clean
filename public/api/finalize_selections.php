<?php
// public/api/finalize_selections.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT.'/src/config.php';
require_once $ROOT.'/src/db.php';
require_once $ROOT.'/src/utils.php';

if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['ok'=>false,'error'=>'bad_method']); exit; }

$tid  = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
if ($tid<=0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
  // scatta solo dopo/alla lock_at
  $tq = $pdo->prepare("SELECT lock_at FROM tournaments WHERE id=:id LIMIT 1");
  $tq->execute([':id'=>$tid]);
  $lockAt = $tq->fetchColumn();
  if (!$lockAt || strtotime($lockAt) > time()) { echo json_encode(['ok'=>false,'error'=>'not_yet']); exit; }

  $pdo->beginTransaction();

  // blocca tutte le scelte ancora aperte del torneo
  $up = $pdo->prepare("
    UPDATE tournament_selections
       SET locked=1, finalized_at=NOW()
     WHERE tournament_id=:t AND locked=0
  ");
  $up->execute([':t'=>$tid]);

  // logga un movimento "choices_locked" per tutti gli utenti che hanno scelte nel torneo
  $uq = $pdo->prepare("
    SELECT DISTINCT user_id FROM tournament_selections
     WHERE tournament_id=:t AND round_no=1
  ");
  $uq->execute([':t'=>$tid]);
  $users = $uq->fetchAll(PDO::FETCH_COLUMN);

  if ($users) {
    $mov = $pdo->prepare("
      INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
      VALUES (:m, :u, :t, 'choices_locked', 0, NOW())
    ");
    foreach ($users as $u){
      $code = generate_unique_code8($pdo,'credit_movements','movement_code',8);
      $mov->execute([':m'=>$code, ':u'=>$u, ':t'=>$tid]);
    }
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'locked'=>$up->rowCount(),'users'=>count($users)]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
