<?php
/**
 * public/torneo.php
 * Pagina singolo torneo (con pulsante Disiscrivi e popup conferma).
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

/* ====== Round corrente + flag "in attesa" ====== */
$currentRoundNo = (int)($torneo['current_round_no'] ?? 1);
$waitingRound = false;
try {
  $st = $pdo->prepare("SELECT COUNT(*) FROM tournament_events WHERE tournament_id=:tid AND round_no=:r");
  $st->execute([':tid'=>$id, ':r'=>$currentRoundNo]);
  $waitingRound = ($torneo['status'] === 'open') && ((int)$st->fetchColumn() === 0);
} catch (Throwable $e) {
  $waitingRound = false;
}

/* ====== BLOCCO UI: consentito solo prima del lock del Round 1 ====== */
$lockedNow = (
  (!empty($torneo['lock_at']) && strtotime($torneo['lock_at']) <= time())
  || ((int)($torneo['choices_locked'] ?? 0) === 1)
);
$beforeLockRound1 = ($currentRoundNo === 1 && !$lockedNow);

/* ====== Eventi SOLO del round corrente ====== */
$events = [];
try {
  $ev = $pdo->prepare("
    SELECT id, fixture_id, home_team_name, away_team_name,
           home_team_id, away_team_id, kickoff
    FROM tournament_events
    WHERE tournament_id = :tid
      AND round_no = :r
      AND is_active = 1
    ORDER BY kickoff IS NULL, kickoff ASC, id ASC
  ");
  $ev->execute([':tid'=>$id, ':r'=>$currentRoundNo]);
  $events = $ev->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $events = [];
}

// === SOLO PER CANON ID (minimo indispensabile) ===
$leagueIdForCanon = (int)($torneo['league_id'] ?? 0);
// preparo uno statement riutilizzabile: (league_id, team_id) -> canon_team_id
$canonMapStmt = $pdo->prepare("
  SELECT canon_team_id
  FROM admin_team_canon_map
  WHERE league_id = ? AND team_id = ?
  LIMIT 1
");

/* Helpers per logo/iniziali ——— (MODIFICA) mappa alias → slug file presenti in /assets/logos/ */
function team_slug(string $name): string {
  $slug = strtolower($name);
  $slug = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$slug);
  $slug = preg_replace('/[^a-z0-9]+/','', $slug);
  return $slug ?: 'team';
}
function team_initials(string $name): string {
  $parts = preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9\s]+/u', '', trim($name)));
  $ini = '';
  foreach ($parts as $p) {
    if ($p === '') continue;
    $ini .= mb_strtoupper(mb_substr($p,0,1));
    if (mb_strlen($ini) >= 2) break;
  }
  return $ini !== '' ? $ini : '??';
}
/* (MODIFICA) restituisce il path del logo locale tenendo conto degli alias */
function team_logo_path(string $name): string {
  // slug "di base"
  $base = team_slug($name);

  // alias comuni → slug di file effettivi caricati in /assets/logos/
  static $alias = [
    'juventus'      => 'juve',
    'inter'         => 'inter',
    'internazionale'=> 'inter',
    'acmilan'       => 'milan',
    'milan'         => 'milan',
    'asroma'        => 'roma',
    'roma'          => 'roma',
    'hellasverona'  => 'hellasverona',
    'verona'        => 'hellasverona',
    // i seguenti sono “identity”
    'atalanta'      => 'atalanta',
    'bologna'       => 'bologna',
    'cagliari'      => 'cagliari',
    'como'          => 'como',
    'cremonese'     => 'cremonese',
    'fiorentina'    => 'fiorentina',
    'genoa'         => 'genoa',
    'lazio'         => 'lazio',
    'lecce'         => 'lecce',
    'napoli'        => 'napoli',
    'parma'         => 'parma',
    'pisa'          => 'pisa',
    'sassuolo'      => 'sassuolo',
    'torino'        => 'torino',
    'udinese'       => 'udinese',
  ];

  $key = $base;
  if ($base === 'ac' && stripos($name, 'milan') !== false)     $key = 'acmilan';
  if ($base === 'as' && stripos($name, 'roma') !== false)      $key = 'asroma';
  if (strpos($base, 'hellas') !== false || strpos($base, 'verona') !== false) $key = 'hellasverona';

  $slug = $alias[$key] ?? $base;
  return "/assets/logos/{$slug}.webp";
}

