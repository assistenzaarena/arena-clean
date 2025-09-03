<?php
// admin/amministrazione.php
// Pannello riepilogo generale per l'admin
require_once __DIR__ . '/../src/guards.php';
require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

// Totale rake incassata
$rake = $pdo->query("
  SELECT 
    DATE_FORMAT(created_at, '%Y-%m') AS mese,
    SUM(amount) AS totale
  FROM admin_rake_ledger
  GROUP BY mese
  ORDER BY mese DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totale_rake = $pdo->query("SELECT SUM(amount) FROM admin_rake_ledger")->fetchColumn() ?: 0;

// Tornei chiusi (solo elenco base per ora)
$tornei = $pdo->query("
  SELECT id, name, tournament_code, closed_at, prize_percent
  FROM tournaments
  WHERE status = 'closed'
  ORDER BY closed_at DESC
  LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Premi richiesti
$premi_richiesti = $pdo->query("
  SELECT pr.id, pr.user_id, pr.premio, pr.crediti_spesi, pr.created_at, u.username
  FROM premi_richiesti pr
  JOIN utenti u ON u.id = pr.user_id
  WHERE pr.status = 'pending'
  ORDER BY pr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Premi riscossi
$premi_riscossi = $pdo->query("
  SELECT pr.id, pr.user_id, pr.premio, pr.crediti_spesi, pr.created_at, u.username
  FROM premi_riscossi pr
  JOIN utenti u ON u.id = pr.user_id
  ORDER BY pr.created_at DESC
  LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
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

  <section>
    <h2>Rake incassata</h2>
    <p><strong>Totale:</strong> <?php echo number_format($totale_rake,0,',','.'); ?> crediti</p>
    <table class="admin-table">
      <thead><tr><th>Mese</th><th>Totale</th></tr></thead>
      <tbody>
        <?php foreach ($rake as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['mese']); ?></td>
            <td><?php echo number_format($r['totale'],0,',','.'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section>
    <h2>Tornei chiusi (ultimi 20)</h2>
    <table class="admin-table">
      <thead><tr><th>ID</th><th>Codice</th><th>Nome</th><th>Chiuso il</th></tr></thead>
      <tbody>
        <?php foreach ($tornei as $t): ?>
          <tr>
            <td><?php echo (int)$t['id']; ?></td>
            <td><?php echo htmlspecialchars($t['tournament_code']); ?></td>
            <td><?php echo htmlspecialchars($t['name']); ?></td>
            <td><?php echo htmlspecialchars($t['closed_at']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section>
    <h2>Premi richiesti (da evadere)</h2>
    <table class="admin-table">
      <thead><tr><th>ID</th><th>Utente</th><th>Premio</th><th>Crediti spesi</th><th>Data</th></tr></thead>
      <tbody>
        <?php foreach ($premi_richiesti as $p): ?>
          <tr>
            <td><?php echo (int)$p['id']; ?></td>
            <td><?php echo htmlspecialchars($p['username']); ?></td>
            <td><?php echo htmlspecialchars($p['premio']); ?></td>
            <td><?php echo (int)$p['crediti_spesi']; ?></td>
            <td><?php echo htmlspecialchars($p['created_at']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section>
    <h2>Premi riscossi (ultimi 20)</h2>
    <table class="admin-table">
      <thead><tr><th>ID</th><th>Utente</th><th>Premio</th><th>Crediti spesi</th><th>Data</th></tr></thead>
      <tbody>
        <?php foreach ($premi_riscossi as $p): ?>
          <tr>
            <td><?php echo (int)$p['id']; ?></td>
            <td><?php echo htmlspecialchars($p['username']); ?></td>
            <td><?php echo htmlspecialchars($p['premio']); ?></td>
            <td><?php echo (int)$p['crediti_spesi']; ?></td>
            <td><?php echo htmlspecialchars($p['created_at']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>

<?php require __DIR__ . '/../footer.php'; ?>
</body>
</html>
