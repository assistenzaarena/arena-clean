<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Calcolo la root del progetto: /var/www/html
$ROOT = dirname(__DIR__); // da /var/www/html/admin → /var/www/html

require_once $ROOT . '/src/guards.php';   // require_login(), require_admin()
require_admin();

require_once $ROOT . '/src/config.php';   // config generica
require_once $ROOT . '/src/db.php';       // connessione PDO

$competitions = require $ROOT . '/config/competitions.php'; // mappa competizioni

// [CSRF] token anti-forgery
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$flash = null;           // messaggi una tantum
$errors = [];            // errori validazione

// ---------- POST: crea torneo ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $posted_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
        http_response_code(400);
        die('CSRF non valido');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        // [INPUT] dai campi del form
        $comp_key  = $_POST['competition'] ?? '';           // chiave competizione (select)
        $season    = trim($_POST['season'] ?? '');          // stagione
        $name      = trim($_POST['name'] ?? '');            // nome torneo

        $cost_per_life      = $_POST['cost_per_life'] ?? '';
        $max_slots          = $_POST['max_slots'] ?? '';
        $max_lives_per_user = $_POST['max_lives_per_user'] ?? '';
        $guaranteed_prize   = $_POST['guaranteed_prize'] ?? '';
        $prize_percent      = $_POST['prize_percent'] ?? '';
        $rake_percent       = $_POST['rake_percent'] ?? '';

        // Questi due sono mutuamente esclusivi in base alla competizione scelta
        $matchday    = $_POST['matchday']    ?? '';         // per round_type = matchday
        $round_label = trim($_POST['round_label'] ?? '');   // per round_type = round_label

        // [VALIDAZIONI] competizione / stagioni / economie
        if ($name === '') { $errors[] = 'Nome torneo obbligatorio.'; }

        if ($comp_key === '' || !isset($competitions[$comp_key])) {
            $errors[] = 'Competizione obbligatoria.';
        } else {
            $comp = $competitions[$comp_key]; // record competizione dalla mappa
        }

        if ($season === '') { $errors[] = 'Stagione obbligatoria.'; }

        if ($cost_per_life === '' || !is_numeric($cost_per_life) || $cost_per_life < 0) {
            $errors[] = 'Costo per vita non valido.';
        }
        if ($max_slots === '' || !ctype_digit((string)$max_slots) || (int)$max_slots < 1) {
            $errors[] = 'Posti disponibili non validi.';
        }
        if ($max_lives_per_user === '' || !ctype_digit((string)$max_lives_per_user) || (int)$max_lives_per_user < 1) {
            $errors[] = 'Vite massime per utente non valide.';
        }
        if ($prize_percent === '' || !ctype_digit((string)$prize_percent)) {
            $errors[] = 'Percentuale montepremi non valida.';
        }
        if ($rake_percent === '' || !ctype_digit((string)$rake_percent)) {
            $errors[] = 'Percentuale rake non valida.';
        }
        if ($prize_percent !== '' && $rake_percent !== '' && ((int)$prize_percent + (int)$rake_percent !== 100)) {
            $errors[] = 'Prize% + Rake% devono sommare 100.';
        }
        if ($guaranteed_prize !== '' && (!is_numeric($guaranteed_prize) || $guaranteed_prize < 0)) {
            $errors[] = 'Montepremi garantito non valido.';
        }

        // [VALIDAZIONE] campi “round” in base al tipo competizione
        if (empty($errors) && isset($comp)) {
            if ($comp['round_type'] === 'matchday') {
                if ($matchday === '' || !ctype_digit((string)$matchday) || (int)$matchday < 1) {
                    $errors[] = 'Giornata obbligatoria (numero).';
                }
                // per coerenza azzeriamo round_label
                $round_label = null;
            } else { // round_label
                if ($round_label === '') {
                    $errors[] = 'Round obbligatorio (es. "Group A - 2", "Round of 16").';
                }
                // per coerenza azzeriamo matchday
                $matchday = null;
            }
        }

        // [INSERT] se tutto ok, scrivo il torneo (stato iniziale: 'draft')
        if (!$errors) {
            $sql = "INSERT INTO tournaments
                    (name, cost_per_life, max_slots, max_lives_per_user, guaranteed_prize,
                     prize_percent, rake_percent,
                     league_id, league_name, season, round_type, matchday, round_label, status,
                     created_at, updated_at)
                    VALUES
                    (:name, :cpl, :slots, :mlu, :gp, :pp, :rp,
                     :lid, :lname, :season, :rtype, :mday, :rlabel, 'draft',
                     NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name'   => $name,
                ':cpl'    => (float)$cost_per_life,
                ':slots'  => (int)$max_slots,
                ':mlu'    => (int)$max_lives_per_user,
                ':gp'     => ($guaranteed_prize === '' ? null : (float)$guaranteed_prize),
                ':pp'     => (int)$prize_percent,
                ':rp'     => (int)$rake_percent,

                // dati competizione dalla mappa
                ':lid'    => (int)$comp['league_id'],
                ':lname'  => $comp['name'],

                ':season' => $season,
                ':rtype'  => $comp['round_type'],  // 'matchday' | 'round_label'
                ':mday'   => ($matchday === '' ? null : (int)$matchday),
                ':rlabel' => ($round_label === '' ? null : $round_label),
            ]);

            $_SESSION['flash'] = 'Torneo creato (bozza).';
            header('Location: /admin/crea_torneo.php'); exit; // PRG redirect
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Crea Tornei</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/crea_torneo.css"><!-- stile dedicato Step 1 -->
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?><!-- header admin -->