/* ====== AGGIUNTA MINIMA: verifica eventi del round corrente per mostrare messaggio "in attesa" ====== */
$currentRoundNo = (int)($torneo['current_round_no'] ?? 1);
$waitingRound = false;
try {
  $st = $pdo->prepare("SELECT COUNT(*) FROM tournament_events WHERE tournament_id=:tid AND round_no=:r");
  $st->execute([':tid'=>$id, ':r'=>$currentRoundNo]);
  $waitingRound = ($torneo['status'] === 'open') && ((int)$st->fetchColumn() === 0);
} catch (Throwable $e) {
  $waitingRound = false;
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
  <link rel="stylesheet" href="/assets/lobby.css">
<script>window.CSRF = "<?php echo htmlspecialchars($csrf); ?>";</script>  <!-- ADDED -->
<style>
/* … resto invariato … */
    .torneo-wrap{max-width:1280px; margin:40px auto; padding:0 24px; color:#fff; position:relative;}
    .torneo-head{display:flex; justify-content:space-between; align-items:center; margin-bottom:32px;}
    .torneo-title{font-size:24px; font-weight:900;}
    .btn{display:inline-block; padding:6px 12px; border-radius:6px; font-weight:700; cursor:pointer; text-decoration:none;}
    .btn--warn{background:#e62329; border:1px solid #e62329; color:#fff;}
    .btn--warn:hover{background:#c01c21;}
  
  /* Pulsante discreto (senza colore pieno) per Disiscriviti */
.btn--ghost{
  background: transparent;                          /* niente riempimento */
  border: 1px solid rgba(255,255,255,.22);          /* bordo tenue */
  color: #cfcfcf;                                   /* testo grigio chiaro */
  font-weight: 700;                                 /* resta leggibile */
}
.btn--ghost:hover{
  border-color: rgba(255,255,255,.45);              /* un filo più visibile al hover */
  color: #fff;                                      /* testo bianco al hover */
  background: rgba(255,255,255,.04);                /* leggerissima velatura */
}

    /* Overlay popup */
    .modal-overlay{position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:1000;}
    .modal-card{background:#111; padding:20px; border-radius:8px; max-width:320px; width:100%; color:#fff;}
    .modal-card h3{margin:0 0 10px;}
    .modal-card .actions{display:flex; justify-content:flex-end; gap:10px; margin-top:16px;}

    /* ====== STILI CARD EVENTI (aggiornati: VS centrato) ====== */
    .events-grid{
      display:grid;
      grid-template-columns: repeat(2, minmax(320px, 1fr));
      gap:14px;
      margin-top:10px;
    }
/* Card evento – sfondo più chiaro */
.event-card{
  /* sfondo più chiaro con un gradiente molto soft */
  background: linear-gradient(180deg, #1a1f26 0%, #11161c 100%);
  border: 1px solid rgba(255,255,255,.20);            /* bordo un filo più presente */
  border-radius: 14px;
  padding: 16px;

  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  column-gap: 12px;

  /* un minimo di “corpo” in più */
  box-shadow:
    0 10px 26px rgba(0,0,0,.22),
    inset 0 0 0 1px rgba(255,255,255,.04);
}
  .event-card:hover{
  background: linear-gradient(180deg, #202734 0%, #151b22 100%);
}
    .ec-team{ display:flex; align-items:center; gap:12px; min-width:0; }
    .event-card .ec-team:first-child{ justify-content:flex-start; }
    .event-card .ec-team:last-child{  justify-content:flex-end;  }
    .ec-vs{ font-weight:900; color:#c9c9c9; letter-spacing:.04em; text-align:center; min-width:28px; }
    /* Logo/badge squadra – più chiaro e più “massiccio” */
.logo-wrap{
  width: 44px;                          /* ↑ da 28 → 44 */
  height: 44px;
  position: relative;
  flex: 0 0 44px;
  border-radius: 9999px;
  overflow: hidden;

  /* fondo leggermente più chiaro con gradiente soft */
  background: radial-gradient(90% 90% at 50% 10%, #1f2630 0%, #14181f 100%);

  /* bordo più marcato */
  border: 2px solid rgba(255,255,255,.18);

  /* un filo di sostanza */
  box-shadow:
    0 2px 8px rgba(0,0,0,.35),                /* ombra esterna */
    inset 0 0 0 1px rgba(255,255,255,.06);    /* leggerissimo ring interno */

  display:flex;
  align-items:center;
  justify-content:center;
}
    .team-logo{
  width:100%;
  height:100%;
  object-fit:contain;
  display:block;
  background:transparent;
  filter: drop-shadow(0 0 2px rgba(0,0,0,.35));   /* micro glow per staccare dallo sfondo */
}
    .team-initials{ position:absolute; inset:0; display:none; align-items:center; justify-content:center; font-size:12px; font-weight:900; color:#fff; background:#2a2f36; border-radius:9999px; }
    .team-name{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px; color:#e6e6e6; font-weight:800; }
    .team-side{ cursor:pointer; }
    .life-heart{ cursor:pointer; }
    .life-heart--active{ outline:2px solid #00c074; border-radius:6px; padding:2px 4px; }
    .life-heart .pick-logo{ width:16px; height:16px; vertical-align:middle; margin-left:6px; }

  .team-side.disabled {
  pointer-events: none;
  opacity: 0.3;
  filter: grayscale(100%);
  cursor: not-allowed;
}
    @media (max-width: 720px){ .events-grid{ grid-template-columns: 1fr; } }

    /* ====== AGGIUNTA: banner "in attesa" ====== */
    .notice-wait{background:#262a31;border:1px solid rgba(255,255,255,.15);padding:12px;border-radius:10px;margin:12px 0;color:#c9c9c9;}
  /* Distanze generali tra le sezioni */
section{
  margin-top: 30px;      /* spazio tra i vari blocchi */
  margin-bottom: 30px;
}
</style>
</head>
<body>

<?php require $ROOT . '/header_user.php'; ?>

<main class="torneo-wrap" data-waiting="<?php echo $waitingRound ? '1' : '0'; ?>">
  <div class="torneo-head">
    <h1 class="torneo-title">
      Torneo <?php echo htmlspecialchars($torneo['name']); ?>
      <small style="font-size:14px; color:#aaa;">#<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',$id)); ?></small>
    </h1>

    <?php if ($enrolled && $beforeLockRound1): ?>
      <!-- Pulsante Disiscrivi -->
      <form id="unenrollForm" method="post" action="/api/unenroll.php">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
        <input type="hidden" name="tournament_id" value="<?php echo (int)$id; ?>">
        <input type="hidden" name="redirect" value="1">
        <button class="btn btn--ghost" type="button" id="unenrollBtn">Disiscriviti</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- ======= CARD INFO ======= -->
  <section class="card card--ps" data-tid="<?php echo (int)$id; ?>">
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

  <!-- ====== Messaggio "in attesa" se round corrente senza eventi ====== -->
  <?php if ($waitingRound): ?>
    <div class="notice-wait">
      In attesa del <strong>round <?php echo (int)$currentRoundNo; ?></strong>. Torna tra poco: stiamo pubblicando la nuova giornata.
    </div>
  <?php endif; ?>

  <!-- ====== Azioni vite + cuori ====== -->
  <section style="margin-top:14px; display:flex; align-items:center; gap:16px;">
    <?php if ($enrolled && $beforeLockRound1 && !$waitingRound): ?>
      <button id="btnAddLife" class="btn" style="background:#00c074;border:1px solid #00c074;color:#fff;font-weight:800;">
        Aggiungi vita
      </button>
    <?php endif; ?>

    <div id="livesWrap" style="display:flex; align-items:center; gap:6px;">
      <?php
        $hearts = max(0, $userLives);
        if ($hearts === 0) {
          echo '<span class="muted">Nessuna vita</span>';
        } else {
          for ($i=0; $i<$hearts; $i++) {
            echo '<span class="life-heart" data-life="'.($i).'" title="Vita '.($i+1).'" style="font-size:18px;">❤️</span>';
          }
        }
      ?>
    </div>
  </section>

  <!-- Eventi -->
  <section style="margin-top:20px;">
    
    <h2>
  Eventi del torneo
  <small class="muted" style="font-weight:700; margin-left:6px;">
    — Round <?php echo (int)$currentRoundNo; ?>
  </small>
</h2>
    
    <?php if ($waitingRound): ?>
      <div class="muted">Le partite verranno mostrate appena il round sarà pubblicato.</div>
    <?php else: ?>
      <?php if (empty($events)): ?>
        <div class="muted">Qui mostreremo le partite/round (Step successivi).</div>
      <?php else: ?>
        <div class="events-grid">
          <?php foreach ($events as $ev): ?>
            <?php
              $hn = trim($ev['home_team_name'] ?? 'Casa');
              $an = trim($ev['away_team_name'] ?? 'Trasferta');

              $hLogo = team_logo_path($hn);
              $aLogo = team_logo_path($an);

              $hIni  = team_initials($hn);
              $aIni  = team_initials($an);

              // --- CALCOLO CANON ID (unica variazione funzionale) ---
              $homeCanonId = (int)($ev['home_team_id'] ?? 0);
              $awayCanonId = (int)($ev['away_team_id'] ?? 0);
              if ($leagueIdForCanon > 0 && $homeCanonId > 0) {
                $canonMapStmt->execute([$leagueIdForCanon, $homeCanonId]);
                $c = $canonMapStmt->fetchColumn();
                if ($c !== false && $c !== null) { $homeCanonId = (int)$c; }
              }
              if ($leagueIdForCanon > 0 && $awayCanonId > 0) {
                $canonMapStmt->execute([$leagueIdForCanon, $awayCanonId]);
                $c = $canonMapStmt->fetchColumn();
                if ($c !== false && $c !== null) { $awayCanonId = (int)$c; }
              }
            ?>
  <div class="event-card"
       data-event-id="<?php echo (int)$ev['id']; ?>"
       data-home-logo="<?php echo htmlspecialchars($hLogo); ?>"
       data-away-logo="<?php echo htmlspecialchars($aLogo); ?>">

<div class="ec-team team-side"
         data-side="home"
         data-team-id="<?php echo $homeCanonId; ?>"                <!-- canon per UI -->
         data-team-id-raw="<?php echo (int)($ev['home_team_id'] ?? 0); ?>" <!-- RAW per API -->
         title="Seleziona casa">
      <span class="logo-wrap">
        <img class="team-logo"
             src="<?php echo htmlspecialchars($hLogo); ?>"
             alt="<?php echo htmlspecialchars($hn); ?>"
             onerror="this.style.display='none'; this.parentNode.querySelector('.initials-home').style.display='inline-flex';">
        <span class="team-initials initials-home"><?php echo htmlspecialchars($hIni); ?></span>
      </span>
      <span class="team-name"><?php echo htmlspecialchars($hn); ?></span>
    </div>

    <div class="ec-vs">VS</div>

 <div class="ec-team team-side"
         data-side="away"
         data-team-id="<?php echo $awayCanonId; ?>"                 <!-- canon per UI -->
         data-team-id-raw="<?php echo (int)($ev['away_team_id'] ?? 0); ?>"  <!-- RAW per API -->
         title="Seleziona trasferta">
      <span class="team-name" style="text-align:right;"><?php echo htmlspecialchars($an); ?></span>
      <span class="logo-wrap">
        <img class="team-logo"
             src="<?php echo htmlspecialchars($aLogo); ?>"
             alt="<?php echo htmlspecialchars($an); ?>"
             onerror="this.style.display='none'; this.parentNode.querySelector('.initials-away').style.display='inline-flex';">
        <span class="team-initials initials-away"><?php echo htmlspecialchars($aIni); ?></span>
      </span>
    </div>
  </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

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
    // Se round in attesa, niente bottone (non renderizzato) e niente selezioni (gestito più sotto)
    var btn = document.getElementById('btnAddLife');
    if (!btn) return;

    // Evidenzia vita selezionata
    var selectedLife = null;
    document.querySelectorAll('.life-heart').forEach(function(h){
      h.addEventListener('click', function(){
        document.querySelectorAll('.life-heart').forEach(function(x){ x.classList.remove('life-heart--active'); });
        h.classList.add('life-heart--active');
        selectedLife = parseInt(h.getAttribute('data-life')||'0',10);
      });
    });

    // Click su un lato (home/away) → se c’è vita selezionata, aggancia logo accanto al cuore
    document.querySelectorAll('.event-card .team-side').forEach(function(side){
      side.addEventListener('click', function(){
        var card = side.closest('.event-card');
        if (!card) return;
        var sideName = side.getAttribute('data-side'); // 'home' | 'away'
        var logoUrl = (sideName === 'home') ? card.getAttribute('data-home-logo') : card.getAttribute('data-away-logo');

        if (selectedLife === null){
          showMsg('Seleziona una vita', 'Per favore seleziona prima un cuore (vita) e poi la squadra.', 'error');
          return;
        }

        var heart = document.querySelector('.life-heart[data-life="'+selectedLife+'"]');
        if (!heart) return;

        var old = heart.querySelector('.pick-logo'); if (old) old.remove();

        var img = document.createElement('img');
        img.className = 'pick-logo';
        img.src = logoUrl;
        img.alt = 'Pick';
        img.onerror = function(){ this.remove(); };
        heart.appendChild(img);
      });
    });

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
        selectedLife = null;
        return;
      }
      for (var i=0; i<n; i++){
        var h = document.createElement('span');
        h.className = 'life-heart';
        h.setAttribute('data-life', i);
        h.title = 'Vita '+(i+1);
        h.textContent = '❤️';
        h.style.fontSize = '18px';
        h.style.cursor = 'pointer';
        h.addEventListener('click', function(ev){
          document.querySelectorAll('.life-heart').forEach(function(x){ x.classList.remove('life-heart--active'); });
          ev.currentTarget.classList.add('life-heart--active');
          selectedLife = parseInt(ev.currentTarget.getAttribute('data-life')||'0',10);
        });
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
        selectedLife = null;
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

  // ===== Disabilita selezioni quando round è "in attesa" =====
  (function(){
    var waiting = document.querySelector('main.torneo-wrap')?.getAttribute('data-waiting') === '1';
    if (!waiting) return;
    // Disattiva click sulle squadre
    document.querySelectorAll('.event-card .team-side').forEach(function(el){
      el.style.pointerEvents = 'none';
      el.style.opacity = '0.5';
      el.title = 'Round non pubblicato';
    });
  })();
</script>
  
<script src="/assets/torneo_selections.js?v=1"></script>

  <script>
// Disabilita selezioni quando lock è passato (UI)
(function(){
  var due = document.querySelector('.countdown[data-due]');
  if (!due) return;

  function isClosed() {
    var attr = due.getAttribute('data-due');
    if (!attr) return false;
    var t = new Date(attr.replace(' ','T')).getTime();
    return (Date.now() >= t);
  }

  function applyLockUI() {
    if (!isClosed()) return;
    document.querySelectorAll('.event-card .team-side').forEach(function(el){
      el.style.pointerEvents = 'none';
      el.style.opacity = '0.5';
      el.title = 'Scelte chiuse';
    });
    var add = document.getElementById('btnAddLife');
    if (add) add.style.display = 'none';
  }

  applyLockUI();
  setInterval(applyLockUI, 15000);
})();
</script>
  
  <script>
(function(){
  var tid = <?php echo (int)$id; ?>;
  var roundNow = <?php echo (int)$currentRoundNo; ?>;
  var key = 'arena_last_round_t'+tid;
  var last = parseInt(localStorage.getItem(key) || '0', 10);

  if (last !== roundNow) {
    // round cambiato → pulisco i loghi accanto ai cuori
    document.querySelectorAll('.life-heart .pick-logo').forEach(function(img){ img.remove(); });
    // tolgo evidenziazione
    document.querySelectorAll('.life-heart').forEach(function(h){ h.classList.remove('life-heart--active'); });
    localStorage.setItem(key, String(roundNow));
  }
})();
    </script>
<script>
(function(){
  var tid = <?php echo (int)$id; ?>;
  var roundNow = <?php echo (int)$currentRoundNo; ?>;
  var key = 'arena_last_round_'+tid;
  var last = 0;
  try { last = parseInt(localStorage.getItem(key) || '0', 10); } catch(_) {}

  if (last !== roundNow) {
    // round cambiato → pulisco i loghi accanto ai cuori
    document.querySelectorAll('.life-heart .pick-logo').forEach(function(img){ img.remove(); });
    // tolgo evidenziazione dai cuori
    document.querySelectorAll('.life-heart').forEach(function(h){ h.classList.remove('life-heart--active'); });
    // aggiorno storage
    try { localStorage.setItem(key, String(roundNow)); } catch(_) {}
    // reset variabile globale se esiste
    if (window.selectedLife !== undefined) window.selectedLife = null;
  }
})();
</script>

  <script>
(function(){
  var selectedLife = null;

  // evidenzia la vita selezionata e chiama refresh delle squadre
  document.querySelectorAll('.life-heart').forEach(function(h){
    h.addEventListener('click', function(){
      document.querySelectorAll('.life-heart').forEach(function(x){ x.classList.remove('life-heart--active'); });
      h.classList.add('life-heart--active');
      selectedLife = parseInt(h.getAttribute('data-life')||'0',10);

      refreshDisabledTeams(selectedLife);
    });
  });

  // funzione che colora grigio le squadre già usate o bloccate
  function refreshDisabledTeams(lifeIndex){
    if (lifeIndex === null) return;
    fetch('/api/used_teams.php?tournament_id=<?php echo (int)$id; ?>&life_index='+lifeIndex,
          {credentials:'same-origin'})
      .then(r => r.ok ? r.json() : null)
      .then(js => {
        if (!js || !js.ok) return;

        // reset: tolgo tutti i disabled
        document.querySelectorAll('.team-side').forEach(function(el){
          el.classList.remove('disabled');
        });

        // disabilita squadre già usate
        (js.used || []).forEach(function(teamId){
          document.querySelectorAll('.team-side[data-team-id="'+teamId+'"]').forEach(function(el){
            el.classList.add('disabled');
          });
        });

        // disabilita squadre non disponibili (eventi bloccati)
        (js.blocked || []).forEach(function(teamId){
          document.querySelectorAll('.team-side[data-team-id="'+teamId+'"]').forEach(function(el){
            el.classList.add('disabled');
          });
        });

      }).catch(()=>{});
  }
})();
</script>
  
  
</body>
</html>
