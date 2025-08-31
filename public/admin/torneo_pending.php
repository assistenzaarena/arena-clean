<?php
/**
 * public/admin/torneo_pending.php
 *
 * SCOPO: Preview ADMIN quando un torneo è in stato 'pending' (fixtures incompleti).
 * MOSTRA: meta torneo + lista fixtures trovati (via API live) + motivo incompletezza.
 * AZIONE: pulsante "Conferma e passa a DRAFT" (senza editing manuale in 2B).
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/guards.php';  require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
$competitions = require $ROOT . '/src/config/competitions.php';
require_once $ROOT . '/src/services/football_api.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// --- Parametri ---
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); die('ID torneo mancante'); }

// --- Carico torneo ---
$t = $pdo->prepare("SELECT * FROM tournaments WHERE id = :id LIMIT 1");
$t->execute([':id'=>$id]);
$torneo = $t->fetch(PDO::FETCH_ASSOC);
if (!$torneo) { http_response_code(404); die('Torneo non trovato'); }

if ($torneo['status'] !== 'pending') {
  // Se non è pending, lo rimando alla creazione (o alla futura gestione)
  $_SESSION['flash'] = 'Torneo non in pending.';
  header('Location: /admin/crea_torneo.php'); exit;
}

// --- Dalla competizione ricavo round type e parametri fetch ---
$comp_key = null;
// Trovo la key dalla mappa (in base a league_id + name)
foreach ($competitions as $k=>$c) {
  if ((int)$c['league_id'] === (int)$torneo['league_id']) { $comp_key = $k; break; }
}
$roundType = $comp_key ? $competitions[$comp_key]['round_type'] : ($torneo['round_type'] ?? 'matchday');

$fixturesMin = [];
$err = null;

// --- Fetch live per mostrare la situazione corrente ---
try {
  if ($roundType === 'matchday') {
    $resp = fb_fixtures_matchday((int)$torneo['league_id'], $torneo['season'], (int)$torneo['matchday'], 'Regular Season - %d');
    if (!$resp['ok']) { $err = 'Errore API: '.$resp['error'].' (HTTP '.$resp['status'].')'; }
    else { $fixturesMin = fb_extract_fixtures_minimal($resp['data']); }
  } else {
    $resp = fb_fixtures_round_label((int)$torneo['league_id'], $torneo['season'], (string)$torneo['round_label']);
    if (!$resp['ok']) { $err = 'Errore API: '.$resp['error'].' (HTTP '.$resp['status'].')'; }
    else { $fixturesMin = fb_extract_fixtures_minimal($resp['data']); }
  }
} catch (Throwable $e) {
  $err = 'Eccezione fetch: '.$e->getMessage();
}

// --- POST: conferma e passa a draft ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
    http_response_code(400); die('CSRF non valido');
  }
  $act = $_POST['action'] ?? '';
  if ($act === 'confirm_draft') {
    $pdo->prepare("UPDATE tournaments SET status='draft' WHERE id=:id")->execute([':id'=>$id]);
    $_SESSION['flash'] = 'Torneo confermato: stato bozza (draft).';
    header('Location: /admin/crea_torneo.php'); exit;
  }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Pending torneo #<?php echo (int)$id; ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width: 1100px; margin: 20px auto; padding: 0 16px; color:#fff;}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .meta{font-size:14px;color:#c9c9c9}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    .btn-primary{background:#00c074;border-color:#00c074}
    .btn-warn{background:#e62329;border-color:#e62329}
    .flash{color:#00d07e;margin:8px 0}
    .err{color:#ff6b6b;margin:8px 0}
  </style>
</head>
<body>

<?php require $ROOT . '/header_admin.php'; ?>

<div class="wrap">
  <h1>Preview torneo pending #<?php echo (int)$id; ?></h1>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="meta">
      <div><b>Nome:</b> <?php echo htmlspecialchars($torneo['name']); ?></div>
      <div><b>Competizione:</b> <?php echo htmlspecialchars($torneo['league_name']); ?> (ID: <?php echo (int)$torneo['league_id']; ?>)</div>
      <div><b>Stagione:</b> <?php echo htmlspecialchars($torneo['season']); ?></div>
      <div><b>Tipo round:</b> <?php echo htmlspecialchars($roundType); ?></div>
      <?php if ($roundType==='matchday'): ?>
        <div><b>Giornata:</b> <?php echo (int)$torneo['matchday']; ?></div>
      <?php else: ?>
        <div><b>Round label:</b> <?php echo htmlspecialchars($torneo['round_label']); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($err): ?>
    <div class="card err"><?php echo htmlspecialchars($err); ?></div>
  <?php endif; ?>

  <div class="card">
    <h3 style="margin-top:0">Fixtures trovati</h3>
    <?php if ($fixturesMin): ?>
      <table>
        <thead><tr><th>Fixture ID</th><th>Data</th><th>Casa</th><th>Trasferta</th></tr></thead>
        <tbody>
          <?php foreach ($fixturesMin as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$r['fixture_id']); ?></td>
              <td><?php echo htmlspecialchars($r['date']); ?></td>
              <td><?php echo htmlspecialchars($r['home_name']); ?></td>
              <td><?php echo htmlspecialchars($r['away_name']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div style="color:#aaa">Nessuna partita trovata per i parametri indicati.</div>
    <?php endif; ?>
  </div>

  <form method="post" action="" style="display:inline">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="action" value="confirm_draft">
    <button class="btn btn-primary" type="submit">Conferma e passa a DRAFT</button>
  </form>
  <a class="btn" href="/admin/crea_torneo.php">Torna</a>
</div>

</body>
</html>
