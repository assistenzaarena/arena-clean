<?php
// public/admin/calc_round.php
// SCOPO: Calcolo manuale round corrente (Admin + CSRF) con tentativo di preload round+1.
// OUTPUT: JSON { ok, msg, round, survivors, closed, next_round_loaded }

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Allineo lo stile dei tuoi admin script
$ROOT = dirname(__DIR__, 1); // /var/www/html/public/admin -> /var/www/html
require_once $ROOT . '/src/guards.php';    // require_admin()
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/round_loader.php'; // nuovo helper
require_once $ROOT . '/src/payouts.php';      // <<< AGGIUNTA: chiusura & payout automatici

require_admin();

// ---------- Risposta JSON ----------
header('Content-Type: application/json; charset=utf-8');
function jexit(array $payload, int $code = 200) {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

// ---------- Metodo e CSRF ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jexit(['ok'=>false,'msg'=>'bad_method'], 405);
}
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  jexit(['ok'=>false,'msg'=>'bad_csrf'], 403);
}

// ---------- Input ----------
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$force         = isset($_POST['force']) ? (int)$_POST['force'] : 0;
if ($tournament_id <= 0) {
  jexit(['ok'=>false,'msg'=>'tournament_id mancante'], 400);
}

try {
  // Transazione per consistenza
  $pdo->beginTransaction();

  // 1) Lock torneo
  $tq = $pdo->prepare("SELECT id, status, current_round_no, choices_locked FROM tournaments WHERE id=:id FOR UPDATE");
  $tq->execute([':id'=>$tournament_id]);
  $torneo = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$torneo) {
    throw new RuntimeException('Torneo non trovato');
  }
  $round_no = (int)$torneo['current_round_no'];
  $choices_locked = (int)($torneo['choices_locked'] ?? 0);

  if (!$force && $choices_locked !== 1) {
    throw new RuntimeException('Precondizione non soddisfatta: choices_locked=1 (usa force=1 per forzare)');
  }

  // 2) Mappa risultati eventi del round corrente
  $stEv = $pdo->prepare("
    SELECT id, result_status
    FROM tournament_events
    WHERE tournament_id = :tid AND round_no = :r
  ");
  $stEv->execute([':tid'=>$tournament_id, ':r'=>$round_no]);
  $eventResults = [];
  foreach ($stEv as $row) {
    $eventResults[(int)$row['id']] = (string)($row['result_status'] ?? 'pending');
  }

  // 3) Selezioni finalizzate del round corrente
  $stSel = $pdo->prepare("
    SELECT user_id, life_index, event_id, side
    FROM tournament_selections
    WHERE tournament_id = :tid
      AND round_no = :r
      AND finalized_at IS NOT NULL
  ");
  $stSel->execute([':tid'=>$tournament_id, ':r'=>$round_no]);
  $selections = $stSel->fetchAll(PDO::FETCH_ASSOC);

  // 4) Calcolo perdite per utente
  //    Regola:
  //    - draw/void/postponed/pending => SOPRAVVIVE (non elimini vita)
  //    - home_win  => survive se side='home'
  //    - away_win  => survive se side='away'
  //    - altrimenti => perde 1 vita
  $losses = [];            // user_id => quante vite perdere
  $seenLifeByUser = [];    // user_id => set delle life_index selezionate

  foreach ($selections as $s) {
    $uid  = (int)$s['user_id'];
    $life = (int)$s['life_index'];
    $eid  = (int)$s['event_id'];
    $side = (string)$s['side'];

    if (!isset($seenLifeByUser[$uid])) $seenLifeByUser[$uid] = [];
    $seenLifeByUser[$uid][$life] = true;

    $res = $eventResults[$eid] ?? 'pending';

    $survive = in_array($res, ['draw','void','postponed','pending'], true)
            || ($res === 'home_win' && $side === 'home')
            || ($res === 'away_win' && $side === 'away');

    if (!$survive) {
      $losses[$uid] = ($losses[$uid] ?? 0) + 1;
    }
  }

  // 4b) No-pick = vita persa (vite attuali - selezioni fatte)
  $stEn = $pdo->prepare("SELECT user_id, lives FROM tournament_enrollments WHERE tournament_id = :tid FOR UPDATE");
  $stEn->execute([':tid'=>$tournament_id]);
  $enroll = $stEn->fetchAll(PDO::FETCH_ASSOC);
  foreach ($enroll as $e) {
    $uid = (int)$e['user_id'];
    $lives_now = (int)$e['lives'];
    $picked = isset($seenLifeByUser[$uid]) ? count($seenLifeByUser[$uid]) : 0;
    $miss = max(0, $lives_now - $picked);
    if ($miss > 0) {
      $losses[$uid] = ($losses[$uid] ?? 0) + $miss;
    }
  }

  // 5) Applica perdite
  $upd = $pdo->prepare("UPDATE tournament_enrollments SET lives = GREATEST(0, lives - :k) WHERE tournament_id = :tid AND user_id = :uid");
  foreach ($losses as $uid => $k) {
    if ($k > 0) $upd->execute([':k'=>$k, ':tid'=>$tournament_id, ':uid'=>$uid]);
  }

  // 6) Sopravvissuti
  $stSur = $pdo->prepare("SELECT COUNT(*) FROM tournament_enrollments WHERE tournament_id=:tid AND lives>0");
  $stSur->execute([':tid'=>$tournament_id]);
  $survivors = (int)$stSur->fetchColumn();

  $closed = 0;
  $next_round_loaded = null;

  if ($survivors <= 1) {
    // >>> MODIFICA: chiusura & payout automatici
    if ($pdo->inTransaction()) $pdo->commit();
    try {
      $payRes = tp_close_and_payout($pdo, $tournament_id, null);
      jexit([
        'ok'        => true,
        'msg'       => "Round {$round_no} calcolato. Torneo chiuso e payout eseguito.",
        'round'     => $round_no,
        'survivors' => $survivors,
        'closed'    => 1,
        'payout'    => $payRes,
        'next_round_loaded' => null
      ]);
    } catch (Throwable $e) {
      jexit(['ok'=>false, 'msg'=>'Errore payout: '.$e->getMessage(), 'stage'=>'payout'], 500);
    }
  } else {
    // Avanza round e sblocca scelte
    $new_round = $round_no + 1;
    $pdo->prepare("UPDATE tournaments SET current_round_no=:r, choices_locked=0 WHERE id=:id")
        ->execute([':r'=>$new_round, ':id'=>$tournament_id]);

    // Tenta preload della giornata successiva (solo round_type='matchday')
    $next_round_loaded = attempt_preload_next_round($pdo, $tournament_id, $round_no, $new_round);
    $msg = "Round {$round_no} calcolato. Sopravvissuti: {$survivors}. "
         . ($next_round_loaded ? "Precaricato round {$new_round}." : "Intervento admin richiesto per round {$new_round}.");

    $pdo->commit();

    jexit([
      'ok' => true,
      'msg' => $msg,
      'round' => $round_no,
      'survivors' => $survivors,
      'closed' => 0,
      'next_round_loaded' => $next_round_loaded
    ]);
  }

} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  error_log('[calc_round] ' . $e->getMessage());
  jexit(['ok'=>false, 'msg'=>'Errore: '.$e->getMessage()], 400);
}
