<?php
/**
 * public/lobby.php
 * Lobby con due sezioni:
 * - I miei tornei (iscritto)
 * - Tornei in partenza (tutti gli open)
 * Solo lettura: nessun bottone (arriva nello step 3C).
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__; // /var/www/html
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';

// opzionale: obbliga login
require_login();

// utente loggato
$uid = (int)($_SESSION['user_id'] ?? 0);

// Tornei OPEN (tutti)
$all = $pdo->query("
  SELECT id, tournament_code, name, league_name, season,
         cost_per_life, max_slots, max_lives_per_user, guaranteed_prize, lock_at
  FROM tournaments
  WHERE status = 'open'
  ORDER BY lock_at IS NULL, lock_at ASC, created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// I miei tornei (se c'è la tabella iscrizioni). Non fallire se non esiste.
$my = [];
try {
  $q = $pdo->prepare("
    SELECT t.id, t.tournament_code, t.name, t.league_name, t.season,
           t.cost_per_life, t.max_slots, t.max_lives_per_user, t.guaranteed_prize, t.lock_at
    FROM tournaments t
    JOIN tournament_enrollments e ON e.tournament_id = t.id
    WHERE e.user_id = :uid AND t.status = 'open'
    ORDER BY t.lock_at IS NULL, t.lock_at ASC, t.created_at DESC
  ");
  $q->execute([':uid'=>$uid]);
  $my = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // tabella non esiste ancora → sezione "vuota"
  $my = [];
}
  <?php
function safeCode(array $t){
  return $t['tournament_code'] ?: sprintf('%05d',(int)$t['id']);
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Lobby tornei</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_user.css"><!-- se esiste -->
  <link rel="stylesheet" href="/assets/lobby.css?v=2">
</head>
<body>

<?php
$headerPath = $ROOT . '/header_user.php';
if (file_exists($headerPath)) { require $headerPath; }
?>

<main class="lobby-wrap lobby--decor">
  <h1 class="page-title">Lobby tornei</h1>

  <!-- Sezione: I miei tornei -->
  <section class="section">
    <div class="section__head">
      <h2>I miei tornei</h2>
    </div>
    <?php if (empty($my)): ?>
      <div class="muted">Non sei iscritto a nessun torneo.</div>
    <?php else: ?>
      <div class="cards cards--wide">
        <?php foreach ($my as $t): ?>
          <?php
            $code   = $t['tournament_code'] ?: sprintf('%05d', (int)$t['id']);
            $lockAt = $t['lock_at'] ? strtotime($t['lock_at']) : null;
          ?>
          <article class="card card--red" data-id="<?php echo (int)$t['id']; ?>">
            <header class="card__head">
              <span class="code">#<?php echo htmlspecialchars($code); ?></span>
              <span class="badge badge--open">ISCRITTO</span>
            </header>

            <h3 class="card__title"><?php echo htmlspecialchars($t['name']); ?></h3>
            <div class="meta"><?php echo htmlspecialchars($t['league_name']); ?> • Stagione <?php echo htmlspecialchars($t['season']); ?></div>

            <dl class="grid">
              <div><dt>Buy-in</dt><dd><?php echo number_format((float)$t['cost_per_life'], 0, ',', '.'); ?> crediti</dd></div>
              <div><dt>Posti</dt><dd><?php echo (int)$t['max_slots']; ?></dd></div>
              <div><dt>Vite max/utente</dt><dd><?php echo (int)$t['max_lives_per_user']; ?></dd></div>
              <div>
                <dt>Crediti in palio</dt>
                <dd>
                  <span class="pot">—</span>
                  <?php if (!empty($t['guaranteed_prize'])): ?>
                    <small class="guarantee"> (Garantiti: <?php echo number_format((float)$t['guaranteed_prize'], 0, ',', '.'); ?> crediti)</small>
                  <?php endif; ?>
                </dd>
              </div>
            </dl>

            <div class="card__footer">
              <?php if ($lockAt): ?>
                <div class="countdown-row">
                  <time class="lock" datetime="<?php echo htmlspecialchars($t['lock_at']); ?>">
                    <?php echo date('d/m/Y H:i', $lockAt); ?>
                  </time>
                  <span class="countdown" data-due="<?php echo htmlspecialchars($t['lock_at']); ?>"></span>
                </div>
              <?php else: ?>
                <div class="countdown-row">—</div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Sezione: Tornei in partenza -->
  <section class="section">
    <div class="section__head">
      <h2>Tornei in partenza</h2>
    </div>
    <?php if (empty($all)): ?>
      <div class="muted">Nessun torneo disponibile al momento.</div>
    <?php else: ?>
      <div class="cards cards--wide">
        <?php foreach ($all as $t): ?>
          <?php
            $code   = $t['tournament_code'] ?: sprintf('%05d', (int)$t['id']);
            $lockAt = $t['lock_at'] ? strtotime($t['lock_at']) : null;
          ?>
          <article class="card card--red" data-id="<?php echo (int)$t['id']; ?>">
            <header class="card__head">
              <span class="code">#<?php echo htmlspecialchars($code); ?></span>
              <span class="badge badge--open">OPEN</span>
            </header>

            <h3 class="card__title"><?php echo htmlspecialchars($t['name']); ?></h3>
            <div class="meta"><?php echo htmlspecialchars($t['league_name']); ?> • Stagione <?php echo htmlspecialchars($t['season']); ?></div>

            <dl class="grid">
              <div><dt>Buy-in</dt><dd><?php echo number_format((float)$t['cost_per_life'], 0, ',', '.'); ?> crediti</dd></div>
              <div><dt>Posti</dt><dd><?php echo (int)$t['max_slots']; ?></dd></div>
              <div><dt>Vite max/utente</dt><dd><?php echo (int)$t['max_lives_per_user']; ?></dd></div>
              <div>
                <dt>Crediti in palio</dt>
                <dd>
                  <span class="pot">—</span>
                  <?php if (!empty($t['guaranteed_prize'])): ?>
                    <small class="guarantee"> (Garantiti: <?php echo number_format((float)$t['guaranteed_prize'], 0, ',', '.'); ?> crediti)</small>
                  <?php endif; ?>
                </dd>
              </div>
            </dl>

            <div class="card__footer">
              <?php if ($lockAt): ?>
                <div class="countdown-row">
                  <time class="lock" datetime="<?php echo htmlspecialchars($t['lock_at']); ?>">
                    <?php echo date('d/m/Y H:i', $lockAt); ?>
                  </time>
                  <span class="countdown" data-due="<?php echo htmlspecialchars($t['lock_at']); ?>"></span>
                </div>
              <?php else: ?>
                <div class="countdown-row">—</div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<script>
// Countdown semplice
(function(){
  function tick(el){
    var due = el.getAttribute('data-due'); if(!due) return;
    var end = new Date(due.replace(' ', 'T')).getTime();
    var now = Date.now(); var diff = end - now;
    if(diff <= 0){ el.textContent = 'CHIUSO'; return; }
    var s = Math.floor(diff/1000), d = Math.floor(s/86400); s%=86400;
    var h = Math.floor(s/3600); s%=3600; var m = Math.floor(s/60); s%=60;
    el.textContent = (d>0? d+'g ':'') + h+'h ' + m+'m ' + s+'s';
    setTimeout(function(){ tick(el); }, 1000);
  }
  document.querySelectorAll('.countdown').forEach(tick);
})();

// Aggiorna "Crediti in palio" ogni 10s
(function(){
  function updatePot(card){
    var id = card.getAttribute('data-id');
    if(!id) return;
    fetch('/api/tournament_stats.php?id='+id)
      .then(r => r.ok ? r.json() : null)
      .then(js => {
        if(!js || !js.ok) return;
        var el = card.querySelector('.pot');
        if(el) el.textContent = (js.pot || 0).toLocaleString('it-IT') + ' crediti';
      })
      .catch(()=>{});
  }
  function loop(){
    document.querySelectorAll('article.card[data-id]').forEach(updatePot);
    setTimeout(loop, 10000);
  }
  loop();
})();
</script>

</body>
</html>
