<?php
// public/api/save_selection.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // per generate_unique_code8()

// ---------- helper risposta ----------
function respond(array $js, int $http = 200) {
    http_response_code($http);
    echo json_encode($js);
    exit;
}

// ---------- guard ----------
if (empty($_SESSION['user_id'])) {
    respond(['ok'=>false,'error'=>'not_logged'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok'=>false,'error'=>'bad_method'], 405);
}
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    respond(['ok'=>false,'error'=>'bad_csrf'], 400);
}

// ---------- input ----------
$user_id       = (int)$_SESSION['user_id'];
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$event_id      = isset($_POST['event_id'])      ? (int)$_POST['event_id']      : 0;
$life_index    = isset($_POST['life_index'])    ? (int)$_POST['life_index']    : -1;
$side          = isset($_POST['side'])          ? strtolower((string)$_POST['side']) : '';
$team_id_post  = isset($_POST['team_id'])       ? (int)$_POST['team_id']       : 0; // può essere RAW o CANON

if ($tournament_id <= 0 || $event_id <= 0 || $life_index < 0 || !in_array($side, ['home','away'], true)) {
    respond(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
    // 1) torneo deve essere OPEN e prima del lock (+ controllo choices_locked)
    $st = $pdo->prepare("SELECT status, lock_at, max_lives_per_user, choices_locked, current_round_no, league_id FROM tournaments WHERE id = ? LIMIT 1");
    $st->execute([$tournament_id]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t)                    respond(['ok'=>false,'error'=>'not_found'], 404);
    if ((int)($t['choices_locked'] ?? 0) === 1) {
        respond(['ok'=>false,'error'=>'locked'], 400);
    }
    if (($t['status'] ?? '')!=='open')  respond(['ok'=>false,'error'=>'not_open'], 400);
    if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
        respond(['ok'=>false,'error'=>'locked'], 400);
    }
    $current_round_no = (int)($t['current_round_no'] ?? 1);
    $league_id        = (int)($t['league_id'] ?? 0);

    // 2) iscrizione esistente e life_index valido
    $se = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id = ? AND tournament_id = ? LIMIT 1");
    $se->execute([$user_id, $tournament_id]);
    $enr = $se->fetch(PDO::FETCH_ASSOC);
    if (!$enr) respond(['ok'=>false,'error'=>'not_enrolled'], 400);

    $lives = (int)$enr['lives'];
    if ($life_index < 0 || $life_index >= $lives) {
        respond(['ok'=>false,'error'=>'life_out_of_range'], 400);
    }

    // 3) evento valido e attivo nel torneo (recupero anche dati utili per le regole)
    $sv = $pdo->prepare("SELECT id, round_no, is_active, pick_locked, home_team_id, away_team_id
                         FROM tournament_events
                         WHERE id=? AND tournament_id=? AND is_active=1
                         LIMIT 1");
    $sv->execute([$event_id, $tournament_id]);
    $ev = $sv->fetch(PDO::FETCH_ASSOC);
    if (!$ev) {
        respond(['ok'=>false,'error'=>'event_invalid'], 400);
    }

    // ======= Normalizzazione ID squadra scelto =======
    // RAW atteso dall'evento (per coerenza lato DB/constraint)
    $raw_expected = ($side === 'home') ? (int)$ev['home_team_id'] : (int)$ev['away_team_id'];

    // Se è arrivato un team_id dal client, può essere RAW o CANON.
    // - Se coincide con il RAW atteso → ok.
    // - Se è diverso e ho league_id → provo a mappare canon->raw e verifico che combaci col RAW atteso.
    if ($team_id_post > 0 && $team_id_post !== $raw_expected && $league_id > 0) {
        $m = $pdo->prepare("SELECT team_id FROM admin_team_canon_map WHERE league_id=? AND canon_team_id=? LIMIT 1");
        $m->execute([$league_id, $team_id_post]);
        $maybeRaw = (int)$m->fetchColumn();
        if ($maybeRaw === $raw_expected) {
            // ok, l'utente ha inviato un canon id coerente: accetto
        } else {
            // ignoro l'id inviato (prendo quello dall'evento per evitare inconsistenze)
            $team_id_post = 0;
        }
    }
    // In generale usiamo come "scelto" quello dell'evento (coerente col lato server)
    $team_chosen_raw = $raw_expected;

    // CANON per regole “no-duplica” e salvataggio (se presente la colonna team_id)
    $team_chosen_canon = $team_chosen_raw;
    if ($league_id > 0) {
        $cst = $pdo->prepare("SELECT canon_team_id FROM admin_team_canon_map WHERE league_id=? AND team_id=? LIMIT 1");
        $cst->execute([$league_id, $team_chosen_raw]);
        $canon = $cst->fetchColumn();
        if ($canon !== false && $canon !== null) $team_chosen_canon = (int)$canon;
    }
    // ================================================

    // ============ REGOLA "NO TEAM DUPLICATO PER VITA" CON CICLI + FALLBACK ============
    // -- SCHEMA DETECTION: abilito percorso "pieno" se le colonne ci sono
    $cols = [];
    $ci = $pdo->prepare("
      SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'tournament_selections'
        AND COLUMN_NAME IN ('round_no','team_id','is_fallback','cycle_no')
    ");
    $ci->execute();
    foreach ($ci->fetchAll(PDO::FETCH_COLUMN) as $c) { $cols[$c] = true; }
    $HAS_ROUND_NO = !empty($cols['round_no']);
    $HAS_TEAM_ID  = !empty($cols['team_id']);
    $HAS_FALLBACK = !empty($cols['is_fallback']);
    $HAS_CYCLE_NO = !empty($cols['cycle_no']);

    // set di TUTTE le squadre CANON del torneo (serve per le rimanenti)
    $stTeamsCanon = $pdo->prepare("
      SELECT DISTINCT COALESCE(mh.canon_team_id, te.home_team_id) AS canon_id
      FROM tournament_events te
      LEFT JOIN admin_team_canon_map mh
             ON mh.league_id = :lg AND mh.team_id = te.home_team_id
      WHERE te.tournament_id = :tid AND te.home_team_id IS NOT NULL AND te.home_team_id > 0
      UNION
      SELECT DISTINCT COALESCE(ma.canon_team_id, te.away_team_id) AS canon_id
      FROM tournament_events te
      LEFT JOIN admin_team_canon_map ma
             ON ma.league_id = :lg AND ma.team_id = te.away_team_id
      WHERE te.tournament_id = :tid AND te.away_team_id IS NOT NULL AND te.away_team_id > 0
    ");
    $stTeamsCanon->execute([':lg'=>$league_id, ':tid'=>$tournament_id]);
    $teamsAllCanon = array_map('intval', $stTeamsCanon->fetchAll(PDO::FETCH_COLUMN));
    $allCount = count($teamsAllCanon);

    // helper: rimanenti selezionabili nel round corrente — su CANON
    $checkSelectableCanon = function(array $remaining) use ($pdo, $tournament_id, $ev, $league_id) : bool {
      if (empty($remaining)) return false;
      $place = implode(',', array_fill(0, count($remaining), '?'));
      $sql = "
        SELECT COUNT(*)
        FROM tournament_events te
        LEFT JOIN admin_team_canon_map mh
               ON mh.league_id = ? AND mh.team_id = te.home_team_id
        LEFT JOIN admin_team_canon_map ma
               ON ma.league_id = ? AND ma.team_id = te.away_team_id
        WHERE te.tournament_id = ?
          AND te.round_no = ?
          AND te.is_active = 1
          AND te.pick_locked = 0
          AND (
            COALESCE(mh.canon_team_id, te.home_team_id) IN ($place)
            OR
            COALESCE(ma.canon_team_id, te.away_team_id) IN ($place)
          )
      ";
      $params = array_merge([$league_id, $league_id, $tournament_id, (int)$ev['round_no']], $remaining, $remaining);
      $st = $pdo->prepare($sql);
      $st->execute($params);
      return ((int)$st->fetchColumn() > 0);
    };

    // PERCORSO "PIENO": con cycle_no + team_id + is_fallback
    if ($HAS_CYCLE_NO && $HAS_TEAM_ID && $HAS_FALLBACK) {

        // ciclo corrente (se assente, parti da 1)
        $stC = $pdo->prepare("
          SELECT COALESCE(MAX(cycle_no), 0)
          FROM tournament_selections
          WHERE tournament_id=? AND user_id=? AND life_index=?
        ");
        $stC->execute([$tournament_id, $user_id, $life_index]);
        $curCycle = (int)$stC->fetchColumn();
        if ($curCycle <= 0) $curCycle = 1;

        // usate nel ciclo corrente (solo scelte normali) — su CANON, FINALIZZATE e in round precedenti
        $roundNow = (int)$ev['round_no'];
        $stU = $pdo->prepare("
          SELECT DISTINCT COALESCE(m.canon_team_id, ts.team_id) AS team_id
          FROM tournament_selections ts
          LEFT JOIN admin_team_canon_map m
                 ON m.league_id = :lg AND m.team_id = ts.team_id
          WHERE ts.tournament_id = :tid
            AND ts.user_id = :uid
            AND ts.life_index = :life
            AND ts.cycle_no = :cy
            AND COALESCE(ts.is_fallback,0)=0
            AND ts.team_id IS NOT NULL
            AND ts.finalized_at IS NOT NULL
            AND (ts.round_no IS NULL OR ts.round_no < :rnow)
        ");
        $stU->execute([
          ':lg'=>$league_id, ':tid'=>$tournament_id, ':uid'=>$user_id,
          ':life'=>$life_index, ':cy'=>$curCycle, ':rnow'=>$roundNow
        ]);
        $usedNow   = array_map('intval', $stU->fetchAll(PDO::FETCH_COLUMN));
        $usedCount = count($usedNow);

        $startNewCycle = ($allCount > 0 && $usedCount >= $allCount);
        $cycleToUse    = $startNewCycle ? ($curCycle + 1) : $curCycle;
        $remaining     = $startNewCycle ? $teamsAllCanon : array_values(array_diff($teamsAllCanon, $usedNow));
        $hasSelectableRemaining = $checkSelectableCanon($remaining);

        $isFallbackNow = 0;
        if ($hasSelectableRemaining) {
            if (!in_array($team_chosen_canon, $remaining, true)) {
                respond(['ok'=>false,'error'=>'team_already_used','msg'=>'Con questa vita hai già usato questa squadra in questo giro.'], 400);
            }
            $isFallbackNow = 0;
        } else {
            $isFallbackNow = 1;
            // ultima fallback storica (CANON)
            $stLastFb = $pdo->prepare("
              SELECT COALESCE(m.canon_team_id, ts.team_id) AS canon_id
              FROM tournament_selections ts
              LEFT JOIN admin_team_canon_map m
                     ON m.league_id = :lg AND m.team_id = ts.team_id
              WHERE ts.tournament_id = :tid AND ts.user_id = :uid AND ts.life_index = :life AND COALESCE(ts.is_fallback,0)=1
              ORDER BY ts.id DESC
              LIMIT 1
            ");
            $stLastFb->execute([':lg'=>$league_id, ':tid'=>$tournament_id, ':uid'=>$user_id, ':life'=>$life_index]);
            $lastFbTeam = (int)($stLastFb->fetchColumn() ?: 0);
            if ($lastFbTeam > 0 && $team_chosen_canon === $lastFbTeam) {
              respond(['ok'=>false,'error'=>'fallback_same_twice','msg'=>'Blocco totale: non puoi ripetere la stessa fallback della scorsa volta.'], 400);
            }
        }
        // UPSERT (+ metadati ciclo/fallback) — versione con placeholder nominati (evita HY093)
        $pdo->beginTransaction();

        $selCode = generate_unique_code8($pdo, 'tournament_selections','selection_code', 8);

        // base colonne/valori
        $cols = [
          'tournament_id','user_id','life_index','event_id','side','selection_code',
          'created_at','locked_at','finalized_at'
        ];
        $vals = [
          ':tid', ':uid', ':life', ':eid', ':side', ':scode',
          'NOW()', 'NULL', 'NULL'
        ];
        $bind = [
          ':tid'   => $tournament_id,
          ':uid'   => $user_id,
          ':life'  => $life_index,
          ':eid'   => $event_id,
          ':side'  => $side,
          ':scode' => $selCode,
        ];

        $update = [
          'event_id = VALUES(event_id)',
          'side     = VALUES(side)',
          'locked_at = NULL',
          'finalized_at = NULL',
          'selection_code = IFNULL(selection_code, VALUES(selection_code))'
        ];

        // colonne opzionali
        if ($HAS_ROUND_NO) { $cols[]='round_no';    $vals[]=':round_no';    $bind[':round_no']    = (int)$ev['round_no']; }
        if ($HAS_TEAM_ID)  { $cols[]='team_id';     $vals[]=':team_id';     $bind[':team_id']     = $team_chosen_canon;  $update[]='team_id = VALUES(team_id)'; }
        if ($HAS_FALLBACK) { $cols[]='is_fallback'; $vals[]=':is_fallback'; $bind[':is_fallback'] = $isFallbackNow ? 1 : 0; $update[]='is_fallback = VALUES(is_fallback)'; }
        if ($HAS_CYCLE_NO) { $cols[]='cycle_no';    $vals[]=':cycle_no';    $bind[':cycle_no']    = $cycleToUse; $update[]='cycle_no = VALUES(cycle_no)'; }

        $sql = "
          INSERT INTO tournament_selections
            (".implode(',', $cols).")
          VALUES (".implode(',', $vals).")
          ON DUPLICATE KEY UPDATE
            ".implode(', ', $update)."
        ";

        $ins = $pdo->prepare($sql);
        $ins->execute($bind);

        $pdo->commit();

        respond(['ok'=>true, 'selection_code'=>$selCode]);

    } else {
        // ========== COMPAT MODE (schema minimale) ==========
        // storicità base: vieta ripetizioni su round precedenti quando esistono alternative

        $round_now = (int)($ev['round_no'] ?? $current_round_no);

        // usate da questa vita in round precedenti (FINALIZZATE) — CANON
        $stUsed = $pdo->prepare("
          SELECT DISTINCT COALESCE(m.canon_team_id, CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END) AS team_id
          FROM tournament_selections ts
          JOIN tournament_events te
            ON te.id = ts.event_id AND te.tournament_id = ts.tournament_id
          LEFT JOIN admin_team_canon_map m
            ON m.league_id = :lg
           AND m.team_id  = (CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END)
          WHERE ts.tournament_id = :tid
            AND ts.user_id = :uid
            AND ts.life_index = :life
            AND te.round_no < :round_now
            AND ts.finalized_at IS NOT NULL
        ");
        $stUsed->execute([':lg'=>$league_id, ':tid'=>$tournament_id, ':uid'=>$user_id, ':life'=>$life_index, ':round_now'=>$round_now]);
        $usedByLife = array_map('intval', $stUsed->fetchAll(PDO::FETCH_COLUMN));

        // set completo CANON
        $teamsAll = $teamsAllCanon;
        $allCount = count($teamsAll);

        // se ho usato tutte → eccezione A: posso ripartire
        $usedAll = ($allCount > 0 && count(array_unique($usedByLife)) >= $allCount);

        // rimanenti CANON
        $remaining = $usedAll ? $teamsAll : array_values(array_diff($teamsAll, $usedByLife));

        // ci sono rimanenti selezionabili nel round corrente?
        $hasSelectableRemaining = $checkSelectableCanon($remaining);

        if ($hasSelectableRemaining) {
            if (!$usedAll && in_array($team_chosen_canon, $usedByLife, true)) {
                respond(['ok'=>false,'error'=>'team_already_used','msg'=>'Con questa vita hai già usato questa squadra in un round precedente.'], 400);
            }
        }
        // altrimenti fallback libero (compat: non traccio is_fallback)

        // upsert “semplice”: una riga per vita
        $pdo->beginTransaction();

        $sx = $pdo->prepare("SELECT id, selection_code FROM tournament_selections WHERE user_id=? AND tournament_id=? AND life_index=? LIMIT 1");
        $sx->execute([$user_id, $tournament_id, $life_index]);
        $row = $sx->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $up = $pdo->prepare("UPDATE tournament_selections SET event_id=?, side=?, locked_at=NULL, finalized_at=NULL WHERE id=?");
            $up->execute([$event_id, $side, (int)$row['id']]);
            $selCode = $row['selection_code'] ?: generate_unique_code8($pdo, 'tournament_selections','selection_code', 8);
            if (!$row['selection_code']) {
                $up2 = $pdo->prepare("UPDATE tournament_selections SET selection_code=? WHERE id=?");
                $up2->execute([$selCode, (int)$row['id']]);
            }
        } else {
            $selCode = generate_unique_code8($pdo, 'tournament_selections','selection_code', 8);
            $ins = $pdo->prepare("INSERT INTO tournament_selections
                  (tournament_id, user_id, life_index, event_id, side, selection_code, created_at, locked_at, finalized_at)
                  VALUES (?,?,?,?,?,?, NOW(), NULL, NULL)");
            $ins->execute([$tournament_id, $user_id, $life_index, $event_id, $side, $selCode]);
        }

        $pdo->commit();

        respond(['ok'=>true, 'selection_code'=>$selCode]);
    }
    // ======================= FINE REGOLA =======================

} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    // log dettagliato
    $logDir = $ROOT . '/storage/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    $payload = [
      'post' => $_POST,
      'err'  => ['msg'=>$e->getMessage(),'code'=>$e->getCode(),'file'=>$e->getFile(),'line'=>$e->getLine()],
    ];
    @file_put_contents($logDir.'/selection_error.log', '['.date('c')."] save_selection ERROR: ".json_encode($payload).PHP_EOL, FILE_APPEND);
    $dev = (defined('APP_ENV') && APP_ENV === 'production') ? null : $payload['err'];
    respond(['ok'=>false,'error'=>'exception','dev'=>$dev], 500);
}
