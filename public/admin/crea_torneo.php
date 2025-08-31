<?php
/**
 * public/admin/crea_torneo.php
 *
 * SCOPO: SOLO creazione tornei (nessuna gestione). Accesso riservato all'admin.
 * STEP 1: select competizioni (da mappa), stagione e matchday/round obbligatori.
 * NESSUNA chiamata API in questo step (arriverà nello Step 2).
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* ------------------------------------------------------------------
   ROOT DEL PROGETTO (DOCROOT) = /var/www/html
   Da /public/admin risalgo di 1 livello: dirname(__DIR__) = /var/www/html
   ------------------------------------------------------------------ */
$ROOT = dirname(__DIR__);  // /var/www/html

/* ----------------- include di sicurezza ----------------- */
require_once $ROOT . '/src/guards.php';   // require_login(), require_admin()
require_admin();                          // blocca non-admin

require_once $ROOT . '/src/config.php';   // config generica / env
require_once $ROOT . '/src/db.php';       // connessione PDO ($pdo)

/* ------------------------------------------------------------------
   Mappa competizioni: POSIZIONA competitions.php in /src/config/ !
   Percorso: /var/www/html/src/config/competitions.php
   ------------------------------------------------------------------ */
$competitions = require $ROOT . '/src/config/competitions.php'; // <-- QUI
// Se vuoi debug veloce: uncomment
// if (!file_exists($ROOT . '/src/config/competitions.php')) { die('competitions.php non trovato!'); }

/* ----------------- CSRF ----------------- */
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$flash  = null;
$errors = [];

