<?php
// =====================================================================
// /premi.php — Catalogo premi lato utente (richiesta premio + debit)
// Requisiti DB:
//   - admin_prize_catalog (id, name, description, credits_cost, image_url, is_active, created_at, updated_at)
//   - admin_prize_requests (id, user_id, prize_id, requested_item, credits_cost, status, created_at, ...)
//   - credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__;
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';
require_once $ROOT . '/src/utils.php'; // per generate_unique_code8()

require_login();
$uid = (int)($_SESSION['user_id'] ?? 0);

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// Flash helpers
$FLASH = function(string $kind, string $text){
  $_SESSION['flash'] = ['kind'=>$kind, 'text'=>$text];
};
$POP = function(){
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $f;
};

// =====================================================================
// POST: Richiesta premio
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($csrf, $posted_csrf)) {
    http_response_code(400);
    $FLASH('err','Sessione scaduta. Ricarica la pagina e riprova.');
    header('Location: /premi.php'); exit;
  }

  $action = $_POST['action'] ?? '';
  if ($action === 'redeem') {
    $prizeId = (int)($_POST['prize_id'] ?? 0);

    try {
      // 1) Verifica premio attivo
      $st = $pdo->prepare("SELECT id, name, credits_cost, is_active FROM admin_prize_catalog WHERE id=:id LIMIT 1");
      $st->execute([':id'=>$prizeId]);
      $prize = $st->fetch(PDO::FETCH_ASSOC);

      if (!$prize || (int)$prize['is_active'] !== 1) {
        $FLASH('err','Premio non disponibile.');
        header('Location: /premi.php'); exit;
      }

      $cost = (int)$prize['credits_cost'];
      if ($cost <= 0) {
        $FLASH('err','Costo premio non valido.');
        header('Location: /premi.php'); exit;
      }

      // 2) Transazione: blocco saldo, scalare crediti, creare richiesta, log movimento
      $pdo->beginTransaction();

      // 2a) saldo FOR UPDATE
      $st = $pdo->prepare("SELECT crediti FROM utenti WHERE id=:u FOR UPDATE");
      $st->execute([':u'=>$uid]);
      $saldo = (float)$st->fetchColumn();

      if ($saldo < $cost) {
        $pdo->rollBack();
        $FLASH('err','Crediti insufficienti.');
        header('Location: /premi.php'); exit;
      }

      // 2b) scala crediti
      $up = $pdo->prepare("UPDATE utenti SET crediti = crediti - :c WHERE id=:u");
      $up->execute([':c'=>$cost, ':u'=>$uid]);

// 2c) inserisci richiesta (allineato allo schema reale)
// NB: la tabella ha default: status='pending', requested_at=CURRENT_TIMESTAMP
$ins = $pdo->prepare("
  INSERT INTO admin_prize_requests (user_id, requested_item, credits_cost)
  VALUES (:u, :item, :cost)
");
$ins->execute([
  ':u'    => $uid,
  ':item' => $prize['name'],
  ':cost' => $cost
]);

      // 2d) log movimento contabile (redeem)
      $code = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
      $mv = $pdo->prepare("
        INSERT INTO credit_movements
          (movement_code, user_id, tournament_id, type, amount, created_at)
        VALUES
          (:code, :u, NULL, 'redeem', :amt, NOW())
      ");
      $mv->execute([':code'=>$code, ':u'=>$uid, ':amt'=>-(int)$cost]);

      $pdo->commit();

      $FLASH('ok', 'Richiesta inviata! Ti contatteremo a breve per la consegna.');
      header('Location: /premi.php'); exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      error_log('[premi_redeem] '.$e->getMessage());
      $FLASH('err','Errore interno. Riprova più tardi.');
      header('Location: /premi.php'); exit;
    }
  }

  // fallback
  header('Location: /premi.php'); exit;
}

// =====================================================================
// GET: Dati pagina — saldo, ricerca, paginazione, lista premi
// =====================================================================
$saldoAttuale = 0;
try {
  $q = $pdo->prepare("SELECT crediti FROM utenti WHERE id=:u LIMIT 1");
  $q->execute([':u'=>$uid]);
  $saldoAttuale = (float)$q->fetchColumn();
} catch (Throwable $e) { $saldoAttuale = 0; }

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page-1)*$perPage;

$bindings = [':active'=>1];
$where = " WHERE is_active=:active ";

if ($q !== '') {
  $where .= " AND (name LIKE :q OR description LIKE :q) ";
  $bindings[':q'] = '%'.$q.'%';
}

