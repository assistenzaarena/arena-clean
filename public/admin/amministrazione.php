<?php
// ==========================================================
// admin/amministrazione.php — Pannello amministrativo extra
// Mostra: Rake incassata (totale e per mese)
// ==========================================================

require_once __DIR__ . '/../src/guards.php';
require_admin();

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

// =====================
// 1. Rake incassata per mese
// =====================
$rake_mensile = [];
$rake_totale  = 0;

try {
    // Somma importi raggruppati per mese (usiamo created_at già presente)
    $sql = "
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS mese,
               SUM(amount) AS totale
        FROM admin_rake_ledger
        GROUP BY mese
        ORDER BY mese DESC
    ";
    $stmt = $pdo->query($sql);
    $rake_mensile = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Somma totale di tutta la rake
    $stmt = $pdo->query("SELECT SUM(amount) AS totale FROM admin_rake_ledger");
    $rake_totale = (float) $stmt->fetchColumn();
} catch (Throwable $e) {
    $rake_mensile = [];
    $rake_totale  = 0;
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Amministrazione</title>
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
    .rake-table{
      width:100%;
      border-collapse:collapse;
    }
    .rake-table th,
    .rake-table td{
      text-align:left;
      padding:8px 10px;
      border-bottom:1px solid rgba(255,255,255,.12);
    }
    .rake-total{
      font-size:16px;
      font-weight:900;
      color:#00d07e;
      margin-top:10px;
    }
    .btn-reset{
      margin-top:12px;
      display:inline-block;
      background:#e62329;
      border:1px solid #e62329;
      border-radius:8px;
      color:#fff;
      padding:6px 12px;
      font-weight:800;
      text-decoration:none;
    }
    .btn-reset:hover{ background:#c01c21; }
  </style>
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wrap">
  <h1 class="page-title">Amministrazione</h1>

  <!-- Sezione Rake -->
  <div class="admin-card">
    <h2>Rake incassata</h2>

    <?php if (empty($rake_mensile)): ?>
      <p class="muted">Nessun dato disponibile.</p>
    <?php else: ?>
      <table class="rake-table">
        <thead>
          <tr>
            <th>Mese</th>
            <th>Totale (€)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rake_mensile as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['mese']); ?></td>
              <td><?php echo number_format((float)$row['totale'], 2, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="rake-total">
        Totale complessivo: <?php echo number_format($rake_totale, 2, ',', '.'); ?> €
      </div>
      <a href="/admin/azzera_rake.php" class="btn-reset">Azzera rake</a>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/../footer.php'; ?>

</body>
</html>
