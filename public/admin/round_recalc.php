<?php
// admin/round_recalc.php — Anteprima ricalcolo round + apply
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/tournament_admin_utils.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$tournaments = ta_get_tournaments($pdo);
$tid = (int)($_GET['tournament_id'] ?? 0);
$rounds = $tid ? ta_fetch_rounds($pdo, $tid) : [];
$round = (int)($_GET['round_no'] ?? 0);

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$preview = ['summary'=>['total'=>0,'wins'=>0,'losses'=>0,'pending'=>0], 'users'=>[]];
if ($tid && $round) {
  $preview = ta_compute_round_preview($pdo, $tid, $round);
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Anteprima ricalcolo round</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1280px;margin:24px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .hstack{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .spacer{flex:1 1 auto}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;font-weight:800;background:#202326;text-decoration:none;cursor:pointer}
    .btn:hover{border-color:#fff}
    .btn-ok{background:#00c074;border-color:#00c074}
    .btn-danger{background:#e62329;border-color:#e62329}
    label{font-size:12px;color:#c9c9c9}
    select{height:36px;background:#0a0a0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;padding:0 8px}
    table{width:100%;border-collapse:separate;border-spacing:0 8px}
    th{color:#c9c9c9;font-size:12px;letter-spacing:.03em;text-transform:uppercase;text-align:left;padding:8px}
    td{background:#111;border:1px solid rgba(255,255,255,.12);padding:10px 12px}
    .flash{padding:10px 12px;border-radius:8px;margin-bottom:12px;background:rgba(0,192,116,.1);border:1px solid rgba(0,192,116,.4);color:#00c074}
  </style>
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="wrap">
  <div class="hstack">
    <h1 style="margin:0">Anteprima ricalcolo round</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/gestisci_tornei.php">Torna a gestione tornei</a>
  </div>

  <?php if ($flash): ?>
    <div class="flash"><?php echo is_array($flash)?htmlspecialchars(implode(' ', $flash)):htmlspecialchars($flash); ?></div>
  <?php endif; ?>

  <section class="card">
    <form id="f" method="get" action="/admin/round_recalc.php" class="hstack" style="gap:10px">
      <label for="selT">Torneo</label>
      <select id="selT" name="tournament_id">
        <option value="">—</option>
        <?php foreach ($tournaments as $t): ?>
          <option value="<?php echo (int)$t['id']; ?>" <?php echo ($tid===(int)$t['id']?'selected':''); ?>>
            <?php echo '#'.$t['id'].' — '.htmlspecialchars($t['name']??'Torneo'); ?>
            <?php if (!empty($t['status'])) echo ' ('.htmlspecialchars($t['status']).')'; ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="selR">Round</label>
      <select name="round_no" id="selR">
        <?php if ($rounds): foreach ($rounds as $r): ?>
          <option value="<?php echo (int)$r; ?>" <?php echo ($round===(int)$r?'selected':''); ?>>
            <?php echo (int)$r; ?>
          </option>
        <?php endforeach; else: ?>
          <option value="">—</option>
        <?php endif; ?>
      </select>

      <button class="btn" type="submit">Calcola anteprima</button>

      <?php if ($tid && $round): ?>
        <span class="spacer"></span>
        <form method="post" action="/admin/round_recalc_apply.php" style="display:inline;">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
          <input type="hidden" name="tournament_id" value="<?php echo (int)$tid; ?>">
          <input type="hidden" name="round_no" value="<?php echo (int)$round; ?>">
          <button class="btn btn-danger" type="submit" onclick="return confirm('Confermi l\\\'applicazione del ricalcolo per il round <?php echo (int)$round; ?>? Azione irreversibile.');">
            Applica ricalcolo
          </button>
        </form>
      <?php endif; ?>
    </form>
  </section>

  <?php if ($tid && $round): ?>
    <section class="card">
      <h2 style="margin:0 0 10px">Riepilogo</h2>
      <div class="hstack" style="gap:20px">
        <div><strong>Pick totali:</strong> <?php echo (int)$preview['summary']['total']; ?></div>
        <div><strong>Vinte:</strong> <?php echo (int)$preview['summary']['wins']; ?></div>
        <div><strong>Perse:</strong> <?php echo (int)$preview['summary']['losses']; ?></div>
        <div><strong>Pending:</strong> <?php echo (int)$preview['summary']['pending']; ?></div>
      </div>
    </section>

    <section class="card">
      <h2 style="margin:0 0 10px">Dettaglio per utente</h2>
      <table>
        <thead>
          <tr>
            <th>Utente</th><th>Pick</th><th>Vinte</th><th>Perse</th><th>Pending</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$preview['users']): ?>
            <tr><td colspan="5" class="muted">Nessun dato per questo round.</td></tr>
          <?php else: foreach ($preview['users'] as $u): ?>
            <tr>
              <td><?php echo htmlspecialchars($u['username']); ?> <span class="muted">(#<?php echo (int)$u['user_id']; ?>)</span></td>
              <td><?php echo (int)$u['picks']; ?></td>
              <td><?php echo (int)$u['wins']; ?></td>
              <td><?php echo (int)$u['losses']; ?></td>
              <td><?php echo (int)$u['pending']; ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>
</main>

<script>
  (function(){
    var selT = document.getElementById('selT');
    var form = document.getElementById('f');
    if (selT && form) {
      selT.addEventListener('change', function(){ form.submit(); });
    }
  })();
</script>
</body>
</html>
