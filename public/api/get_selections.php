<?php
// public/api/get_selections.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT.'/src/config.php';
require_once $ROOT.'/src/db.php';

if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }

$uid = (int)$_SESSION['user_id'];
$tid = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
if ($tid<=0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

function team_slug($name){
  $slug = strtolower($name);
  $slug = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$slug);
  return preg_replace('/[^a-z0-9]+/','',$slug) ?: 'team';
}

try {
  // round corrente = 1 (estendibile)
  $q = $pdo->prepare("
    SELECT s.life_no, s.team_side, e.home_team_name, e.away_team_name
      FROM tournament_selections s
      JOIN tournament_events e ON e.id = s.event_id
     WHERE s.tournament_id=:t AND s.user_id=:u AND s.round_no=1
  ");
  $q->execute([':t'=>$tid, ':u'=>$uid]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  $out = [];
  foreach ($rows as $r){
    $name = $r['team_side']==='home' ? $r['home_team_name'] : $r['away_team_name'];
    $slug = team_slug($name);
    $out[] = [
      'life_no'  => (int)$r['life_no'],
      'logo_url' => "/assets/logos/{$slug}.webp",
    ];
  }
  echo json_encode(['ok'=>true,'items'=>$out]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
