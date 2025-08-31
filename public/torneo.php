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

/* ====== AGGIUNTA: vite correnti e CSRF ====== */
$userLives = 0;
if ($enrolled) {
  $lv = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $lv->execute([':u'=>$uid, ':t'=>$id]);
  $userLives = (int)$lv->fetchColumn();
}
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];
/* ============================================ */
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
    
 <!-- Qui compariranno i cuori -->
  <div id="heartsWrap" style="display:inline-block; margin-left:10px;"></div>
</div>
    
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

  <!-- ====== AGGIUNTA: Azioni vite + cuori + countdown ====== -->
  <section style="margin-top:14px; display:flex; align-items:center; gap:16px;">
    <?php if ($enrolled): ?>
      <button id="btnAddLife" class="btn" style="background:#00c074;border:1px solid #00c074;color:#fff;font-weight:800;">
        + Aggiungi vita
      </button>
    <?php endif; ?>

    <div id="livesWrap" style="display:flex; align-items:center; gap:6px;">
      <?php
        $hearts = max(0, $userLives);
        if ($hearts === 0) {
          echo '<span class="muted">Nessuna vita</span>';
        } else {
          for ($i=0; $i<$hearts; $i++) {
            echo '<span class="life-heart" title="Vita '.($i+1).'" style="font-size:18px;">❤️</span>';
          }
        }
      ?>
    </div>

    <div style="margin-left:auto; text-align:center;">
      <?php if (!empty($torneo['lock_at'])): ?>
        <div style="font-size:12px;color:#c9c9c9;margin-bottom:2px;">Lock scelte</div>
        <div>
          <time class="lock" datetime="<?php echo htmlspecialchars($torneo['lock_at']); ?>">
            <?php echo date('d/m/Y H:i', strtotime($torneo['lock_at'])); ?>
          </time>
          <span class="countdown" data-due="<?php echo htmlspecialchars($torneo['lock_at']); ?>"></span>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <!-- ======================================================= -->

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
    if(cancelBtn){
      cancelBtn.addEventListener('click', function(){
        modal.style.display = 'none';
      });
    }
    if(confirmBtn){
      confirmBtn.addEventListener('click', function(){
        modal.style.display = 'none';
        form.submit();
      });
    }
    if(modal){
      modal.addEventListener('click', function(e){
        if(e.target === modal){ modal.style.display = 'none'; }
      });
    }
  })();

  /* ====== AGGIUNTA: acquisto 1 vita con aggiornamento cuori/saldo ====== */
  (function(){
    var btn = document.getElementById('btnAddLife');
    if (!btn) return;

    function renderHearts(n){
      var w = document.getElementById('livesWrap');
      if (!w) return;
      w.innerHTML = '';
      if (n <= 0) {
        var s = document.createElement('span');
        s.className = 'muted';
        s.textContent = 'Nessuna vita';
        w.appendChild(s);
        return;
      }
      for (var i=0;i<n;i++){
        var h = document.createElement('span');
        h.className = 'life-heart';
        h.title = 'Vita '+(i+1);
        h.textContent = '❤️';
        h.style.fontSize = '18px';
        w.appendChild(h);
      }
    }

    function refreshHeaderCredits(){
      fetch('/api/user_credits.php')
        .then(r => r.ok ? r.json() : null)
        .then(js => {
          if (js && js.ok && typeof js.crediti !== 'undefined') {
            var el = document.getElementById('headerCrediti');
            if (el) el.textContent = js.crediti;
          }
        }).catch(()=>{});
    }

    btn.addEventListener('click', function(){
      fetch('/api/add_life.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type':'application/x-www-form-urlencoded' },
        body: 'csrf=' + encodeURIComponent('<?php echo htmlspecialchars($csrf); ?>')
            + '&tournament_id=' + encodeURIComponent('<?php echo (int)$id; ?>')
      })
      .then(r => r.json().catch(()=>null).then(js => js || {ok:false,error:'non_json'}))
      .then(js => {
        if (!js.ok) {
          var msg = 'Errore';
          if (js.error === 'insufficient_funds') msg = 'Crediti insufficienti';
          else if (js.error === 'lives_limit')     msg = 'Hai raggiunto il limite di vite';
          else if (js.error === 'locked')         msg = 'Scelte bloccate';
          else if (js.error === 'not_enrolled')   msg = 'Non sei iscritto a questo torneo';
          alert(msg);
          return;
        }
        renderHearts(parseInt(js.lives || 0, 10));
        refreshHeaderCredits();
      })
      .catch(()=> alert('Errore di rete'));
    });
  })();

  // Countdown semplice (riuso logica lobby)
  (function(){
    function tick(el){
      var due = el.getAttribute('data-due'); if(!due) return;
      var end = new Date(due.replace(' ', 'T')).getTime();
      var d = end - Date.now(); if (d <= 0){ el.textContent = 'CHIUSO'; return; }
      var s = Math.floor(d/1000), g = Math.floor(s/86400); s%=86400;
      var h=Math.floor(s/3600); s%=3600; var m=Math.floor(s/60); s%=60;
      el.textContent = (g>0? g+'g ':'') + h+'h ' + m+'m ' + s+'s';
      setTimeout(function(){ tick(el); }, 1000);
    }
    document.querySelectorAll('.countdown').forEach(tick);
  })();
</script>
<script>
  (function(){
    var btnAdd = document.getElementById('btnAddLife');
    if (!btnAdd) return;

    btnAdd.addEventListener('click', function(){
      // Parametri
      var tid = <?php echo (int)$id; ?>;
      var csrf = "<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>";

      fetch('/api/add_life.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type':'application/x-www-form-urlencoded' },
        body: 'csrf=' + encodeURIComponent(csrf) +
              '&tournament_id=' + encodeURIComponent(tid)
      })
      .then(r => r.json().catch(()=>null))
      .then(js => {
        if(!js || !js.ok){
          // messaggi più chiari
          var em = (js && (js.msg || js.error)) ? (js.msg || js.error) : 'errore';
          alert('Acquisto vita non riuscito: ' + em);
          return;
        }
        // Aggiorna i cuori in pagina se hai un container dedicato
        // es. <div id="heartsWrap"></div> – ricostruisco i cuori in base a js.lives
        var wrap = document.getElementById('heartsWrap');
        if (wrap) {
          var n = parseInt(js.lives||0,10);
          wrap.innerHTML = '';
          for (var i=0;i<n;i++){
            var s = document.createElement('span');
            s.textContent = '❤';
            s.style.marginRight = '6px';
            s.style.color = '#ff6b6b';
            wrap.appendChild(s);
          }
        }
        // feedback leggero
        // alert('Vita aggiunta!'); // se vuoi un feedback modale
      })
      .catch(() => alert('Errore di rete'));
    });
  })();
</script>
</body>
</html>
