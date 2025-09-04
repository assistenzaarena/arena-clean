<?php
// src/tournament_admin_utils.php
// Utility robuste e "schema-aware" per ricalcolo e gestione utenti torneo.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

function ta_safe_table_exists(PDO $pdo, string $table): bool {
  try {
    $st = $pdo->prepare("SHOW TABLES LIKE :t");
    $st->execute([':t' => $table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}

function ta_safe_column_exists(PDO $pdo, string $table, string $col): bool {
  try {
    $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
    $st->execute([':c' => $col]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}

function ta_get_tournaments(PDO $pdo): array {
  try {
    $q = $pdo->query("SELECT id, name, status FROM tournaments ORDER BY id DESC");
    return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { return []; }
}

/**
 * Trova il nome della colonna "vincitore" negli eventi.
 * Proverà in ordine: winner_team_id, result_team_id, winning_team_id.
 * Se nessuna esiste, ritorna null (anteprima segnalerà 'pending').
 */
function ta_resolve_winner_column(PDO $pdo): ?string {
  $candidates = ['winner_team_id','result_team_id','winning_team_id'];
  foreach ($candidates as $c) {
    if (ta_safe_column_exists($pdo, 'tournament_events', $c)) return $c;
  }
  return null;
}

/** Elenco round per un torneo (eventi -> fallback selections -> fallback current_round_no) */
function ta_fetch_rounds(PDO $pdo, int $tid): array {
  if ($tid <= 0) return [];
  try {
    $st = $pdo->prepare("
      SELECT DISTINCT round_no
      FROM tournament_events
      WHERE tournament_id = :t AND round_no IS NOT NULL AND round_no > 0
      ORDER BY round_no ASC
    ");
    $st->execute([':t'=>$tid]);
    $r = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (!empty($r)) return $r;
  } catch (Throwable $e) {}

  // Fallback: risalire dai selections
  try {
    $sf = $pdo->prepare("
      SELECT DISTINCT te.round_no
      FROM tournament_selections ts
      JOIN tournament_events te ON te.id = ts.event_id
      WHERE ts.tournament_id = :t
        AND te.round_no IS NOT NULL AND te.round_no > 0
      ORDER BY te.round_no ASC
    ");
    $sf->execute([':t'=>$tid]);
    $r = array_map('intval', $sf->fetchAll(PDO::FETCH_COLUMN));
    if (!empty($r)) return $r;
  } catch (Throwable $e) {}

  // Fallback finale: 1..current_round_no
  try {
    $cr = $pdo->prepare("SELECT COALESCE(current_round_no,0) FROM tournaments WHERE id=:t LIMIT 1");
    $cr->execute([':t'=>$tid]);
    $maxR = (int)$cr->fetchColumn();
    if ($maxR > 0) return range(1, $maxR);
  } catch (Throwable $e) {}

  return [];
}

/** Lookup username "user-friendly" */
function ta_username(PDO $pdo, int $uid): string {
  if ($uid <= 0) return 'user#?';
  try {
    if (ta_safe_table_exists($pdo, 'utenti')) {
      $cols = ['username','user','nome','email'];
      foreach ($cols as $c) {
        if (ta_safe_column_exists($pdo, 'utenti', $c)) {
          $st = $pdo->prepare("SELECT `$c` FROM utenti WHERE id=:u LIMIT 1");
          $st->execute([':u'=>$uid]);
          $v = $st->fetchColumn();
          if ($v) return (string)$v;
        }
      }
    }
  } catch (Throwable $e) {}
  return 'user#'.$uid;
}

/**
 * Anteprima ricalcolo: conta win/lose/pending per utente nel round selezionato.
 * Non scrive nulla; ritorna summary + dettagli per tabella.
 */
function ta_compute_round_preview(PDO $pdo, int $tid, int $round): array {
  $out = ['summary'=>['total'=>0,'wins'=>0,'losses'=>0,'pending'=>0], 'users'=>[]];
  if ($tid <= 0 || $round <= 0) return $out;

  $winnerCol = ta_resolve_winner_column($pdo); // può essere null
  $sql = "
    SELECT ts.user_id, ts.pick_team_id,
           te.id AS event_id, te.round_no
           ".($winnerCol ? ", te.`$winnerCol` AS winner_team_id " : ", NULL AS winner_team_id ")."
    FROM tournament_selections ts
    JOIN tournament_events te ON te.id = ts.event_id
    WHERE ts.tournament_id = :t AND te.round_no = :r
  ";

  $rows = [];
  try {
    $st = $pdo->prepare($sql);
    $st->execute([':t'=>$tid, ':r'=>$round]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    // Se anche questa query fallisse, ritorna out vuoto.
    return $out;
  }

  $byUser = [];
  foreach ($rows as $r) {
    $uid = (int)$r['user_id'];
    $winTid = isset($r['winner_team_id']) ? (int)$r['winner_team_id'] : null;
    $pick  = (int)$r['pick_team_id'];

    if (!isset($byUser[$uid])) $byUser[$uid] = ['user_id'=>$uid,'username'=>ta_username($pdo,$uid),'wins'=>0,'losses'=>0,'pending'=>0,'picks'=>0];

    if ($winnerCol === null || $winTid === null || $winTid === 0) {
      $byUser[$uid]['pending']++;
    } elseif ($winTid === $pick) {
      $byUser[$uid]['wins']++;
    } else {
      $byUser[$uid]['losses']++;
    }
    $byUser[$uid]['picks']++;
  }

  $sum = ['total'=>0,'wins'=>0,'losses'=>0,'pending'=>0];
  foreach ($byUser as $u) {
    $sum['total']   += $u['picks'];
    $sum['wins']    += $u['wins'];
    $sum['losses']  += $u['losses'];
    $sum['pending'] += $u['pending'];
  }

  $out['summary'] = $sum;
  $out['users']   = array_values($byUser);
  return $out;
}

/**
 * Applica il ricalcolo al round:
 * - aggiorna tournament_selections.{status|is_correct} se presenti
 * - tenta aggiornamento vite solo se trova tabella/colonne compatibili
 * Ritorna un array di messaggi (stringhe) da mostrare come flash.
 */
function ta_apply_round_recalc(PDO $pdo, int $tid, int $round): array {
  $msgs = [];
  if ($tid <= 0 || $round <= 0) return ['Parametri non validi.'];

  $winnerCol = ta_resolve_winner_column($pdo);
  if (!$winnerCol) {
    return ['Nessuna colonna esito in tournament_events (winner/result). Applicazione impossibile.'];
  }

  try {
    $pdo->beginTransaction();

    // Aggiorna selections.status se esiste
    if (ta_safe_column_exists($pdo, 'tournament_selections', 'status')) {
      $sql = "
        UPDATE tournament_selections ts
        JOIN tournament_events te ON te.id = ts.event_id
        SET ts.status = CASE
            WHEN te.`$winnerCol` IS NULL THEN 'pending'
            WHEN te.`$winnerCol` = ts.pick_team_id THEN 'win'
            ELSE 'lose'
        END
        WHERE ts.tournament_id = :t AND te.round_no = :r
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':t'=>$tid, ':r'=>$round]);
      $msgs[] = 'Aggiornata colonna tournament_selections.status.';
    } elseif (ta_safe_column_exists($pdo, 'tournament_selections', 'is_correct')) {
      // Aggiorna selections.is_correct se esiste
      $sql = "
        UPDATE tournament_selections ts
        JOIN tournament_events te ON te.id = ts.event_id
        SET ts.is_correct = (te.`$winnerCol` IS NOT NULL AND te.`$winnerCol` = ts.pick_team_id)
        WHERE ts.tournament_id = :t AND te.round_no = :r
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':t'=>$tid, ':r'=>$round]);
      $msgs[] = 'Aggiornata colonna tournament_selections.is_correct.';
    } else {
      $msgs[] = 'Nessuna colonna risultato rilevata in tournament_selections (status/is_correct).';
    }

    // (Opzionale) Ricalcolo vite se tabella/colonne presenti
    if (ta_safe_table_exists($pdo, 'tournament_users')) {
      // cerco colonne candidate
      $hasLivesRem = ta_safe_column_exists($pdo, 'tournament_users', 'lives_remaining');
      $hasInitLives= ta_safe_column_exists($pdo, 'tournament_users', 'initial_lives');
      if ($hasLivesRem && $hasInitLives) {
        // Ricalcola vite da round 1..current (o ..$round, dipende dalla tua regola; qui fino a current_round_no)
        // Strategia: vite = initial_lives - totale sconfitte accumulato ad oggi (win non toglie).
        $cr = $pdo->prepare("SELECT COALESCE(current_round_no,0) FROM tournaments WHERE id=:t LIMIT 1");
        $cr->execute([':t'=>$tid]);
        $maxR = max((int)$cr->fetchColumn(), $round);

        // Aggrega sconfitte per utente
        $sqlLoss = "
          SELECT ts.user_id, SUM(
            CASE WHEN te.`$winnerCol` IS NOT NULL AND te.`$winnerCol` <> ts.pick_team_id THEN 1 ELSE 0 END
          ) AS losses
          FROM tournament_selections ts
          JOIN tournament_events te ON te.id = ts.event_id
          WHERE ts.tournament_id = :t AND te.round_no BETWEEN 1 AND :mr
          GROUP BY ts.user_id
        ";
        $st = $pdo->prepare($sqlLoss);
        $st->execute([':t'=>$tid, ':mr'=>$maxR]);
        $losses = $st->fetchAll(PDO::FETCH_KEY_PAIR); // user_id => losses

        // Applica vite_remaining = GREATEST(initial_lives - losses, 0)
        $upd = $pdo->prepare("
          UPDATE tournament_users tu
          SET tu.lives_remaining = GREATEST(tu.initial_lives - :los, 0)
          WHERE tu.tournament_id=:t AND tu.user_id=:u
        ");
        $count=0;
        foreach ($losses as $uid => $los) {
          $upd->execute([':los'=>(int)$los, ':t'=>$tid, ':u'=>(int)$uid]);
          $count += $upd->rowCount();
        }
        $msgs[] = "Ricalcolate vite per {$count} utente/i (tournament_users.lives_remaining).";
      } else {
        $msgs[] = 'Salto aggiornamento vite: colonne initial_lives/lives_remaining non trovate.';
      }
    } else {
      $msgs[] = 'Salto aggiornamento vite: tabella tournament_users non trovata.';
    }

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msgs[] = 'Errore durante l’applicazione: '.$e->getMessage();
  }

  return $msgs;
}

/** Dettaglio picks per utente, raggruppati per round */
function ta_fetch_user_picks(PDO $pdo, int $tid, int $uid): array {
  $out = ['username'=>ta_username($pdo,$uid), 'rounds'=>[]];
  if ($tid<=0 || $uid<=0) return $out;

  $winnerCol = ta_resolve_winner_column($pdo);
  $sql = "
    SELECT te.round_no, ts.event_id, ts.pick_team_id
         ".($winnerCol ? ", te.`$winnerCol` AS winner_team_id " : ", NULL AS winner_team_id ")."
    FROM tournament_selections ts
    JOIN tournament_events te ON te.id = ts.event_id
    WHERE ts.tournament_id = :t AND ts.user_id = :u
    ORDER BY te.round_no ASC, ts.event_id ASC
  ";
  try {
    $st = $pdo->prepare($sql);
    $st->execute([':t'=>$tid, ':u'=>$uid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { return $out; }

  $byR = [];
  foreach ($rows as $r) {
    $rn = (int)$r['round_no'];
    if (!isset($byR[$rn])) $byR[$rn] = [];
    $status = 'pending';
    if ($winnerCol && $r['winner_team_id'] !== null) {
      $status = ((int)$r['winner_team_id'] === (int)$r['pick_team_id']) ? 'win':'lose';
    }
    $byR[$rn][] = [
      'event_id' => (int)$r['event_id'],
      'pick_team_id' => (int)$r['pick_team_id'],
      'status' => $status
    ];
  }
  ksort($byR);
  foreach ($byR as $rn => $list) {
    $out['rounds'][] = ['round_no'=>$rn, 'picks'=>$list];
  }
  return $out;
}

/** Aggiusta vite di un utente (se lo schema lo consente) */
function ta_adjust_user_lives(PDO $pdo, int $tid, int $uid, int $delta): string {
  if (!ta_safe_table_exists($pdo, 'tournament_users')) return 'Tabella tournament_users assente.';
  if (!ta_safe_column_exists($pdo, 'tournament_users', 'lives_remaining')) return 'Colonna lives_remaining assente.';
  try {
    $st = $pdo->prepare("
      UPDATE tournament_users
      SET lives_remaining = GREATEST(lives_remaining + :d, 0)
      WHERE tournament_id=:t AND user_id=:u
    ");
    $st->execute([':d'=>$delta, ':t'=>$tid, ':u'=>$uid]);
    if ($st->rowCount() > 0) return 'Vite aggiornate.';
    return 'Nessuna riga aggiornata (controlla tournament_id / user_id).';
  } catch (Throwable $e) {
    return 'Errore: '.$e->getMessage();
  }
}
