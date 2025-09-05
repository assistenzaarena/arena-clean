<?php
// =====================================================================
// /public/admin/round_ricalcolo.php
// Anteprima differenze + applicazione ricalcolo del round
// Dipendenze: src/round_recalc_lib.php (rr_get_current_round, rr_preview)
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/round_recalc_lib.php';

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// Flash
$POP_FLASH = function() {
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash'], $_SESSION['flash_type']);
  return $f ? ['k'=>($_SESSION['flash_type'] ?? 'ok'), 't'=>$f] : ($f);
};
$flash = $POP_FLASH();

// Query params
$tid          = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$roundFromGet = isset($_GET['round']) ? (int)$_GET['round'] : (isset($_GET['round_no']) ? (int)$_GET['round_no'] : 0);
$forceCalc    = isset($_GET['calc']) ? (int)$_GET['calc'] : 0;      // opzionale
$from         = $_GET['from'] ?? '';                                 // “result_save” quando torno dal salvataggio

// Elenco tornei
$torneos = [];
try {
  $st = $pdo->query("SELECT id, name, current_round_no FROM tournaments ORDER BY id DESC");
  $torneos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $torneos = [];
}

// Anteprima
$roundNo = null;
$preview = null;
$diagErr = null;         // errore thrown da rr_preview
$diag    = ['events'=>0,'by_status'=>[]]; // diagnostica quando anteprima non disponibile

