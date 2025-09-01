<?php
// public/api/compute_round.php
// Calcola il round corrente: determina quali vite sopravvivono o vengono eliminate
// in base a tournament_events.result_status e alle scelte finalizzate degli utenti.
//
// Accesso: solo admin. Risposta: JSON { ok:bool, ... } con "stage" in caso d'errore.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';   // require_admin()

function jexit(array $p, int $http = 200) {
  http_response_code($http);
  echo json_encode($p);
  exit;
}

// ---------- Guardie ----------
try { require_admin(); } catch (Throwable $e) { jexit(['ok'=>false,'error'=>'not_admin'], 403); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jexit(['ok'=>false,'error'=>'bad_method'], 405); }

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { jexit(['ok'=>false,'error'=>'bad_csrf'], 403); }

$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
if ($tournament_id <= 0) { jexit(['ok'=>false,'error'=>'bad_params'], 400); }

try {
  // 1) Carico torneo
  $stage = 'load_tournament';
  $st = $pdo->prepare("SELECT id, name, status, current_round_no FROM tournaments WHERE id = ? LIMIT 1");
  $st->execute([$tournament_id]);
  $T = $st->fetch(PDO::FETCH_ASSOC);
  if (!$T) jexit(['ok'=>false,'error'=>'not_found','stage'=>$stage], 404);
  if (($T['status'] ?? '') !== 'open') jexit(['ok'=>false,'error'=>'not_open','stage'=>$stage], 409);

  $roundNo = (int)($T['current_round_no'] ?? 1);

  // 2) Carico risultati eventi del torneo (devono avere result_status)
  $stage = 'load_events';
  $ev = $pdo->prepare("
    SELECT id, result_status
    FROM tournament_events
    WHERE tournament_id = ?
  ");
  $ev->execute([$tournament_id]);
  $events = $ev->fetchAll(PDO::FETCH_KEY_PAIR); // [event_id => result_status]
  if (!$events) { jexit(['ok'=>false,'error'=>'no_events','stage'=>$stage], 400); }

  // Mappa risultato -> lato vincente/neutral
  $resultKeep = [
    'home_win'  => 'home',
    'away_win'  => 'away',
    'draw'      => 'none',
    'postponed' => 'both',
    'void'      => 'both',
  ];

  // 3) Carico scelte finalizzate (finalized_at non NULL) per round corrente
  //    Se la colonna round_no NON esiste in tournament_selections, non filtro per round_no.
  $stage = 'check_round_no_column';
  $hasRoundNo = false;
  try {
    $col = $pdo->prepare("
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'tournament_selections'
        AND COLUMN_NAME  = 'round_no'
      LIMIT 1
    ");
    $col->execute();
    $hasRoundNo = (bool)$col->fetchColumn();
  } catch (Throwable $e) {
    // se fallisce il check, consideriamo che NON esista
    $hasRoundNo = false;
  }

  $stage = 'load_finalized_selections';
  if ($hasRoundNo) {
    $sql = "
      SELECT s.user_id, s.life_index, s.event_id, s.side
      FROM tournament_selections s
      WHERE s.tournament_id = ?
        AND s.finalized_at IS NOT NULL
        AND s.round_no = ?
    ";
    $params = [$tournament_id, $roundNo];
  } else {
    $sql = "
      SELECT s.user_id, s.life_index, s.event_id, s.side
      FROM tournament_selections s
      WHERE s.tournament_id = ?
        AND s.finalized_at IS NOT NULL
    ";
    $params = [$tournament_id];
  }
  $sel = $pdo->prepare($sql);
  $sel->execute($params);
  $finalized = $sel->fetchAll(PDO::FETCH_ASSOC);

  if (!$finalized) {
    jexit([
      'ok'=>true,
      'msg'=>'Nessuna selezione finalizzata nel round corrente: nessuna modifica.',
      'frozen'=>0,
      'survivors'=>0,
      'eliminated'=>0
    ]);
  }

  // 4) Valuto sopravvivenza per ogni (user_id, life_index)
  $stage = 'evaluate';
  $lifeOutcome = []; // key "uid:life" => 'keep' | 'elim' | 'neutral'
  foreach ($finalized as $r) {
    $uid  = (int)$r['user_id'];
    $life = (int)$r['life_index'];
    $eid  = (int)$r['event_id'];
    $side = (string)$r['side'];
    $key  = $uid . ':' . $life;

    $res = $events[$eid] ?? null; // result_status dell'evento
    if (!$res) {
      if (!isset($lifeOutcome[$key])) $lifeOutcome[$key] = 'neutral';
      continue;
    }

    $rule = $resultKeep[$res] ?? 'neutral';
    if ($rule === 'both') {
      $lifeOutcome[$key] = 'keep';
    } elseif ($rule === 'none') {
      $lifeOutcome[$key] = 'elim';
    } else {
      $lifeOutcome[$key] = ($side === $rule) ? 'keep' : 'elim';
    }
  }

  // 5) Raggruppo outcome per utente -> conteggio vite da mantenere
  $stage = 'group_outcome';
  $keepCount = []; // user_id => quante vite manteniamo
  $elimCount = 0;
  $kept      = 0;

  foreach ($lifeOutcome as $key => $out) {
    [$uidStr, $lifeStr] = explode(':', $key, 2);
    $uidI = (int)$uidStr;
    if (!isset($keepCount[$uidI])) $keepCount[$uidI] = 0;
    if ($out === 'keep') { $keepCount[$uidI]++; $kept++; }
    elseif ($out === 'elim') { $elimCount++; }
  }

  // 6) Applico risultati su enrollment.lives solo per utenti visti
  $stage = 'apply';
  $pdo->beginTransaction();

  $en = $pdo->prepare("SELECT user_id, lives FROM tournament_enrollments WHERE tournament_id = ?");
  $en->execute([$tournament_id]);
  $enrollments = $en->fetchAll(PDO::FETCH_ASSOC);

  $touched = 0;
  foreach ($enrollments as $row) {
    $uidI  = (int)$row['user_id'];
    $cur   = (int)$row['lives'];

    if (array_key_exists($uidI, $keepCount)) {
      $newLives = (int)$keepCount[$uidI];
      if ($newLives !== $cur) {
        $up = $pdo->prepare("UPDATE tournament_enrollments SET lives = ? WHERE user_id = ? AND tournament_id = ?");
        $up->execute([$newLives, $uidI, $tournament_id]);
        $touched += $up->rowCount();
      }
    }
  }

  $pdo->commit();

  jexit([
    'ok'=>true,
    'msg'=>'Calcolo round completato.',
    'round'=>$roundNo,
    'users_touched'=>$touched,
    'survivor_lives'=>$kept,
    'eliminated_lives'=>$elimCount
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  jexit(['ok'=>false,'error'=>'exception','stage'=>($stage ?? 'outer'),'msg'=>$e->getMessage()], 500);
}
