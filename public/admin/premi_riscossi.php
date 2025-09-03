<?php
// /admin/premi_riscossi.php — Elenco richieste evase (fulfilled) + rifiutate (opzione)
require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$show = $_GET['show'] ?? 'fulfilled'; // fulfilled|rejected
if (!in_array($show, ['fulfilled','rejected'], true)) $show = 'fulfilled';

$sql = "
  SELECT r.*, u.username, u.email
  FROM admin_prize_requests r
  JOIN utenti u ON u.id = r.user_id
  WHERE r.status = :s
  ORDER BY COALESCE(r.processed_at, r.requested_at) DESC
";
$st = $pdo->prepare($sql);
$st->execute([':s'=>$show]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Premi riscossi</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/admin_extra.css">
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wide">
  <div class="hstack">
    <h1 style="margin:0;">Premi — <?php echo $show==='fulfilled'?'Riscossi':'Rifiutati'; ?></h1>
    <span class="spacer"></span>
    <a class="btn btn-ghost" href="/admin/premi_richiesti.php">Torna a richieste</a>
  </div>

  <div class="card">
    <div class="hstack">
      <a class="btn <?php echo $show==='fulfilled'?'btn-ok':''; ?>" href="/admin/premi_riscossi.php?show=fulfilled">Riscossi</a>
      <a class="btn <?php echo $show==='rejected'?'btn-ok':''; ?>"  href="/admin/premi_riscossi.php?show=rejected">Rifiutati</a>
    </div>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>ID</th>
            <th>Utente</th>
            <th>Premio</th>
            <th class="num">Crediti</th>
            <th><?php echo $show==='fulfilled' ? 'Evaso il' : 'Rifiutato il'; ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="muted">Nessun elemento.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr>
              <td><code class="inline">#<?php echo (int)$r['id']; ?></code></td>
              <td>
                <strong><?php echo htmlspecialchars($r['username']); ?></strong><br>
                <span class="muted"><?php echo htmlspecialchars($r['email']); ?></span>
              </td>
              <td><?php echo htmlspecialchars($r['requested_item']); ?></td>
              <td class="num"><?php echo number_format((float)$r['credits_cost'], 2, ',', '.'); ?></td>
              <td><?php echo $r['processed_at'] ? date('d/m/Y H:i', strtotime($r['processed_at'])) : '—'; ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../footer.php'; ?>
</body>
</html>
