<?php
/**
 * public/torneo.php
 * Pagina singolo torneo (versione base con pulsante Disiscriviti).
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__; // /var/www/html/public
require_once dirname($ROOT) . '/src/config.php';
require_once dirname($ROOT) . '/src/db.php';
require_once dirname($ROOT) . '/src/guards.php';

// L’utente deve essere loggato
require_login();
$uid = (int)($_SESSION['user_id'] ?? 0);

// Torneo id
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); die('ID torneo mancante'); }

// Carico dati torneo
$q = $pdo->prepare("SELECT * FROM tournaments WHERE id=:id LIMIT 1");
$q->execute([':id'=>$id]);
$torneo = $q->fetch(PDO::FETCH_ASSOC);

if (!$torneo) { http_response_code(404); die('Torneo non trovato'); }

// Controllo iscrizione utente
$enrolled = false;
try {
  $chk = $pdo->prepare("SELECT 1 FROM tournament_enrollments WHERE tournament_id=:tid AND user_id=:uid LIMIT 1");
  $chk->execute([':tid'=>$id, ':uid'=>$uid]);
  $enrolled = (bool)$chk->fetchColumn();
} catch (Throwable $e) {
  $enrolled = false;
}

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Torneo #<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',$id)); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_user.css">
  <link rel="stylesheet" href="/assets/lobby.css"><!-- riuso stile card -->
  <style>
    .torneo-wrap{max-width:1000px; margin:20px auto; padding:0 16px; color:#fff; position:relative;}
    .torneo-head{display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;}
    .torneo-title{font-size:24px; font-weight:900;}
    .btn{display:inline-block; padding:6px 12px; border-radius:6px; font-weight:700; cursor:pointer; text-decoration:none;}
    .btn--warn{background:#e62329; border:1px solid #e62329; color:#fff;}
    .btn--warn:hover{background:#c01c21;}
  </style>
</head>
<body>

<?php require $ROOT . '/header_user.php'; ?>

<main class="torneo-wrap">
  <div class="torneo-head">
    <h1 class="torneo-title">
      Torneo <?php echo htmlspecialchars($torneo['name']); ?>
      <small style="font-size:14px; color:#aaa;">#<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',$id)); ?></small>
    </h1>

    <?php if ($enrolled): ?>
      <!-- Pulsante Disiscriviti -->
      <form method="post" action="/api/unenroll.php" 
            onsubmit="return confirm('Vuoi davvero disiscriverti da questo torneo?');">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
        <input type="hidden" name="tournament_id" value="<?php echo (int)$id; ?>">
        <button class="btn btn--warn" type="submit">Disiscriviti</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Info base torneo -->
  <section class="card card--ps">
    <h3 class="card__title"><?php echo htmlspecialchars($torneo['league_name']); ?> • Stagione <?php echo htmlspecialchars($torneo['season']); ?></h3>
    <dl class="grid">
      <div><dt>Buy-in</dt><dd><?php echo (int)$torneo['cost_per_life']; ?> crediti</dd></div>
      <div><dt>Posti disp.</dt><dd><?php echo (int)$torneo['max_slots']; ?></dd></div>
      <div><dt>Vite max/utente</dt><dd><?php echo (int)$torneo['max_lives_per_user']; ?></dd></div>
      <div><dt>Montepremi garantito</dt><dd><?php echo $torneo['guaranteed_prize'] ? (int)$torneo['guaranteed_prize'].' crediti' : '—'; ?></dd></div>
    </dl>
  </section>

  <!-- Placeholder eventi torneo -->
  <section style="margin-top:20px;">
    <h2>Eventi del torneo</h2>
    <div class="muted">Qui mostreremo le partite/round (Step successivi).</div>
  </section>
</main>

<?php require $ROOT . '/footer.php'; ?>

</body>
</html>
