<?php
// =====================================================================
// /admin/utente_scelte.php — Dettaglio scelte di un utente per torneo
// Mostra round per round le scelte effettuate (per vita) ed esito.
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/guards.php';  require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

$tournamentId = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$userId       = isset($_GET['user_id'])       ? (int)$_GET['user_id']       : 0;

if ($tournamentId <= 0 || $userId <= 0) {
  http_response_code(400);
  echo 'Parametri mancanti.';
  exit;
}

// Info torneo e utente (per header pagina)
$torneo = ['id'=>$tournamentId,'name'=>'Torneo'];
$utente = ['id'=>$userId,'username'=>'utente'];
try {
  $st = $pdo->prepare("SELECT id, name FROM tournaments WHERE id=? LIMIT 1");
  $st->execute([$tournamentId]);
  if ($r = $st->fetch(PDO::FETCH_ASSOC)) $torneo = $r;
} catch(Throwable $e){/*noop*/}

try {
  $st = $pdo->prepare("SELECT id, username FROM utenti WHERE id=? LIMIT 1");
  $st->execute([$userId]);
  if ($r = $st->fetch(PDO::FETCH_ASSOC)) $utente = $r;
} catch(Throwable $e){/*noop*/}

// Carico le scelte.
// Schema atteso (più comune):
//   tournament_selections s: id, tournament_id, user_id, round_no, life_index,
//                            event_id, pick_side ('home'|'away'|'draw'?), pick_team_name
//   tournament_events e:    id, home_team_name, away_team_name, result_status, result_at
//
// Se il DB ha nomi diversi per le colonne pick_* il codice mostra comunque la riga, ma senza esito.
$rows = [];
$errorMsg = null;
try {
  $sql = "
    SELECT
      s.id,
      s.round_no,
      s.life_index,
      s.event_id,
      /* pick-side/label possono non esistere in alcuni schemi */
      CASE
        WHEN EXISTS(
          SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tournament_selections' AND COLUMN_NAME='pick_side'
        ) THEN s.pick_side
        ELSE NULL
      END AS pick_side,
      CASE
        WHEN EXISTS(
          SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tournament_selections' AND COLUMN_NAME='pick_team_name'
        ) THEN s.pick_team_name
        ELSE NULL
      END AS pick_team_name,

      e.home_team_name, e.away_team_name, e.result_status, e.result_at
    FROM tournament_selections s
    LEFT JOIN tournament_events e
      ON e.id = s.event_id
    WHERE s.tournament_id = :t AND s.user_id = :u
    ORDER BY s.round_no ASC, s.life_index ASC, s.id ASC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':t'=>$tournamentId, ':u'=>$userId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $errorMsg = 'Impossibile leggere le scelte (schema DB non compatibile).';
}

// Funzione esito: prova a dedurre WIN/LOSE se abbiamo pick_side e result_status
function esito_testuale(array $r): array {
  $status = $r['result_status'] ?? null;
  $side   = $r['pick_side']     ?? null;

  // normalizza
  $status = is_string($status) ? strtolower($status) : null;
  $side   = is_string($side)   ? strtolower($side)   : null;

  $text = '—';
  $class = ''; // '' | win | lose | pend

  if (!$status) {
    return ['text'=>$text, 'class'=>''];
  }

  if ($status === 'pending') {
    return ['text'=>'In attesa', 'class'=>'pend'];
  }
  if ($status === 'postponed') {
    return ['text'=>'Rinviata', 'class'=>'pend'];
  }
  if ($status === 'void') {
    return ['text'=>'Annullata', 'class'=>'pend'];
  }

  // se non abbiamo pick_side, mostriamo solo lo stato evento
  if (!$side) {
    $map = ['home_win'=>'Casa vince','away_win'=>'Trasferta vince','draw'=>'Pareggio'];
    return ['text'=>($map[$status] ?? $status), 'class'=>''];
  }

  $win = (
    ($status === 'home_win' && $side === 'home') ||
    ($status === 'away_win' && $side === 'away') ||
    ($status === 'draw'     && $side === 'draw')
  );

  if ($win) {
    return ['text'=>'Vinta', 'class'=>'win'];
  }
  // se lo stato evento è definito e side non corrisponde -> persa
  if (in_array($status, ['home_win','away_win','draw'], true)) {
    return ['text'=>'Persa', 'class'=>'lose'];
  }

  return ['text'=>'—', 'class'=>''];
}

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Scelte — <?php echo htmlspecialchars($utente['username']); ?> — #<?php echo (int)$torneo['id']; ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1100px;margin:20px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .muted{color:#bdbdbd}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .pill{display:inline-flex;align-items:center;justify-content:center;height:22px;padding:0 10px;border-radius:999px;font-size:12px;font-weight:800}
    .pill.win{background:#0b6e44;color:#fff}
    .pill.lose{background:#7a1a1a;color:#fff}
    .pill.pend{background:#30343a;color:#ddd}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
  </style>
</head>
<body>
<?php require $ROOT . '/header_admin.php'; ?>
<div class="wrap">

  <div class="card">
    <h1 style="margin:0 0 8px;">Scelte utente — <?php echo htmlspecialchars($utente['username']); ?></h1>
    <div class="muted">Torneo #<?php echo (int)$torneo['id']; ?> — <?php echo htmlspecialchars($torneo['name']); ?></div>
    <div style="margin-top:10px">
      <a class="btn" href="/admin/utente_vite.php?tournament_id=<?php echo (int)$torneo['id']; ?>">← Torna a Gestione vite</a>
    </div>
  </div>

  <?php if ($errorMsg): ?>
    <div class="card" style="color:#ff7076;"><?php echo htmlspecialchars($errorMsg); ?></div>
  <?php elseif (!$rows): ?>
    <div class="card muted">Nessuna scelta trovata per questo utente in questo torneo.</div>
  <?php else: ?>
    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Round</th>
            <th>Vita</th>
            <th>Partita</th>
            <th>Scelta</th>
            <th>Esito</th>
            <th>Ultimo aggiornamento</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): 
            $match = '—';
            if (!empty($r['home_team_name']) || !empty($r['away_team_name'])) {
              $match = trim(($r['home_team_name'] ?? '??').' vs '.($r['away_team_name'] ?? '??'));
            }
            $pick = $r['pick_team_name'] ?? null;
            if (!$pick) { // se non abbiamo il nome squadra, usa pick_side
              if (!empty($r['pick_side'])) {
                $sideLbl = ['home'=>'Casa','away'=>'Trasferta','draw'=>'Pareggio'];
                $pick = $sideLbl[strtolower($r['pick_side'])] ?? $r['pick_side'];
              } else {
                $pick = '—';
              }
            }
            $esito = esito_testuale($r);
          ?>
            <tr>
              <td><?php echo (int)($r['round_no'] ?? 0); ?></td>
              <td><?php echo (int)($r['life_index'] ?? 0); ?></td>
              <td><?php echo htmlspecialchars($match); ?></td>
              <td><?php echo htmlspecialchars($pick); ?></td>
              <td>
                <span class="pill <?php echo htmlspecialchars($esito['class']); ?>">
                  <?php echo htmlspecialchars($esito['text']); ?>
                </span>
              </td>
              <td><?php echo !empty($r['result_at']) ? htmlspecialchars($r['result_at']) : '—'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
