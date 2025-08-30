<?php
// ============================================================================
// admin/crea_torneo.php  —  Pagina SOLO-ADMIN per CREARE tornei (no gestione).
// ============================================================================

// [SESSIONE] Se la sessione non è attiva, la avvio (serve per $_SESSION e CSRF).
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// [GUARDIE] Importo le protezioni e consento l’accesso solo a utenti admin.
require_once __DIR__ . '/../src/guards.php';  // contiene require_login(), require_admin()
require_admin();                               // blocca e redirige se non è admin

// [CONFIG + DB] Costanti di configurazione e connessione PDO ($pdo).
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

// [CSRF] Se il token non esiste, lo genero. Lo useremo sui form POST.
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); } // token 128-bit
$csrf = $_SESSION['csrf'];

// [STATE] Variabili per comunicazioni all’utente.
$flash  = null;   // messaggio “una tantum” (success/warn) mostrato dopo il redirect
$errors = [];     // array di errori validazione

// ============================================================================
// POST HANDLER — Creo un torneo (PRG: Post/Redirect/Get)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // [CSRF CHECK] Confronto il token inviato nel form con quello in sessione.
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
    http_response_code(400);
    die('CSRF non valido');
  }

  // [AZIONE] In questa pagina gestiamo UNA sola azione: "create".
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {

    // ---------------------------
    // 1) RACCOLTA INPUT DAL FORM
    // ---------------------------
    $name               = trim($_POST['name'] ?? '');              // nome torneo
    $cost_per_life      = $_POST['cost_per_life'] ?? '';           // costo per vita
    $max_slots          = $_POST['max_slots'] ?? '';               // posti disponibili
    $max_lives_per_user = $_POST['max_lives_per_user'] ?? '';      // vite massime per utente
    $guaranteed_prize   = $_POST['guaranteed_prize'] ?? '';        // montepremi garantito (opz.)
    $prize_percent      = $_POST['prize_percent'] ?? '';           // % crediti → montepremi
    $rake_percent       = $_POST['rake_percent'] ?? '';            // % crediti → rake sito
    $league_id          = $_POST['league_id'] ?? '';               // ID competizione (API-FOOTBALL)
    $league_name        = trim($_POST['league_name'] ?? '');       // nome leggibile competizione (opz.)
    $season             = trim($_POST['season'] ?? '');            // es. 2024

    // ---------------------------
    // 2) VALIDAZIONE SERVER-SIDE
    // ---------------------------
    if ($name === '')                                           { $errors[] = 'Nome torneo obbligatorio.'; }
    if ($season === '')                                         { $errors[] = 'Stagione obbligatoria (es. 2024).'; }
    if ($league_id === '' || !ctype_digit((string)$league_id))  { $errors[] = 'League ID obbligatorio (numero).'; }

    if ($cost_per_life === '' || !is_numeric($cost_per_life) || $cost_per_life < 0)    { $errors[] = 'Costo per vita non valido.'; }
    if ($max_slots === '' || !ctype_digit((string)$max_slots) || (int)$max_slots < 1)  { $errors[] = 'Posti disponibili non validi.'; }
    if ($max_lives_per_user === '' || !ctype_digit((string)$max_lives_per_user) || (int)$max_lives_per_user < 1)
                                                                                      { $errors[] = 'Vite massime per utente non valide.'; }

    if ($prize_percent === '' || !ctype_digit((string)$prize_percent)) { $errors[] = 'Percentuale montepremi non valida.'; }
    if ($rake_percent === ''  || !ctype_digit((string)$rake_percent))  { $errors[] = 'Percentuale rake non valida.'; }
    if ((int)$prize_percent + (int)$rake_percent !== 100)              { $errors[] = 'Prize% + Rake% devono sommare 100.'; }

    if ($guaranteed_prize !== '' && (!is_numeric($guaranteed_prize) || $guaranteed_prize < 0))
                                                                      { $errors[] = 'Montepremi garantito non valido.'; }

    // ---------------------------------
    // 3) INSERT su DB se tutto è ok
    // ---------------------------------
    if (!$errors) {
      // Nota: lo stato iniziale è 'draft' (bozza). La pagina “gestione” potrà aprirlo/chiuderlo.
      $sql = "INSERT INTO tournaments
              (name, cost_per_life, max_slots, max_lives_per_user, guaranteed_prize,
               prize_percent, rake_percent, league_id, league_name, season, status)
              VALUES
              (:name, :cpl, :slots, :mlu, :gp, :pp, :rp, :lid, :lname, :season, 'draft')";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':name'   => $name,
        ':cpl'    => (float)$cost_per_life,
        ':slots'  => (int)$max_slots,
        ':mlu'    => (int)$max_lives_per_user,
        ':gp'     => ($guaranteed_prize === '' ? null : (float)$guaranteed_prize),
        ':pp'     => (int)$prize_percent,
        ':rp'     => (int)$rake_percent,
        ':lid'    => (int)$league_id,
        ':lname'  => ($league_name === '' ? null : $league_name),
        ':season' => $season,
      ]);

      // [PRG] Salvo un messaggio di conferma e ridirigo (evita doppio invio modulo).
      $_SESSION['flash'] = 'Torneo creato (bozza).';
      header('Location: /admin/crea_torneo.php'); exit;
    }
  }
}

// ============================================================================
// VIEW — HTML + inclusione CSS dedicato
// ============================================================================
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8"><!-- meta charset -->
  <title>Admin — Crea Tornei</title><!-- titolo pagina -->
  <link rel="stylesheet" href="/assets/base.css"><!-- stile base -->
  <link rel="stylesheet" href="/assets/header_admin.css"><!-- header admin -->
  <link rel="stylesheet" href="/assets/crea_torneo.css"><!-- stile specifico di questa pagina -->
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?><!-- barra di navigazione admin -->

<main class="admin-wrap"><!-- contenitore centrale -->

  <h1 class="page-title">Crea Tornei</h1><!-- titolo visivo pagina -->

  <!-- messaggi di conferma/errore -->
  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="err"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <!-- ===================== FORM CREAZIONE (SOLO CREAZIONE) ===================== -->
  <div class="card"><!-- card con bordo arrotondato -->
    <form method="post" action="" class="form"><!-- submit su stessa pagina -->
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><!-- CSRF -->
      <input type="hidden" name="action" value="create"><!-- azione: create -->

      <div class="grid"><!-- layout a 2 colonne -->
        <div class="field">
          <label for="name">Nome torneo</label>
          <input id="name" name="name" type="text" required>
        </div>

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

        <div class="field">
          <label for="lid">League ID (API-FOOTBALL)</label>
          <input id="lid" name="league_id" type="number" min="1" required>
          <small class="hint">Imposta l’ID della competizione (es. Serie A, Premier, …).</small>
        </div>

        <div class="field">
          <label for="lname">Nome competizione (opz.)</label>
          <input id="lname" name="league_name" type="text" placeholder="Serie A, Premier League …">
        </div>

        <div class="field">
          <label for="season">Stagione</label>
          <input id="season" name="season" type="text" placeholder="2024" required>
        </div>
      </div>

      <div class="actions"><!-- CTA -->
        <button class="btn btn-primary" type="submit">Salva torneo</button>
        <a class="btn" href="/admin/crea_torneo.php">Annulla</a>
      </div>
    </form>
  </div>

  <!-- Nota: la GESTIONE tornei (apri/chiudi/elimina) sarà in una pagina separata es. /admin/gestisci_tornei.php -->
  <!-- La LOBBY per gli utenti leggerà i tornei con status = 'open' -->

</main><!-- /admin-wrap -->
</body>
</html>