if ($tid > 0) {
  $roundNo = ($roundFromGet > 0) ? $roundFromGet : rr_get_current_round($pdo, $tid);

  if ($roundNo !== null) {
    try {
      // Se rr_preview supporta un 4° parametro “force”, puoi decommentare e passare $forceCalc===1
      // $preview = rr_preview($pdo, $tid, $roundNo, $forceCalc===1);
      $preview = rr_preview($pdo, $tid, $roundNo);
    } catch (Throwable $e) {
      $preview = null;
      $diagErr = $e->getMessage();
    }

    if ($preview === null) {
      try {
        $q1 = $pdo->prepare("SELECT COUNT(*) FROM tournament_events WHERE tournament_id=? AND round_no=?");
        $q1->execute([$tid, $roundNo]);
        $diag['events'] = (int)$q1->fetchColumn();

        $q2 = $pdo->prepare("
          SELECT result_status, COUNT(*) AS c
          FROM tournament_events
          WHERE tournament_id=? AND round_no=?
          GROUP BY result_status
          ORDER BY result_status
        ");
        $q2->execute([$tid, $roundNo]);
        $diag['by_status'] = $q2->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {
        error_log('[round_ricalcolo:diag] '.$e->getMessage());
      }
    }
  }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Ricalcolo ultimo round</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1280px;margin:24px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .hstack{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .spacer{flex:1 1 auto}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;font-weight:800;background:#202326;text-decoration:none;cursor:pointer}
    .btn:hover{border-color:#fff}
    .btn-ok{background:#00c074;border-color:#00c074}
    .btn-danger{background:#e62329;border-color:#e62329}
    .muted{color:#bdbdbd}
    .tbl{width:100%;border-collapse:separate;border-spacing:0 8px}
    .tbl th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:#c9c9c9;text-align:left;padding:8px 10px;white-space:nowrap}
    .tbl td{background:#111;border:1px solid rgba(255,255,255,.12);padding:8px 10px;vertical-align:middle}
    .flash{padding:10px 12px;border-radius:8px;margin-bottom:12px}
    .ok{background:rgba(0,192,116,.1);border:1px solid rgba(0,192,116,.4);color:#00c074}
    .err{background:rgba(230,35,41,.1);border:1px solid rgba(230,35,41,.4);color:#ff7076}
    .warn{background:rgba(255,186,0,.1);border:1px solid rgba(255,186,0,.4);color:#ffba00}
    .note{font-size:12px;color:#c9c9c9}
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.6);z-index:9999;padding:16px}
    .modal-card{background:#0f1114;border:1px solid rgba(255,255,255,.15);border-radius:12px;color:#fff;width:520px;max-width:calc(100vw - 32px);padding:16px}
  </style>
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="wrap">
  <div class="hstack">
    <h1 style="margin:0">Ricalcolo ultimo round</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/gestisci_tornei.php">Torna a Gestisci Tornei</a>
  </div>

  <?php if ($flash): ?>
    <div class="flash <?php echo htmlspecialchars($_SESSION['flash_type'] ?? 'ok'); ?>">
      <?php echo htmlspecialchars($flash['t'] ?? (is_string($flash)?$flash:'')); ?>
    </div>
  <?php endif; ?>

  <?php if ($from==='result_save'): ?>
    <div class="flash warn">Hai aggiornato un risultato. Esegui l’<b>Anteprima</b> qui sotto per ricalcolare la differenza.</div>
  <?php endif; ?>

  <!-- Selettore torneo + round (round nascosto se già noto) -->
  <section class="card">
    <form class="hstack" method="get" action="/admin/round_ricalcolo.php">
      <label class="note">Torneo</label>
      <select name="tournament_id" style="background:#0a0a0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;height:36px;padding:0 10px;">
        <option value="0">— seleziona —</option>
        <?php foreach ($torneos as $t): ?>
          <option value="<?php echo (int)$t['id']; ?>" <?php echo ($tid===(int)$t['id']?'selected':''); ?>>
            <?php echo '#'.(int)$t['id'].' — '.htmlspecialchars($t['name']).' (Round attuale: '.(int)$t['current_round_no'].')'; ?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if ($roundNo !== null): ?>
        <input type="hidden" name="round" value="<?php echo (int)$roundNo; ?>">
      <?php endif; ?>

      <button class="btn" type="submit">Anteprima</button>

      <?php if ($tid>0 && $roundNo!==null): ?>
        <span class="spacer"></span>
        <a class="btn" href="/admin/risultati_round.php?tournament_id=<?php echo (int)$tid; ?>&round=<?php echo (int)$roundNo; ?>">Modifica risultati partite</a>
      <?php endif; ?>
    </form>
  </section>

  <?php if ($tid>0 && $roundNo!==null && $preview===null): ?>
    <section class="card">
      <?php if ($diagErr): ?>
        <div class="flash err">Errore anteprima: <?php echo htmlspecialchars($diagErr); ?></div>
      <?php else: ?>
        <div class="flash warn">Anteprima non disponibile per torneo #<?php echo (int)$tid; ?>, round <?php echo (int)$roundNo; ?>.</div>
      <?php endif; ?>

      <div class="muted" style="margin-top:8px">
        <div>Eventi nel round: <strong><?php echo (int)$diag['events']; ?></strong></div>
        <?php if (!empty($diag['by_status'])): ?>
          <div>Distribuzione risultati:</div>
          <ul style="margin:6px 0 0 18px">
            <?php foreach ($diag['by_status'] as $r): ?>
              <li><?php echo htmlspecialchars($r['result_status'] ?? 'NULL'); ?> → <?php echo (int)($r['c'] ?? 0); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="note" style="margin-top:8px">
        Se hai appena aggiornato i risultati, torna sopra e premi <b>Anteprima</b>.<br>
        In alternativa verifica che il round selezionato sia quello corretto.
      </div>
    </section>
  <?php endif; ?>

  <?php if ($tid>0 && $roundNo!==null && $preview): 
        $before = $preview['before_lives'] ?? [];
        $after  = $preview['after_lives'] ?? [];
        $diffRows = [];
        foreach ($after as $uid=>$new) {
          $old = (int)($before[$uid] ?? 0);
          if ($old !== (int)$new) $diffRows[] = [$uid,$old,(int)$new, ((int)$new - $old)];
        }
  ?>
    <section class="card">
      <h2 style="margin:0 0 8px">Anteprima differenze — Torneo #<?php echo (int)$tid; ?> — Round <?php echo (int)$roundNo; ?></h2>
      <?php if (!$diffRows): ?>
        <p class="muted">Nessuna differenza: il ricalcolo produrrebbe lo stesso stato vite.</p>
      <?php else: ?>
        <table class="tbl">
          <thead>
            <tr>
              <th>User ID</th>
              <th>Vite attuali</th>
              <th>Vite dopo ricalcolo</th>
              <th>Δ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($diffRows as $r): ?>
              <tr>
                <td><?php echo (int)$r[0]; ?></td>
                <td><?php echo (int)$r[1]; ?></td>
                <td><?php echo (int)$r[2]; ?></td>
                <td><?php echo ($r[3]>0?'+':'').(int)$r[3]; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <p class="note" style="margin-top:8px">Le vite vengono ricalcolate per ogni (user_id, life_index) in base alle selezioni finalizzate nel round e ai risultati correnti delle partite.</p>

      <form id="applyForm" method="post" action="/admin/round_ricalcolo_apply.php" class="hstack" style="justify-content:flex-end;margin-top:12px">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="tournament_id" value="<?php echo (int)$tid; ?>">
        <input type="hidden" name="round_no" value="<?php echo (int)$roundNo; ?>">
        <input type="text" name="note" placeholder="Nota (opzionale)" style="background:#0a0a0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;height:34px;padding:0 10px;min-width:260px">
        <button type="button" class="btn btn-danger" id="btnApply">Applica ricalcolo</button>
      </form>
    </section>
  <?php elseif ($tid>0 && $roundNo===null): ?>
    <section class="card">
      <div class="flash err">Impossibile individuare il round attuale del torneo selezionato.</div>
    </section>
  <?php endif; ?>
</main>

<!-- Modal conferma -->
<div id="modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="mTitle">
  <div class="modal-card">
    <h3 id="mTitle" style="margin:0 0 8px">Confermi il ricalcolo?</h3>
    <p class="muted" style="margin:0 0 12px">L’operazione aggiornerà le vite degli iscritti in base alle selezioni del round e ai risultati attuali. L’azione è tracciata in audit.</p>
    <div class="hstack" style="justify-content:flex-end">
      <button type="button" class="btn" id="mCancel">Annulla</button>
      <button type="button" class="btn btn-ok" id="mConfirm">Conferma</button>
    </div>
  </div>
</div>

<script>
  (function(){
    var b = document.getElementById('btnApply');
    var m = document.getElementById('modal');
    var c = document.getElementById('mCancel');
    var k = document.getElementById('mConfirm');
    var f = document.getElementById('applyForm');
    if(!b || !m || !c || !k || !f) return;
    b.addEventListener('click', function(){ m.style.display='flex'; });
    c.addEventListener('click', function(){ m.style.display='none'; });
    m.addEventListener('click', function(e){ if(e.target===m) m.style.display='none'; });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') m.style.display='none'; });
    k.addEventListener('click', function(){ f.submit(); });
  })();
</script>
</body>
</html>