<main class="admin-wrap">
  <h1 class="page-title">Crea Tornei</h1>

  <!-- messaggi -->
  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="err"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <!-- CARD: form di creazione -->
  <div class="card">
    <form method="post" action="" class="form">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="create">

      <div class="grid">
        <!-- Nome torneo -->
        <div class="field">
          <label for="name">Nome torneo</label>
          <input id="name" name="name" type="text" required>
        </div>

        <!-- Competizione (select dalla mappa) -->
        <div class="field">
          <label for="competition">Competizione</label>
          <select id="competition" name="competition" required>
            <option value="">— Seleziona competizione —</option>
            <?php foreach ($competitions as $key => $c): ?>
              <option value="<?php echo htmlspecialchars($key); ?>"
                      data-round-type="<?php echo htmlspecialchars($c['round_type']); ?>"
                      data-default-season="<?php echo htmlspecialchars($c['default_season']); ?>">
                <?php echo htmlspecialchars($c['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Stagione -->
        <div class="field">
          <label for="season">Stagione</label>
          <input id="season" name="season" type="text" placeholder="2024/2025" required>
          <small class="hint">Puoi cambiare la stagione proposta se necessario.</small>
        </div>

        <!-- Giornata (solo per round_type = matchday) -->
        <div class="field" id="wrap-matchday" style="display:none;">
          <label for="matchday">Giornata</label>
          <input id="matchday" name="matchday" type="number" min="1">
        </div>

        <!-- Round label (solo per round_type = round_label) -->
        <div class="field" id="wrap-roundlabel" style="display:none;">
          <label for="round_label">Round</label>
          <input id="round_label" name="round_label" type="text" placeholder="Es. Group A - 2 / Round of 16">
        </div>

        <!-- Economia -->
        <div class="field">
          <label for="cpl">Costo per vita (€)</label>
          <input id="cpl" name="cost_per_life" type="number" step="0.01" min="0" required>
        </div>
        <div class="field">
          <label for="slots">Posti disponibili</label>
          <input id="slots" name="max_slots" type="number" min="1" required>
        </div>
        <div class="field">
          <label for="mlu">Vite max per utente</label>
          <input id="mlu" name="max_lives_per_user" type="number" min="1" required>
        </div>
        <div class="field">
          <label for="gp">Montepremi garantito (opz.)</label>
          <input id="gp" name="guaranteed_prize" type="number" step="0.01" min="0">
        </div>
        <div class="field">
          <label for="pp">% a montepremi</label>
          <input id="pp" name="prize_percent" type="number" min="0" max="100" required>
        </div>
        <div class="field">
          <label for="rp">% rake sito</label>
          <input id="rp" name="rake_percent" type="number" min="0" max="100" required>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit">Salva torneo</button>
        <a class="btn" href="/admin/crea_torneo.php">Annulla</a>
      </div>
    </form>
  </div>
</main>

<!-- JS minimo: imposta stagione di default e mostra il giusto campo round -->
<script>
(function () {
  const sel = document.getElementById('competition');   // select competizione
  const season = document.getElementById('season');     // input stagione
  const wrapMD = document.getElementById('wrap-matchday');
  const wrapRL = document.getElementById('wrap-roundlabel');
  const md = document.getElementById('matchday');
  const rl = document.getElementById('round_label');

  function applyCompUI() {
    const opt = sel.options[sel.selectedIndex];
    const rtype = opt.getAttribute('data-round-type') || '';
    const defSeas = opt.getAttribute('data-default-season') || '';
    if (defSeas && !season.value) season.value = defSeas; // precompila stagione se vuota

    if (rtype === 'matchday') {
      wrapMD.style.display = '';
      wrapRL.style.display = 'none';
      rl.value = '';
      md.required = true;
      rl.required = false;
    } else if (rtype === 'round_label') {
      wrapMD.style.display = 'none';
      wrapRL.style.display = '';
      md.value = '';
      md.required = false;
      rl.required = true;
    } else {
      // nulla selezionato
      wrapMD.style.display = 'none';
      wrapRL.style.display = 'none';
      md.required = false;
      rl.required = false;
    }
  }

  sel.addEventListener('change', applyCompUI);
  // Prima inizializzazione (se ricarichi con select vuota non fa nulla)
  applyCompUI();
})();
</script>

</body>
</html>