// Conteggio
$total = 0;
try {
  $sqlC = "SELECT COUNT(*) FROM admin_prize_catalog $where";
  $sc = $pdo->prepare($sqlC);
  foreach ($bindings as $k=>$v){ $sc->bindValue($k, $v, ($k===':active'?PDO::PARAM_INT:PDO::PARAM_STR)); }
  $sc->execute();
  $total = (int)$sc->fetchColumn();
} catch (Throwable $e) { $total = 0; }

// Lista
$items = [];
try {
  $sql = "
    SELECT id, name, description, credits_cost, image_url
    FROM admin_prize_catalog
    $where
    ORDER BY credits_cost ASC, updated_at DESC, id DESC
    LIMIT :lim OFFSET :off
  ";
  $st = $pdo->prepare($sql);
  foreach ($bindings as $k=>$v){ $st->bindValue($k, $v, ($k===':active'?PDO::PARAM_INT:PDO::PARAM_STR)); }
  $st->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
  $st->bindValue(':off', (int)$offset, PDO::PARAM_INT);
  $st->execute();
  $items = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $items=[]; }

$pages = max(1, (int)ceil($total / $perPage));
$flash = $POP();

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Premi</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_user.css">
  <style>
    .wrap{max-width:1280px;margin:24px auto;padding:0 16px;color:#fff}
    .headbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}
    .spacer{flex:1 1 auto}
    .saldo{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:8px 12px;font-weight:900}
    .search{display:flex;gap:8px}
    .search input[type=text]{height:36px;background:#0a0a0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;padding:0 10px}
    .grid{display:grid;grid-template-columns: repeat(4, minmax(240px,1fr)); gap:14px}
    @media (max-width:1080px){ .grid{ grid-template-columns: repeat(3, minmax(220px,1fr)); } }
    @media (max-width:820px){ .grid{ grid-template-columns: repeat(2, minmax(220px,1fr)); } }
    @media (max-width:540px){ .grid{ grid-template-columns: 1fr; } }

    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:10px;box-shadow:0 8px 28px rgba(0,0,0,.18)}
    .thumb{width:80px;height:80px;border-radius:50%;object-fit:cover;border:1px solid rgba(255,255,255,.15);background:#0f1114}
    .title{font-size:16px;font-weight:900;margin:0}
    .desc{color:#cfcfcf;font-size:13px;min-height:38px}
    .price{font-weight:900}
    .row{display:flex;align-items:center;gap:10px}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;font-weight:800;background:#202326;text-decoration:none;cursor:pointer}
    .btn:hover{border-color:#fff}
    .btn-ok{background:#00c074;border-color:#00c074}
    .btn-disabled{background:#2a2d31;border-color:#3a3f45;color:#9aa0a6;cursor:not-allowed}
    .muted{color:#bdbdbd}

    /* Flash coerenti */
    .flash{padding:10px 14px;border-radius:8px;margin:10px 0;font-weight:800;opacity:1;transition:opacity .4s ease}
    .flash.ok{background:rgba(0,192,116,.12);border:1px solid rgba(0,192,116,.45);color:#00c074}
    .flash.err{background:rgba(230,35,41,.12);border:1px solid rgba(230,35,41,.45);color:#ff7076}

    /* Paginazione */
    .pag{display:flex;gap:8px;margin:16px 0}
    .pag a{color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.25);padding:6px 10px;border-radius:8px}
    .pag a.on{background:#fff;color:#000}

    /* Modal conferma */
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.6);z-index:9999;padding:16px}
    .modal-card{background:#0f1114;border:1px solid rgba(255,255,255,.15);border-radius:12px;color:#fff;width:420px;max-width:calc(100vw - 32px);padding:16px;box-shadow:0 24px 60px rgba(0,0,0,.35)}
    .modal-title{margin:0 0 8px;font-weight:900;font-size:18px}
    .modal-text{margin:0 0 12px;color:#ddd}
    .modal-actions{display:flex;gap:8px;justify-content:flex-end}
    .btn-ghost{background:transparent;border:1px solid rgba(255,255,255,.28);color:#fff;border-radius:8px;padding:6px 12px;font-weight:800}
    .btn-danger{background:#00c074;border:1px solid #00c074;color:#fff;border-radius:8px;padding:6px 12px;font-weight:800}
  </style>
</head>
<body>

<?php require $ROOT . '/header_user.php'; ?>

<main class="wrap">
  <div class="headbar">
    <h1 style="margin:0">Premi</h1>
    <span class="spacer"></span>
    <div class="saldo">Saldo: <?php echo number_format($saldoAttuale, 0, ',', '.'); ?> crediti</div>
  </div>

  <?php if ($flash): ?>
    <div id="flashBox" class="flash <?php echo htmlspecialchars($flash['kind'] ?? 'ok'); ?>">
      <?php echo htmlspecialchars($flash['text'] ?? ''); ?>
    </div>
  <?php endif; ?>

  <!-- Ricerca -->
  <form class="search" method="get" action="/premi.php">
    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Cerca premio...">
    <button class="btn" type="submit">Cerca</button>
    <?php if ($q!==''): ?>
      <a class="btn" href="/premi.php">Pulisci</a>
    <?php endif; ?>
  </form>

  <!-- Griglia premi -->
  <?php if (!$items): ?>
    <p class="muted" style="margin-top:12px">Nessun premio disponibile.</p>
  <?php else: ?>
    <div class="grid" style="margin-top:12px">
      <?php foreach ($items as $it):
        $id    = (int)$it['id'];
        $name  = (string)$it['name'];
        $desc  = (string)($it['description'] ?? '');
        $cost  = (int)$it['credits_cost'];
        $img   = (string)($it['image_url'] ?? '');
        $enough = ($saldoAttuale >= $cost);
      ?>
      <div class="card">
        <div class="row">
          <img class="thumb" src="<?php echo $img ? htmlspecialchars($img) : '/assets/placeholder.webp'; ?>" alt="thumb">
          <div>
            <h3 class="title"><?php echo htmlspecialchars($name); ?></h3>
            <div class="price"><?php echo number_format($cost, 0, ',', '.'); ?> crediti</div>
          </div>
        </div>
        <div class="desc"><?php echo $desc !== '' ? htmlspecialchars($desc) : '—'; ?></div>
        <div class="row" style="justify-content:flex-end;gap:8px">
          <?php if ($enough): ?>
            <button class="btn btn-ok btn-redeem"
                    data-id="<?php echo $id; ?>"
                    data-name="<?php echo htmlspecialchars($name); ?>"
                    data-cost="<?php echo $cost; ?>">
              Richiedi
            </button>
          <?php else: ?>
            <button class="btn btn-disabled" title="Crediti insufficienti" disabled>Richiedi</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Paginazione -->
    <?php if ($pages > 1): ?>
      <div class="pag">
        <?php for($p=1;$p<=$pages;$p++):
          $qs = http_build_query(['q'=>$q,'page'=>$p]);
          $href = '/premi.php?'.$qs;
        ?>
          <a href="<?php echo $href; ?>" class="<?php echo ($p===$page?'on':''); ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Form nascosto per redemption -->
  <form id="redeemForm" method="post" action="/premi.php" style="display:none">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="action" value="redeem">
    <input type="hidden" name="prize_id" id="rf_prize">
  </form>
</main>

<?php require $ROOT . '/footer.php'; ?>

<!-- Modal conferma -->
<div id="redeemModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="modal-card">
    <h3 class="modal-title" id="modalTitle">Conferma richiesta</h3>
    <p class="modal-text" id="modalText">Confermi di voler richiedere questo premio?</p>
    <div class="modal-actions">
      <button type="button" class="btn-ghost" id="btnCancel">Annulla</button>
      <button type="button" class="btn-danger" id="btnConfirm">Conferma</button>
    </div>
  </div>
</div>

<script>
  // Flash auto-hide
  (function(){
    var box = document.getElementById('flashBox');
    if(!box) return;
    setTimeout(function(){
      box.style.opacity = '0';
      setTimeout(function(){ if(box && box.parentNode) box.parentNode.removeChild(box); }, 400);
    }, 4500);
  })();

  // Modal Redeem
  (function(){
    var modal = document.getElementById('redeemModal');
    var txt   = document.getElementById('modalText');
    var btnC  = document.getElementById('btnCancel');
    var btnOk = document.getElementById('btnConfirm');
    var form  = document.getElementById('redeemForm');
    var hid   = document.getElementById('rf_prize');
    var currentId = null;

    function open(id, name, cost){
      currentId = id;
      txt.textContent = "Confermi di voler richiedere “" + name + "” per " + cost.toLocaleString('it-IT') + " crediti?";
      modal.style.display = 'flex';
    }
    function close(){ modal.style.display = 'none'; currentId = null; }

    Array.prototype.slice.call(document.querySelectorAll('.btn-redeem')).forEach(function(b){
      b.addEventListener('click', function(){
        var id   = parseInt(b.getAttribute('data-id'),10);
        var name = b.getAttribute('data-name') || '';
        var cost = parseInt(b.getAttribute('data-cost'),10) || 0;
        open(id, name, cost);
      });
    });

    btnC.addEventListener('click', close);
    modal.addEventListener('click', function(e){ if(e.target === modal) close(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && modal.style.display==='flex') close(); });

    btnOk.addEventListener('click', function(){
      if(!currentId) return;
      hid.value = String(currentId);
      form.submit();
    });
  })();
</script>
</body>
</html>
