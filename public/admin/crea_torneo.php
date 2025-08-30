<?php
// ==========================================================
// admin/crea_torneo.php — Pagina amministratore per creare / gestire tornei
// ==========================================================

// [SESSIONE] Avvia la sessione se non già attiva (necessaria per $_SESSION e CSRF)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// [GUARDIE] Importa le funzioni di protezione (richiede admin loggato)
require_once __DIR__ . '/../src/guards.php';  // include require_login(), require_admin()
require_admin();                               // blocca l’accesso se non è admin

// [CONFIG/DB] Costanti d’ambiente e connessione PDO ($pdo)
require_once __DIR__ . '/../src/config.php';  // costanti (es. API_FOOTBALL_KEY dalle env)
require_once __DIR__ . '/../src/db.php';      // connessione PDO con prepared reali

// [CSRF] Inizializza il token CSRF se assente (contro POST forgiati)
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); } // 128 bit
$csrf = $_SESSION['csrf']; // token da inserire nei form

// [STATE] Messaggi di stato
$flash  = null;  // messaggio “una tantum” per conferme
$errors = [];    // array di errori da mostrare se validazione fallisce

// ========================= POST HANDLER (PRG) =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {                   // se è stato inviato un form
  $posted_csrf = $_POST['csrf'] ?? '';                         // recupera token CSRF dal form
  if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {         // confronta con quello in sessione
    http_response_code(400);                                   // bad request
    die('CSRF non valido');                                    // blocca subito
  }

  $action = $_POST['action'] ?? '';                            // tipo azione: create / toggle_status / delete

  // ------------------------- CREA TORNEO -------------------------
  if ($action === 'create') {
    // [INPUT] Recupera e normalizza tutti i campi dal form
    $name               = trim($_POST['name'] ?? '');                 // nome torneo
    $cost_per_life      = $_POST['cost_per_life'] ?? '';              // costo per vita
    $max_slots          = $_POST['max_slots'] ?? '';                  // posti disponibili
    $max_lives_per_user = $_POST['max_lives_per_user'] ?? '';         // vite max per utente
    $guaranteed_prize   = $_POST['guaranteed_prize'] ?? '';           // montepremi garantito (opz)
    $prize_percent      = $_POST['prize_percent'] ?? '';              // % a montepremi
    $rake_percent       = $_POST['rake_percent'] ?? '';               // % al sito
    $league_id          = $_POST['league_id'] ?? '';                  // ID competizione API-FOOTBALL
    $league_name        = trim($_POST['league_name'] ?? '');          // label competizione (opz)
    $season             = trim($_POST['season'] ?? '');               // stagione (es. 2024)

    // [VALIDAZIONE] Controlli base e di tipo
    if ($name === '')                                           { $errors[] = 'Nome torneo obbligatorio.'; }
    if ($season === '')                                         { $errors[] = 'Stagione obbligatoria (es. 2024).'; }
    if ($league_id === '' || !ctype_digit((string)$league_id))  { $errors[] = 'League ID obbligatorio (numero).'; }
    if ($cost_per_life === '' || !is_numeric($cost_per_life) || $cost_per_life < 0)
                                                                { $errors[] = 'Costo per vita non valido.'; }
    if ($max_slots === '' || !ctype_digit((string)$max_slots) || (int)$max_slots < 1)
                                                                { $errors[] = 'Posti disponibili non validi.'; }
    if ($max_lives_per_user === '' || !ctype_digit((string)$max_lives_per_user) || (int)$max_lives_per_user < 1)
                                                                { $errors[] = 'Vite massime per utente non valide.'; }
    if ($prize_percent === '' || !ctype_digit((string)$prize_percent))
                                                                { $errors[] = 'Percentuale montepremi non valida.'; }
    if ($rake_percent === ''  || !ctype_digit((string)$rake_percent))
                                                                { $errors[] = 'Percentuale rake non valida.'; }
    if ((int)$prize_percent + (int)$rake_percent !== 100)       { $errors[] = 'Prize% + Rake% devono sommare 100.'; }
    if ($guaranteed_prize !== '' && (!is_numeric($guaranteed_prize) || $guaranteed_prize < 0))
                                                                { $errors[] = 'Montepremi garantito non valido.'; }

    // [INSERT] Se nessun errore, salva il torneo come “draft”
    if (!$errors) {
      $sql = "INSERT INTO tournaments
              (name, cost_per_life, max_slots, max_lives_per_user, guaranteed_prize,
               prize_percent, rake_percent, league_id, league_name, season, status)
              VALUES
              (:name, :cpl, :slots, :mlu, :gp, :pp, :rp, :lid, :lname, :season, 'draft')";
      $stmt = $pdo->prepare($sql);                                         // prepara query
      $stmt->execute([                                                     // esegue con bind
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

      $_SESSION['flash'] = 'Torneo creato (bozza).';                       // messaggio conferma
      header("Location: /admin/crea_torneo.php"); exit;                    // PRG redirect
    }
  }

  // ---------------------- TOGGLE STATO TORNEO ---------------------
  if ($action === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);                                        // id torneo
    $to = $_POST['to'] ?? 'draft';                                         // stato destinazione
    if ($id > 0 && in_array($to, ['draft','open','closed'], true)) {       // convalida valori
      $u = $pdo->prepare("UPDATE tournaments SET status = :s WHERE id = :id");
      $u->execute([':s' => $to, ':id' => $id]);                             // aggiorna stato
      $_SESSION['flash'] = "Stato torneo #$id impostato a $to.";           // feedback
    }
    header("Location: /admin/crea_torneo.php"); exit;                      // PRG redirect
  }

  // --------------------------- DELETE ------------------------------
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);                                        // id torneo
    if ($id > 0) {                                                         // valida id
      $d = $pdo->prepare("DELETE FROM tournaments WHERE id = :id");        // elimina riga
      $d->execute([':id' => $id]);
      $_SESSION['flash'] = "Torneo #$id eliminato.";                        // feedback
    }
    header("Location: /admin/crea_torneo.php"); exit;                      // PRG redirect
  }
}

