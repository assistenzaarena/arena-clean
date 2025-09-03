<?php
// /admin/premi_richiesti.php — Elenco richieste premio in stato "pending" o "approved"
require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$status = $_GET['status'] ?? 'pending';  // pending|approved
if (!in_array($status, ['pending','approved'], true)) $status = 'pending';

$q = trim($_GET['q'] ?? ''); // ricerca per username/email/item

$sql = "
  SELECT r.*, u.username, u.nome, u.cognome, u.email, u.phone
  FROM admin_prize_requests r
  JOIN utenti u ON u.id = r.user_id
  WHERE r.status = :s
    AND (
      :q = '' OR
      u.username LIKE :like OR u.email LIKE :like OR r.requested_item LIKE :like
    )
  ORDER BY r.requested_at DESC
";
$st = $pdo->prepare($sql);
$like = '%'.$q.'%';
$st->execute([':s'=>$status, ':q'=>$q, ':like'=>$like]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Premi richiesti</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/admin_extra.css">
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wide">
  <div class="hstack">
    <h1 style="margin:0;">Premi — Richieste</h1>
    <span class="badge gray"><?php echo htmlspecialchars($status); ?></span>
    <span class="spacer"></span>
    <a class="btn btn-ghost" href="/admin/premi_riscossi.php">Vai a premi riscossi</a>
  </div>

  <div class="card hstack">
    <form method="get" class="hstack" action="/admin/premi_richiesti.php" style="gap:8px;">
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
      <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Cerca utente/email/premio" style="height:34px;border-radius:8px;border:1px solid rgba(255,255,255,.25);background:#0a0a0b;color:#fff;padding:0 10px;">
      <button class="btn">Cerca</button>
    </form>
    <span class="spacer"></span>
    <div class="hstack">
      <a class="btn <?php echo $status==='pending'?'btn-ok':''; ?>"    href="/admin/premi_richiesti.php?status=pending">Da approvare</a>
      <a class="btn <?php echo $status==='approved'?'btn-ok':''; ?>"   href="/admin/premi_richiesti.php?status=approved">Approvati</a>
    </div>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>ID</th>
            <th>Utente</th>
            <th>Richiesta</th>
            <th class="num">Crediti</th>
            <th>Richiesto il</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="muted">Nessuna richiesta.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><code class="inline">#<?php echo (int)$r['id']; ?></code></td>
              <td>
                <strong><?php echo htmlspecialchars($r['username']); ?></strong><br>
                <span class="muted"><?php echo htmlspecialchars($r['email']); ?></span>
              </td>
              <td><?php echo htmlspecialchars($r['requested_item']); ?></td>
              <td class="num"><?php echo number_format((float)$r['credits_cost'], 2, ',', '.'); ?></td>
              <td><?php echo date('d/m/Y H:i', strtotime($r['requested_at'])); ?></td>
              <td class="num">
                <a class="btn btn-ok" href="/admin/premio_dettaglio.php?id=<?php echo (int)$r['id']; ?>">Dettaglio</a>
              </td>
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
