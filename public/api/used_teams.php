<?php
// public/api/used_teams.php — versione CANON (Step C1)
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
$life_index   = isset($_GET['life_index'])    ? (int)$_GET['life_index']    : -1;

if ($tournament_id<=0 || $life_index<0) {
  echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

try {
  // round corrente + league_id (ci serve per la mappa CANON)
  $tq = $pdo->prepare("SELECT current_round_no, league_id FROM tournaments WHERE id=? LIMIT 1");
  $tq->execute([$tournament_id]);
  $rowT = $tq->fetch(PDO::FETCH_ASSOC);
  $round_now = (int)($rowT['current_round_no'] ?? 1);
  $league_id = (int)($rowT['league_id'] ?? 0);

  // 1) Squadre già usate (VITA SELEZIONATA) in round precedenti (FINALIZZATE) — su CANON
  // COALESCE(m.canon_team_id, id_raw_event) -> se manca la mappa, resta l'id raw dell'evento
  $stUsed = $pdo->prepare("
    SELECT DISTINCT
      COALESCE(m.canon_team_id,
        CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END
      ) AS canon_id
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
  $used = array_values(array_unique(array_map('intval', $stUsed->fetchAll(PDO::FETCH_COLUMN))));

  // 2) Squadre BLOCCATE per il round corrente (pick_locked=1 o is_active=0) — su CANON
  $stBlk = $pdo->prepare("
    SELECT
      COALESCE(mh.canon_team_id, te.home_team_id) AS home_canon,
      COALESCE(ma.canon_team_id, te.away_team_id) AS away_canon
    FROM tournament_events te
    LEFT JOIN admin_team_canon_map mh
      ON mh.league_id = :lg AND mh.team_id = te.home_team_id
    LEFT JOIN admin_team_canon_map ma
      ON ma.league_id = :lg AND ma.team_id = te.away_team_id
    WHERE te.tournament_id = :tid
      AND te.round_no = :r
      AND (te.is_active = 0 OR te.pick_locked = 1)
  ");
  $stBlk->execute([':lg'=>$league_id, ':tid'=>$tournament_id, ':r'=>$round_now]);
  $blocked = [];
  foreach ($stBlk as $r) {
    if (!empty($r['home_canon'])) $blocked[] = (int)$r['home_canon'];
    if (!empty($r['away_canon'])) $blocked[] = (int)$r['away_canon'];
  }
  $blocked = array_values(array_unique($blocked));

  echo json_encode(['ok'=>true,'used'=>$used,'blocked'=>$blocked,'canon'=>true]);
} catch(Throwable $e){
  error_log('[used_teams canon] '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
