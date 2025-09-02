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

// helper per slug logo locale (stessa logica di torneo.php)
function team_slug(string $name): string {
  $slug = strtolower($name);
  // transliterate se disponibile
  $t = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$slug);
  if ($t !== false) $slug = $t;
  $slug = preg_replace('/[^a-z0-9]+/','', $slug);
  return $slug ?: 'team';
}

try {
  // 1) round corrente
  $stR = $pdo->prepare("SELECT current_round_no FROM tournaments WHERE id=? LIMIT 1");
  $stR->execute([$tid]);
  $current_round_no = (int)$stR->fetchColumn();
  if ($current_round_no <= 0) $current_round_no = 1;

  // 2) selezioni SOLO del round corrente:
  //    - prendo per ogni life_index la selezione più recente (MAX id) *di questo round*
  $sql = "
    SELECT ts.life_index, ts.side, e.home_team_name, e.away_team_name
    FROM tournament_selections ts
    JOIN tournament_events e ON e.id = ts.event_id
    JOIN (
      SELECT life_index, MAX(id) AS max_id
      FROM tournament_selections
      WHERE user_id = ? AND tournament_id = ? AND round_no = ?
      GROUP BY life_index
    ) x ON x.max_id = ts.id
    WHERE ts.tournament_id = ? AND ts.user_id = ? AND ts.round_no = ?
    ORDER BY ts.life_index ASC
  ";
  $q = $pdo->prepare($sql);
  $q->execute([$uid, $tid, $current_round_no, $tid, $uid, $current_round_no]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  // 3) mappo in output per la UI (logo accanto al cuore)
  $out = [];
  foreach ($rows as $r){
    $name = ($r['side']==='home') ? ($r['home_team_name'] ?? '') : ($r['away_team_name'] ?? '');
    $slug = team_slug($name);
    $out[] = [
      'life_index' => (int)$r['life_index'],
      'logo_url'   => "/assets/logos/{$slug}.webp",
    ];
  }
  echo json_encode(['ok'=>true,'items'=>$out, 'round'=>$current_round_no]);
} catch (Throwable $e) {
  error_log('[get_selections] '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]);
}
