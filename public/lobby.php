<?php
/**
 * public/lobby.php
 *
 * SCOPO: mostra in sola lettura i tornei in stato 'open'.
 * - Card con: tournament_code (5 cifre), nome, competizione, stagione,
 *   costo vita, posti totali, vite vendute (placeholder 0), lock_at con countdown.
 * - Nessun bottone (iscrizioni arriveranno nello step 3C).
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__; // /var/www/html
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php'; // se vuoi controllare login utente

// Carico tornei open (pubblicati)
$sql = "
  SELECT
    id, tournament_code, name, league_name, season,
    cost_per_life, max_slots, lock_at, created_at, updated_at
  FROM tournaments
  WHERE status = 'open'
  ORDER BY created_at DESC
";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Lobby tornei</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_user.css"><!-- se hai un header utente -->
  <link rel="stylesheet" href="/assets/lobby.css">
</head>
<body>

<?php
// Se hai un header user già pronto:
$headerPath = $ROOT . '/header_user.php';
if (file_exists($headerPath)) { require $headerPath; }
?>

<main class="lobby-wrap">
  <h1 class="page-title">Tornei disponibili</h1>

  <?php if (empty($rows)): ?>
    <div class="muted">Nessun torneo pubblicato al momento.</div>
  <?php else: ?>
    <div class="cards">
      <?php foreach ($rows as $t): ?>
        <?php
          $code   = $t['tournament_code'] ?: sprintf('%05d', (int)$t['id']);
          $lockAt = $t['lock_at'] ? strtotime($t['lock_at']) : null;
        ?>
        <article class="card">
          <header class="card__head">
            <span class="code">#<?php echo htmlspecialchars($code); ?></span>
            <span class="badge badge--open">OPEN</span>
          </header>

          <h2 class="card__title"><?php echo htmlspecialchars($t['name']); ?></h2>
          <div class="meta">
            <div><?php echo htmlspecialchars($t['league_name']); ?> • Stagione <?php echo htmlspecialchars($t['season']); ?></div>
          </div>

          <dl class="grid">
            <div>
              <dt>Costo vita</dt>
              <dd>€ <?php echo number_format((float)$t['cost_per_life'], 2, ',', '.'); ?></dd>
            </div>
            <div>
              <dt>Posti totali</dt>
              <dd><?php echo (int)$t['max_slots']; ?></dd>
            </div>
            <div>
              <dt>Vite vendute</dt>
              <dd>0</dd><!-- placeholder: lo collegheremo in 3C -->
            </div>
            <div>
              <dt>Lock scelte</dt>
              <dd>
                <?php if ($lockAt): ?>
                  <time class="lock" datetime="<?php echo htmlspecialchars($t['lock_at']); ?>">
                    <?php echo date('d/m/Y H:i', $lockAt); ?>
                  </time>
                  <span class="countdown" data-due="<?php echo htmlspecialchars($t['lock_at']); ?>"></span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </dd>
            </div>
          </dl>

          <!-- Nessun bottone qui (arriveranno allo step 3C) -->
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<script>
// Countdown semplice per tutti gli elementi .countdown
(function(){
  function tick(node){
    var due = node.getAttribute('data-due');
    if(!due) return;
    var end = new Date(due.replace(' ', 'T')).getTime();
    var now = Date.now();
    var diff = end - now;
    if(diff <= 0){ node.textContent = 'CHIUSO'; return; }
    var s = Math.floor(diff/1000);
    var d = Math.floor(s/86400); s%=86400;
    var h = Math.floor(s/3600);  s%=3600;
    var m = Math.floor(s/60);    s%=60;
    node.textContent = (d>0? d+'g ':'') + (h+'h ') + (m+'m ') + (s+'s');
    requestAnimationFrame(function(){ setTimeout(function(){ tick(node); }, 1000); });
  }
  document.querySelectorAll('.countdown').forEach(tick);
})();
</script>

</body>
</html>
