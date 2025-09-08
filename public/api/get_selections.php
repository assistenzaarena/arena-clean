<?php
// public/api/get_selections.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

// ---- helper out ----
function out($js, $code=200){ http_response_code($code); echo json_encode($js); exit; }

// ---- guard ----
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) out(['ok'=>false,'error'=>'not_logged'], 401);

$tournament_id = (int)($_GET['tournament_id'] ?? 0);
if ($tournament_id <= 0) out(['ok'=>false,'error'=>'bad_params'], 400);

// ---- piccole utility per il logo locale (stessa logica di torneo.php) ----
function team_slug(string $name): string {
  $slug = strtolower($name);
  $slug = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$slug);
  $slug = preg_replace('/[^a-z0-9]+/','', $slug);
  return $slug ?: 'team';
}
function team_logo_path(string $name): string {
  static $alias = [
    'juventus'=>'juve','inter'=>'inter','internazionale'=>'inter','acmilan'=>'milan',
    'milan'=>'milan','asroma'=>'roma','roma'=>'roma','hellasverona'=>'hellasverona','verona'=>'hellasverona',
    'atalanta'=>'atalanta','bologna'=>'bologna','cagliari'=>'cagliari','como'=>'como','cremonese'=>'cremonese',
    'fiorentina'=>'fiorentina','genoa'=>'genoa','lazio'=>'lazio','lecce'=>'lecce','napoli'=>'napoli',
    'parma'=>'parma','pisa'=>'pisa','sassuolo'=>'sassuolo','torino'=>'torino','udinese'=>'udinese',
  ];
  $base = team_slug($name);
  if ($base === 'ac' && stripos($name,'milan') !== false) $base = 'acmilan';
  if ($base === 'as' && stripos($name,'roma')  !== false) $base = 'asroma';
  if (strpos($base,'hellas') !== false || strpos($base,'verona') !== false) $base = 'hellasverona';
  $slug = $alias[$base] ?? $base;
  return "/assets/logos/{$slug}.webp";
}

try {
  // Prendo l'ULTIMA selezione per ogni vita (life_index) dell'utente in questo torneo
  // Non richiedo finalized_at: così dopo il salvataggio si rivede subito
  $sql = "
    SELECT ts.life_index, ts.event_id, ts.side,
           te.home_team_name, te.away_team_name
    FROM tournament_selections ts
    JOIN (
      SELECT life_index, MAX(id) AS max_id
      FROM tournament_selections
      WHERE user_id = :uid AND tournament_id = :tid
      GROUP BY life_index
    ) last ON last.max_id = ts.id
    JOIN tournament_events te
         ON te.id = ts.event_id
        AND te.tournament_id = :tid2
    ORDER BY ts.life_index ASC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':uid'=>$user_id, ':tid'=>$tournament_id, ':tid2'=>$tournament_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $items = [];
  foreach ($rows as $r) {
    $life = (int)$r['life_index'];
    $side = strtolower($r['side'] ?? '');
    $logo = ($side === 'home')
      ? team_logo_path((string)$r['home_team_name'])
      : team_logo_path((string)$r['away_team_name']);

    // sanity: se side non è valido, skip
    if ($side !== 'home' && $side !== 'away') continue;

    $items[] = [
      'life_index' => $life,
      'logo_url'   => $logo,
      // === AGGIUNTA per persistenza flag ===
      'event_id'   => (int)$r['event_id'],
      'side'       => $side,
    ];
  }

  out(['ok'=>true, 'items'=>$items]);

} catch (Throwable $e) {
  // logging leggero
  $logDir = $ROOT . '/storage/logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  @file_put_contents($logDir.'/get_selections_error.log',
    '['.date('c').'] '.$e->getMessage().PHP_EOL, FILE_APPEND);
  out(['ok'=>false,'error'=>'exception'], 500);
}
