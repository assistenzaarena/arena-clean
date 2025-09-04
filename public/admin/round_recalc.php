<?php
// =====================================================================
// admin/round_recalc.php — ANTEPRIMA ricalcolo di un round (READ-ONLY)
// - Nessuna UPDATE/DELETE/INSERT: calcola solo a video “cosa succederebbe”
// - Replica la logica di calc_round.php (perdite su wrong pick + no-pick)
// =====================================================================

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

// Input
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$round_no      = isset($_GET['round_no'])      ? (int)$_GET['round_no']      : 0;

$flash = null;

// Helper: leggi lista tornei (pochi campi)
function all_tournaments(PDO $pdo): array {
  try {
    $q = $pdo->query("SELECT id, tournament_code, name, status, current_round_no FROM tournaments ORDER BY id DESC");
    return $q->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { return []; }
}

// Helper: round disponibili nel torneo
function rounds_in_tournament(PDO $pdo, int $tid): array {
  try {
    $st = $pdo->prepare("SELECT DISTINCT round_no FROM tournament_events WHERE tournament_id=? ORDER BY round_no ASC");
    $st->execute([$tid]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
  } catch (Throwable $e) { return []; }
}

// Helper: risultato sopravvivenza su una singola pick
// ritorna true se la pick SOPRAVVIVE, false se PERDE 1 vita
function survives_pick(?string $result_status, string $side): bool {
  $res = strtolower((string)$result_status);
  if ($res === '' || in_array($res, ['pending','postponed','void','draw'], true)) {
    return true; // nessuna perdita
  }
  if ($res === 'home_win') return ($side === 'home');
  if ($res === 'away_win') return ($side === 'away');
  // Qualsiasi altro valore (per sicurezza): non sopravvive se non corrisponde
  return false;
}

// Precarica tornei per la tendina
$tornei = all_tournaments($pdo);

// Preparazione dati calcolo (se ho parametri validi)
$torneo = null;
$rounds = [];
$preview = [
  'users' => [],        // per utente: breakdown
  'totals'=> [ 'users'=>0, 'picks'=>0, 'wrong'=>0, 'no_pick'=>0, 'would_eliminated'=>0 ],
];

if ($tournament_id > 0) {
  // Torneo
  $stT = $pdo->prepare("SELECT * FROM tournaments WHERE id=? LIMIT 1");
  $stT->execute([$tournament_id]);
  $torneo = $stT->fetch(PDO::FETCH_ASSOC) ?: null;

  // Round disponibili
  $rounds = rounds_in_tournament($pdo, $tournament_id);

  if ($torneo && $round_no > 0) {
    try {
      // 1) Risultati eventi del round selezionato
      $stEv = $pdo->prepare("
        SELECT id, result_status
        FROM tournament_events
        WHERE tournament_id=? AND round_no=?
      ");
      $stEv->execute([$tournament_id, $round_no]);
      $evRes = [];
      foreach ($stEv as $r) {
        $evRes[(int)$r['id']] = (string)$r['result_status'];
      }

      // 2) Utenti iscritti (stato attuale vite)
      $stEn = $pdo->prepare("
        SELECT e.user_id, e.lives AS lives_now, u.username, u.email
        FROM tournament_enrollments e
        JOIN utenti u ON u.id = e.user_id
        WHERE e.tournament_id=?
        ORDER BY u.username ASC, e.user_id ASC
      ");
      $stEn->execute([$tournament_id]);
      $enrolled = [];
      foreach ($stEn as $r) {
        $uid = (int)$r['user_id'];
        $enrolled[$uid] = [
          'user_id'   => $uid,
          'username'  => (string)$r['username'],
          'email'     => (string)$r['email'],
          'lives_now' => (int)$r['lives_now'],
        ];
      }

      // 3) Selezioni finalizzate per il round selezionato
      //    Prendiamo round_no da ts.round_no SE esiste, altrimenti dal join con te.round_no
      $stSel = $pdo->prepare("
        SELECT
          ts.user_id,
          ts.life_index,
          ts.event_id,
          ts.side,
          COALESCE(ts.round_no, te.round_no) AS rno
        FROM tournament_selections ts
        JOIN tournament_events te ON te.id = ts.event_id AND te.tournament_id = ts.tournament_id
        WHERE ts.tournament_id = :tid
          AND COALESCE(ts.round_no, te.round_no) = :r
          AND ts.finalized_at IS NOT NULL
      ");
      $stSel->execute([':tid'=>$tournament_id, ':r'=>$round_no]);
      $picks = $stSel->fetchAll(PDO::FETCH_ASSOC);

      // 4) Aggrega per utente: quante pick, quante wrong
      $agg = []; // uid => ['picks'=>N, 'wrong'=>W]
      foreach ($picks as $p) {
        $uid  = (int)$p['user_id'];
        $evId = (int)$p['event_id'];
        $side = (string)$p['side'];
        if (!isset($agg[$uid])) $agg[$uid] = ['picks'=>0, 'wrong'=>0];

        $agg[$uid]['picks']++;

        $res = $evRes[$evId] ?? null;
        $survive = survives_pick($res, $side);
        if (!$survive) {
          $agg[$uid]['wrong']++;
        }
      }

      // 5) Calcola no-pick per utente in base alle vite attuali
      //    NB: replica calc_round.php: no-pick = max(0, lives_now - picks_in_round)
      $rowsUsers = [];
      $tot = [ 'users'=>0, 'picks'=>0, 'wrong'=>0, 'no_pick'=>0, 'would_eliminated'=>0 ];

      foreach ($enrolled as $uid => $info) {
        $picksCnt = (int)($agg[$uid]['picks']  ?? 0);
        $wrongCnt = (int)($agg[$uid]['wrong']  ?? 0);
        $livesNow = (int)$info['lives_now'];

        $noPick   = max(0, $livesNow - $picksCnt);
        $wouldLose= $wrongCnt + $noPick;
        $wouldNew = max(0, $livesNow - $wouldLose);
        $elim     = ($wouldNew <= 0) ? 1 : 0;

        $rowsUsers[] = [
          'user_id'  => $uid,
          'username' => $info['username'],
          'email'    => $info['email'],
          'lives_now'=> $livesNow,
          'picks'    => $picksCnt,
          'wrong'    => $wrongCnt,
          'no_pick'  => $noPick,
          'would_lose' => $wouldLose,
          'would_new'  => $wouldNew,
          'would_eliminated' => $elim,
        ];

        $tot['users']++;
        $tot['picks'] += $picksCnt;
        $tot['wrong'] += $wrongCnt;
        $tot['no_pick'] += $noPick;
        $tot['would_eliminated'] += $elim;
      }

      // Ordinamento a video: eliminati in alto
      usort($rowsUsers, function($a,$b){
        if ($a['would_eliminated'] !== $b['would_eliminated']) return $b['would_eliminated'] - $a['would_eliminated'];
        if ($a['would_new'] !== $b['would_new']) return $a['would_new'] - $b['would_new'];
        return strcmp($a['username'] ?? '', $b['username'] ?? '');
      });

      $preview['users']  = $rowsUsers;
      $preview['totals'] = $tot;

    } catch (Throwable $e) {
      $flash = 'Errore durante il calcolo: ' . $e->getMessage();
    }
  }
}

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Anteprima ricalcolo round</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/dashboard.css">
  <style>
    .wrap{max-width:1280px;margin:24px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:0 8px 28px rgba(0,0,0,.18)}
    .hstack{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .spacer{flex:1}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;font-weight:800;background:#202326;text-decoration:none;cursor:pointer}
    .btn:hover{border-color:#fff}
    .muted{color:#bdbdbd}
    table.tbl{width:100%;border-collapse:separate;border-spacing:0 8px}
    .tbl th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:#c9c9c9;text-align:left;padding:8px 10px}
    .tbl td{background:#111;border:1px solid rgba(255,255,255,.12);padding:10px 12px;vertical-align:middle}
    .pill{display:inline-flex;align-items:center;justify-content:center;height:24px;padding:0 10px;border-radius:9999px;font-size:12px;font-weight:900}
    .pill-red{background:#e62329;color:#fff}
    .pill-green{background:#00c074;color:#04140c}
  </style>
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="wrap">
  <h1 style="margin:0 0 8px;">Anteprima ricalcolo round</h1>

  <section class="card">
    <form method="get" action="/admin/round_recalc.php" class="hstack" style="gap:8px; align-items:flex-end;">
      <div>
        <label style="display:block;font-size:12px;color:#c9c9c9">Torneo</label>
        <select name="tournament_id" required style="min-width:300px;background:#0a0a0b;border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:8px;height:36px;padding:0 8px">
          <option value="">— seleziona —</option>
          <?php foreach ($tornei as $t): ?>
            <option value="<?php echo (int)$t['id']; ?>" <?php echo ($tournament_id===(int)$t['id']?'selected':''); ?>>
              #<?php echo htmlspecialchars($t['tournament_code'] ?? sprintf('%05d',(int)$t['id'])); ?> — <?php echo htmlspecialchars($t['name'] ?? ''); ?> (<?php echo htmlspecialchars($t['status'] ?? ''); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label style="display:block;font-size:12px;color:#c9c9c9">Round</label>
        <select name="round_no" required style="min-width:120px;background:#0a0a0b;border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:8px;height:36px;padding:0 8px">
          <option value="">—</option>
          <?php foreach ($rounds as $r): ?>
            <option value="<?php echo (int)$r; ?>" <?php echo ($round_no===(int)$r?'selected':''); ?>><?php echo (int)$r; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button class="btn" type="submit">Calcola anteprima</button>

      <span class="spacer"></span>
      <a class="btn" href="/admin/gestisci_tornei.php">Torna a gestione tornei</a>
    </form>
  </section>

  <?php if ($flash): ?>
    <section class="card" style="border-color:#e62329">
      <div class="muted"><?php echo $flash; ?></div>
    </section>
  <?php endif; ?>

  <?php if ($torneo && $round_no>0): ?>
    <section class="card">
      <h2 style="margin:0 0 10px">Riepilogo</h2>
      <div class="hstack" style="gap:14px;flex-wrap:wrap">
        <div><strong>Torneo:</strong> #<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',$tournament_id)); ?> — <?php echo htmlspecialchars($torneo['name'] ?? ''); ?></div>
        <div><strong>Round:</strong> <?php echo (int)$round_no; ?></div>
        <div><strong>Iscritti:</strong> <?php echo (int)$preview['totals']['users']; ?></div>
        <div><strong>Pick totali:</strong> <?php echo (int)$preview['totals']['picks']; ?></div>
        <div><strong>Perdite da wrong pick:</strong> <?php echo (int)$preview['totals']['wrong']; ?></div>
        <div><strong>Perdite da no-pick:</strong> <?php echo (int)$preview['totals']['no_pick']; ?></div>
        <div><strong>Eliminati (ipotetici):</strong> <?php echo (int)$preview['totals']['would_eliminated']; ?></div>
      </div>
      <p class="muted" style="margin-top:6px">Questi numeri rappresentano l’effetto che avrebbe il ricalcolo del round sullo stato attuale delle vite, senza apportare modifiche.</p>
    </section>

    <section class="card">
      <h2 style="margin:0 0 10px">Dettaglio per utente (anteprima)</h2>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Vite attuali</th>
              <th>Pick</th>
              <th>Wrong</th>
              <th>No-pick</th>
              <th>Perdite totali</th>
              <th>Vite dopo</th>
              <th>Esito</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($preview['users'])): ?>
              <tr><td colspan="9" class="muted">Nessun utente iscritto o nessuna selezione per questo round.</td></tr>
            <?php else: ?>
              <?php foreach ($preview['users'] as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['username']); ?></td>
                  <td class="muted"><?php echo htmlspecialchars($row['email']); ?></td>
                  <td><?php echo (int)$row['lives_now']; ?></td>
                  <td><?php echo (int)$row['picks']; ?></td>
                  <td><?php echo (int)$row['wrong']; ?></td>
                  <td><?php echo (int)$row['no_pick']; ?></td>
                  <td><?php echo (int)$row['would_lose']; ?></td>
                  <td><?php echo (int)$row['would_new']; ?></td>
                  <td>
                    <?php if ((int)$row['would_eliminated'] === 1): ?>
                      <span class="pill pill-red">Eliminato</span>
                    <?php else: ?>
                      <span class="pill pill-green">Vivo</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <p class="muted" style="margin-top:6px">Nota: il ricalcolo “vero” applicherà le stesse regole; questa è una simulazione sullo stato attuale del torneo.</p>
    </section>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer.php'; ?>
</body>
</html>
