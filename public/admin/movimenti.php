<?php
// ==========================================================
// admin/movimenti.php — Lista movimenti di UN utente (solo admin)
// Filtri: tipo, torneo, data; paginazione; somma importi.
// ==========================================================

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Guardie + DB
require_once __DIR__ . '/../src/guards.php';
require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

// ---- Parametri obbligatori ----
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
  http_response_code(400);
  die('user_id mancante o non valido.');
}

// Dati utente (per intestazione pagina)
$uq = $pdo->prepare("SELECT id, user_code, username, nome, cognome FROM utenti WHERE id = ? LIMIT 1");
$uq->execute([$user_id]);
$user = $uq->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  http_response_code(404);
  die('Utente non trovato.');
}

// ---- Filtri opzionali ----
$allowedTypes = ['enroll','buy_life','payout','unenroll','refund','adjustment']; // estendi se nel DB avete altre tipologie
$type   = trim($_GET['type'] ?? '');
$type   = in_array($type, $allowedTypes, true) ? $type : '';

$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

$date_from = trim($_GET['date_from'] ?? ''); // YYYY-MM-DD
$date_to   = trim($_GET['date_to']   ?? ''); // YYYY-MM-DD

// ---- Paginazione ----
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

// ---- Costruzione WHERE dinamico ----
$where   = "m.user_id = :uid";
$params  = [':uid' => $user_id];

if ($type !== '') {
  $where .= " AND m.type = :type";
  $params[':type'] = $type;
}
if ($tournament_id > 0) {
  $where .= " AND m.tournament_id = :tid";
  $params[':tid'] = $tournament_id;
}
if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
  $where .= " AND m.created_at >= :df";
  $params[':df'] = $date_from . ' 00:00:00';
}
if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
  $where .= " AND m.created_at <= :dt";
  $params[':dt'] = $date_to . ' 23:59:59';
}

// ---- Conteggio + somma importi ----
$sqlCount = "SELECT COUNT(*) FROM credit_movements m WHERE $where";
$sc = $pdo->prepare($sqlCount);
$sc->execute($params);
$total = (int)$sc->fetchColumn();

$sqlSum = "SELECT COALESCE(SUM(m.amount),0) FROM credit_movements m WHERE $where";
$ss = $pdo->prepare($sqlSum);
$ss->execute($params);
$sumAmount = (float)$ss->fetchColumn();

// ---- Select movimenti (join con tornei per nome/codice) ----
$sql = "
  SELECT m.id, m.movement_code, m.type, m.amount, m.created_at,
         m.tournament_id, t.name AS t_name, t.tournament_code AS t_code
  FROM credit_movements m
  LEFT JOIN tournaments t ON t.id = m.tournament_id
  WHERE $where
  ORDER BY m.created_at DESC, m.id DESC
  LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($sql);
