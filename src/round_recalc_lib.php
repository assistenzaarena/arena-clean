<?php
// =====================================================================
// src/round_recalc_lib.php — libreria ricalcolo ultimo round (preview/apply)
// Dipendenze: src/db.php (PDO in $pdo), src/guards.php (require_admin())
// Tabelle usate (già esistenti nel tuo progetto):
//  - tournaments(id, name, current_round_no, ...)
//  - tournament_events(id, tournament_id, round_no, result_status, ...)
//  - tournament_enrollments(tournament_id, user_id, lives, ...)
//  - tournament_selections(id, tournament_id, user_id, life_index, event_id, side,
//                          is_fallback, finalized_at, created_at, ...)
//  - utenti(id, username, ...)
// Nuove tabelle di audit (vedi SQL più sotto):
//  - admin_round_recalc_audit
//  - admin_life_adjustments
// =====================================================================

if (session_status() === PHP_SESSION_NONE) { session_start(); }

function rr_get_current_round(PDO $pdo, int $tournamentId): ?int {
    $st = $pdo->prepare("SELECT current_round_no FROM tournaments WHERE id=? LIMIT 1");
    $st->execute([$tournamentId]);
    $r = $st->fetchColumn();
    if ($r === false || $r === null) return null;
    return (int)$r;
}

/**
 * Restituisce gli eventi (id => result_status) per un torneo/round.
 */
function rr_fetch_event_results(PDO $pdo, int $tournamentId, int $roundNo): array {
    $st = $pdo->prepare("
        SELECT id, result_status
        FROM tournament_events
        WHERE tournament_id = :tid AND round_no = :r
    ");
    $st->execute([':tid'=>$tournamentId, ':r'=>$roundNo]);
    $out = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $out[(int)$row['id']] = (string)($row['result_status'] ?? 'pending');
    }
    return $out;
}

/**
 * Determina se la selezione (side) “tiene” la vita alla luce del result_status dell’evento.
 * Regole allineate a /api/history_selections.php:
 *  - void/postponed/pending  => KEEP
 *  - draw => KEEP se side='draw', altrimenti LOSE
 *  - home_win => KEEP se side='home'
 *  - away_win => KEEP se side='away'
 */
function rr_selection_keeps_life(string $side, string $resultStatus): bool {
    $rs = strtolower($resultStatus);
    $sd = strtolower($side);

    if ($rs === 'void' || $rs === 'postponed' || $rs === 'pending') return true;
    if ($rs === 'draw') return ($sd === 'draw');
    if ($rs === 'home_win') return ($sd === 'home');
    if ($rs === 'away_win') return ($sd === 'away');
    // qualunque altro valore: prudenzialmente LOSE
    return false;
}

/**
 * Calcola quante vite “restano” per utente dopo il round (preview).
 * Torna:
 *  - before_lives: [user_id => lives attuali]
 *  - after_lives:  [user_id => vite che tengono dopo il ricalcolo]
 *  - details:      [user_id][life_index] => ['kept'=>bool,'event_id'=>int,'side'=>string,'result'=>string]
 */
