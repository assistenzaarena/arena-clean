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

// helper per costruire lo slug del logo locale
function team_slug($name){
  $slug = strtolower($name);
  $slug = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$slug);
  return preg_replace('/[^a-z0-9]+/','',$slug) ?: 'team';
}

try {
  // Prendo l'ULTIMA selezione per ogni vita (life_index) dell'utente su questo torneo
  // usando MAX(id) come selezione più recente, poi recupero lato/evento per calcolare il logo
  $sql = "
    SELECT ts.life_index, ts.side, e.home_team_name, e.away_team_name
    FROM tournament_selections ts
    INNER JOIN (
      SELECT life_index, MAX(id) AS max_id
      FROM tournament_selections
      WHERE user_id = :u AND tournament_id = :t
      GROUP BY life_index
    ) x  ON x.max_id = ts.id
    JOIN tournament_events e ON e.id = ts.event_id
    ORDER BY ts.life_index ASC
  ";
  $q = $pdo->prepare($sql);
  $q->execute([':u'=>$uid, ':t'=>$tid]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  $out = [];
  foreach ($rows as $r){
    // nome squadra in base al lato scelto
    $name = ($r['side']==='home') ? ($r['home_team_name'] ?? '') : ($r['away_team_name'] ?? '');
    $slug = team_slug($name);
    $out[] = [
      'life_index' => (int)$r['life_index'],
      'logo_url'   => "/assets/logos/{$slug}.webp",
    ];
  }
  echo json_encode(['ok'=>true,'items'=>$out]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
