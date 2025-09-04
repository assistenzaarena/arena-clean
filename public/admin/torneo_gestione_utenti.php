<?php
// admin/torneo_gestione_utenti.php — Vista per-utente: scelte e vite
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/tournament_admin_utils.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$tournaments = ta_get_tournaments($pdo);
$tid = (int)($_GET['tournament_id'] ?? 0);

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

// Distinti utenti del torneo (dalle selections) + vite se disponibili
$users = [];
if ($tid > 0) {
  try {
    $sql = "
      SELECT ts.user_id, COUNT(*) AS picks
      FROM tournament_selections ts
      WHERE ts.tournament_id = :t
      GROUP BY ts.user_id
      ORDER BY picks DESC, user_id ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':t'=>$tid]);
    $users = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $users = []; }

  // Join vite (se esistono)
  if (ta_safe_table_exists($pdo, 'tournament_users') && ta_safe_column_exists($pdo, 'tournament_users','lives_remaining')) {
    try {
      $st = $pdo->prepare("SELECT user_id, lives_remaining FROM tournament_users WHERE tournament_id=:t");
      $st->execute([':t'=>$tid]);
      $livesMap = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
      foreach ($users as &$u) { $u['lives_remaining'] = isset($livesMap[$u['user_id']]) ? (int)$livesMap[$u['user_id']] : null; }
      unset($u);
    } catch (Throwable $e) {}
  }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Gestione partecipanti torneo</title>
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
    select,input[type=number]{height:36px;background:#0a0a0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;padding:0 8px}
    table{width:100%;border-collapse:separate;border-spacing:0 8px}
    th{color:#c9c9c9;font-size:12px;letter-spacing:.03em;text-transform:uppercase;text-align:left;padding:8px}
    td{background:#111;border:1px solid rgba(255,255,255,.12);padding:10px 12px;vertical-align:middle}
    .flash{padding:10px 12px;border-radius:8px;margin-bottom:12px;background:rgba(0,192,116,.1);border:1px solid rgba(0,192,116,.4);color:#00c074}
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.6);z-index:9999;padding:16px}
    .modal-card{background:#0f1114;border:1px solid rgba(255,255,255,.15);border-radius:12px;color:#fff;width:720px;max-width:calc(100vw - 32px);padding:16px}
  </style>
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>
<main class="wrap">
  <div class="hstack">
    <h1 style="margin:0">Gestione partecipanti</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/gestisci_tornei.php">Torna a gestione tornei</a>
  </div>

  <?php if ($flash): ?>
    <div class="flash"><?php echo htmlspecialchars(is_array($flash)?implode(' ', $flash):$flash); ?></div>
  <?php endif; ?>

  <section class="card">
    <form id="f" method="get" action="/admin/torneo_gestione_utenti.php" class="hstack">
      <label for="selT">Torneo</label>
      <select id="selT" name="tournament_id">
        <option value="">—</option>
        <?php foreach ($tournaments as $t): ?>
          <option value="<?php echo (int)$t['id']; ?>" <?php echo ($tid===(int)$t['id']?'selected':''); ?>>
            <?php echo '#'.$t['id'].' — '.htmlspecialchars($t['name']??'Torneo'); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Apri</button>
    </form>
  </section>

  <?php if ($tid>0): ?>
    <section class="card">
      <h2 style="margin:0 0 10px">Partecipanti</h2>
      <table>
        <thead>
          <tr>
            <th>Utente</th><th>Pick totali</th><th>Vite</th><th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$users): ?>
            <tr><td colspan="4" class="muted">Nessun partecipante trovato.</td></tr>
          <?php else: foreach ($users as $u):
            $uid = (int)$u['user_id'];
            $uname = ta_username($pdo, $uid);
            $lives = array_key_exists('lives_remaining',$u) ? ($u['lives_remaining'] ?? '—') : '—';
          ?>
            <tr>
              <td><?php echo htmlspecialchars($uname); ?> <span class="muted">(#<?php echo $uid; ?>)</span></td>
              <td><?php echo (int)$u['picks']; ?></td>
              <td><?php echo htmlspecialchars((string)$lives); ?></td>
              <td class="hstack">
                <button class="btn" type="button" data-uid="<?php echo $uid; ?>" data-uname="<?php echo htmlspecialchars($uname); ?>" onclick="openUser('<?php echo $uid; ?>','<?php echo htmlspecialchars($uname, ENT_QUOTES); ?>')">Dettagli</button>
                <?php if ($lives !== '—'): ?>
                  <form method="post" action="/admin/api_user_lives.php" onsubmit="return confirm('Aggiungere 1 vita a <?php echo htmlspecialchars($uname, ENT_QUOTES); ?>?');">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="tournament_id" value="<?php echo (int)$tid; ?>">
                    <input type="hidden" name="user_id" value="<?php echo (int)$uid; ?>">
                    <input type="hidden" name="delta" value="1">
                    <button class="btn btn-ok" type="submit">+1 vita</button>
                  </form>
                  <form method="post" action="/admin/api_user_lives.php" onsubmit="return confirm('Togliere 1 vita a <?php echo htmlspecialchars($uname, ENT_QUOTES); ?>?');">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="tournament_id" value="<?php echo (int)$tid; ?>">
                    <input type="hidden" name="user_id" value="<?php echo (int)$uid; ?>">
                    <input type="hidden" name="delta" value="-1">
                    <button class="btn btn-danger" type="submit">-1 vita</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>
</main>

<!-- Modal -->
<div id="modal" class="modal" role="dialog" aria-modal="true">
  <div class="modal-card">
    <div class="hstack" style="justify-content:space-between;align-items:center;">
      <h3 id="mTitle" style="margin:0">Dettaglio utente</h3>
      <button class="btn" onclick="closeUser()">Chiudi</button>
    </div>
    <div id="mBody" style="margin-top:10px;font-size:14px"></div>
  </div>
</div>

<script>
function openUser(uid, uname){
  var m=document.getElementById('modal'); var t=document.getElementById('mTitle'); var b=document.getElementById('mBody');
  t.textContent = 'Dettaglio — ' + uname + ' (#'+uid+')';
  b.innerHTML = 'Carico…';
  m.style.display='flex';
  fetch('/admin/user_round_picks.php?tournament_id=<?php echo (int)$tid; ?>&user_id='+encodeURIComponent(uid), {credentials:'same-origin'})
    .then(r=>r.json()).then(function(j){
      if(!j || !j.ok){ b.textContent='Errore caricamento.'; return; }
      var html = '';
      if(!j.data.rounds || j.data.rounds.length===0){ html = '<p class="muted">Nessuna scelta registrata.</p>'; }
      else{
        j.data.rounds.forEach(function(R){
          html += '<div style="margin:10px 0;"><strong>Round '+R.round_no+'</strong><ul style="margin:6px 0 0 16px">';
          R.picks.forEach(function(p){
            html += '<li>Event #'+p.event_id+' — pick '+p.pick_team_id+' — <em>'+p.status+'</em></li>';
          });
          html += '</ul></div>';
        });
      }
      b.innerHTML = html;
    }).catch(function(){ b.textContent='Errore di rete.'; });
}
function closeUser(){ document.getElementById('modal').style.display='none'; }
</script>
</body>
</html>
