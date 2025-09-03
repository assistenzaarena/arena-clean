<?php
// /admin/premio_dettaglio.php — Dettaglio singola richiesta + azioni admin
require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); die('ID richiesta mancante'); }

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// carica richiesta + utente
$st = $pdo->prepare("
  SELECT r.*, u.username, u.nome, u.cognome, u.email, u.phone, u.crediti AS user_credits
  FROM admin_prize_requests r
  JOIN utenti u ON u.id = r.user_id
  WHERE r.id = :id
  LIMIT 1
");
$st->execute([':id'=>$id]);
$r = $st->fetch(PDO::FETCH_ASSOC);
if (!$r) { http_response_code(404); die('Richiesta non trovata'); }
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Dettaglio premio #<?php echo (int)$id; ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/admin_extra.css">
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-narrow">
  <div class="hstack">
    <h1 style="margin:0;">Dettaglio richiesta #<?php echo (int)$r['id']; ?></h1>
    <span class="badge <?php
      echo $r['status']==='pending'  ? 'sand' :
           ($r['status']==='approved' ? 'green' :
           ($r['status']==='fulfilled' ? 'green' : 'red'));
    ?>"><?php echo htmlspecialchars($r['status']); ?></span>
    <span class="spacer"></span>
    <a class="btn btn-ghost" href="/admin/premi_richiesti.php">Torna all’elenco</a>
  </div>

  <div class="grid two">
    <div class="card">
      <h3 style="margin:0 0 8px;">Utente</h3>
      <div><strong><?php echo htmlspecialchars($r['username']); ?></strong></div>
      <div class="muted"><?php echo htmlspecialchars(trim(($r['cognome']??'').' '.($r['nome']??''))); ?></div>
      <div class="muted"><?php echo htmlspecialchars($r['email']); ?> · <?php echo htmlspecialchars($r['phone']); ?></div>
      <div style="margin-top:8px;"><strong>Crediti attuali:</strong> <?php echo number_format((float)$r['user_credits'],2,',','.'); ?></div>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px;">Richiesta</h3>
      <div><strong>Premio:</strong> <?php echo htmlspecialchars($r['requested_item']); ?></div>
      <div><strong>Crediti richiesti:</strong> <?php echo number_format((float)$r['credits_cost'],2,',','.'); ?></div>
      <div><strong>Richiesta il:</strong> <?php echo date('d/m/Y H:i', strtotime($r['requested_at'])); ?></div>
      <?php if (!empty($r['processed_at'])): ?>
        <div><strong>Processata il:</strong> <?php echo date('d/m/Y H:i', strtotime($r['processed_at'])); ?></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px;">Spedizione</h3>
      <div><strong>Destinatario:</strong> <?php echo htmlspecialchars($r['shipping_name'] ?? '—'); ?></div>
      <div><strong>Indirizzo:</strong><br><?php echo nl2br(htmlspecialchars($r['shipping_address'] ?? '—')); ?></div>
      <div><strong>Note:</strong><br><?php echo nl2br(htmlspecialchars($r['shipping_note'] ?? '—')); ?></div>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px;">Azioni</h3>
      <form method="post" action="/admin/api_premi.php" class="hstack" style="gap:8px;">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="id"   value="<?php echo (int)$r['id']; ?>">

        <?php if ($r['status']==='pending'): ?>
          <button class="btn btn-ok"     name="action" value="approve" onclick="return confirm('Confermi approvazione?');">Approva</button>
          <button class="btn btn-danger" name="action" value="reject"  onclick="return confirm('Confermi rifiuto?');">Rifiuta</button>
        <?php elseif ($r['status']==='approved'): ?>
          <button class="btn btn-ok"     name="action" value="fulfill" onclick="return confirm('Confermi evasione/riscossione del premio?');">Segna come evaso</button>
          <button class="btn btn-danger" name="action" value="reject"  onclick="return confirm('Confermi rifiuto?');">Rifiuta</button>
        <?php else: ?>
          <span class="muted">Richiesta già processata.</span>
        <?php endif; ?>
      </form>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../footer.php'; ?>
</body>
</html>
