<?php
/**
 * src/game_rules.php
 * 
 * SCOPO:
 * - Funzioni riutilizzabili che implementano le regole di gioco:
 *   1) blocco scelte a -5 minuti dal primo evento del round (o se lock_at è passato)
 *   2) blocco iscrizioni/disiscrizioni/aggiunta vite:
 *        - sempre bloccate dal round 2 in poi
 *        - nel round 1: bloccate da -5 minuti dal primo evento del round 1
 *
 * USO:
 * - require_once __DIR__ . '/game_rules.php';
 * - chiama choices_locked_now($pdo, $torneo) prima di salvare una scelta squadra
 * - chiama enroll_family_blocked_now($pdo, $torneo) in enroll/unenroll/add_life
 *
 * DIPENDENZE:
 * - Richiede PDO $pdo connesso al DB
 * - Si aspetta una riga torneo come array $torneo con chiavi:
 *     ['id']                int
 *     ['current_round_no']  int
 *     ['lock_at']           string|NULL (formato DATETIME del DB)
 */

/** 
 * [HELPER] Ritorna "adesso" come timestamp (int).
 *  - wrapper piccolo per evitare di ripetere time() e facilitare eventuali override/test.
 */
function _now_ts(): int {
    return time(); // <-- timestamp unix corrente
}

/**
 * [QUERY] Ritorna il kickoff più precoce (MIN) per un ROUND dato di un torneo.
 *  - Solo eventi attivi (is_active=1) e con kickoff non NULL.
 *  - Se non ci sono eventi validi → ritorna NULL.
 *
 * @param PDO   $pdo           connessione al DB
 * @param int   $tournament_id id torneo
 * @param int   $round_no      round di cui trovare il primo kickoff
 * @return string|null         'YYYY-MM-DD HH:MM:SS' oppure NULL
 */
function first_kickoff_for_round(PDO $pdo, int $tournament_id, int $round_no): ?string {
    // Preparo la query per prendere il MIN(kickoff) del round.
    $q = $pdo->prepare("
        SELECT MIN(kickoff)
        FROM tournament_events
        WHERE tournament_id = ?
          AND is_active = 1
          AND round_no = ?
          AND kickoff IS NOT NULL
    "); // <-- uso placeholder "?" per evitare problemi con parametri nominati
    $q->execute([$tournament_id, $round_no]); // <-- bindo i due parametri in ordine
    $dt = $q->fetchColumn();                  // <-- prendo la singola colonna (MIN)
    return $dt ? (string)$dt : null;          // <-- se c'è un valore lo ritorno, altrimenti NULL
}

/**
 * [REGOLA] Scelte “bloccate adesso” per un torneo?
 *  TRUE se:
 *   - lock_at è impostato ed è già passata (ora >= lock_at), OPPURE
 *   - mancano <= 5 minuti al primo kickoff del round corrente
 *  FALSE altrimenti.
 *
 * @param PDO   $pdo      connessione
 * @param array $torneo   riga torneo: ['id','current_round_no','lock_at']
 * @return bool           TRUE = scelte bloccate; FALSE = scelte permesse
 */
function choices_locked_now(PDO $pdo, array $torneo): bool {
    // Prendo "adesso" come timestamp intero.
    $now = _now_ts(); // <-- equivalente a time()

    // Se esiste lock_at e siamo oltre/uguali → scelte bloccate.
    //  - strtotime converte 'YYYY-MM-DD HH:MM:SS' in timestamp int.
    if (!empty($torneo['lock_at'])) {                       // <-- lock_at valorizzato?
        $lockTs = strtotime((string)$torneo['lock_at']);    // <-- timestamp di lock_at
        if ($lockTs !== false && $lockTs <= $now) {         // <-- lock passato?
            return true;                                    // <-- sì: blocco
        }
    }

    // Calcolo il primo kickoff del round corrente.
    $round = (int)($torneo['current_round_no'] ?? 1);       // <-- numero round corrente (default 1)
    $firstKick = first_kickoff_for_round($pdo, (int)$torneo['id'], $round); // <-- 'YYYY-MM-DD ...' o NULL

    if ($firstKick === null) {                              // <-- se non ci sono eventi validi
        return false;                                       // <-- non posso bloccare sulle 5 min; scelte aperte
    }

    // Se mancano <= 5 minuti al primo kickoff → scelte bloccate.
    //  - 5 minuti = 300 secondi
    $kickTs = strtotime($firstKick);                        // <-- timestamp kickoff
    if ($kickTs !== false && ($kickTs - 300) <= $now) {     // <-- siamo dentro la finestra "meno 5"?
        return true;                                        // <-- sì: blocco scelte
    }

    // Altrimenti scelte permesse.
    return false; // <-- scelte aperte
}

/**
 * [REGOLA] Iscrizione/Disiscrizione/Aggiunta vite “bloccate adesso”?
 *  TRUE se:
 *   - round > 1  (sempre bloccate dal round 2 in poi)
 *   - round = 1  e mancano <= 5 minuti al primo kickoff del round 1
 *  FALSE altrimenti.
 *
 * @param PDO   $pdo
 * @param array $torneo ['id','current_round_no']
 * @return bool TRUE = enroll/unenroll/add_life bloccati; FALSE = permessi
 */
function enroll_family_blocked_now(PDO $pdo, array $torneo): bool {
    // Se siamo oltre il round 1 → sempre bloccati.
    $round = (int)($torneo['current_round_no'] ?? 1); // <-- round corrente
    if ($round > 1) {                                 // <-- oltre round 1?
        return true;                                  // <-- sì: blocco permanente di queste azioni
    }

    // Round 1: controllo i -5 minuti prima del primo kickoff del round 1.
    $firstKick = first_kickoff_for_round($pdo, (int)$torneo['id'], 1); // <-- primo kickoff del round 1
    if ($firstKick === null) {
        return false;                                  // <-- senza kickoff non posso bloccare: lascio aperto
    }

    // Se siamo dentro i -5 minuti → blocco.
    $now = _now_ts();                                  // <-- timestamp corrente
    $kickTs = strtotime($firstKick);                   // <-- timestamp kickoff
    if ($kickTs !== false && ($kickTs - 300) <= $now) {// <-- 300 sec = 5 minuti
        return true;                                   // <-- blocco azioni family enroll
    }

    // Altrimenti permesso.
    return false; // <-- enroll/unenroll/add_life consentiti
}