// bind param dinamici
foreach ($params as $k => $v) {
  if ($k === ':uid' || $k === ':tid')      { $st->bindValue($k, (int)$v, PDO::PARAM_INT); }
  else if ($k === ':df' || $k === ':dt' || $k === ':type') { $st->bindValue($k, $v, PDO::PARAM_STR); }
}
// paginazione
$st->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
$st->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// ---- Per select Torneo nel filtro ----
$ts = $pdo->query("SELECT id, name, tournament_code FROM tournaments ORDER BY id DESC LIMIT 500");
$tornei = $ts ? $ts->fetchAll(PDO::FETCH_ASSOC) : [];

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Movimenti — <?php echo htmlspecialchars($user['username'] ?? ('ID '.$user_id)); ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/dashboard.css">
  <style>
    .admin-wrap{ max-width: 1200px; margin:24px auto; padding:0 16px; color:#fff; }
    .kpi{ background:#111; border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px 12px; display:inline-block; margin: 0 8px 12px 0; }
    .filters{ display:flex; flex-wrap:wrap; gap:8px; align-items:end; margin:12px 0 16px; }
    .filters label{ font-size:12px; color:#c9c9c9; display:block; margin-bottom:4px; }
    .filters .row{ display:flex; gap:8px; align-items:end; flex-wrap:wrap; }
    .filters input[type="date"], .filters select{
      height:34px; border:1px solid rgba(255,255,255,.25); background:#0a0a0b; color:#fff; border-radius:8px; padding:0 8px;
    }
    table.mov{ width:100%; border-collapse:separate; border-spacing:0 8px; }
    table.mov thead th{ text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:.03em; color:#c9c9c9; }
    table.mov tbody tr{ background:#111; border:1px solid rgba(255,255,255,.08); }
    table.mov td{ padding:8px 10px; vertical-align:middle; }
    .amount{ font-weight:900; }
    .amount.pos{ color:#00c074; }
    .amount.neg{ color:#ff6b6b; }
    .badge-small{ display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:800; border:1px solid rgba(255,255,255,.25); }
    .badge-type{ background:#202326; }
    .t-code{ color:#cfcfcf; font-size:12px; }
    .pag{ margin-top:12px; display:flex; gap:6px; }
    .pag a{ color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,.25); padding:4px 8px; border-radius:6px; }
    .pag .on{ background:#fff; color:#000; }
    .back{ margin: 10px 0 16px; display:inline-flex; gap:8px; align-items:center; }
    .back a{ color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,.25); padding:6px 10px; border-radius:8px; }
    .back a:hover{ border-color:#fff; }
  </style>
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wrap">
  <div class="back">
    <a href="/admin/dashboard.php">← Torna alla dashboard</a>
  </div>

  <h1 style="margin:0 0 8px;">Movimenti — <?php
    $label = trim(($user['nome'] ?? '').' '.($user['cognome'] ?? ''));
    $label = $label !== '' ? $label.' ('.$user['username'].')' : ($user['username'] ?? ('ID '.$user_id));
    echo htmlspecialchars($label);
  ?></h1>

  <!-- KPI -->
  <div class="kpi"><strong>Totale righe:</strong> <?php echo (int)$total; ?></div>
  <div class="kpi"><strong>Somma importi:</strong>
    <span class="amount <?php echo $sumAmount >= 0 ? 'pos' : 'neg'; ?>">
      <?php echo number_format($sumAmount, 2, ',', '.'); ?>
    </span>
  </div>

  <!-- Filtri -->
  <form class="filters" method="get" action="/admin/movimenti.php">
    <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>">
    <div>
      <label>Tipo</label>
      <select name="type">
        <option value="">Tutti</option>
        <?php foreach ($allowedTypes as $t): ?>
          <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($type===$t?'selected':''); ?>>
            <?php echo htmlspecialchars($t); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Torneo</label>
      <select name="tournament_id">
        <option value="0">Tutti</option>
        <?php foreach ($tornei as $tt): ?>
          <option value="<?php echo (int)$tt['id']; ?>" <?php echo ($tournament_id==(int)$tt['id']?'selected':''); ?>>
            <?php
              $tc = $tt['tournament_code'] ?: sprintf('%05d',(int)$tt['id']);
              echo htmlspecialchars($tc.' — '.$tt['name']);
            ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Dal</label>
      <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
    </div>
    <div>
      <label>Al</label>
      <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
    </div>
    <div>
      <button class="btn" type="submit">Applica filtri</button>
    </div>
  </form>

  <!-- Tabella movimenti -->
  <?php if (empty($rows)): ?>
    <div class="kpi" style="background:#0f1114;">Nessun movimento trovato con questi filtri.</div>
  <?php else: ?>
    <table class="mov">
      <thead>
        <tr>
          <th>Data</th>
          <th>Tipo</th>
          <th>Importo</th>
          <th>Torneo</th>
          <th>Codice</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at']))); ?></td>
          <td><span class="badge-small badge-type"><?php echo htmlspecialchars($r['type']); ?></span></td>
          <td>
            <?php
              $cls = ((float)$r['amount'] >= 0) ? 'pos' : 'neg';
              echo '<span class="amount '.$cls.'">'.number_format((float)$r['amount'], 2, ',', '.').'</span>';
            ?>
          </td>
          <td>
            <?php
              if (!empty($r['tournament_id'])) {
                $tc = $r['t_code'] ?: sprintf('%05d',(int)$r['tournament_id']);
                $tn = $r['t_name'] ?: '—';
                echo '<span class="t-code">#'.htmlspecialchars($tc).'</span> — '.htmlspecialchars($tn);
              } else {
                echo '—';
              }
            ?>
          </td>
          <td><?php echo htmlspecialchars($r['movement_code'] ?? ''); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Paginazione -->
    <div class="pag">
      <?php
        $pages = max(1, (int)ceil($total / $perPage));
        for ($p=1; $p <= $pages; $p++):
          $cls = ($p === $page) ? 'on' : '';
          $url = '/admin/movimenti.php?' . http_build_query([
            'user_id' => $user_id,
            'type'    => $type,
            'tournament_id' => $tournament_id,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'page'      => $p
          ]);
      ?>
        <a class="<?php echo $cls; ?>" href="<?php echo $url; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer.php'; ?>
</body>
</html>
