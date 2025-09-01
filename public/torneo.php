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

/* ====== AGGIUNTA: vite in gioco totali + montepremi iniziale ====== */
$totLives = 0;
try {
  $sl = $pdo->prepare("SELECT COALESCE(SUM(lives),0) FROM tournament_enrollments WHERE tournament_id=:t");
  $sl->execute([':t'=>$id]);
  $totLives = (int)$sl->fetchColumn();
} catch (Throwable $e) {
  $totLives = 0;
}
$buyin  = (int)($torneo['cost_per_life'] ?? 0);
$pp     = isset($torneo['prize_percent']) ? (int)$torneo['prize_percent'] : 100;
$g      = isset($torneo['guaranteed_prize']) ? (float)$torneo['guaranteed_prize'] : 0.0;
$potNow = max($g, $totLives * $buyin * ($pp/100));
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

    /* Overlay popup */
    .modal-overlay{position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:1000;}
    .modal-card{background:#111; padding:20px; border-radius:8px; max-width:320px; width:100%; color:#fff;}
    .modal-card h3{margin:0 0 10px;}
    .modal-card .actions{display:flex; justify-content:flex-end; gap:10px; margin-top:16px;}

    /* === Event cards (grid + stile) === */
    .events-grid{
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap:12px;
      margin-top:10px;
    }
    .event-card{
      background:#111;
      border:1px solid rgba(255,255,255,.12);
      border-radius:12px;
      padding:12px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      box-shadow:0 6px 16px rgba(0,0,0,.18);
      cursor:pointer;
      transition:transform .12s ease, box-shadow .12s ease;
    }
    .event-card:hover{
      transform:translateY(-2px);
      box-shadow:0 10px 24px rgba(0,0,0,.28);
    }
    .event-side{
      display:flex; align-items:center; gap:8px; min-width:0;
    }
    .event-side img{
      width:30px; height:30px; object-fit:contain; border-radius:4px;
      background:#0a0a0b; border:1px solid rgba(255,255,255,.08);
    }
    .event-side .team{
      display:block; font-weight:800; font-size:13px; color:#fff;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
      max-width:150px;
    }
    .event-vs{
      font-size:12px; color:#c9c9c9; font-weight:900; margin:0 8px;
    }
    .pick-home, .pick-away{ outline:2px solid transparent; border-radius:8px; padding:4px; }
    .event-card[data-pick="home"]  .pick-home{ outline-color:#00c074; }
    .event-card[data-pick="away"]  .pick-away{ outline-color:#00c074; }

    /* Modal scelta (riuso overlay) */
    .modal-overlay.pick{ z-index:1001; }
    .modal-card.pick{ width:360px; border-radius:10px; }
    .pick-row{ display:flex; gap:10px; align-items:center; margin-top:10px; }
    .pick-heart{ font-size:20px; cursor:pointer; opacity:.5 }
    .pick-heart.active{ opacity:1 }
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

  <!-- ======= CARD INFO: nome + buy-in + vite in gioco + montepremi + countdown + vite max ======= -->
  <section class="card card--ps" data-tid="<?php echo (int)$id; ?>">
    <!-- Titolo card: Nome torneo • Buy-in -->
    <h3 class="card__title">
      <?php echo htmlspecialchars($torneo['name']); ?>
      • Buy-in <?php echo number_format($buyin, 0, ',', '.'); ?> crediti
    </h3>

    <dl class="grid">
      <div>
        <dt>Vite in gioco</dt>
        <dd><span class="lives-in-play"><?php echo (int)$totLives; ?></span></dd>
      </div>

      <div>
        <dt>Montepremi</dt>
        <dd><span class="pot"><?php echo number_format($potNow, 0, ',', '.'); ?></span> crediti</dd>
      </div>

      <div>
        <dt>Countdown scelte</dt>
        <dd>
          <?php if (!empty($torneo['lock_at'])): ?>
            <time class="lock" datetime="<?php echo htmlspecialchars($torneo['lock_at']); ?>">
              <?php echo date('d/m/Y H:i', strtotime($torneo['lock_at'])); ?>
            </time>
            <span class="countdown" data-due="<?php echo htmlspecialchars($torneo['lock_at']); ?>"></span>
          <?php else: ?>
            —
          <?php endif; ?>
        </dd>
      </div>

      <div>
        <dt>Vite max utente</dt>
        <dd><?php echo (int)($torneo['max_lives_per_user'] ?? 1); ?></dd>
      </div>
    </dl>
  </section>
  <!-- =========================================================================================== -->

  <!-- ====== Azioni vite + cuori (resto invariato) ====== -->
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
  </section>

  <!-- Eventi -->
  <section style="margin-top:20px;">
    <h2>Eventi del torneo</h2>

    <?php
      // Carico gli eventi (puoi filtrare per round_no se necessario)
      $ev = $pdo->prepare("
        SELECT id, fixture_id, round_no,
               home_team_id, home_team_name,
               away_team_id, away_team_name
        FROM tournament_events
        WHERE tournament_id = :tid
        ORDER BY id ASC
      ");
      $ev->execute([':tid' => $id]);
      $events = $ev->fetchAll(PDO::FETCH_ASSOC);

      if (!$events) {
        echo '<div class="muted">Nessun evento disponibile al momento.</div>';
      } else {
        echo '<div class="events-grid">';
        foreach ($events as $e) {
          $homeId   = (int)($e['home_team_id'] ?? 0);
          $awayId   = (int)($e['away_team_id'] ?? 0);
          $homeLogo = $homeId ? "https://media.api-sports.io/football/teams/{$homeId}.png" : '/assets/placeholder_team.png';
          $awayLogo = $awayId ? "https://media.api-sports.io/football/teams/{$awayId}.png" : '/assets/placeholder_team.png';

          $homeName = htmlspecialchars($e['home_team_name'] ?: '—');
          $awayName = htmlspecialchars($e['away_team_name'] ?: '—');

          echo '<div class="event-card"'
              .' data-event-id="'.(int)$e['id'].'"'
              .' data-home-id="'.$homeId.'"'
              .' data-away-id="'.$awayId.'"'
              .' data-home-name="'.$homeName.'"'
              .' data-away-name="'.$awayName.'">';

            echo '<div class="event-side pick-home">';
              echo '<img src="'.htmlspecialchars($homeLogo).'" alt="'.$homeName.' logo" loading="lazy" decoding="async">';
              echo '<span class="team">'.$homeName.'</span>';
            echo '</div>';

            echo '<span class="event-vs">VS</span>';

            echo '<div class="event-side pick-away" style="justify-content:flex-end;">';
              echo '<span class="team" style="text-align:right;">'.$awayName.'</span>';
              echo '<img src="'.htmlspecialchars($awayLogo).'" alt="'.$awayName.' logo" loading="lazy" decoding="async">';
            echo '</div>';

          echo '</div>';
        }
        echo '</div>';
      }
    ?>
  </section>
</main>

<?php require $ROOT . '/footer.php'; ?>

<!-- Popup Disiscrizione (invariato) -->
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

<!-- Popup messaggi riutilizzabile (invariato) -->
<div id="msgModal" class="modal-overlay">
  <div class="modal-card">
    <h3 id="msgTitle" style="margin:0 0 10px;">Messaggio</h3>
    <p id="msgText"  style="margin:0 0 12px; color:#ddd;">Testo</p>
    <div class="actions">
      <button type="button" id="msgOk" class="btn">OK</button>
    </div>
  </div>
</div>

<!-- Modal scelta (vita + lato) -->
<div id="pickModal" class="modal-overlay">
  <div class="modal-card pick">
    <h3 style="margin:0 0 8px;">Seleziona scelta</h3>
    <div class="muted" id="pickMatch" style="margin-bottom:8px;">—</div>

    <div>
      <div style="font-size:12px;color:#c9c9c9;">Scegli la vita</div>
      <div class="pick-row" id="pickHearts">
        <!-- cuori inseriti da JS -->
      </div>
    </div>

    <div class="pick-row" style="justify-content:flex-end; margin-top:14px;">
      <button type="button" class="btn" id="pickCancel">Annulla</button>
      <button type="button" class="btn" style="background:#00c074;border:1px solid #00c074;color:#fff;font-weight:800;" id="pickConfirm">
        Conferma
      </button>
    </div>
  </div>
</div>

<script>
  // Popup disiscrizione (invariato)
  (function(){
    var btn = document.getElementById('unenrollBtn');
    var modal = document.getElementById('unenrollModal');
    var cancelBtn = document.getElementById('cancelUnenroll');
    var confirmBtn = document.getElementById('confirmUnenroll');
    var form = document.getElementById('unenrollForm');

    if(btn){
      btn.addEventListener('click', function(){ modal.style.display = 'flex'; });
    }
    if(cancelBtn){
      cancelBtn.addEventListener('click', function(){ modal.style.display = 'none'; });
    }
    if(confirmBtn){
      confirmBtn.addEventListener('click', function(){ modal.style.display = 'none'; form.submit(); });
    }
    if(modal){
      modal.addEventListener('click', function(e){ if(e.target === modal){ modal.style.display = 'none'; } });
    }
  })();

  // ===== Popup messaggi riutilizzabile (invariato) =====
  (function(){
    var modal = document.getElementById('msgModal');
    var title = document.getElementById('msgTitle');
    var text  = document.getElementById('msgText');
    var okBtn = document.getElementById('msgOk');

    window.showMsg = function(tit, msg, kind){
      title.textContent = tit || 'Messaggio';
      text.textContent  = msg || '';
      if (kind === 'error')      title.style.color = '#ff6b6b';
      else if (kind === 'success') title.style.color = '#00c074';
      else                       title.style.color = '#fff';
      modal.style.display = 'flex';
    };
    window.closeMsg = function(){ modal.style.display = 'none'; };

    okBtn.addEventListener('click', closeMsg);
    modal.addEventListener('click', function(e){ if (e.target === modal) closeMsg(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMsg(); });
  })();

  // Acquisto vita (+ aggiornamento cuori e saldo header) — versione diagnostica (invariato)
  (function(){
    var btn = document.getElementById('btnAddLife');
    if (!btn) return;

    function renderHearts(n){
      var w = document.getElementById('livesWrap');
      if (!w) return;
      w.innerHTML = '';
      n = parseInt(n||0,10);
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
      fetch('/api/user_credits.php', { credentials:'same-origin' })
        .then(r => r.ok ? r.json() : null)
        .then(js => {
          if (js && js.ok && typeof js.crediti !== 'undefined') {
            var el = document.getElementById('headerCrediti');
            if (el) el.textContent = js.crediti;
          }
        }).catch(()=>{});
    }

    btn.addEventListener('click', function(){
      fetch(window.location.origin + '/api/add_life.php', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Content-Type':'application/x-www-form-urlencoded' },
        body: 'csrf=' + encodeURIComponent('<?php echo htmlspecialchars($GLOBALS["csrf"]); ?>')
            + '&tournament_id=' + encodeURIComponent('<?php echo (int)$GLOBALS["id"]; ?>')
            + '&_ts=' + Date.now()
      })
      .then(async (r) => {
        const status = r.status;
        let text = '';
        try { text = await r.text(); } catch(_) {}
        let js = null;
        try { js = text ? JSON.parse(text) : null; } catch(_) {}

        if (!js) { showMsg('Errore', 'HTTP '+status+' (non JSON):\n'+(text ? text.slice(0,400) : '(vuota)'), 'error'); throw new Error('non_json'); }
        return js;
      })
      .then(function(js){
        if (!js.ok) {
          var msg = (js.msg || js.error || 'errore');
          if (msg === 'insufficient_funds')                msg = 'Crediti insufficienti.';
          if (msg === 'lives_limit' || msg==='max_reached') msg = 'Hai raggiunto il limite di vite consentite.';
          if (msg === 'locked')                            msg = 'Le scelte sono bloccate per questo torneo.';
          if (msg === 'not_enrolled')                      msg = 'Non sei iscritto a questo torneo.';
          if (msg === 'bad_csrf')                          msg = 'Sessione scaduta: ricarica la pagina e riprova.';
          showMsg('Acquisto vita non riuscito', msg, 'error');
          return;
        }
        renderHearts(js.lives);
        if (typeof js.header_credits !== 'undefined') {
          var el = document.getElementById('headerCrediti');
          if (el) el.textContent = js.header_credits;
        } else {
          refreshHeaderCredits();
        }
      })
      .catch(function(){ showMsg('Errore di rete', 'Controlla la connessione e riprova.', 'error'); });
    });
  })();

  // Countdown semplice
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

  /* ===== Polling “vite in gioco” + “montepremi” (ogni 10s) ===== */
  (function(){
    var card = document.querySelector('.card.card--ps[data-tid]');
    if (!card) return;
    var tid  = card.getAttribute('data-tid');
    var potEl = card.querySelector('.pot');
    var livEl = card.querySelector('.lives-in-play');

    function upd(){
      fetch('/api/tournament_stats.php?id='+encodeURIComponent(tid), {credentials:'same-origin'})
        .then(r => r.ok ? r.json() : null)
        .then(js => {
          if (!js || !js.ok) return;
          if (potEl) potEl.textContent = (js.pot || 0).toLocaleString('it-IT');
          if (livEl) livEl.textContent = (js.lives || 0);
        })
        .catch(()=>{});
    }
    upd();
    setInterval(upd, 10000);
  })();

  /* ===== Scelta squadra per evento (UI) ===== */
  (function(){
    var USER_LIVES = <?php echo (int)$userLives; ?>;

    var modal   = document.getElementById('pickModal');
    var row     = document.getElementById('pickHearts');
    var txt     = document.getElementById('pickMatch');
    var btnOk   = document.getElementById('pickConfirm');
    var btnNo   = document.getElementById('pickCancel');

    var current = { eventId:null, homeId:null, awayId:null, homeName:'', awayName:'', lifeIndex:0, side:'home' };

    function renderHeartsPick(n){
      row.innerHTML = '';
      if (n <= 0) {
        row.innerHTML = '<span class="muted">Non hai vite disponibili</span>';
        btnOk.disabled = true;
        return;
      }
      btnOk.disabled = false;
      for (var i=0; i<n; i++){
        var sp = document.createElement('span');
        sp.className = 'pick-heart' + (i===0?' active':'');
        sp.textContent = '❤️';
        sp.dataset.idx = i;
        sp.addEventListener('click', function(){
          var idx = parseInt(this.dataset.idx,10);
          current.lifeIndex = idx;
          [].forEach.call(row.querySelectorAll('.pick-heart'), function(p){ p.classList.remove('active'); });
          this.classList.add('active');
        });
        row.appendChild(sp);
      }
    }

    document.querySelectorAll('.event-card').forEach(function(card){
      var openHome = function(e){ e.stopPropagation(); openPick(card, 'home'); };
      var openAway = function(e){ e.stopPropagation(); openPick(card, 'away'); };
      var ph = card.querySelector('.pick-home');
      var pa = card.querySelector('.pick-away');
      if (ph) ph.addEventListener('click', openHome);
      if (pa) pa.addEventListener('click', openAway);
      card.addEventListener('click', function(){ openPick(card, 'home'); });
    });

    function openPick(card, side){
      current.eventId  = parseInt(card.getAttribute('data-event-id'), 10);
      current.homeId   = parseInt(card.getAttribute('data-home-id'), 10);
      current.awayId   = parseInt(card.getAttribute('data-away-id'), 10);
      current.homeName = card.getAttribute('data-home-name') || '';
      current.awayName = card.getAttribute('data-away-name') || '';
      current.lifeIndex= 0;
      current.side     = (side === 'away' ? 'away' : 'home');

      txt.textContent = current.homeName + ' vs ' + current.awayName;
      renderHeartsPick(USER_LIVES);

      document.querySelectorAll('.event-card').forEach(function(c){ c.removeAttribute('data-pick'); });
      card.setAttribute('data-pick', current.side);

      modal.style.display = 'flex';
    }

    btnNo.addEventListener('click', function(){ modal.style.display = 'none'; });

    btnOk.addEventListener('click', function(){
      modal.style.display = 'none';
      var sideTxt = (current.side==='home') ? current.homeName : current.awayName;
      var t = document.createElement('div');
      t.textContent = 'Scelta registrata (UI): Vita '+(current.lifeIndex+1)+' → '+ sideTxt;
      t.style.position='fixed'; t.style.bottom='16px'; t.style.left='16px';
      t.style.background='#111'; t.style.color='#fff'; t.style.border='1px solid #333';
      t.style.padding='8px 12px'; t.style.borderRadius='8px'; t.style.zIndex='2000';
      document.body.appendChild(t);
      setTimeout(function(){ t.remove(); }, 2200);
    });

    modal.addEventListener('click', function(e){ if (e.target === modal) { modal.style.display='none'; } });
  })();
</script>

</body>
</html>
