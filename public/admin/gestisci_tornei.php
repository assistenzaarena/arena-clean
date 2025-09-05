<?php
/**
 * admin/gestisci_tornei.php
 * Lista tornei PUBBLICATI/IN CORSO (status='open') con link alla pagina di gestione.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/guards.php';    require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$q = trim($_GET['q'] ?? '');

$where = "status = 'open'";
$params = [];
if ($q !== '') {
  $where .= " AND (name LIKE :q OR league_name LIKE :q OR tournament_code LIKE :q)";
  $params[':q'] = '%'.$q.'%';
}

$sql = "
  SELECT
    id,
    tournament_code,
    name,
    league_name,
    season,
    current_round_no,
    lock_at,
    choices_locked,         -- << NUOVO: per colonna 'Stato scelte'
    created_at,
    updated_at
  FROM tournaments
  WHERE {$where}
  ORDER BY updated_at DESC, created_at DESC
";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k, $v, PDO::PARAM_STR);
$st->execute();
$list = $st->fetchAll(PDO::FETCH_ASSOC);

// KPI totale
$tot_open = (int)$pdo->query("SELECT COUNT(*) FROM tournaments WHERE status='open'")->fetchColumn();
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Gestisci Tornei (in corso)</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
<style>
    .wrap{max-width: 1200px; margin:20px auto; padding:0 16px; color:#fff;}
    .kpi{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:12px 14px;display:inline-block;margin-bottom:12px}
    .filters{display:flex;gap:8px;align-items:center;margin-bottom:12px}
    .filters input[type=text]{height:34px;border-radius:8px;border:1px solid rgba(255,255,255,.25);background:#0a0a0b;color:#fff;padding:0 10px;min-width:320px}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}

    /* Pulsanti ordinati in griglia nella colonna Azioni */
    .actions { 
      display:grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); /* pulsanti uniformi */
      gap:8px;
      align-items:center;
      justify-items:center;
    }
    .actions form { margin:0; }
 .actions .btn { 
  width:100px;      /* più stretti */
  height:23px;      /* più bassi */
  font-size:10px;   /* testo più piccolo */
  flex:0 0 120px;   /* stessa larghezza per tutti */
  text-align:center;
}
</style>
</head>
<body>
<?php require $ROOT . '/header_admin.php'; ?>

<main class="wrap">
  <?php if (!empty($_SESSION['flash'])): ?>
  <div id="flashModal" style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:9999">
    <div style="background:#111;border:1px solid rgba(255,255,255,.15);border-radius:12px;padding:16px 18px;max-width:420px;width:100%;color:#fff;text-align:center">
      <h3 style="margin:0 0 8px;color:<?php echo (($_SESSION['flash_type']??'')==='error')?'#ff6b6b':'#00c074'; ?>">
        <?php echo (($_SESSION['flash_type']??'')==='error')?'Operazione non riuscita':'Operazione riuscita'; ?>
      </h3>
      <p style="margin:0 0 12px;color:#ddd;"><?php echo htmlspecialchars($_SESSION['flash']); ?></p>
      <button onclick="document.getElementById('flashModal').remove()" class="btn">OK</button>
    </div>
  </div>
  <?php unset($_SESSION['flash'], $_SESSION['flash_type']); ?>
<?php endif; ?>
  <div class="kpi"><strong>Tornei in corso:</strong> <?php echo $tot_open; ?></div>

  <form class="filters" method="get" action="/admin/gestisci_tornei.php">
    <input type="text" name="q" placeholder="Cerca (nome, lega, codice torneo)" value="<?php echo htmlspecialchars($q); ?>">
    <button class="btn" type="submit">Cerca</button>
  </form>

  <?php if (!$list): ?>
    <div class="kpi">Nessun torneo in corso trovato.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Codice</th>
          <th>Nome</th>
          <th>Lega</th>
          <th>Stagione</th>
          <th>Round attuale</th>
          <th>Lock scelte</th>
          <th>Stato scelte</th> <!-- << NUOVO -->
          <th>Aggiornato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $t): ?>
  <tr>
    <td>#<?php echo htmlspecialchars($t['tournament_code'] ?: sprintf('%05d',(int)$t['id'])); ?></td>
    <td><?php echo htmlspecialchars($t['name']); ?></td>
    <td><?php echo htmlspecialchars($t['league_name']); ?></td>
    <td><?php echo htmlspecialchars($t['season']); ?></td>
    <td><?php echo (int)($t['current_round_no'] ?? 1); ?></td>
    <td><?php echo $t['lock_at'] ? date('d/m/Y H:i', strtotime($t['lock_at'])) : '—'; ?></td>
    <td>
      <?php echo ((int)($t['choices_locked'] ?? 0) === 1)
        ? '<span style="color:#e62329;">Bloccate</span>'
        : '<span style="color:#00c074;">Aperte</span>'; ?>
    </td>
    <td><?php echo htmlspecialchars($t['updated_at']); ?></td>
<td>
  <div class="actions">
    <!-- Link gestione esistente -->
    <a class="btn" href="/admin/torneo_open.php?id=<?php echo (int)$t['id']; ?>">Gestisci</a>

    <!-- FINALIZZA -->
    <form method="post" action="/api/finalize_round.php"
          onsubmit="return confirm('Finalizzare le scelte di questo torneo? L’azione congela le scelte correnti.');">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="tournament_id" value="<?php echo (int)$t['id']; ?>">
      <input type="hidden" name="action" value="finalize">
      <input type="hidden" name="redirect" value="1">
      <button class="btn" type="submit">Finalizza scelte</button>
    </form>

    <!-- RIAPRI -->
    <form method="post" action="/api/finalize_round.php"
          onsubmit="return confirm('Vuoi RIAPRIRE le scelte di questo torneo? Verranno sbloccate (round corrente).');">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="tournament_id" value="<?php echo (int)$t['id']; ?>">
      <input type="hidden" name="action" value="reopen">
      <input type="hidden" name="redirect" value="1">
      <button class="btn" type="submit">Riapri scelte</button>
    </form>

    <!-- RICALCOLO -->
    <a class="btn" href="/admin/round_ricalcolo.php?tournament_id=<?php echo (int)$t['id']; ?>">
      Ricalcolo round
    </a>

    <!-- GESTIONE VITE -->
    <a class="btn" href="/admin/utente_vite.php?tournament_id=<?php echo (int)$t['id']; ?>">
      Gestione vite
    </a>
  </div>
</td>
  </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>

</body>
</html>
