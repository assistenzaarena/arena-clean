<?php
// public/api/history_selections.php
// Ritorna le scelte finali per round e per vita dell'utente nel torneo selezionato.
// Output: { ok:true, tour:{id, name, code}, items:[{round, life_index, match, side, outcome, is_fallback}] }

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT.'/src/config.php';
require_once $ROOT.'/src/db.php';

function out($arr,$code=200){ http_response_code($code); echo json_encode($arr); exit; }

if (empty($_SESSION['user_id'])) out(['ok'=>false,'error'=>'not_logged'],401);

$uid = (int)$_SESSION['user_id'];
$tid = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
if ($tid <= 0) out(['ok'=>false,'error'=>'bad_params'],400);

// safety: verifica che l'utente abbia partecipato e che il torneo sia chiuso
$st = $pdo->prepare("SELECT t.id, t.name, t.tournament_code, t.status
                     FROM tournaments t
                     JOIN tournament_enrollments e ON e.tournament_id=t.id
                     WHERE t.id=? AND e.user_id=? LIMIT 1");
$st->execute([$tid,$uid]);
$t = $st->fetch(PDO::FETCH_ASSOC);
if (!$t) out(['ok'=>false,'error'=>'not_found'],404);
if (($t['status'] ?? '') !== 'closed') out(['ok'=>false,'error'=>'not_closed'],400);

// Selezioni finali per round/vita (ultima per round e per vita)
$sql = "
  SELECT ts.round_no, ts.life_index, ts.side,
         te.home_team_name, te.away_team_name, te.result_status,
         COALESCE(ts.is_fallback,0) AS is_fallback
  FROM tournament_selections ts
  JOIN tournament_events te ON te.id = ts.event_id
  JOIN (
    SELECT round_no, life_index, MAX(id) AS max_id
    FROM tournament_selections
    WHERE tournament_id=? AND user_id=?
    GROUP BY round_no, life_index
  ) x ON x.max_id = ts.id
  WHERE ts.tournament_id=? AND ts.user_id=?
  ORDER BY ts.round_no ASC, ts.life_index ASC
";
$q = $pdo->prepare($sql);
$q->execute([$tid,$uid,$tid,$uid]);

$items = [];
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
  $res = (string)($r['result_status'] ?? 'pending');
  $side = (string)$r['side'];
  // outcome: vinta (survive) / persa
  $win = in_array($res, ['draw','void','postponed','pending'], true)
      || ($res==='home_win' && $side==='home')
      || ($res==='away_win' && $side==='away');

  $items[] = [
    'round'       => (int)$r['round_no'],
    'life_index'  => (int)$r['life_index'],
    'match'       => (string)($r['home_team_name'].' vs '.$r['away_team_name']),
    'side'        => $side,
    'outcome'     => $win ? 'win' : 'lose',
    'is_fallback' => (int)$r['is_fallback'] ? 1 : 0,
  ];
}

out([
  'ok'=>true,
  'tour'=>[
    'id'=>(int)$t['id'],
    'name'=>(string)$t['name'],
    'code'=>(string)($t['tournament_code'] ?? sprintf('%05d',(int)$t['id'])),
  ],
  'items'=>$items
]);
