<?php
// /admin/tornei_chiusi.php — Elenco tornei chiusi con KPI
require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// Prendiamo i tornei NON aperti (closed/archived/settled/ecc.)
$sql = "
  SELECT
    t.id, t.tournament_code, t.name, t.status, t.lock_at, t.created_at,
    t.cost_per_life, t.prize_percent, t.guaranteed_prize,
    (SELECT COUNT(*) FROM tournament_enrollments e WHERE e.tournament_id = t.id) AS participants,
    (SELECT COALESCE(SUM(e.lives),0) FROM tournament_enrollments e WHERE e.tournament_id = t.id) AS lives_sold
  FROM tournaments t
  WHERE t.status <> 'open'
  ORDER BY COALESCE(t.lock_at, t.created_at) DESC, t.id DESC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Tornei chiusi</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/admin_extra.css">
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wide">
  <div class="hstack">
    <h1 style="margin:0;">Tornei chiusi</h1>
    <span class="spacer"></span>
    <a class="btn btn-ghost" href="/admin/dashboard.php">Torna alla dashboard</a>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Codice</th>
            <th>Nome</th>
            <th>Chiuso il</th>
            <th class="num">Partecipanti</th>
            <th class="num">Vite vendute</th>
            <th class="num">Buy‑in</th>
            <th class="num">Montepremi</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="muted">Nessun torneo chiuso.</td></tr>
          <?php else: foreach ($rows as $r):
            $buyin = (float)($r['cost_per_life'] ?? 0);
            $pp    = (int)($r['prize_percent'] ?? 100);
            $g     = (float)($r['guaranteed_prize'] ?? 0);
            $lives = (int)($r['lives_sold'] ?? 0);
            $gross = $lives * $buyin;
            $pot   = max($g, $gross * ($pp / 100));
          ?>
            <tr>
              <td><code class="inline">#<?php echo htmlspecialchars($r['tournament_code'] ?? sprintf('%05d',$r['id'])); ?></code></td>
              <td><?php echo htmlspecialchars($r['name'] ?? ''); ?></td>
              <td><?php echo $r['lock_at'] ? date('d/m/Y H:i', strtotime($r['lock_at'])) : '—'; ?></td>
              <td class="num"><?php echo (int)$r['participants']; ?></td>
              <td class="num"><?php echo (int)$lives; ?></td>
              <td class="num"><?php echo number_format($gross, 0, ',', '.'); ?></td>
              <td class="num"><?php echo number_format($pot,   0, ',', '.'); ?></td>
              <td class="num">
                <a class="btn btn-ok" href="/admin/torneo_chiuso.php?id=<?php echo (int)$r['id']; ?>">Dettaglio</a>
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
