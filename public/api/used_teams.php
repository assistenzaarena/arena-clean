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
  // league_id del torneo: ci serve per la mappa canon
  $q = $pdo->prepare("SELECT league_id, current_round_no FROM tournaments WHERE id=? LIMIT 1");
  $q->execute([$tournament_id]);
  $tinfo = $q->fetch(PDO::FETCH_ASSOC);
  $league_id = (int)($tinfo['league_id'] ?? 0);
  $round_now = (int)($tinfo['current_round_no'] ?? 1);

  // Squadre già usate con questa vita in round precedenti (canon)
  // Nota: facciamo LEFT JOIN sulla mappa per ottenere il canon_team_id di ciascun pick
  $stUsed = $pdo->prepare("
    SELECT DISTINCT
      COALESCE(m.canon_team_id,
               CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END) AS canon_id
    FROM tournament_selections ts
    JOIN tournament_events te
         ON te.id = ts.event_id
        AND te.tournament_id = ts.tournament_id
    LEFT JOIN admin_team_canon_map m
         ON m.league_id = :lg
        AND m.team_id = (CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END)
    WHERE ts.tournament_id = :tid
      AND ts.user_id = :uid
      AND ts.life_index = :life
      AND te.round_no < :round_now
      AND ts.finalized_at IS NOT NULL
      AND (CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END) IS NOT NULL
  ");
  $stUsed->execute([
    ':lg'=>$league_id,
    ':tid'=>$tournament_id,
    ':uid'=>$user_id,
    ':life'=>$life_index,
    ':round_now'=>$round_now
  ]);
  $used = array_map('intval',$stUsed->fetchAll(PDO::FETCH_COLUMN));

  // Squadre bloccate per il round corrente (canon) → eventi is_active=0 o pick_locked=1
  $stBlk = $pdo->prepare("
    SELECT DISTINCT
      COALESCE(mh.canon_team_id, te.home_team_id) AS home_canon,
      COALESCE(ma.canon_team_id, te.away_team_id) AS away_canon
    FROM tournament_events te
    LEFT JOIN admin_team_canon_map mh
           ON mh.league_id = :lg AND mh.team_id = te.home_team_id
    LEFT JOIN admin_team_canon_map ma
           ON ma.league_id = :lg AND ma.team_id = te.away_team_id
    WHERE te.tournament_id = :tid
      AND te.round_no = :r
      AND (te.is_active=0 OR te.pick_locked=1)
  ");
  $stBlk->execute([':lg'=>$league_id, ':tid'=>$tournament_id, ':r'=>$round_now]);
  $blocked = [];
  foreach ($stBlk as $row) {
    if (!empty($row['home_canon'])) $blocked[]=(int)$row['home_canon'];
    if (!empty($row['away_canon'])) $blocked[]=(int)$row['away_canon'];
  }

  echo json_encode(['ok'=>true,'used'=>array_values(array_unique($used)), 'blocked'=>array_values(array_unique($blocked))]);
} catch(Throwable $e){
  error_log('[used_teams] '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
