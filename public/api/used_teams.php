<?php
// public/api/used_teams.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT.'/src/config.php';
require_once $ROOT.'/src/db.php';

if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'error'=>'not_logged']); exit;
}

$user_id      = (int)$_SESSION['user_id'];
$tournament_id= isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$life_index   = isset($_GET['life_index']) ? (int)$_GET['life_index'] : -1;

if ($tournament_id<=0 || $life_index<0) {
  echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

try {
  // round corrente
  $tq = $pdo->prepare("SELECT current_round_no FROM tournaments WHERE id=? LIMIT 1");
  $tq->execute([$tournament_id]);
  $round_now = (int)$tq->fetchColumn();

  // === AGGIORNATO: squadre già usate con questa vita nei round precedenti
  //     usiamo l'ULTIMA selezione per ciascun round < round_now
  $stUsed = $pdo->prepare("
    SELECT
      CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END AS team_id
    FROM tournament_selections ts
    JOIN tournament_events te ON te.id = ts.event_id
    JOIN (
      SELECT round_no, MAX(id) AS max_id
      FROM tournament_selections
      WHERE tournament_id = ? AND user_id = ? AND life_index = ? AND round_no < ?
      GROUP BY round_no
    ) last ON last.max_id = ts.id
    WHERE (CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END) IS NOT NULL
  ");
  $stUsed->execute([$tournament_id, $user_id, $life_index, $round_now]);
  $used = array_map('intval',$stUsed->fetchAll(PDO::FETCH_COLUMN));

  // squadre bloccate per il round corrente (pick_locked=1 o is_active=0)
  $stBlk = $pdo->prepare("
    SELECT DISTINCT home_team_id, away_team_id
    FROM tournament_events
    WHERE tournament_id = ?
      AND round_no = ?
      AND (is_active=0 OR pick_locked=1)
  ");
  $stBlk->execute([$tournament_id, $round_now]);
  $blocked = [];
  foreach ($stBlk as $row) {
    if ($row['home_team_id']) $blocked[]=(int)$row['home_team_id'];
    if ($row['away_team_id']) $blocked[]=(int)$row['away_team_id'];
  }

  echo json_encode(['ok'=>true,'used'=>$used,'blocked'=>$blocked]);
} catch(Throwable $e){
  error_log('[used_teams] '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]);
}
