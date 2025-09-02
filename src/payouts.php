<?php
// src/payouts.php
declare(strict_types=1);

// <<< AGGIUNTA: serve per generate_unique_code8()
require_once __DIR__ . '/utils.php';

function tp_compute_pot(PDO $pdo, int $tournament_id): array {
  // legge % e garantito
  $st = $pdo->prepare("SELECT prize_percent, guaranteed_prize FROM tournaments WHERE id=? LIMIT 1");
  $st->execute([$tournament_id]);
  $t = $st->fetch(PDO::FETCH_ASSOC);
  if (!$t) throw new RuntimeException("Tournament not found");

  $pp = (int)($t['prize_percent'] ?? 100);
  $g  = (int)($t['guaranteed_prize'] ?? 0);

  // somma contabile
  $sm = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0) AS s
    FROM credit_movements
    WHERE tournament_id = ?
      AND type IN ('enroll','buy_life')
  ");
  $sm->execute([$tournament_id]);
  $sum = (int)$sm->fetchColumn(); // tipicamente negativo
  $gross = -$sum;                  // converti a positivo

  $pot = max($g, (int)floor($gross * $pp / 100));
  return ['pot' => $pot, 'prize_percent' => $pp, 'guaranteed' => $g, 'gross_in' => $gross];
}

