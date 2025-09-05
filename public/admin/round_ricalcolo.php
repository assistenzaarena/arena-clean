<?php
// =====================================================================
// /admin/round_ricalcolo.php — Anteprima + applicazione ricalcolo ultimo round
// Usa la libreria src/round_recalc_lib.php (funz.: rr_get_current_round, rr_preview)
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/round_recalc_lib.php';

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// Flash helpers
$FLASH = function(string $kind, string $text){ $_SESSION['flash'] = ['k'=>$kind,'t'=>$text]; };
$POP   = function(){ $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; };

$flash = $POP();

// --- Parametri GET ---
$tid           = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$roundFromGet  = isset($_GET['round']) ? (int)$_GET['round'] : ( isset($_GET['round_no']) ? (int)$_GET['round_no'] : 0 );
$forceCalc     = isset($_GET['calc']) ? (int)$_GET['calc'] : 0;     // quando ritorno da "Salva risultato"
$fromResultSave= isset($_GET['from']) && $_GET['from']==='result_save';

// --- Elenco tornei per la select ---
$torneos = [];
try {
  $q = $pdo->query("SELECT id, name, current_round_no FROM tournaments ORDER BY id DESC");
  $torneos = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $torneos = []; }

// --- Anteprima ---
$roundNo  = null;
$preview  = null;
$diagErr  = null;
$diagCount= [];

if ($tid > 0) {
  // Se mi hanno passato un round in query lo onoro, altrimenti prendo il current del torneo
  if ($roundFromGet > 0) {
    $roundNo = $roundFromGet;
  } else {
    $roundNo = rr_get_current_round($pdo, $tid);
  }

  if ($roundNo !== null) {
    try {
      // rr_preview() deve calcolare un'anteprima in base ai risultati attuali delle partite
      // Se hai implementato un flag $forceCalc nella tua libreria, puoi passarlo qui.
      // $preview = rr_preview($pdo, $tid, $roundNo, $forceCalc===1);
      $preview = rr_preview($pdo, $tid, $roundNo);
    } catch (Throwable $e) {
      $preview = null;
      $diagErr = $e->getMessage();
    }

    // Se preview è null, preparo qualche diagnostica sugli eventi
    if ($preview === null) {
      try {
        // Quanti eventi ha il round?
        $st = $pdo->prepare("SELECT COUNT(*) FROM tournament_events WHERE tournament_id=? AND round_no=?");
        $st->execute([$tid, $roundNo]);
        $diagCount['events'] = (int)$st->fetchColumn();

        // Distribuzione per result_status
        $st = $pdo->prepare("
          SELECT result_status, COUNT(*) AS c
          FROM tournament_events
          WHERE tournament_id=? AND round_no=?
          GROUP BY result_status
          ORDER BY result_status
        ");
        $st->execute([$tid, $roundNo]);
        $diagCount['by_status'] = $st->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {
        // solo log
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
    <div class="flash <?php echo htmlspecialchars($flash['k']); ?>"><?php echo htmlspecialchars($flash['t']); ?></div>
  <?php endif; ?>

  <!-- Selettore torneo -->
  <section class="card">
    <form class="hstack" method="get" action="/admin/round_ricalcolo.php">
      <label for="tid" class="note">Torneo</label>
      <select id="tid" name="tournament_id" style="background:#0a0a0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;height:36px;padding:0 10px;">
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
        <a class="btn" href="/admin/risultati_round.php?tournament_id=<?php echo (int)$tid; ?>&round=<?php echo (int)$roundNo; ?>">
          Modifica risultati partite
        </a>
      <?php endif; ?>
    </form>
  </section>

  <?php
  // Blocco diagnostico quando non c'è anteprima (evita "schermata nera")
  if ($tid>0 && $roundNo!==null && $preview===null): ?>
    <section class="card">
      <?php if (!empty($diagErr)): ?>
        <div class="flash err">Errore anteprima: <?php echo htmlspecialchars($diagErr); ?></div>
      <?php else: ?>
        <div class="flash warn">Nessuna anteprima disponibile per torneo #<?php echo (int)$tid; ?>, round <?php echo (int)$roundNo; ?>.</div>
      <?php endif; ?>

      <?php if (!empty($diagCount)): ?>
        <div class="muted" style="margin-top:8px">
          <div>Eventi nel round: <strong><?php echo (int)($diagCount['events'] ?? 0); ?></strong></div>
          <?php if (!empty($diagCount['by_status'])): ?>
            <div>Distribuzione risultati:</div>
            <ul>
              <?php foreach ($diagCount['by_status'] as $r): ?>
                <li><?php echo htmlspecialchars($r['result_status'] ?? 'NULL'); ?> → <?php echo (int)($r['c'] ?? 0); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="muted" style="margin-top:8px">
        Se hai appena aggiornato un risultato, verifica che il round selezionato in alto sia quello corretto e premi <b>Anteprima</b>.
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
        <p class="note" style="margin-top:8px">Le vite vengono ricalcolate per ogni (user_id, life_index) in base alle selezioni finalizzate nel round e ai risultati correnti delle partite.</p>

        <form id="applyForm" method="post" action="/admin/round_ricalcolo_apply.php" class="hstack" style="justify-content:flex-end;margin-top:12px">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
          <input type="hidden" name="tournament_id" value="<?php echo (int)$tid; ?>">
          <input type="hidden" name="round_no" value="<?php echo (int)$roundNo; ?>">
          <input type="text" name="note" placeholder="Nota (opzionale)" style="background:#0a0a0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;height:34px;padding:0 10px;min-width:260px">
          <button type="button" class="btn btn-danger" id="btnApply">Applica ricalcolo</button>
        </form>
      <?php endif; ?>
    </section>
  <?php elseif ($tid>0 && $roundNo===null): ?>
    <section class="card">
      <div class="flash err">Impossibile individuare il round attuale del torneo selezionato.</div>
    </section>
  <?php endif; ?>
</main>

<!-- Conferma modal -->
<div id="modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="mTitle">
  <div class="modal-card">
    <h3 id="mTitle" style="margin:0 0 8px">Confermi il ricalcolo?</h3>
    <p class="muted" style="margin:0 0 12px">L’operazione aggiornerà le vite degli iscritti in base alle selezioni del round e ai risultati attuali. È registrata in audit.</p>
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
