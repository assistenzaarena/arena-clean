<?php
// /admin/ajax_user_selections.php — Ritorna scelte per utente, raggruppate per vita, con esito
require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$user_id       = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

header('Content-Type: application/json; charset=utf-8');

if ($tournament_id<=0 || $user_id<=0) {
  echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

// Query: round_no da ts.round_no se presente, altrimenti da te.round_no
$sql = "
  SELECT 
    ts.life_index,
    COALESCE(ts.round_no, te.round_no) AS round_no,
    ts.side,
    te.home_team_name, te.away_team_name,
    te.result_status
  FROM tournament_selections ts
  JOIN tournament_events te ON te.id = ts.event_id
  WHERE ts.tournament_id = :tid
    AND ts.user_id = :uid
  ORDER BY COALESCE(ts.round_no, te.round_no), ts.life_index, ts.id
";
$st = $pdo->prepare($sql);
$st->execute([':tid'=>$tournament_id, ':uid'=>$user_id]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Calcolo esito
$items = [];
foreach ($rows as $r) {
  $res = $r['result_status'] ?? 'pending';
  $side= $r['side'] ?? null;
  $outcome = 'wait';
  if ($res === 'home_win') {
    $outcome = ($side==='home') ? 'win' : 'lose';
  } elseif ($res === 'away_win') {
    $outcome = ($side==='away') ? 'win' : 'lose';
  } elseif (in_array($res, ['draw','void','postponed','pending'], true)) {
    $outcome = 'wait'; // neutro o ininfluente
  }
  $items[] = [
    'life_index'      => (int)$r['life_index'],
    'round_no'        => (int)$r['round_no'],
    'side'            => $side,
    'home_team_name'  => $r['home_team_name'],
    'away_team_name'  => $r['away_team_name'],
    'result_status'   => $res,
    'outcome'         => $outcome,
  ];
}

echo json_encode(['ok'=>true,'items'=>$items]);
