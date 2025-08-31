<?php
/**
 * public/torneo.php
 * Pagina singolo torneo (con pulsante Disiscriviti e popup conferma).
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__;
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';

require_login();
$uid = (int)($_SESSION['user_id'] ?? 0);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); die('ID torneo mancante'); }

$q = $pdo->prepare("SELECT * FROM tournaments WHERE id=:id LIMIT 1");
$q->execute([':id'=>$id]);
$torneo = $q->fetch(PDO::FETCH_ASSOC);
if (!$torneo) { http_response_code(404); die('Torneo non trovato'); }

$enrolled = false;
try {
  $chk = $pdo->prepare("SELECT 1 FROM tournament_enrollments WHERE tournament_id=:tid AND user_id=:uid LIMIT 1");
  $chk->execute([':tid'=>$id, ':uid'=>$uid]);
  $enrolled = (bool)$chk->fetchColumn();
} catch (Throwable $e) {
  $enrolled = false;
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Torneo #<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',$id)); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_user.css">
  <link rel="stylesheet" href="/assets/lobby.css">
  <style>
    .torneo-wrap{max-width:1000px; margin:20px auto; padding:0 16px; color:#fff; position:relative;}
    .torneo-head{display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;}
    .torneo-title{font-size:24px; font-weight:900;}
    .btn{display:inline-block; padding:6px 12px; border-radius:6px; font-weight:700; cursor:pointer; text-decoration:none;}
    .btn--warn{background:#e62329; border:1px solid #e62329; color:#fff;}
    .btn--warn:hover{background:#c01c21;}

    /* Overlay popup */
    .modal-overlay{position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:1000;}
    .modal-card{background:#111; padding:20px; border-radius:8px; max-width:320px; width:100%; color:#fff;}
    .modal-card h3{margin:0 0 10px;}
    .modal-card .actions{display:flex; justify-content:flex-end; gap:10px; margin-top:16px;}
  </style>
</head>
<body>

<?php require $ROOT . '/header_user.php'; ?>

<main class="torneo-wrap">
  <div class="torneo-head">
    <h1 class="torneo-title">
      Torneo <?php echo htmlspecialchars($torneo['name']); ?>
      <small style="font-size:14px; color:#aaa;">#<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',$id)); ?></small>
    </h1>

    <?php if ($enrolled): ?>
      <!-- Pulsante Disiscriviti -->
      <form id="unenrollForm" method="post" action="/api/unenroll.php">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
        <input type="hidden" name="tournament_id" value="<?php echo (int)$id; ?>">
        <input type="hidden" name="redirect" value="1">
        <button class="btn btn--warn" type="button" id="unenrollBtn">Disiscriviti</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Info torneo -->
  <section class="card card--ps">
    <h3 class="card__title"><?php echo htmlspecialchars($torneo['league_name']); ?> • Stagione <?php echo htmlspecialchars($torneo['season']); ?></h3>
    <dl class="grid">
      <div><dt>Buy-in</dt><dd><?php echo (int)$torneo['cost_per_life']; ?> crediti</dd></div>
      <div><dt>Posti disp.</dt><dd><?php echo (int)$torneo['max_slots']; ?></dd></div>
      <div><dt>Vite max/utente</dt><dd><?php echo (int)$torneo['max_lives_per_user']; ?></dd></div>
      <div><dt>Montepremi garantito</dt><dd><?php echo $torneo['guaranteed_prize'] ? (int)$torneo['guaranteed_prize'].' crediti' : '—'; ?></dd></div>
    </dl>
  </section>

  <!-- Eventi -->
  <section style="margin-top:20px;">
    <h2>Eventi del torneo</h2>
    <div class="muted">Qui mostreremo le partite/round (Step successivi).</div>
  </section>
</main>

<?php require $ROOT . '/footer.php'; ?>

<!-- Popup Disiscrizione -->
<div id="unenrollModal" class="modal-overlay">
  <div class="modal-card">
    <h3>Conferma disiscrizione</h3>
    <p>Vuoi davvero disiscriverti da questo torneo?</p>
    <div class="actions">
      <button type="button" id="cancelUnenroll" class="btn">Annulla</button>
      <button type="button" id="confirmUnenroll" class="btn btn--warn">Conferma</button>
    </div>
  </div>
</div>

<script>
  (function(){
    var btn = document.getElementById('unenrollBtn');
    var modal = document.getElementById('unenrollModal');
    var cancelBtn = document.getElementById('cancelUnenroll');
    var confirmBtn = document.getElementById('confirmUnenroll');
    var form = document.getElementById('unenrollForm');

    if(btn){
      btn.addEventListener('click', function(){
        modal.style.display = 'flex';
      });
    }
    cancelBtn.addEventListener('click', function(){
      modal.style.display = 'none';
    });
    confirmBtn.addEventListener('click', function(){
      modal.style.display = 'none';
      form.submit();
    });
    modal.addEventListener('click', function(e){
      if(e.target === modal){ modal.style.display = 'none'; }
    });
  })();
</script>

</body>
</html>
