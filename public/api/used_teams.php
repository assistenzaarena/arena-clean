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
  if ($round_now <= 0) $round_now = 1;

  // set di tutte le squadre del torneo (ID > 0)
  $stTeams = $pdo->prepare("
    SELECT DISTINCT x.team_id FROM (
      SELECT home_team_id AS team_id FROM tournament_events WHERE tournament_id=? AND home_team_id IS NOT NULL AND home_team_id > 0
      UNION
      SELECT away_team_id AS team_id FROM tournament_events WHERE tournament_id=? AND away_team_id IS NOT NULL AND away_team_id > 0
    ) x
  ");
  $stTeams->execute([$tournament_id, $tournament_id]);
  $teamsAll = array_map('intval', $stTeams->fetchAll(PDO::FETCH_COLUMN));

  // ciclo corrente per questa vita
  $stC = $pdo->prepare("
    SELECT COALESCE(MAX(cycle_no),0)
    FROM tournament_selections
    WHERE tournament_id=? AND user_id=? AND life_index=?
  ");
  $stC->execute([$tournament_id, $user_id, $life_index]);
  $curCycle = (int)$stC->fetchColumn();
  if ($curCycle <= 0) $curCycle = 1;

  // squadre già usate (scelte normali) in questo ciclo, finalizzate, in round precedenti
  $stU = $pdo->prepare("
    SELECT DISTINCT team_id
    FROM tournament_selections
    WHERE tournament_id=? AND user_id=? AND life_index=? AND cycle_no=?
      AND COALESCE(is_fallback,0)=0
      AND team_id IS NOT NULL
      AND finalized_at IS NOT NULL
      AND (round_no IS NULL OR round_no < ?)
  ");
  $stU->execute([$tournament_id, $user_id, $life_index, $curCycle, $round_now]);
  $usedInCycle = array_map('intval', $stU->fetchAll(PDO::FETCH_COLUMN));

  // rimanenti
  $startNewCycle = (count($teamsAll)>0 && count($usedInCycle)>=count($teamsAll));
  $remaining = $startNewCycle ? $teamsAll : array_values(array_diff($teamsAll, $usedInCycle));

  // squadre bloccate per il round corrente (pick_locked=1 o is_active=0)
  $stBlk = $pdo->prepare("
    SELECT DISTINCT home_team_id, away_team_id
    FROM tournament_events
    WHERE tournament_id = :tid
      AND round_no = :r
      AND (is_active=0 OR pick_locked=1)
  ");
  $stBlk->execute([':tid'=>$tournament_id, ':r'=>$round_now]);
  $blocked = [];
  foreach ($stBlk as $row) {
    if ($row['home_team_id']) $blocked[]=(int)$row['home_team_id'];
    if ($row['away_team_id']) $blocked[]=(int)$row['away_team_id'];
  }

  // check se esiste almeno una rimanente selezionabile nel round corrente
  $hasSelectableRemaining = false;
  if (!empty($remaining)) {
    $place = implode(',', array_fill(0, count($remaining), '?'));
    $sql = "
      SELECT COUNT(*) FROM tournament_events
      WHERE tournament_id = ?
        AND round_no = ?
        AND is_active = 1
        AND pick_locked = 0
        AND (
          home_team_id IN ($place)
          OR
          away_team_id IN ($place)
        )
    ";
    $params = array_merge([$tournament_id, $round_now], $remaining, $remaining);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $hasSelectableRemaining = ((int)$st->fetchColumn() > 0);
  }

  // ultima fallback storica (per evitare same fallback consecutive)
  $stLastFb = $pdo->prepare("
    SELECT team_id
    FROM tournament_selections
    WHERE tournament_id=? AND user_id=? AND life_index=? AND COALESCE(is_fallback,0)=1
    ORDER BY id DESC
    LIMIT 1
  ");
  $stLastFb->execute([$tournament_id, $user_id, $life_index]);
  $lastFallbackTeam = (int)($stLastFb->fetchColumn() ?: 0);

  if ($hasSelectableRemaining) {
    // modalità normale: used = già usate in questo ciclo (finalizzate)
    $used = $usedInCycle;
    echo json_encode(['ok'=>true,'used'=>$used,'blocked'=>$blocked,'fallback'=>false]);
  } else {
    // modalità fallback: NON grigiamo le used, blocchiamo solo le "blocked" e l'ultima fallback
    $disabledExtra = [];
    if ($lastFallbackTeam > 0) $disabledExtra[] = $lastFallbackTeam;
    echo json_encode(['ok'=>true,'used'=>[], 'blocked'=>array_values(array_unique(array_merge($blocked, $disabledExtra))), 'fallback'=>true, 'last_fallback_team'=>$lastFallbackTeam]);
  }

} catch(Throwable $e){
  error_log('[used_teams] '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
