<?php
/**
 * public/admin/publish_torneo.php
 *
 * SCOPO: Endpoint ADMIN per pubblicare un torneo (stato: draft -> open).
 * USO:   /admin/publish_torneo.php?id=123
 * FLOW:  GET  -> mostra conferma
 *        POST -> se confermato e stato coerente, aggiorna a 'open' (visibile agli utenti)
 *
 * NOTE:  Questo micro-step NON tocca la lobby utenti; serve solo a cambiare lo stato.
 *        La Lobby la facciamo nello step successivo.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__);               // /var/www/html
require_once $ROOT . '/src/guards.php'; // permessi
require_admin();

require_once $ROOT . '/src/config.php'; // config/env
require_once $ROOT . '/src/db.php';     // $pdo connessione

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// --- Leggo ID torneo dalla querystring ---
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  die('ID torneo mancante.');
}

// --- Carico il torneo ---
$q = $pdo->prepare("SELECT id, name, status, league_name, season, round_type, matchday, round_label 
                    FROM tournaments WHERE id = :id LIMIT 1");
$q->execute([':id' => $id]);
$t = $q->fetch(PDO::FETCH_ASSOC);

if (!$t) {
  http_response_code(404);
  die('Torneo non trovato.');
}

// --- Regola: si può pubblicare SOLO se è draft (bozza) ---
if ($t['status'] !== 'draft') {
  $_SESSION['flash'] = "Il torneo #{$t['id']} non è in bozza (stato: {$t['status']}).";
  header('Location: /admin/crea_torneo.php'); exit;
}

// --- POST = conferma pubblicazione ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
    http_response_code(400); die('CSRF non valido');
  }

  // Aggiorno stato a 'open'
  $u = $pdo->prepare("UPDATE tournaments SET status = 'open', updated_at = NOW() WHERE id = :id AND status = 'draft'");
  $u->execute([':id' => $t['id']]);

  // Per robustezza, verifico che 1 riga sia stata aggiornata
  if ($u->rowCount() === 1) {
    $_SESSION['flash'] = "Torneo #{$t['id']} pubblicato (stato: open).";
  } else {
    $_SESSION['flash'] = "Pubblicazione non eseguita: stato già cambiato o torneo inesistente.";
  }

  // PRG redirect
  header('Location: /admin/crea_torneo.php'); exit;
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Pubblica torneo #<?php echo (int)$t['id']; ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width: 900px; margin: 24px auto; padding: 0 16px; color:#fff;}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .meta{font-size:14px;color:#c9c9c9}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    .btn-primary{background:#00c074;border-color:#00c074}
  </style>
</head>
<body>

<?php require $ROOT . '/header_admin.php'; ?>

<div class="wrap">
  <h1>Pubblica torneo</h1>

  <div class="card">
    <div class="meta">
      <div><b>ID:</b> <?php echo (int)$t['id']; ?></div>
      <div><b>Nome:</b> <?php echo htmlspecialchars($t['name']); ?></div>
      <div><b>Competizione:</b> <?php echo htmlspecialchars($t['league_name']); ?> — <b>Stagione:</b> <?php echo htmlspecialchars($t['season']); ?></div>
      <div><b>Round:</b>
        <?php if ($t['round_type']==='matchday'): ?>
          Giornata <?php echo (int)$t['matchday']; ?>
        <?php else: ?>
          <?php echo htmlspecialchars($t['round_label']); ?>
        <?php endif; ?>
      </div>
      <div><b>Stato attuale:</b> <?php echo htmlspecialchars($t['status']); ?></div>
    </div>
  </div>

  <div class="card">
    <p>Vuoi pubblicare questo torneo? Diventerà visibile come <b>“in partenza”</b> (stato: <code>open</code>), e verrà bloccata l’eventuale disiscrizione al lock T-5’ dal primo kickoff.</p>

    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <button class="btn btn-primary" type="submit">Pubblica ora</button>
      <a class="btn" href="/admin/crea_torneo.php" style="margin-left:8px">Annulla</a>
    </form>
  </div>
</div>

</body>
</html>
