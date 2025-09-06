<?php
// src/round_loader.php
// Helper per precaricare la giornata successiva (round_type='matchday') come round_no=N+1.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/football_api.php';

/* === FUNZIONE AGGIUNTA: normalizza ID squadra verso canon === */
function canonize_team_id(PDO $pdo, int $league_id, int $team_id, string $name): int {
    if ($league_id <= 0 || $team_id <= 0) return $team_id;

    // già canon?
    $st = $pdo->prepare("SELECT canon_team_id FROM admin_team_canon WHERE league_id=? AND canon_team_id=? LIMIT 1");
    $st->execute([$league_id, $team_id]);
    if ($st->fetchColumn()) return $team_id;

    // alias mappato?
    $st = $pdo->prepare("SELECT canon_team_id FROM admin_team_canon_map WHERE league_id=? AND team_id=? LIMIT 1");
    $st->execute([$league_id, $team_id]);
    $canon = (int)($st->fetchColumn() ?: 0);
    if ($canon > 0) return $canon;

    // match per nome normalizzato
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
            $pdo->prepare("INSERT IGNORE INTO admin_team_canon_map (league_id, team_id, canon_team_id) VALUES (?,?,?)")
                ->execute([$league_id, $team_id, $canon]);
            return $canon;
        }
    }

    // crea canon + mappa
    $pdo->prepare("INSERT INTO admin_team_canon (league_id, display_name) VALUES (?, ?)")
        ->execute([$league_id, $name ?: ('Team '.$team_id)]);
    $canon = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT IGNORE INTO admin_team_canon_map (league_id, team_id, canon_team_id) VALUES (?,?,?)")
        ->execute([$league_id, $team_id, $canon]);

    return $canon;
}

/**
 * Prova a caricare gli eventi del "matchday N+1" nel round $new_round del torneo.
 * - Supportato: tornei con round_type='matchday' e campo tournaments.matchday valorizzato.
 * - Inserisce righe in tournament_events (is_active=1, pick_locked=0).
 * - Aggiorna tournaments.matchday = matchday+1 se l’inserimento riesce.
 *
 * @return bool true se ha caricato (o già presenti) eventi per il nuovo round; false altrimenti.
 */
function attempt_preload_next_round(PDO $pdo, int $tournament_id, int $old_round, int $new_round): bool
{
    // 0) Leggi torneo
    $tq = $pdo->prepare("SELECT id, league_id, season, round_type, matchday, round_label FROM tournaments WHERE id=:id LIMIT 1");
    $tq->execute([':id' => $tournament_id]);
    $t = $tq->fetch(PDO::FETCH_ASSOC);
    if (!$t) return false;

    $roundType = (string)($t['round_type'] ?? 'matchday');
    $league_id = (int)($t['league_id'] ?? 0);
    $season    = (string)($t['season'] ?? '');
    $matchday  = isset($t['matchday']) ? (int)$t['matchday'] : null;

    // Solo per 'matchday'
    if ($roundType !== 'matchday' || $league_id <= 0 || $season === '') {
        return false;
    }
    if ($matchday === null) {
        // senza "matchday" di riferimento non possiamo dedurre N+1 in modo affidabile
        return false;
    }

    $next_md = $matchday + 1;

    // 1) Chiama API Football per il matchday successivo
    $resp = fb_fixtures_matchday($league_id, $season, $next_md, 'Regular Season - %d');
    if (!$resp['ok']) {
        return false;
    }

    $list = fb_extract_fixtures_minimal($resp['data']);
    if (!$list || count($list) === 0) {
        return false;
    }

    // 2) Inserisci eventi nel nuovo round (evita duplicati con INSERT IGNORE)
    $ins = $pdo->prepare("
        INSERT IGNORE INTO tournament_events
          (tournament_id, round_no, fixture_id,
           home_team_id, home_team_name, away_team_id, away_team_name,
           kickoff, is_active, pick_locked)
        VALUES
          (:tid, :rnd, :fid, :hid, :hname, :aid, :aname, :kick, 1, 0)
    ");

    $inserted = 0;
    foreach ($list as $fx) {
        // Date -> kickoff (accettiamo NULL se non parsabile)
        $kick = null;
        if (!empty($fx['date'])) {
            $ts = strtotime($fx['date']);
            if ($ts !== false) $kick = date('Y-m-d H:i:s', $ts);
        }

        // === AGGIUNTA: canonizza gli ID provenienti dall'API prima dell'inserimento
        $home_canon = isset($fx['home_id']) ? canonize_team_id($pdo, $league_id, (int)$fx['home_id'], (string)($fx['home_name'] ?? '')) : null;
        $away_canon = isset($fx['away_id']) ? canonize_team_id($pdo, $league_id, (int)$fx['away_id'], (string)($fx['away_name'] ?? '')) : null;

        $ins->execute([
            ':tid'   => $tournament_id,
            ':rnd'   => $new_round,
            ':fid'   => $fx['fixture_id'] ? (int)$fx['fixture_id'] : null,
            ':hid'   => $home_canon,
            ':hname' => $fx['home_name'] ?? null,
            ':aid'   => $away_canon,
            ':aname' => $fx['away_name'] ?? null,
            ':kick'  => $kick
        ]);

        $inserted += ($ins->rowCount() > 0) ? 1 : 0;
    }

    // 3) Verifica che ci siano eventi per il nuovo round
    $chk = $pdo->prepare("SELECT COUNT(*) FROM tournament_events WHERE tournament_id=:tid AND round_no=:r");
    $chk->execute([':tid' => $tournament_id, ':r' => $new_round]);
    $countNew = (int)$chk->fetchColumn();

    if ($countNew > 0) {
        // Aggiorna il "matchday" del torneo a quello appena caricato (N+1)
        $pdo->prepare("UPDATE tournaments SET matchday=:md, updated_at=NOW() WHERE id=:id")
            ->execute([':md'=>$next_md, ':id'=>$tournament_id]);
        return true;
    }

    return false;
}