/* ================================================================
   POST HANDLER: CREA TORNEO (nessuna API/fetch in questo step)
   ================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    $posted_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
        http_response_code(400);
        die('CSRF non valido');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        // ---- INPUT ----
        $comp_key  = $_POST['competition'] ?? '';
        $season    = trim($_POST['season'] ?? '');
        $name      = trim($_POST['name'] ?? '');

        $cost_per_life      = $_POST['cost_per_life'] ?? '';
        $max_slots          = $_POST['max_slots'] ?? '';
        $max_lives_per_user = $_POST['max_lives_per_user'] ?? '';
        $guaranteed_prize   = $_POST['guaranteed_prize'] ?? '';
        $prize_percent      = $_POST['prize_percent'] ?? '';
        $rake_percent       = $_POST['rake_percent'] ?? '';

        // Campi “round”: uno dei due, a seconda della competizione
        $matchday    = $_POST['matchday']    ?? '';
        $round_label = trim($_POST['round_label'] ?? '');

        // ---- VALIDAZIONI BASE ----
        if ($name === '') { $errors[] = 'Nome torneo obbligatorio.'; }

        if ($comp_key === '' || !isset($competitions[$comp_key])) {
            $errors[] = 'Competizione obbligatoria.';
        } else {
            $comp = $competitions[$comp_key]; // record competizione dalla mappa
        }

        if ($season === '') { $errors[] = 'Stagione obbligatoria.'; }

        // Economia
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
        if ($prize_percent !== '' && $rake_percent !== '' &&
            ((int)$prize_percent + (int)$rake_percent !== 100)) {
            $errors[] = 'Prize% + Rake% devono sommare 100.';
        }
        if ($guaranteed_prize !== '' && (!is_numeric($guaranteed_prize) || $guaranteed_prize < 0)) {
            $errors[] = 'Montepremi garantito non valido.';
        }

        // ---- VALIDAZIONE round in base al tipo competizione ----
        if (empty($errors) && isset($comp)) {
            if ($comp['round_type'] === 'matchday') {
                if ($matchday === '' || !ctype_digit((string)$matchday) || (int)$matchday < 1) {
                    $errors[] = 'Giornata obbligatoria (numero).';
                }
                $round_label = null; // coerente
            } else { // 'round_label'
                if ($round_label === '') {
                    $errors[] = 'Round obbligatorio (es. "Group A - 2", "Round of 16").';
                }
                $matchday = null; // coerente
            }
        }

        // ---- INSERT DB (stato iniziale: draft) ----
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

                ':lid'    => (int)$comp['league_id'],
                ':lname'  => $comp['name'],

                ':season' => $season,
                ':rtype'  => $comp['round_type'],      // 'matchday' | 'round_label'
                ':mday'   => ($matchday === '' ? null : (int)$matchday),
                ':rlabel' => ($round_label === '' ? null : $round_label),
            ]);

                // =========================
    // [Step 2B] FETCH FIXTURES & STATO (pending/draft)
    // =========================
    require_once $ROOT . '/src/services/football_api.php'; // wrapper API

    // Recupero l'ID auto-increment del torneo appena creato
    $tournament_id = (int)$pdo->lastInsertId();

    // Determino tipo round e parametri
    $roundType = $comp['round_type']; // 'matchday' | 'round_label'
    $fixturesResp = null;
    $fixturesMin  = [];
    $complete     = false;
    $incompleteReason = '';

    try {
        if ($roundType === 'matchday') {
            // atteso per completezza
            $expected = $comp['expected_matches_per_matchday'] ?? null;
            // ATTENZIONE: molte leghe usano label "Regular Season - {N}" per il round
            $fixturesResp = fb_fixtures_matchday((int)$comp['league_id'], $season, (int)$matchday, 'Regular Season - %d');
            if (!$fixturesResp['ok']) {
                $incompleteReason = 'Errore API: '.$fixturesResp['error'].' (HTTP '.$fixturesResp['status'].')';
            } else {
                $fixturesMin = fb_extract_fixtures_minimal($fixturesResp['data']);
                $count = count($fixturesMin);
                $complete = ($expected !== null) ? ($count === (int)$expected) : ($count > 0);
                if (!$complete && $expected !== null) {
                    $incompleteReason = "Trovate $count partite su $expected.";
                }
            }
        } else {
            // round_label: consideriamo completo se >0 fixtures (soglia semplice per 2B)
            $fixturesResp = fb_fixtures_round_label((int)$comp['league_id'], $season, $round_label);
            if (!$fixturesResp['ok']) {
                $incompleteReason = 'Errore API: '.$fixturesResp['error'].' (HTTP '.$fixturesResp['status'].')';
            } else {
                $fixturesMin = fb_extract_fixtures_minimal($fixturesResp['data']);
                $complete = (count($fixturesMin) > 0);
                if (!$complete) { $incompleteReason = 'Nessuna partita trovata per il round selezionato.'; }
            }
        }
    } catch (Throwable $e) {
        $fixturesResp = null;
        $fixturesMin  = [];
        $complete     = false;
        $incompleteReason = 'Eccezione fetch: '.$e->getMessage();
    }

    // Aggiorno lo status in base alla completezza
    if ($complete) {
        $pdo->prepare("UPDATE tournaments SET status = 'draft' WHERE id = :id")->execute([':id'=>$tournament_id]);
        $_SESSION['flash'] = 'Torneo creato (bozza). Fixtures completi. Pronto alla pubblicazione.';
        header('Location: /admin/crea_torneo.php'); exit;
    } else {
        // (opzionale) potresti salvare anche la "nota" se hai una colonna a disposizione
        $pdo->prepare("UPDATE tournaments SET status = 'pending' WHERE id = :id")->execute([':id'=>$tournament_id]);
        $_SESSION['flash'] = 'Torneo in pending: '.$incompleteReason;
        header('Location: /admin/torneo_pending.php?id='.$tournament_id); exit;
    }
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

<?php require $ROOT . '/header_admin.php'; ?><!-- barra admin -->

<main class="admin-wrap">
  <h1 class="page-title">Crea Tornei</h1>

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

        <!-- Competizione -->
        <div class="field">
          <label for="competition">Competizione</label>
          <select id="competition" name="competition" required>
            <option value="">— Seleziona —</option>
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
          <small class="hint">Precompilata dalla competizione, modificabile.</small>
        </div>

        <!-- Giornata (matchday) -->
        <div class="field" id="wrap-matchday" style="display:none;">
          <label for="matchday">Giornata</label>
          <input id="matchday" name="matchday" type="number" min="1">
        </div>

        <!-- Round label (coppe) -->
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

<!-- JS: imposta stagione di default e mostra matchday/round corretti -->
<script>
(function () {
  const sel = document.getElementById('competition');
  const season = document.getElementById('season');
  const wrapMD = document.getElementById('wrap-matchday');
  const wrapRL = document.getElementById('wrap-roundlabel');
  const md = document.getElementById('matchday');
  const rl = document.getElementById('round_label');

  function applyCompUI() {
    const opt = sel.options[sel.selectedIndex];
    const rtype = opt ? (opt.getAttribute('data-round-type') || '') : '';
    const defSeas = opt ? (opt.getAttribute('data-default-season') || '') : '';

    if (defSeas && !season.value) season.value = defSeas;

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
      wrapMD.style.display = 'none';
      wrapRL.style.display = 'none';
      md.required = false;
      rl.required = false;
    }
  }

  sel.addEventListener('change', applyCompUI);
  applyCompUI(); // init
})();
</script>

</body>
</html>
