<?php
// admin/api/resolve_team_id.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__); // .../html
require_once $ROOT.'/src/guards.php';  require_admin();
require_once $ROOT.'/src/config.php';
require_once $ROOT.'/src/db.php';

header('Content-Type: application/json; charset=utf-8');

function out($js, $code=200){ http_response_code($code); echo json_encode($js); exit; }

/**
 * Ritorna il canon_team_id per (league_id, team_id/name).
 * - Se team_id è già un canon -> lo ritorna.
 * - Se è un alias del provider -> risale alla mappa.
 * - Se esiste match per nome normalizzato -> crea la mappa e ritorna il canon.
 * - Se non esiste nulla -> crea canon e mappa alias.
 */
function canonize_team_id(PDO $pdo, int $league_id, int $team_id, string $name): int {
  if ($league_id <= 0 || $team_id <= 0) return $team_id;

  // 1) già canon?
  $st = $pdo->prepare("SELECT canon_team_id FROM admin_team_canon WHERE league_id=? AND canon_team_id=? LIMIT 1");
  $st->execute([$league_id, $team_id]);
  if ($st->fetchColumn()) return $team_id;

  // 2) alias mappato?
  $st = $pdo->prepare("SELECT canon_team_id FROM admin_team_canon_map WHERE league_id=? AND team_id=? LIMIT 1");
  $st->execute([$league_id, $team_id]);
  $canon = (int)($st->fetchColumn() ?: 0);
  if ($canon > 0) return $canon;

  // 3) prova per nome normalizzato
  $norm = mb_strtolower(str_replace(' ', '', (string)$name));
  if ($norm !== '') {
    $st = $pdo->prepare("
      SELECT canon_team_id FROM admin_team_canon
      WHERE league_id=? AND LOWER(REPLACE(display_name,' ',''))=?
      LIMIT 1
    ");
    $st->execute([$league_id, $norm]);
    $canon = (int)($st->fetchColumn() ?: 0);
    if ($canon > 0) {
      $mk = $pdo->prepare("INSERT IGNORE INTO admin_team_canon_map (league_id, team_id, canon_team_id) VALUES (?,?,?)");
      $mk->execute([$league_id, $team_id, $canon]);
      return $canon;
    }
  }

  // 4) crea canon + mappa
  $ins = $pdo->prepare("INSERT INTO admin_team_canon (league_id, display_name) VALUES (?, ?)");
  $ins->execute([$league_id, $name ?: ('Team '.$team_id)]);
  $canon = (int)$pdo->lastInsertId();

  $mk = $pdo->prepare("INSERT IGNORE INTO admin_team_canon_map (league_id, team_id, canon_team_id) VALUES (?,?,?)");
  $mk->execute([$league_id, $team_id, $canon]);

  return $canon;
}

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

  // 1) dai nomi già presenti nel torneo corrente (canonizzati per evitare duplicati)
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
    $rawId = (int)$r['team_id']; $nm = (string)$r['name'];
    if ($rawId>0 && $nm!=='') {
      $canonId = canonize_team_id($pdo, $lg, $rawId, $nm);
      $sug[$canonId] = $nm; // dedup su canon id
    }
  }

  // 2) se esiste il catalogo canon per la lega, aggiungo anche quelli
  try {
    $check = $pdo->query("SELECT 1 FROM admin_team_canon LIMIT 1");
    if ($check) {
      // NB: niente norm_name; matching su display_name con LIKE + confronto normalizzato
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

  // Recupero league_id e nome lato evento per canonizzare l'ID inserito
  $lgq = $pdo->prepare("SELECT t.league_id, CASE WHEN ?='home' THEN e.home_team_name ELSE e.away_team_name END AS nm
                        FROM tournament_events e JOIN tournaments t ON t.id=e.tournament_id
                        WHERE e.id=? AND e.tournament_id=? LIMIT 1");
  $lgq->execute([$side, $eid, $tid]);
  $lgrow = $lgq->fetch(PDO::FETCH_ASSOC);
  $league_id = (int)($lgrow['league_id'] ?? 0);
  $team_name = (string)($lgrow['nm'] ?? '');

  // Normalizza al canon (se passi un ID provider lo traduce al canon)
  $team = canonize_team_id($pdo, $league_id, $team, $team_name);

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