function rr_preview(PDO $pdo, int $tournamentId, int $roundNo): array {
    // 1) lives attuali
    $before = [];
    $stB = $pdo->prepare("
        SELECT te.user_id, te.lives
        FROM tournament_enrollments te
        WHERE te.tournament_id = ?
    ");
    $stB->execute([$tournamentId]);
    while ($r = $stB->fetch(PDO::FETCH_ASSOC)) {
        $before[(int)$r['user_id']] = (int)$r['lives'];
    }

    // 2) mappa risultati evento
    $eventRes = rr_fetch_event_results($pdo, $tournamentId, $roundNo);

    // 3) selezioni FINALIZZATE della giornata (join su events per round)
    $sel = $pdo->prepare("
        SELECT ts.user_id, ts.life_index, ts.side, ts.is_fallback, ts.finalized_at,
               e.id AS event_id, e.result_status
        FROM tournament_selections ts
        INNER JOIN tournament_events e ON e.id = ts.event_id
        WHERE ts.tournament_id = :tid
          AND e.round_no = :r
          AND ts.finalized_at IS NOT NULL
        ORDER BY ts.user_id ASC, ts.life_index ASC, ts.is_fallback ASC, ts.finalized_at DESC, ts.created_at DESC
    ");
    $sel->execute([':tid'=>$tournamentId, ':r'=>$roundNo]);

    // Usciamo con "prima scelta utile" per (user, life_index)
    $seen = [];
    $keepCount = [];        // user_id => count
    $details = [];          // user_id => life_index => detail

    while ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
        $uid = (int)$row['user_id'];
        $li  = (int)$row['life_index'];
        $key = $uid.'#'.$li;
        if (isset($seen[$key])) continue; // già valutata la vita
        $seen[$key] = true;

        $side   = (string)$row['side'];
        $eid    = (int)$row['event_id'];
        $res    = $eventRes[$eid] ?? strtolower((string)$row['result_status'] ?? 'pending');

        $kept = rr_selection_keeps_life($side, $res);

        if (!isset($keepCount[$uid])) $keepCount[$uid] = 0;
        if (!isset($details[$uid]))   $details[$uid]   = [];

        $details[$uid][$li] = [
            'kept'    => $kept,
            'event_id'=> $eid,
            'side'    => $side,
            'result'  => $res,
        ];
        if ($kept) $keepCount[$uid] += 1;
    }

    // Utenti senza alcuna selezione finalizzata nel round => tutte le vite esistenti perdute
    // (il conteggio "after" per loro va a 0). Garantiamo entry per ogni iscritto.
    foreach ($before as $uid => $_l) {
        if (!isset($keepCount[$uid])) $keepCount[$uid] = 0;
        if (!isset($details[$uid]))   $details[$uid]   = [];
    }

    return [
        'before_lives' => $before,
        'after_lives'  => $keepCount,
        'details'      => $details,
    ];
}

/**
 * Applica il ricalcolo (UPDATE lives) con audit. Operazione atomica.
 * Ritorna array con 'applied'=>int (#righe aggiornate) e 'diffs'=>[...]
 */
function rr_apply(PDO $pdo, int $tournamentId, int $roundNo, int $adminUserId, string $note = ''): array {
    $preview = rr_preview($pdo, $tournamentId, $roundNo);
    $before  = $preview['before_lives'];
    $after   = $preview['after_lives'];

    $diffs = [];
    foreach ($after as $uid => $new) {
        $old = (int)($before[$uid] ?? 0);
        if ($old !== (int)$new) {
            $diffs[] = [
                'user_id' => $uid,
                'before'  => $old,
                'after'   => (int)$new,
                'delta'   => (int)$new - $old,
            ];
        }
    }

    $pdo->beginTransaction();
    try {
        // Aggiorna tutti gli iscritti (anche chi non ha differenze: opzionale)
        $upd = $pdo->prepare("
            UPDATE tournament_enrollments
            SET lives = :l
            WHERE tournament_id = :tid AND user_id = :uid
        ");
        $applied = 0;

        foreach ($after as $uid => $new) {
            $upd->execute([':l'=>$new, ':tid'=>$tournamentId, ':uid'=>$uid]);
            $applied += $upd->rowCount();
        }

        // Audit (salviamo due snapshot JSON come testo)
        $insAudit = $pdo->prepare("
            INSERT INTO admin_round_recalc_audit
              (tournament_id, round_no, before_json, after_json, diffs_json, admin_user_id, note)
            VALUES (:tid, :r, :b, :a, :d, :admin, :note)
        ");
        $insAudit->execute([
            ':tid'   => $tournamentId,
            ':r'     => $roundNo,
            ':b'     => json_encode($before, JSON_UNESCAPED_UNICODE),
            ':a'     => json_encode($after,  JSON_UNESCAPED_UNICODE),
            ':d'     => json_encode($diffs,  JSON_UNESCAPED_UNICODE),
            ':admin' => $adminUserId,
            ':note'  => $note,
        ]);

        $pdo->commit();
        return ['applied'=>$applied, 'diffs'=>$diffs];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
