<?php
// =====================================================================
// /admin/utente_scelte.php — Dettaglio scelte di un utente in un torneo
// Mostra: Round, Vita (life_index), Squadra, Data scelta, Esito (✅/❌/—)
// Dipendenze DB: tournament_selections, tournament_events, utenti, tournaments
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$tid = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$uid = isset($_GET['user_id'])       ? (int)$_GET['user_id']       : 0;

if ($tid <= 0 || $uid <= 0) {
  http_response_code(400);
  echo 'Parametri mancanti (tournament_id / user_id).';
  exit;
}

// Meta: torneo + utente
$torneo = $utente = null;
try {
  $st = $pdo->prepare("SELECT id, name, tournament_code FROM tournaments WHERE id=? LIMIT 1");
  $st->execute([$tid]);
  $torneo = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $torneo = null; }

try {
  $su = $pdo->prepare("SELECT id, username, nome, cognome FROM utenti WHERE id=? LIMIT 1");
  $su->execute([$uid]);
  $utente = $su->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $utente = null; }

if (!$torneo || !$utente) {
  http_response_code(404);
  echo 'Torneo o utente non trovato.';
  exit;
}

// Leggi scelte: join “debole” con eventi del round (per risalire al risultato)
$rows = [];
try {
  $sql = "
    SELECT
      ts.round_no,
      ts.life_index,
      ts.team_id,
      ts.created_at,
      te.id              AS event_id,
      te.home_team_id,
      te.home_team_name,
      te.away_team_id,
      te.away_team_name,
      te.result_status
    FROM tournament_selections ts
    LEFT JOIN tournament_events te
           ON te.tournament_id = ts.tournament_id
          AND te.round_no      = ts.round_no
          AND (te.home_team_id = ts.team_id OR te.away_team_id = ts.team_id)
    WHERE ts.tournament_id = :tid
      AND ts.user_id       = :uid
    ORDER BY ts.round_no ASC, ts.life_index ASC, ts.created_at ASC
  ";
  $q = $pdo->prepare($sql);
  $q->execute([':tid'=>$tid, ':uid'=>$uid]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $rows = [];
}

// Helper: nome squadra scelto
function chosen_team_name(array $r): string {
  $team = (int)($r['team_id'] ?? 0);
  if ($team !== 0) {
    if (!empty($r['home_team_id']) && (int)$r['home_team_id'] === $team) return (string)$r['home_team_name'];
    if (!empty($r['away_team_id']) && (int)$r['away_team_id'] === $team) return (string)$r['away_team_name'];
  }
  // fallback generico
  return $team ? ('Team #'.$team) : '—';
}

// Helper: etichetta esito
function outcome_label(array $r): array {
  // ritorna [label, class]
  $rs = (string)($r['result_status'] ?? '');
  if ($rs === '' || $rs === 'pending') return ['—', 'muted'];
  if ($rs === 'draw' || $rs === 'postponed' || $rs === 'void') return ['✅ Sopravvive', 'ok'];

  $team = (int)($r['team_id'] ?? 0);
  if ($rs === 'home_win') {
    return (!empty($r['home_team_id']) && (int)$r['home_team_id'] === $team)
           ? ['✅ Vinta', 'ok'] : ['❌ Persa', 'err'];
  }
  if ($rs === 'away_win') {
    return (!empty($r['away_team_id']) && (int)$r['away_team_id'] === $team)
           ? ['✅ Vinta', 'ok'] : ['❌ Persa', 'err'];
  }
  return ['—', 'muted'];
}

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Scelte utente — Torneo #<?php echo htmlspecialchars($torneo['tournament_code'] ?? (string)$torneo['id']); ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1100px;margin:24px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .hstack{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .spacer{flex:1 1 auto}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    .meta{color:#cfcfcf}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .ok{color:#00d07e;font-weight:900}
    .err{color:#ff6b6b;font-weight:900}
    .muted{color:#a8a8a8}
    .badge{display:inline-block;border:1px solid rgba(255,255,255,.2);border-radius:999px;padding:2px 8px;font-size:11px}
  </style>
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<div class="wrap">
  <div class="hstack">
    <h1 style="margin:0">Scelte utente</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/utente_vite.php?tournament_id=<?php echo (int)$tid; ?>">← Torna a Gestione vite</a>
  </div>

  <div class="card">
    <div class="meta">
      <div><b>Torneo:</b> #<?php echo htmlspecialchars($torneo['tournament_code'] ?? (string)$torneo['id']); ?> — <?php echo htmlspecialchars($torneo['name'] ?? 'Torneo'); ?></div>
      <div><b>Utente:</b> #<?php echo (int)$utente['id']; ?> — <?php echo htmlspecialchars($utente['username'] ?? ''); ?>
        <?php if (!empty($utente['nome']) || !empty($utente['cognome'])): ?>
          <span class="badge"><?php echo htmlspecialchars(trim(($utente['nome']??'').' '.($utente['cognome']??''))); ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <?php if (!$rows): ?>
      <div class="muted">Nessuna scelta registrata per questo utente in questo torneo.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Round</th>
            <th>Vita</th>
            <th>Squadra</th>
            <th>Data scelta</th>
            <th>Esito</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $teamName = chosen_team_name($r);
            [$esito, $cls] = outcome_label($r);
          ?>
            <tr>
              <td><?php echo (int)$r['round_no']; ?></td>
              <td><?php echo (int)$r['life_index']; ?></td>
              <td><?php echo htmlspecialchars($teamName); ?></td>
              <td><?php echo $r['created_at'] ? htmlspecialchars($r['created_at']) : '—'; ?></td>
              <td class="<?php echo $cls; ?>"><?php echo htmlspecialchars($esito); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" style="margin-top:8px">
        Nota: per il calcolo dell’esito si considera il risultato dell’unico evento del round in cui la squadra scelta ha giocato.
        Per <i>draw / rinviata / annullata</i> la vita sopravvive.
      </p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