// ========================= LISTA TORNEI =========================
// [QUERY] Recupera la lista (semplice) ordinata per creato più recente
$list = $pdo->query("SELECT * FROM tournaments ORDER BY created_at DESC")->fetchAll(); // array di tornei

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8"><!-- [META] charset -->
  <title>Admin — Crea Tornei</title><!-- [TITOLO] pagina -->
  <!-- [CSS] fogli di stile condivisi -->
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/dashboard.css">
  <style>
    /* [CARD] contenitore a pannello con bordi arrotondati */
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;box-shadow:0 8px 28px rgba(0,0,0,.18);margin-bottom:16px}
    /* [GRID] form a due colonne (responsive) */
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .grid .field{display:flex;flex-direction:column;gap:6px}
    .grid input,.grid select{height:36px;border-radius:8px;border:1px solid rgba(255,255,255,.25);background:#0a0a0b;color:#fff;padding:0 10px}
    /* [BTN] pulsanti base */
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    .btn-primary{background:#00c074;border-color:#00c074}
    .btn-warn{background:#e62329;border-color:#e62329}
    /* [LISTA] tabella tornei */
    .list{width:100%;border-collapse:collapse}
    .list th,.list td{padding:8px 10px;border-bottom:1px solid rgba(255,255,255,.08)}
    .list th{color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em;text-align:left}
    .list td small{color:#aaa}
    /* [RESP] stack in colonna su schermi stretti */
    @media (max-width:860px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?><!-- [HEADER] top bar admin -->

<main class="admin-wrap"><!-- [WRAP] contenitore centrale -->

  <h1 style="margin:0 0 12px;">Crea Tornei</h1><!-- [TITLE] pagina -->

  <!-- [FLASH/ERRORI] messaggi di stato -->
  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="err"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <!-- ===================== FORM CREAZIONE ===================== -->
  <div class="card"><!-- [CARD] pannello form -->
    <form method="post" action=""><!-- [FORM] invio alla stessa pagina -->
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><!-- CSRF -->
      <input type="hidden" name="action" value="create"><!-- azione: create -->

      <div class="grid"><!-- [GRID] 2 colonne -->
        <div class="field">
          <label>Nome torneo</label>
          <input type="text" name="name" required><!-- obbligatorio -->
        </div>

        <div class="field">
          <label>Costo per vita (€)</label>
          <input type="number" step="0.01" name="cost_per_life" required><!-- numero/decimali -->
        </div>

        <div class="field">
          <label>Posti disponibili</label>
          <input type="number" name="max_slots" min="1" required><!-- intero >=1 -->
        </div>

        <div class="field">
          <label>Vite max per utente</label>
          <input type="number" name="max_lives_per_user" min="1" required><!-- intero >=1 -->
        </div>

        <div class="field">
          <label>Montepremi garantito (opz.)</label>
          <input type="number" step="0.01" name="guaranteed_prize"><!-- opzionale -->
        </div>

        <div class="field">
          <label>% a montepremi</label>
          <input type="number" name="prize_percent" min="0" max="100" required><!-- 0-100 -->
        </div>

        <div class="field">
          <label>% rake sito</label>
          <input type="number" name="rake_percent" min="0" max="100" required><!-- 0-100 -->
        </div>

        <div class="field">
          <label>League ID (API-FOOTBALL)</label>
          <input type="number" name="league_id" required><!-- per filtrare le partite -->
        </div>

        <div class="field">
          <label>Nome competizione (opz.)</label>
          <input type="text" name="league_name" placeholder="Serie A, Premier League …"><!-- label libera -->
        </div>

        <div class="field">
          <label>Stagione</label>
          <input type="text" name="season" placeholder="2024" required><!-- anno -->
        </div>
      </div>

      <div style="margin-top:12px; display:flex; gap:8px;"><!-- CTA -->
        <button class="btn btn-primary" type="submit">Salva torneo</button><!-- salva -->
        <a class="btn" href="/admin/crea_torneo.php">Annulla</a><!-- reset veloce via GET -->
      </div>
    </form>
  </div>

  <!-- ======================= LISTA TORNEI ======================= -->
  <div class="card"><!-- [CARD] pannello lista -->
    <table class="list"><!-- tabella semplice -->
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>Vita (€)</th>
          <th>Posti</th>
          <th>Vite/utente</th>
          <th>Montepremi (gar.)</th>
          <th>%Prize/%Rake</th>
          <th>League</th>
          <th>Season</th>
          <th>Status</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $t): ?><!-- loop tornei -->
          <tr>
            <td><?php echo (int)$t['id']; ?></td><!-- id -->
            <td><?php echo htmlspecialchars($t['name']); ?></td><!-- nome -->
            <td>€ <?php echo number_format((float)$t['cost_per_life'], 2, ',', '.'); ?></td><!-- costo vita -->
            <td><?php echo (int)$t['max_slots']; ?></td><!-- posti -->
            <td><?php echo (int)$t['max_lives_per_user']; ?></td><!-- vite utente -->
            <td><?php echo is_null($t['guaranteed_prize']) ? '-' : ('€ '.number_format((float)$t['guaranteed_prize'], 2, ',', '.')); ?></td><!-- garanzia -->
            <td><?php echo (int)$t['prize_percent']; ?>% / <?php echo (int)$t['rake_percent']; ?>%</td><!-- % prize/rake -->
            <td><?php echo (int)$t['league_id']; ?> <small><?php echo htmlspecialchars($t['league_name'] ?? ''); ?></small></td><!-- lega -->
            <td><?php echo htmlspecialchars($t['season']); ?></td><!-- stagione -->
            <td><?php echo htmlspecialchars($t['status']); ?></td><!-- stato -->
            <td style="display:flex; gap:6px;"><!-- azioni riga -->
              <?php if ($t['status'] !== 'open'): ?><!-- apri se non già open -->
                <form method="post" action="" style="display:inline">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                  <input type="hidden" name="to" value="open">
                  <button class="btn" type="submit">Apri</button>
                </form>
              <?php endif; ?>

              <?php if ($t['status'] === 'open'): ?><!-- chiudi se open -->
                <form method="post" action="" style="display:inline">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                  <input type="hidden" name="to" value="closed">
                  <button class="btn btn-warn" type="submit">Chiudi</button>
                </form>
              <?php endif; ?>

              <!-- elimina sempre possibile (se sbagli, rigenera) -->
              <form method="post" action="" style="display:inline"
                    onsubmit="return confirm('Eliminare il torneo #<?php echo (int)$t['id']; ?>?');">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                <button class="btn btn-delete" type="submit">Elimina</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$list): ?><!-- fallback se non ci sono tornei -->
          <tr><td colspan="11" style="color:#aaa; padding:10px;">Nessun torneo creato.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main><!-- /admin-wrap -->
</body>
</html>