function tp_get_survivors(PDO $pdo, int $tournament_id): array {
  $st = $pdo->prepare("SELECT user_id, lives FROM tournament_enrollments WHERE tournament_id=? AND lives>0");
  $st->execute([$tournament_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $out = [];
  foreach ($rows as $r) $out[(int)$r['user_id']] = (int)$r['lives'];
  return $out; // user_id => lives
}

/**
 * Ricava le vite all'inizio dell'ultimo round disputato.
 * Strategia:
 *  - Prende il max round_no presente in selections del torneo
 *  - Seleziona gli utenti iscritti e guarda quante "vite" avevano "attive" come proxy:
 *    (approccio pragmatico) calcoliamo il peso usando l'ultima selezione "normale" di ogni vita in quel round.
 *    Se non hai 5D (storico vite), questo è il miglior fallback senza toccare altre tabelle.
 */
function tp_weights_last_round(PDO $pdo, int $tournament_id): array {
  // ultimo round dove esistono selections
  $stR = $pdo->prepare("SELECT COALESCE(MAX(round_no),0) FROM tournament_selections WHERE tournament_id=?");
  $stR->execute([$tournament_id]);
  $last = (int)$stR->fetchColumn();
  if ($last <= 0) return []; // fallback impossibile

  // per ciascun utente: conteggia quante vite hanno espresso una scelta "normale" nell'ultimo round
  $st = $pdo->prepare("
    SELECT user_id, life_index
    FROM tournament_selections
    WHERE tournament_id=? AND round_no=? AND COALESCE(is_fallback,0)=0
    GROUP BY user_id, life_index
  ");
  $st->execute([$tournament_id, $last]);
  $weights = []; // user_id => lives_count
  foreach ($st as $row) {
    $uid = (int)$row['user_id'];
    $weights[$uid] = ($weights[$uid] ?? 0) + 1;
  }

  // Se qualcuno aveva vite "mute" (no-pick), non comparirà. Se vuoi essere inclusivo, puoi sommare vite residue in enrollments se >0.
  return $weights;
}

/**
 * Esegue payout winner-takes-all o proporzionale.
 * - Aggiorna utenti.crediti
 * - Inserisce movement 'payout'
 * - Inserisce righe in tournament_payouts
 * - Chiude torneo
 */
function tp_close_and_payout(PDO $pdo, int $tournament_id, array $weights=null): array {
  $potInfo = tp_compute_pot($pdo, $tournament_id);
  $pot = (int)$potInfo['pot'];

  $surv = tp_get_survivors($pdo, $tournament_id);
  $n = count($surv);

  $pdo->beginTransaction();

  try {
    // >>> AGGIUNTA PRECEDENTE: split forzato se l'admin passa pesi espliciti (anche con n >= 2)
    if (is_array($weights) && !empty($weights)) {
      $den = array_sum($weights);
      if ($den <= 0 || $pot <= 0) {
        $pdo->prepare("UPDATE tournaments SET status='closed', closed_at=NOW() WHERE id=?")->execute([$tournament_id]);
        $pdo->commit();
        return ['ok'=>true,'mode'=>'proportional','pot'=>0,'weights'=>$weights,'note'=>'forced_no_pot_or_den'];
      }

      $assigned = 0; $maxUid = null; $maxW = -1;
      foreach ($weights as $uid => $w) {
        $uid = (int)$uid; $w = (int)$w;
        if ($w > $maxW) { $maxW = $w; $maxUid = $uid; }
        $share = (int) floor($pot * $w / $den);
        if ($share > 0) {
          $pdo->prepare("UPDATE utenti SET crediti = crediti + ? WHERE id = ?")->execute([$share, $uid]);
          $pdo->prepare("INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
                         VALUES (?, ?, ?, 'payout', ?, NOW())")
              ->execute([generate_unique_code8($pdo,'credit_movements','movement_code',8), $uid, $tournament_id, $share]);
          $pdo->prepare("INSERT INTO tournament_payouts (tournament_id, user_id, amount, reason, ratio_num, ratio_den, note)
                         VALUES (?, ?, ?, 'proportional', ?, ?, 'Forced split')")
              ->execute([$tournament_id, $uid, $share, $w, $den]);
          $assigned += $share;
        }
      }
      // residuo al più pesato
      $residual = $pot - $assigned;
      if ($residual > 0 && $maxUid !== null) {
        $pdo->prepare("UPDATE utenti SET crediti = crediti + ? WHERE id = ?")->execute([$residual, (int)$maxUid]);
        $pdo->prepare("INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
                       VALUES (?, ?, ?, 'payout', ?, NOW())")
            ->execute([generate_unique_code8($pdo,'credit_movements','movement_code',8), (int)$maxUid, $tournament_id, $residual]);
        $pdo->prepare("INSERT INTO tournament_payouts (tournament_id, user_id, amount, reason, ratio_num, ratio_den, note)
                       VALUES (?, ?, ?, 'proportional', ?, ?, 'Forced residual')")
            ->execute([$tournament_id, (int)$maxUid, $residual, (int)($weights[$maxUid] ?? 1), $den]);
      }

      $pdo->prepare("UPDATE tournaments SET status='closed', closed_at=NOW() WHERE id=?")->execute([$tournament_id]);
      $pdo->commit();
      return ['ok'=>true,'mode'=>'proportional_forced','pot'=>$pot,'weights'=>$weights];
    }
    // <<< FINE AGGIUNTA PRECEDENTE

    if ($n === 1) {
      // Winner takes all
      $winner_id = array_key_first($surv);
      if ($pot > 0) {
        // accredita
        $up = $pdo->prepare("UPDATE utenti SET crediti = crediti + ? WHERE id = ?");
        $up->execute([$pot, $winner_id]);
        // movement
        $mv = $pdo->prepare("INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
                             VALUES (?, ?, ?, 'payout', ?, NOW())");
        $mv->execute([generate_unique_code8($pdo,'credit_movements','movement_code',8), $winner_id, $tournament_id, $pot]);
        // rendiconto
        $tp = $pdo->prepare("INSERT INTO tournament_payouts (tournament_id, user_id, amount, reason, ratio_num, ratio_den, note)
                             VALUES (?, ?, ?, 'winner', NULL, NULL, 'Winner takes all')");
        $tp->execute([$tournament_id, $winner_id, $pot]);
      }
      // chiudi torneo
      $pdo->prepare("UPDATE tournaments SET status='closed', closed_at=NOW() WHERE id=?")->execute([$tournament_id]);

      $pdo->commit();
      return ['ok'=>true,'mode'=>'winner','winner'=>$winner_id,'pot'=>$pot];
    }

    if ($n === 0) {
      // Proporzionale: usa weights forniti o ricavati
      if ($weights === null || empty($weights)) {
        $weights = tp_weights_last_round($pdo, $tournament_id);
      }
      $den = array_sum($weights);
      if ($den <= 0 || $pot <= 0) {
        // Niente da distribuire
        $pdo->prepare("UPDATE tournaments SET status='closed', closed_at=NOW() WHERE id=?")->execute([$tournament_id]);
        $pdo->commit();
        return ['ok'=>true,'mode'=>'proportional','pot'=>0,'note'=>'no weights/pot'];
      }

      // distribuzione intera (arrotondamento verso il basso, eventuali residui li diamo al maggiore)
      $assigned = 0; $maxUid = null; $maxW = -1;
      foreach ($weights as $uid => $w) {
        if ($w > $maxW) { $maxW = $w; $maxUid = $uid; }
        $share = (int) floor($pot * $w / $den);
        if ($share > 0) {
          // accredita
          $pdo->prepare("UPDATE utenti SET crediti = crediti + ? WHERE id = ?")->execute([$share, (int)$uid]);
          $pdo->prepare("INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
                         VALUES (?, ?, ?, 'payout', ?, NOW())")
              ->execute([generate_unique_code8($pdo,'credit_movements','movement_code',8), (int)$uid, $tournament_id, $share]);
          $pdo->prepare("INSERT INTO tournament_payouts (tournament_id, user_id, amount, reason, ratio_num, ratio_den, note)
                         VALUES (?, ?, ?, 'proportional', ?, ?, 'All lives eliminated')")
              ->execute([$tournament_id, (int)$uid, $share, (int)$w, (int)$den]);
          $assigned += $share;
        }
      }
      // residuo (se presente) al più pesato
      $residual = $pot - $assigned;
      if ($residual > 0 && $maxUid !== null) {
        $pdo->prepare("UPDATE utenti SET crediti = crediti + ? WHERE id = ?")->execute([$residual, (int)$maxUid]);
        $pdo->prepare("INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
                       VALUES (?, ?, ?, 'payout', ?, NOW())")
            ->execute([generate_unique_code8($pdo,'credit_movements','movement_code',8), (int)$maxUid, $tournament_id, $residual]);
        $pdo->prepare("INSERT INTO tournament_payouts (tournament_id, user_id, amount, reason, ratio_num, ratio_den, note)
                       VALUES (?, ?, ?, 'proportional', ?, ?, 'Residual assign')")
            ->execute([$tournament_id, (int)$maxUid, $residual, (int)($weights[$maxUid] ?? 1), (int)$den]);
      }

      // chiudi torneo
      $pdo->prepare("UPDATE tournaments SET status='closed', closed_at=NOW() WHERE id=?")->execute([$tournament_id]);
      $pdo->commit();
      return ['ok'=>true,'mode'=>'proportional','pot'=>$pot,'weights'=>$weights];
    }

    // n >= 2 → non chiudere
    $pdo->rollBack();
    return ['ok'=>false,'error'=>'still_running','survivors'=>$surv];

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}
