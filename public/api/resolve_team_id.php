<?php
// admin/api/resolve_team_id.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__, 2); // .../public
require_once $ROOT.'/src/guards.php';  require_admin();
require_once $ROOT.'/src/config.php';
require_once $ROOT.'/src/db.php';

header('Content-Type: application/json; charset=utf-8');

function out($js, $code=200){ http_response_code($code); echo json_encode($js); exit; }

/* ---- SUGGERIMENTI PER NOME ----
   GET /admin/api/resolve_team_id.php?action=suggest&tournament_id=..&league_id=..&q=...
   Ritorna: { ok:true, suggestions:[{team_id,name}, ...] }
*/
if (($_GET['action'] ?? '') === 'suggest') {
  $tid = (int)($_GET['tournament_id'] ?? 0);
  $lg  = (int)($_GET['league_id'] ?? 0);
  $q   = trim((string)($_GET['q'] ?? ''));

  if ($tid<=0 || $q==='') out(['ok'=>false, 'error'=>'bad_params'], 400);

  $like = '%'.$q.'%';

  $sug = [];

  // 1) dai nomi già presenti nel torneo corrente
  $sqlEv = "
    SELECT DISTINCT home_team_id AS team_id, home_team_name AS name
    FROM tournament_events
    WHERE tournament_id=? AND home_team_name LIKE ? AND home_team_id IS NOT NULL AND home_team_id > 0
    UNION
    SELECT DISTINCT away_team_id AS team_id, away_team_name AS name
    FROM tournament_events
    WHERE tournament_id=? AND away_team_name LIKE ? AND away_team_id IS NOT NULL AND away_team_id > 0
  ";
  $st = $pdo->prepare($sqlEv);
  $st->execute([$tid, $like, $tid, $like]);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $id = (int)$r['team_id']; $nm = (string)$r['name'];
    if ($id>0 && $nm!=='') $sug[$id] = $nm;
  }

  // 2) se esiste il catalogo canon per la lega, aggiungo anche quelli
  try {
    $check = $pdo->query("SELECT 1 FROM admin_team_canon LIMIT 1");
    if ($check) {
      // NB: niente norm_name; facciamo matching su display_name con LIKE
      //     + confronto normalizzato (spazi tolti, tutto lowercase)
      $sc = $pdo->prepare("
        SELECT canon_team_id, display_name
        FROM admin_team_canon
        WHERE league_id = ?
          AND (
                display_name LIKE ?
                OR LOWER(REPLACE(display_name,' ','')) = LOWER(REPLACE(?, ' ', ''))
              )
        ORDER BY display_name ASC
        LIMIT 50
      ");
      $sc->execute([$lg, $like, $q]);
      foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int)$r['canon_team_id']; $nm = (string)$r['display_name'];
        if ($id>0 && $nm!=='') $sug[$id] = $nm;
      }
    }
  } catch (Throwable $e) {
    // tabelle canon non presenti -> ignoro
  }

  $out = [];
  foreach ($sug as $id=>$nm) { $out[] = ['team_id'=>$id, 'name'=>$nm]; }

  // ✅ CONTRATTO STABILE: sempre ok:true; lista vuota se nessun match
  out(['ok' => true, 'suggestions' => $out ?: []], 200);
}

/* ---- APPLICA ID LATO EVENTO ----
   POST tournament_id, event_id, side ('home'|'away'), team_id (numero)
   Ritorna: { ok:true }
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tid  = (int)($_POST['tournament_id'] ?? 0);
  $eid  = (int)($_POST['event_id'] ?? 0);
  $side = strtolower((string)($_POST['side'] ?? ''));
  $team = (int)($_POST['team_id'] ?? 0);

  if ($tid<=0 || $eid<=0 || !in_array($side, ['home','away'], true) || $team<=0) {
    out(['ok'=>false, 'error'=>'bad_params'], 400);
  }

  // Verifica che l'evento appartenga al torneo
  $chk = $pdo->prepare("SELECT id FROM tournament_events WHERE id=? AND tournament_id=? LIMIT 1");
  $chk->execute([$eid, $tid]);
  if (!$chk->fetch(PDO::FETCH_ASSOC)) { out(['ok'=>false,'error'=>'event_not_found'],404); }

  // Aggiorna il campo corretto
  if ($side === 'home') {
    $up = $pdo->prepare("UPDATE tournament_events SET home_team_id=? WHERE id=? AND tournament_id=?");
  } else {
    $up = $pdo->prepare("UPDATE tournament_events SET away_team_id=? WHERE id=? AND tournament_id=?");
  }
  $up->execute([$team, $eid, $tid]);

  out(['ok'=>true, 'applied'=>['event_id'=>$eid,'side'=>$side,'team_id'=>$team]]);
}

out(['ok'=>false,'error'=>'bad_request'], 400);
