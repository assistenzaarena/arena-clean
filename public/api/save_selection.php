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
$user_id      = (int)$_SESSION['user_id'];
$tournament_id= isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$event_id     = isset($_POST['event_id'])      ? (int)$_POST['event_id']      : 0;
$life_index   = isset($_POST['life_index'])    ? (int)$_POST['life_index']    : -1;
$side         = isset($_POST['side'])          ? strtolower((string)$_POST['side']) : '';

if ($tournament_id <= 0 || $event_id <= 0 || $life_index < 0 || !in_array($side, ['home','away'], true)) {
    respond(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
    // 1) torneo deve essere OPEN e prima del lock (+ controllo choices_locked)
    $st = $pdo->prepare("SELECT status, lock_at, max_lives_per_user, choices_locked, current_round_no FROM tournaments WHERE id = ? LIMIT 1");
    $st->execute([$tournament_id]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t)                    respond(['ok'=>false,'error'=>'not_found'], 404);
    if ((int)($t['choices_locked'] ?? 0) === 1) {
        respond(['ok'=>false,'error'=>'locked'], 400);
    }
    if ($t['status']!=='open')  respond(['ok'=>false,'error'=>'not_open'], 400);
    if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
        respond(['ok'=>false,'error'=>'locked'], 400);
    }
    $current_round_no = (int)($t['current_round_no'] ?? 1);

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
    // (opzionale) se vuoi impedire scelte su eventi bloccati:
    // if ((int)$ev['pick_locked'] === 1) respond(['ok'=>false,'error'=>'locked'], 400);

    // ============ REGOLA "NO TEAM DUPLICATO PER VITA" CON CICLI + FALLBACK ============
    // Team che l'utente sta scegliendo in questo momento (in base al side)
    $team_chosen_id = ($side === 'home') ? (int)$ev['home_team_id'] : (int)$ev['away_team_id'];

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

    // ========= INIZIO MODIFICHE STEP B: canon_team_id =========
    // league_id del torneo per usare la mappa canon
    $stL = $pdo->prepare("SELECT league_id FROM tournaments WHERE id=? LIMIT 1");
    $stL->execute([$tournament_id]);
    $lg = (int)$stL->fetchColumn();

    // Risolvo l'ID squadra scelto su canon_team_id (se mappato)
    $team_raw_id = $team_chosen_id;
    $stCanon = $pdo->prepare("SELECT canon_team_id FROM admin_team_canon_map WHERE league_id=? AND team_id=? LIMIT 1");
    $stCanon->execute([$lg, $team_raw_id]);
    $team_chosen_id = (int)($stCanon->fetchColumn() ?: $team_raw_id);
    // ========= FINE MODIFICHE STEP B =========

    // set di TUTTE le squadre del torneo (home/away) — solo ID > 0
    $stTeams = $pdo->prepare("
      SELECT DISTINCT x.team_id FROM (
        SELECT home_team_id AS team_id FROM tournament_events WHERE tournament_id=? AND home_team_id IS NOT NULL AND home_team_id > 0
        UNION
        SELECT away_team_id AS team_id FROM tournament_events WHERE tournament_id=? AND away_team_id IS NOT NULL AND away_team_id > 0
      ) x
    ");
    $stTeams->execute([$tournament_id, $tournament_id]);
    $teamsAll = array_map('intval', $stTeams->fetchAll(PDO::FETCH_COLUMN));
    $allCount = count($teamsAll);

    // helper: disponibilità rimanenti nel round corrente
    $checkSelectable = function(array $remaining) use ($pdo, $tournament_id, $ev) : bool {
        if (empty($remaining)) return false;
        $place = implode(',', array_fill(0, count($remaining), '?'));
        $sql = "
          SELECT COUNT(*) FROM tournament_events
          WHERE tournament_id = ?
            AND round_no = ?
            AND is_active = 1
            AND pick_locked = 0
            AND (
              home_team_id IN ($place)
              OR
              away_team_id IN ($place)
            )
        ";
        $params = array_merge([$tournament_id, (int)$ev['round_no']], $remaining, $remaining);
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return ((int)$st->fetchColumn() > 0);
    };

    // PERCORSO "PIENO": con cycle_no + team_id + is_fallback
    if ($HAS_CYCLE_NO && $HAS_TEAM_ID && $HAS_FALLBACK) {
        // ========= MODIFICHE STEP B: uso CANON anche nel percorso pieno =========

        // (ri)calcolo set squadre CANON del torneo
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
        $stTeamsCanon->execute([':lg'=>$lg, ':tid'=>$tournament_id]);
        $teamsAllCanon = array_map('intval', $stTeamsCanon->fetchAll(PDO::FETCH_COLUMN));
        $allCount = count($teamsAllCanon);

        // ciclo corrente (se assente, parti da 1)
        $stC = $pdo->prepare("
          SELECT COALESCE(MAX(cycle_no), 0)
          FROM tournament_selections
          WHERE tournament_id=? AND user_id=? AND life_index=?
        ");
        $stC->execute([$tournament_id, $user_id, $life_index]);
        $curCycle = (int)$stC->fetchColumn();
        if ($curCycle <= 0) $curCycle = 1;

        // usate nel ciclo corrente (solo scelte "normali"), SOLO finalizzate e in round precedenti — su CANON
        $roundNow = (int)$ev['round_no'];
        $stU = $pdo->prepare("
          SELECT DISTINCT
            COALESCE(m.canon_team_id,
                     ts.team_id) AS team_id
          FROM tournament_selections ts
          LEFT JOIN admin_team_canon_map m
                 ON m.league_id = :lg
                AND m.team_id  = ts.team_id
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
          ':lg'=>$lg, ':tid'=>$tournament_id, ':uid'=>$user_id,
          ':life'=>$life_index, ':cy'=>$curCycle, ':rnow'=>$roundNow
        ]);
        $usedNow   = array_map('intval', $stU->fetchAll(PDO::FETCH_COLUMN));
        $usedCount = count($usedNow);

        $startNewCycle = ($allCount > 0 && $usedCount >= $allCount);

        // se giro completo → il prossimo salvataggio appartiene al ciclo successivo
        $cycleToUse = $startNewCycle ? ($curCycle + 1) : $curCycle;

        // rimanenti nell'attuale ciclo (se resetto, rimanenti = tutte) — su CANON
        $remaining = $startNewCycle ? $teamsAllCanon : array_values(array_diff($teamsAllCanon, $usedNow));

        // esistono rimanenti selezionabili nel round corrente? — su CANON
        $checkSelectableCanon = function(array $remaining) use ($pdo, $tournament_id, $ev, $lg) : bool {
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
          $params = array_merge([$lg, $lg, $tournament_id, (int)$ev['round_no']], $remaining, $remaining);
          $st = $pdo->prepare($sql);
          $st->execute($params);
          return ((int)$st->fetchColumn() > 0);
        };
        $hasSelectableRemaining = $checkSelectableCanon($remaining);

        // logica di ammissibilità
        $isFallbackNow = 0;
        if ($hasSelectableRemaining) {
            // devo scegliere tra le rimanenti (CANON)
            if (!in_array($team_chosen_id, $remaining, true)) {
                respond([
                    'ok'=>false,
                    'error'=>'team_already_used',
                    'msg'=>'Con questa vita hai già usato questa squadra in questo giro.'
                ], 400);
            }
            $isFallbackNow = 0; // scelta "normale"
        } else {
            // BLOCCO TOTALE → fallback consentito ma non ripetere l'ultima squadra scelta in fallback per questa vita (su CANON)
            $isFallbackNow = 1;

            // ultima fallback storica di questa vita (canon)
            $stLastFb = $pdo->prepare("
              SELECT COALESCE(m.canon_team_id, ts.team_id) AS canon_id
              FROM tournament_selections ts
              LEFT JOIN admin_team_canon_map m
                     ON m.league_id = :lg
                    AND m.team_id  = ts.team_id
              WHERE ts.tournament_id = :tid AND ts.user_id = :uid AND ts.life_index = :life AND COALESCE(ts.is_fallback,0)=1
              ORDER BY ts.id DESC
              LIMIT 1
            ");
            $stLastFb->execute([':lg'=>$lg, ':tid'=>$tournament_id, ':uid'=>$user_id, ':life'=>$life_index]);
            $lastFbTeam = (int)($stLastFb->fetchColumn() ?: 0);

            if ($lastFbTeam > 0 && $team_chosen_id === $lastFbTeam) {
              respond([
                  'ok'=>false,
                  'error'=>'fallback_same_twice',
                  'msg'=>'Blocco totale: non puoi ripetere la stessa fallback della scorsa volta.'
              ], 400);
            }
        }

        // 4) UPSERT (una riga per round/vita) + metadati ciclo/fallback
        $pdo->beginTransaction();

        $selCode = generate_unique_code8($pdo, 'tournament_selections','selection_code', 8);

        // composizione dinamica colonne extra presenti
        $colsExtra   = [];
        $placeExtra  = [];
        $valuesExtra = [];

        if ($HAS_ROUND_NO) { $colsExtra[]='round_no';    $placeExtra[]='?'; $valuesExtra[]=(int)$ev['round_no']; }
        if ($HAS_TEAM_ID)  { $colsExtra[]='team_id';     $placeExtra[]='?'; $valuesExtra[]=$team_chosen_id; }
        if ($HAS_FALLBACK) { $colsExtra[]='is_fallback'; $placeExtra[]='?'; $valuesExtra[]=$isFallbackNow ? 1 : 0; }
        if ($HAS_CYCLE_NO) { $colsExtra[]='cycle_no';    $placeExtra[]='?'; $valuesExtra[]=$cycleToUse; }

        $sql = "
          INSERT INTO tournament_selections
            (tournament_id, user_id, life_index, event_id, side, selection_code, created_at, locked_at, finalized_at"
            . (empty($colsExtra) ? "" : ", " . implode(',', $colsExtra)) .
            ")
          VALUES (?,?,?,?,?,?, NOW(), NULL, NULL"
            . (empty($placeExtra) ? "" : ", " . implode(',', $placeExtra)) .
            ")
          ON DUPLICATE KEY UPDATE
            event_id = VALUES(event_id),
            side     = VALUES(side),
            ".($HAS_TEAM_ID  ? "team_id = VALUES(team_id)," : "")."
            ".($HAS_FALLBACK ? "is_fallback = VALUES(is_fallback)," : "")."
            ".($HAS_CYCLE_NO ? "cycle_no = VALUES(cycle_no)," : "")."
            locked_at = NULL,
            finalized_at = NULL,
            selection_code = IFNULL(selection_code, VALUES(selection_code))
        ";
        $ins = $pdo->prepare($sql);
        $params = [$tournament_id, $user_id, $life_index, $event_id, $side, $selCode];
        $ins->execute(array_merge($params, $valuesExtra));

        $pdo->commit();

        respond(['ok'=>true, 'selection_code'=>$selCode]);

    } else {
        // ========== COMPAT MODE (schema minimale): regola base + eccezioni A/B senza tracking completo ==========
        // ========= MODIFICHE STEP B: usiamo CANON anche nel compat =========

        // squadre già usate da QUESTA vita in round precedenti (finalizzate) — su CANON
        $round_now = (int)($ev['round_no'] ?? $current_round_no);
        $stUsed = $pdo->prepare("
          SELECT DISTINCT
            COALESCE(m.canon_team_id,
              CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END
            ) AS team_id
          FROM tournament_selections ts
          JOIN tournament_events te
            ON te.id = ts.event_id
           AND te.tournament_id = ts.tournament_id
          LEFT JOIN admin_team_canon_map m
            ON m.league_id = :lg
           AND m.team_id = (CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END)
          WHERE ts.tournament_id = :tid
            AND ts.user_id = :uid
            AND ts.life_index = :life
            AND te.round_no < :round_now
            AND ts.finalized_at IS NOT NULL
            AND (CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END) IS NOT NULL
        ");
        $stUsed->execute([':lg'=>$lg, ':tid'=>$tournament_id, ':uid'=>$user_id, ':life'=>$life_index, ':round_now'=>$round_now]);
        $usedByLife = array_map('intval', $stUsed->fetchAll(PDO::FETCH_COLUMN));

        // set di TUTTE le squadre (CANON)
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
        $stTeamsCanon->execute([':lg'=>$lg, ':tid'=>$tournament_id]);
        $teamsAll = array_map('intval', $stTeamsCanon->fetchAll(PDO::FETCH_COLUMN));
        $allCount = count($teamsAll);

        // eccezione A: giro completo (approssimata all'intera storia)
        $usedAll = ($allCount > 0 && count(array_unique($usedByLife)) >= $allCount);

        // rimanenti (CANON)
        $remaining = $usedAll ? $teamsAll : array_values(array_diff($teamsAll, $usedByLife));

        // rimanenti selezionabili nel round corrente — su CANON
        $checkSelectableCanon = function(array $remaining) use ($pdo, $tournament_id, $ev, $lg) : bool {
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
          $params = array_merge([$lg, $lg, $tournament_id, (int)$ev['round_no']], $remaining, $remaining);
          $st = $pdo->prepare($sql);
          $st->execute($params);
          return ((int)$st->fetchColumn() > 0);
        };
        $hasSelectableRemaining = $checkSelectableCanon($remaining);

        $alreadyUsed = in_array($team_chosen_id, $usedByLife, true);

        if ($hasSelectableRemaining) {
            // se ho alternative, devo scegliere tra rimanenti
            if (!$usedAll && $alreadyUsed) {
                respond([
                    'ok'=>false,
                    'error'=>'team_already_used',
                    'msg'=>'Con questa vita hai già usato questa squadra in un round precedente.'
                ], 400);
            }
        } else {
            // BLOCCO TOTALE → fallback consentito (in compat non posso marcare né bloccare ripetizione consecutiva in modo certo)
        }

        // 4) upsert selezione (storico limitato: mantengo il tuo comportamento originale)
        $pdo->beginTransaction();

        // Una riga per vita → aggiorno se esiste, altrimenti inserisco
        $sx = $pdo->prepare("SELECT id, selection_code FROM tournament_selections
                             WHERE user_id=? AND tournament_id=? AND life_index=? LIMIT 1");
        $sx->execute([$user_id, $tournament_id, $life_index]);
        $row = $sx->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $up = $pdo->prepare("UPDATE tournament_selections
                                 SET event_id=?, side=?, locked_at=NULL, finalized_at=NULL
                                 WHERE id=?");
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
    error_log('[save_selection] '.$e->getMessage());
    respond(['ok'=>false,'error'=>'exception'], 500);
}
