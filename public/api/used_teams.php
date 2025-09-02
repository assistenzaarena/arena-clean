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

  // SCHEMA DETECTION per percorso "pieno"
  $cols = [];
  $ci = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tournament_selections'
      AND COLUMN_NAME IN ('round_no','team_id','is_fallback','cycle_no')
  ");
  $ci->execute();
  foreach ($ci->fetchAll(PDO::FETCH_COLUMN) as $c) { $cols[$c] = true; }
  $HAS_ROUND_NO = !empty($cols['round_no']);
  $HAS_TEAM_ID  = !empty($cols['team_id']);
  $HAS_FALLBACK = !empty($cols['is_fallback']);
  $HAS_CYCLE_NO = !empty($cols['cycle_no']);

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

  if ($HAS_CYCLE_NO && $HAS_TEAM_ID && $HAS_FALLBACK) {
    // PERCORSO PIENO: used = DISTINCT team_id nel ciclo corrente, SOLO scelte non fallback (giro reale)
    $stC = $pdo->prepare("
      SELECT COALESCE(MAX(cycle_no), 0)
      FROM tournament_selections
      WHERE tournament_id=? AND user_id=? AND life_index=?
    ");
    $stC->execute([$tournament_id, $user_id, $life_index]);
    $curCycle = (int)$stC->fetchColumn();
    if ($curCycle <= 0) $curCycle = 1;

    $stUsed = $pdo->prepare("
      SELECT DISTINCT team_id
      FROM tournament_selections
      WHERE tournament_id=? AND user_id=? AND life_index=? AND cycle_no=? AND COALESCE(is_fallback,0)=0 AND team_id IS NOT NULL
    ");
    $stUsed->execute([$tournament_id, $user_id, $life_index, $curCycle]);
    $used = array_map('intval', $stUsed->fetchAll(PDO::FETCH_COLUMN));

    echo json_encode(['ok'=>true,'used'=>$used,'blocked'=>$blocked]); exit;
  }

  // COMPAT MODE: used = squadre usate in round precedenti finalizzati (approssimato)
  $stUsed = $pdo->prepare("
    SELECT DISTINCT
      CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END AS team_id
    FROM tournament_selections ts
    JOIN tournament_events te ON te.id = ts.event_id
    WHERE ts.tournament_id = ?
      AND ts.user_id = ?
      AND ts.life_index = ?
      AND te.round_no < ?
      AND ts.finalized_at IS NOT NULL
      AND (CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END) IS NOT NULL
  ");
  $stUsed->execute([$tournament_id, $user_id, $life_index, $round_now]);
  $used = array_map('intval',$stUsed->fetchAll(PDO::FETCH_COLUMN));

  echo json_encode(['ok'=>true,'used'=>$used,'blocked'=>$blocked]); exit;

} catch(Throwable $e){
  error_log('[used_teams] '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'exception']); exit;
}
