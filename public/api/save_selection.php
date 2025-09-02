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

    // >>> aggiunta: evento deve essere del round corrente
    if ((int)$ev['round_no'] !== $current_round_no) {
        respond(['ok'=>false,'error'=>'event_wrong_round'], 400);
    }

    // ============ REGOLA "NO TEAM DUPLICATO PER VITA" CON ECCEZIONI ============
    // Team che l'utente sta scegliendo in questo momento (in base al side)
    $team_chosen_id = ($side === 'home') ? (int)$ev['home_team_id'] : (int)$ev['away_team_id'];

    if ($team_chosen_id) {
        // 3a) set di TUTTE le squadre del torneo (home/away) per capire quando "le hai usate tutte"
        $stTeams = $pdo->prepare("
          SELECT DISTINCT x.team_id FROM (
            SELECT home_team_id AS team_id FROM tournament_events WHERE tournament_id=:tid
            UNION
            SELECT away_team_id AS team_id FROM tournament_events WHERE tournament_id=:tid
          ) x WHERE x.team_id IS NOT NULL
        ");
        $stTeams->execute([':tid'=>$tournament_id]);
        $teamsAll = array_map('intval', $stTeams->fetchAll(PDO::FETCH_COLUMN));

        // 3b) squadre già usate da QUESTA vita in round precedenti
        //     (uso il round dell'evento scelto o, in fallback, current_round_no)
        $round_now = (int)($ev['round_no'] ?? $current_round_no);
        $stUsed = $pdo->prepare("
          SELECT DISTINCT
            CASE ts.side
              WHEN 'home' THEN te.home_team_id
              ELSE te.away_team_id
            END AS team_id
          FROM tournament_selections ts
          JOIN tournament_events te ON te.id = ts.event_id
          WHERE ts.tournament_id = :tid
            AND ts.user_id = :uid
            AND ts.life_index = :life
            AND te.round_no < :round_now
            AND ts.finalized_at IS NOT NULL
            AND (CASE ts.side WHEN 'home' THEN te.home_team_id ELSE te.away_team_id END) IS NOT NULL
        ");
        $stUsed->execute([
          ':tid'=>$tournament_id,
          ':uid'=>$user_id,
          ':life'=>$life_index,
          ':round_now'=>$round_now
        ]);
        $usedByLife = array_map('intval', $stUsed->fetchAll(PDO::FETCH_COLUMN));

        // 3c) eccezione A: hai già usato tutte le squadre?
        $allCount  = count($teamsAll);
        $usedCount = count(array_unique($usedByLife));
        $usedAll   = ($allCount > 0 && $usedCount >= $allCount);

        // 3d) se non le hai usate tutte, calcolo le rimanenti e verifico se sono selezionabili nel round corrente
        $hasSelectableRemaining = false;
        if (!$usedAll && $allCount > 0) {
            $remainingTeams = array_values(array_diff($teamsAll, $usedByLife));
            if (count($remainingTeams) > 0) {
                // ci sono eventi attivi e non bloccati nel round corrente per almeno una di queste squadre?
                $place = implode(',', array_fill(0, count($remainingTeams), '?'));
                $sqlAvail = "
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
                $params = array_merge([$tournament_id, $round_now], $remainingTeams, $remainingTeams);
                $stAvail = $pdo->prepare($sqlAvail);
                $stAvail->execute($params);
                $cntAvail = (int)$stAvail->fetchColumn();
                $hasSelectableRemaining = ($cntAvail > 0);
            }
        }

        // 3e) se la squadra è già stata usata da questa vita E NON ricadi in eccezione A o B -> rifiuta
        $alreadyUsed = in_array($team_chosen_id, $usedByLife, true);
        if ($alreadyUsed && !$usedAll && $hasSelectableRemaining) {
            respond([
                'ok'=>false,
                'error'=>'team_already_used',
                'msg'=>'Con questa vita hai già usato questa squadra in un round precedente.'
            ], 400);
        }
        // Altrimenti: consentito (o perché non è doppione, o perché ricadi in eccezioni A/B)
    }
    // ======================= FINE REGOLA =======================

    // 4) upsert selezione
    $pdo->beginTransaction();

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

} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[save_selection] '.$e->getMessage());
    respond(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()], 500);
}
