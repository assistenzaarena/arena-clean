<?php
// /admin/torneo_chiuso.php — Dettaglio “Torneo chiuso” (riassunto + elenco partecipanti + popup scelte)
require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/payouts.php'; // tp_compute_pot()

if (empty($_GET['id'])) { http_response_code(400); die('ID torneo mancante'); }
$tournament_id = (int)$_GET['id'];

// Torneo
$st = $pdo->prepare("SELECT * FROM tournaments WHERE id=? LIMIT 1");
$st->execute([$tournament_id]);
$t = $st->fetch(PDO::FETCH_ASSOC);
if (!$t) { http_response_code(404); die('Torneo non trovato'); }

// Riassunti principali
// 1) Partecipanti
$st = $pdo->prepare("SELECT COUNT(*) FROM tournament_enrollments WHERE tournament_id=?");
$st->execute([$tournament_id]);
$partecipanti = (int)$st->fetchColumn();

// 2) Vite vendute = #movimenti di tipo enroll/buy_life
$st = $pdo->prepare("
  SELECT COUNT(*) FROM credit_movements 
  WHERE tournament_id=? AND type IN ('enroll','buy_life')
");
$st->execute([$tournament_id]);
$vite_vendute = (int)$st->fetchColumn();

// 3) Buy-in (per vita)
$buy_in = (float)($t['cost_per_life'] ?? 0);

// 4) Montepremi (consistente con il resto del sito)
$potInfo = tp_compute_pot($pdo, $tournament_id);
$montepremi = (int)($potInfo['pot'] ?? 0);

// 5) Vincitore/i (tournament_payouts) — se esiste almeno un 'winner', mostro il/i winner
$st = $pdo->prepare("
  SELECT tp.user_id, tp.amount, tp.reason, u.username
  FROM tournament_payouts tp
  JOIN utenti u ON u.id = tp.user_id
  WHERE tp.tournament_id=?
  ORDER BY tp.amount DESC
");
$st->execute([$tournament_id]);
$payouts = $st->fetchAll(PDO::FETCH_ASSOC);

$vincitori = [];
if ($payouts) {
  $hasWinner = false;
  foreach ($payouts as $row) {
    if ($row['reason'] === 'winner') { $hasWinner = true; break; }
  }
  foreach ($payouts as $row) {
    if ($hasWinner) {
      if ($row['reason'] === 'winner') $vincitori[] = $row['username'];
    } else {
      // nessun 'winner': prendo chi ha amount > 0 come co-vincitori
      if ((float)$row['amount'] > 0) $vincitori[] = $row['username'];
    }
  }
}

// Partecipanti elenco
$st = $pdo->prepare("
  SELECT e.user_id, u.username, u.nome, u.cognome, u.email, u.phone
  FROM tournament_enrollments e
  JOIN utenti u ON u.id = e.user_id
  WHERE e.tournament_id=?
  ORDER BY u.username ASC
");
$st->execute([$tournament_id]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// CSRF per eventuali action future (qui solo GET)
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Torneo chiuso #<?php echo htmlspecialchars($t['tournament_code'] ?? sprintf('%05d',$tournament_id)); ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .admin-wide{ max-width:1280px; margin:22px auto; padding:0 16px; color:#fff; }
    .hstack{ display:flex; align-items:center; gap:10px; }
    .spacer{ flex:1 1 auto; }
    .card{ background:#111; border:1px solid rgba(255,255,255,.12); border-radius:12px; padding:16px; margin:12px 0; }
    .pill{ display:inline-flex; align-items:center; justify-content:center; height:28px; padding:0 12px; border-radius:9999px; font-weight:900; }
    .pill-dark{ background:#0f1114; border:1px solid rgba(255,255,255,.15); }
    .pill-ok{ background:#00c074; color:#04140c; }
    .muted{ color:#c9c9c9; }
    .tbl{ width:100%; border-collapse:separate; border-spacing:0 8px; }
    .tbl th{ text-align:left; color:#c9c9c9; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
    .tbl td, .tbl th{ padding:10px 12px; }
    .tbl tbody tr{ background:#111; border:1px solid rgba(255,255,255,.12); }
    .btn{ display:inline-flex; align-items:center; justify-content:center; height:32px; padding:0 12px; border:1px solid rgba(255,255,255,.25); border-radius:8px; color:#fff; text-decoration:none; font-weight:800; }
    .btn:hover{ border-color:#fff; }
    .btn-ghost{ background:transparent; }
    /* Modal */
    .overlay{ position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:9999; }
    .modal{ background:#0f1114; border:1px solid rgba(255,255,255,.15); border-radius:12px; max-width:860px; width:calc(100% - 32px); color:#fff; box-shadow:0 24px 60px rgba(0,0,0,.4); }
    .modal .hd{ display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.12); }
    .modal .bd{ padding:14px 16px; max-height:70vh; overflow:auto; }
    /* mini tabella per scelte */
    .subtbl{ width:100%; border-collapse:separate; border-spacing:0 6px; }
    .subtbl th{ text-align:left; color:#c9c9c9; font-size:12px; text-transform:uppercase; }
    .badge-win{ background:#00c074; color:#04140c; border-radius:9999px; padding:2px 8px; font-size:12px; font-weight:900; }
    .badge-lose{ background:#e62329; color:#fff; border-radius:9999px; padding:2px 8px; font-size:12px; font-weight:900; }
    .badge-wait{ background:#444; color:#fff; border-radius:9999px; padding:2px 8px; font-size:12px; font-weight:900; }
  </style>
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wide">
  <div class="hstack">
    <h1 style="margin:0;">Torneo chiuso —
      <?php echo htmlspecialchars($t['name'] ?? 'Torneo'); ?>
      <small class="muted">#<?php echo htmlspecialchars($t['tournament_code'] ?? sprintf('%05d',$tournament_id)); ?></small>
    </h1>
    <span class="spacer"></span>
    <a class="btn btn-ghost" href="/admin/tornei_chiusi.php">Torna all’elenco</a>
  </div>

  <!-- Riepilogo -->
  <div class="card">
    <div class="hstack" style="gap:16px; flex-wrap:wrap;">
      <div class="pill pill-dark">Partecipanti: <strong style="margin-left:6px;"><?php echo (int)$partecipanti; ?></strong></div>
      <div class="pill pill-dark">Vite vendute: <strong style="margin-left:6px;"><?php echo (int)$vite_vendute; ?></strong></div>
      <div class="pill pill-dark">Buy-in: <strong style="margin-left:6px;"><?php echo number_format($buy_in,0,',','.'); ?> crediti</strong></div>
      <div class="pill pill-dark">Montepremi: <strong style="margin-left:6px;"><?php echo number_format($montepremi,0,',','.'); ?> crediti</strong></div>
      <?php if ($vincitori): ?>
        <div class="pill pill-ok">
          <?php echo (count($vincitori) > 1 ? 'Co-vincitori:' : 'Vincitore:'); ?>
          <strong style="margin-left:6px;"><?php echo htmlspecialchars(implode(', ', $vincitori)); ?></strong>
        </div>
      <?php else: ?>
        <div class="pill pill-dark">Vincitore: <strong style="margin-left:6px;">—</strong></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Partecipanti -->
  <h2 style="margin:10px 0;">Partecipanti</h2>
  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Utente</th>
            <th>Contatti</th>
            <th class="num"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="3" class="muted">Nessun partecipante.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($r['username']); ?></strong><br>
                <span class="muted"><?php echo htmlspecialchars(trim(($r['nome']??'').' '.($r['cognome']??''))); ?></span>
              </td>
              <td>
                <span class="muted"><?php echo htmlspecialchars($r['email'] ?? ''); ?></span><br>
                <span class="muted"><?php echo htmlspecialchars($r['phone'] ?? ''); ?></span>
              </td>
              <td class="num">
                <button class="btn js-detail" 
                        data-user="<?php echo (int)$r['user_id']; ?>" 
                        data-username="<?php echo htmlspecialchars($r['username']); ?>">
                  Dettaglio scelte
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal -->
<div class="overlay" id="userModal">
  <div class="modal">
    <div class="hd">
      <strong id="mTitle">Scelte</strong>
      <button class="btn btn-ghost" id="mClose">Chiudi</button>
    </div>
    <div class="bd" id="mBody">
      <!-- riempita via JS -->
    </div>
  </div>
</div>

<script>
(function(){
  var modal = document.getElementById('userModal');
  var mBody = document.getElementById('mBody');
  var mTitle= document.getElementById('mTitle');
  var close = document.getElementById('mClose');

  function openModal(){ modal.style.display='flex'; }
  function closeModal(){ modal.style.display='none'; mBody.innerHTML=''; }
  close.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });

  function badge(outcome){
    if(outcome==='win') return '<span class="badge-win">Vinta</span>';
    if(outcome==='lose') return '<span class="badge-lose">Persa</span>';
    return '<span class="badge-wait">—</span>';
  }

  // click su “Dettaglio scelte”
  document.querySelectorAll('.js-detail').forEach(function(btn){
    btn.addEventListener('click', function(){
      var uid = btn.getAttribute('data-user');
      var uname = btn.getAttribute('data-username') || 'Utente';
      mTitle.textContent = 'Scelte — ' + uname;

      fetch('/admin/ajax_user_selections.php?tournament_id=<?php echo (int)$tournament_id; ?>&user_id='+encodeURIComponent(uid))
        .then(r => r.ok ? r.json() : null)
        .then(js => {
          if(!js || !js.ok){ mBody.innerHTML='<p class="muted">Nessun dato disponibile.</p>'; openModal(); return; }

          // raggruppo per life_index
          var perLife = {};
          js.items.forEach(function(row){
            var L = row.life_index;
            if(!perLife[L]) perLife[L] = [];
            perLife[L].push(row);
          });

          var html = '';
          Object.keys(perLife).sort(function(a,b){return (+a)-(+b)}).forEach(function(L){
            html += '<h3 style="margin:14px 0 6px;">Vita '+ (parseInt(L,10)+1) +'</h3>';
            html += '<table class="subtbl"><thead><tr>'
                 +  '<th>Round</th><th>Scelta</th><th>Esito</th></tr></thead><tbody>';
            perLife[L].sort(function(a,b){ return (+a.round_no) - (+b.round_no); }).forEach(function(r){
              var team = r.side==='home' ? r.home_team_name : r.away_team_name;
              html += '<tr>'
                   +  '<td>'+ (r.round_no||'-') +'</td>'
                   +  '<td>'+ (team? team : '-') +'</td>'
                   +  '<td>'+ badge(r.outcome) +'</td>'
                   +  '</tr>';
            });
            html += '</tbody></table>';
          });

          if(!html) html = '<p class="muted">Nessuna scelta disponibile.</p>';
          mBody.innerHTML = html;
          openModal();
        })
        .catch(function(){ mBody.innerHTML='<p class="muted">Errore di rete.</p>'; openModal(); });
    });
  });
})();
</script>

<?php require __DIR__ . '/../footer.php'; ?>
</body>
</html>
