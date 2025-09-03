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
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wrap">
  <h1>Amministrazione</h1>

  <!-- Rake totale -->
  <section class="card-table">
    <h2>Rake incassata</h2>
    <p><strong>Totale:</strong> <?php echo number_format($totale, 2, ',', '.'); ?> crediti</p>

    <form method="post" action="/admin/azzera_rake.php" onsubmit="return confirm('Sei sicuro di voler azzerare la rake?');">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <button class="btn btn--danger" type="submit" name="action" value="azzera_rake">Azzera rake</button>
    </form>
  </section>

  <!-- Rake per mese -->
  <section class="card-table" style="margin-top:20px;">
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
</main>

<?php require __DIR__ . '/../footer.php'; ?>
</body>
</html>
