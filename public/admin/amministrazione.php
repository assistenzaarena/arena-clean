<?php
// ==========================================================
// admin/amministrazione.php — Pannello amministrativo extra
// Mostra: Rake incassata (totale e per mese)
// ==========================================================

require_once __DIR__ . '/../src/guards.php';
require_admin();

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

// CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Flash (da azzera_rake.php)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// --- Query per rake mensile ---
$sql = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS mese,
        SUM(amount) AS totale
    FROM admin_rake_ledger
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mese DESC
";
$rows = [];
try {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}

// --- Query per rake totale ---
$totale = 0;
try {
    $totale = (float)$pdo->query("SELECT SUM(amount) FROM admin_rake_ledger")->fetchColumn();
} catch (Throwable $e) {
    $totale = 0;
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Amministrazione</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/dashboard.css">
  <style>
    .admin-card{
      background:#111;
      border:1px solid rgba(255,255,255,.12);
      border-radius:12px;
      padding:16px;
      margin-bottom:20px;
    }
    .admin-card h2{
      margin:0 0 12px;
      font-size:18px;
      font-weight:900;
    }
    .admin-table th, .admin-table td{ padding:8px 10px; }

    /* Flash message */
    .flash-msg{
      padding:10px 14px;
      border-radius:8px;
      margin-bottom:16px;
      font-weight:700;
      opacity:1;
      transition: opacity .4s ease;
    }
    .flash-ok{
      background: rgba(0, 192, 116, 0.15);
      border: 1px solid rgba(0, 192, 116, 0.5);
      color: #00c074;
    }
    .flash-err{
      background: rgba(230, 35, 41, 0.15);
      border: 1px solid rgba(230, 35, 41, 0.5);
      color: #e62329;
    }

    /* Popup conferma azzeramento */
    .modal-overlay{
      position: fixed; inset: 0; display: none;
      align-items: center; justify-content: center;
      background: rgba(0,0,0,.6); z-index: 9999;
      padding: 16px;
    }
    .modal-card{
      background: #0f1114; color:#fff;
      border: 1px solid rgba(255,255,255,.15);
      border-radius:12px; padding:18px; width: 420px; max-width: calc(100vw - 32px);
      box-shadow: 0 20px 60px rgba(0,0,0,.35);
    }
    .modal-title{ margin:0 0 8px; font-weight:900; font-size:18px; }
    .modal-text{ margin:0 0 14px; color:#dcdcdc; }
    .modal-actions{ display:flex; gap:8px; justify-content:flex-end; }
    .btn--ghost{
      background: transparent;
      border: 1px solid rgba(255,255,255,.28);
      color:#fff;
      border-radius:8px; padding:6px 12px; font-weight:800;
    }
    .btn--danger{
      background:#e62329; border:1px solid #e62329;
      color:#fff; border-radius:8px; padding:6px 12px; font-weight:800;
    }
    .btn--danger:hover{ background:#c01c21; }
  </style>
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wrap">
  <h1>Amministrazione</h1>

  <!-- Flash -->
  <?php if ($flash): ?>
    <div id="flashBox" class="flash-msg <?php echo (stripos($flash, 'successo') !== false ? 'flash-ok' : 'flash-err'); ?>">
      <?php echo htmlspecialchars($flash); ?>
    </div>
  <?php endif; ?>

  <!-- Rake: totale + azzera -->
  <section class="admin-card">
    <h2>Rake incassata</h2>
    <p><strong>Totale:</strong> <?php echo number_format($totale, 2, ',', '.'); ?> crediti</p>

    <!-- Form vero (nascosto), sarà inviato dal popup -->
    <form id="rakeResetForm" method="post" action="/admin/azzera_rake.php" style="display:none;">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="azzera_rake">
    </form>

    <!-- Bottone che apre il popup -->
    <button id="btnOpenReset" class="btn btn--danger" type="button">Azzera rake</button>
  </section>

  <!-- Rake per mese -->
  <section class="admin-card">
    <h2>Rake per mese</h2>
    <?php if (empty($rows)): ?>
      <p class="muted">Nessun dato disponibile.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Mese</th>
            <th>Totale crediti</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['mese']); ?></td>
              <td><?php echo number_format((float)$r['totale'], 2, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
    
      <!-- Catalogo premi -->
  <section class="admin-card">
    <h2>Catalogo premi</h2>
    <p class="muted">Gestisci i premi mostrati agli utenti nella pagina <em>Premi</em>.</p>
    <a class="btn btn--outline" href="/admin/premi_catalogo.php">Apri catalogo</a>
  </section>
    
</main>

<!-- Popup conferma -->
<div id="modalReset" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="resetTitle">
  <div class="modal-card">
    <h3 id="resetTitle" class="modal-title">Conferma azzeramento</h3>
    <p class="modal-text">Sei sicuro di voler azzerare la rake? L’operazione cancella definitivamente i dati di rake.</p>
    <div class="modal-actions">
      <button type="button" id="btnCancelReset" class="btn--ghost">Annulla</button>
      <button type="button" id="btnConfirmReset" class="btn--danger">Conferma</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>

<script>
  // Auto-hide flash dopo 5s con fade
  (function(){
    var box = document.getElementById('flashBox');
    if (!box) return;
    setTimeout(function(){
      box.style.opacity = '0';
      setTimeout(function(){ if (box && box.parentNode) box.parentNode.removeChild(box); }, 400);
    }, 5000);
  })();

  // Popup conferma azzeramento rake
  (function(){
    var overlay = document.getElementById('modalReset');
    var openBtn = document.getElementById('btnOpenReset');
    var cancelBtn = document.getElementById('btnCancelReset');
    var confirmBtn = document.getElementById('btnConfirmReset');
    var form = document.getElementById('rakeResetForm');

    function openModal(){ overlay.style.display = 'flex'; }
    function closeModal(){ overlay.style.display = 'none'; }

    if (openBtn)   openBtn.addEventListener('click', openModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (overlay)   overlay.addEventListener('click', function(e){ if(e.target === overlay) closeModal(); });
    if (confirmBtn)confirmBtn.addEventListener('click', function(){ form.submit(); });

    // ESC chiude
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && overlay && overlay.style.display === 'flex') { closeModal(); }
    });
  })();
</script>
</body>
</html>
