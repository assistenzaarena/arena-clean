<?php
// public/api/compute_round.php
//
// [SCOPO] Calcolo manuale del round corrente (admin):
//   - Per ogni utente: prende l'ULTIMA selezione finalizzata per ciascuna vita
//     e valuta sopravvivenza in base a result_status dell'evento.
//   - Aggiorna lives = #sopravvissuti
//   - Se rimane 1 solo utente vivo -> chiude il torneo (status='closed')
//     altrimenti avanza: current_round_no += 1, choices_locked = 0 (riapre scelte).
//
// [NOTE IMPLEMENTATIVE]
//   - Non usiamo round_no nelle selections (non presente). Per distinguere
//     il round corrente prendiamo per ogni vita la selection con finalized_at più recente.
//   - Idempotente: ricalcolare produce lo stesso risultato (perché lives vengono impostate
//     al numero di sopravvissuti calcolato, non incrementate/decrementate).

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';     // require_admin()
require_once $ROOT . '/src/utils.php';      // generate_unique_code8()

// --- helper JSON
function out(array $js, int $code = 200) {
  http_response_code($code);
  echo json_encode($js);
  exit;
}

// --- solo admin
require_admin();

// --- metodo e CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok'=>false,'error'=>'bad_method'], 405);
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) out(['ok'=>false,'error'=>'bad_csrf'], 403);

// --- input
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
if ($tournament_id <= 0) out(['ok'=>false,'error'=>'bad_params'], 400);

// opzionale: forzare chiusura o avanzamento anche se scelte non finalizzate (non usato ora)
// $force = !empty($_POST['force']) ? 1 : 0;

try {
  // 1) Carico torneo
  $st = $pdo->prepare("SELECT id, name, status, current_round_no FROM tournaments WHERE id = ? LIMIT 1");
  $st->execute([$tournament_id]);
  $T = $st->fetch(PDO::FETCH_ASSOC);
  if (!$T) out(['ok'=>false,'error'=>'not_found'], 404);
  if (($T['status'] ?? '') !== 'open') out(['ok'=>false,'error'=>'not_open'], 409);

  $roundNo = (int)($T['current_round_no'] ?? 1);

  // 2) Mappa risultati eventi del torneo
  $ev = $pdo->prepare("SELECT id, result_status FROM tournament_events WHERE tournament_id = ?");
  $ev->execute([$tournament_id]);
  $eventResult = []; // event_id => result_status
  while ($r = $ev->fetch(PDO::FETCH_ASSOC)) {
    $eventResult[(int)$r['id']] = $r['result_status'] ?? null;
  }

  // 3) Elenco iscritti (user_id, lives correnti)
  $en = $pdo->prepare("SELECT user_id, lives FROM tournament_enrollments WHERE tournament_id = ?");
  $en->execute([$tournament_id]);
  $enrollments = $en->fetchAll(PDO::FETCH_ASSOC);

  // Se non ci sono iscritti (strano per 'open'), chiudo il torneo
  if (!$enrollments) {
    $pdo->prepare("UPDATE tournaments SET status='closed', updated_at=NOW() WHERE id=?")->execute([$tournament_id]);
    out(['ok'=>true,'closed'=>true,'reason'=>'no_enrollments']);
  }

  // 4) Per ogni utente calcolo quante vite sopravvivono
  $survivorsByUser = []; // user_id => survivors_count

  foreach ($enrollments as $row) {
    $uid   = (int)$row['user_id'];
    $lives = max(0, (int)$row['lives']);

    if ($lives <= 0) {
      $survivorsByUser[$uid] = 0;
      continue;
    }

    // Prendo l'ULTIMA selezione finalizzata per ciascuna vita di questo utente
    // (no round_no: uso finalized_at DESC per vita_index)
    $sel = $pdo->prepare("
      SELECT s.life_index, s.event_id, s.side, s.finalized_at
      FROM tournament_selections s
      WHERE s.tournament_id = ? AND s.user_id = ? AND s.finalized_at IS NOT NULL
      ORDER BY s.life_index ASC, s.finalized_at DESC, s.id DESC
    ");
    $sel->execute([$tournament_id, $uid]);

    // Tengo solo la più recente per ciascuna life_index
    $lastByLife = []; // life_index => [event_id, side]
    while ($s = $sel->fetch(PDO::FETCH_ASSOC)) {
      $li = (int)$s['life_index'];
      if (!array_key_exists($li, $lastByLife)) {
        $lastByLife[$li] = [
          'event_id' => (int)$s['event_id'],
          'side'     => (string)$s['side'],
        ];
      }
    }

    // Valuto sopravvivenza
    $survive = 0;
    for ($li = 0; $li < $lives; $li++) {
      if (!isset($lastByLife[$li])) {
        // nessuna selezione finalizzata -> vita muore
        continue;
      }
      $evId = $lastByLife[$li]['event_id'];
      $side = $lastByLife[$li]['side'];

      $res  = $eventResult[$evId] ?? null;

      // Regole:
      // - home_win -> sopravvive se side='home'
      // - away_win -> sopravvive se side='away'
      // - draw/postponed/void -> sopravvive
      // - altro/null -> muore
      $ok = false;
      if ($res === 'home_win') {
        $ok = ($side === 'home');
      } elseif ($res === 'away_win') {
        $ok = ($side === 'away');
      } elseif ($res === 'draw' || $res === 'postponed' || $res === 'void') {
        $ok = true;
      } else {
        $ok = false;
      }

      if ($ok) $survive++;
    }

    $survivorsByUser[$uid] = $survive;
  }

  // 5) Applico i risultati con transazione
  $pdo->beginTransaction();

  // Aggiorno lives = #sopravvissuti
  $updLives = $pdo->prepare("UPDATE tournament_enrollments SET lives = ?, updated_at = NOW() WHERE user_id = ? AND tournament_id = ?");
  foreach ($survivorsByUser as $uid => $cnt) {
    $updLives->execute([$cnt, $uid, $tournament_id]);
  }

  // Log movimento informativo (0 crediti) round_result
  $insMov = $pdo->prepare("
    INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
    VALUES (?, ?, ?, 'round_result', 0, NOW())
  ");
  foreach (array_keys($survivorsByUser) as $uid) {
    $code = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
    $insMov->execute([$code, $uid, $tournament_id]);
  }

  // 6) Decido se chiudere o proseguire
  //   - conteggio utenti con lives > 0
  $st = $pdo->prepare("SELECT COUNT(*) FROM tournament_enrollments WHERE tournament_id = ? AND lives > 0");
  $st->execute([$tournament_id]);
  $aliveUsers = (int)$st->fetchColumn();

  if ($aliveUsers <= 1) {
    // chiudo il torneo
    $pdo->prepare("UPDATE tournaments SET status='closed', updated_at=NOW() WHERE id=?")->execute([$tournament_id]);
    $pdo->commit();
    out(['ok'=>true,'closed'=>true,'alive_users'=>$aliveUsers]);
  } else {
    // avanzo round e riapro scelte (l'admin aggiornerà lock_at per il nuovo round)
    $pdo->prepare("
      UPDATE tournaments
         SET current_round_no = current_round_no + 1,
             choices_locked   = 0,
             updated_at       = NOW()
       WHERE id = ?
    ")->execute([$tournament_id]);

    $pdo->commit();
    out(['ok'=>true,'closed'=>false,'alive_users'=>$aliveUsers,'next_round'=>$roundNo+1]);
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('[compute_round] '.$e->getMessage());
  out(['ok'=>false,'error'=>'exception'], 500);
}
