<?php
// ==========================================================
// admin/dashboard.php — Pannello gestione utenti stile "griglia"
// Mostra: nome, cognome, username, email, telefono, attivo, saldo (editabile),
// reset password, pulsante “Applica modifiche” e link "Lista movimenti".
// Include: ricerca, ordinamento e paginazione base.
// ==========================================================

// [RIGA] Importiamo le guardie (solo admin) — path corretto salendo di UN livello da /admin
require_once __DIR__ . '/../src/guards.php';         // include funzioni require_login/require_admin
require_admin();                                      // blocca chi non è admin

// [RIGA] DB & config
require_once __DIR__ . '/../src/config.php';         // costanti APP_ENV, ecc.
require_once __DIR__ . '/../src/db.php';             // $pdo (PDO con prepared reali)

// ------------------------
// Protezione basilare CSRF
// ------------------------
// [RIGA] Creiamo un token CSRF in sessione per i POST (per evitare modifiche cross-site)
if (empty($_SESSION['csrf'])) {                       // se non esiste ancora un token…
    $_SESSION['csrf'] = bin2hex(random_bytes(16));    // generiamo 32 hex chars (128 bit)
}
$csrf = $_SESSION['csrf'];                            // copiamo il token da usare nei form

// ------------------------
// Gestione aggiornamenti riga (POST)
// ------------------------
$flash = null;                                        // messaggi flash informativi
$errors = [];                                         // lista errori operativi

// [FIX FLASH] Se c'è un messaggio flash in sessione (post-redirect), lo recuperiamo e lo puliamo.
if (isset($_SESSION['flash'])) {                      // ← aggiunta minima per conservare il messaggio dopo il redirect
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ------------------------
// Gestione aggiornamenti riga (POST)
// ------------------------
$flash = null;     // [RIGA] Messaggio flash da mostrare all’admin dopo un’azione
$errors = [];      // [RIGA] Eventuali errori di validazione
// Inizializzazioni safe per richieste GET (evita notice/warning)

$action  = '';         // azione corrente (vuota in GET)
$user_id = 0;          // id utente corrente (0 in GET)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {   // [RIGA] Se arriva una richiesta POST (click pulsante)
    // [RIGA] Verifica token CSRF
    $posted_csrf = $_POST['csrf'] ?? '';       // recupero il token inviato nel form
    if (!hash_equals($_SESSION['csrf'], $posted_csrf)) { // confronto sicuro contro quello in sessione
        http_response_code(400);               // risposta HTTP 400 = bad request
        die('CSRF non valido');                // blocco l’esecuzione
    }

    // [RIGA] Recupero azione richiesta e ID utente
    $action  = $_POST['action']  ?? '';        // es. "toggle_active", "update_user", "admin_verify_email"
    $user_id = (int)($_POST['user_id'] ?? 0);  // id utente coinvolto

    // ==========================================================
    // 1. TOGGLE ATTIVO/DISATTIVO
    // ==========================================================
if ($action === 'toggle_active' && $user_id > 0) {
    // [SICUREZZA] Prelevo lo username della riga che sto toccando
    $chk = $pdo->prepare("SELECT username FROM utenti WHERE id = :id LIMIT 1"); // prepared per sicurezza
    $chk->execute([':id' => $user_id]);                                         // eseguo bind id
    $uname = $chk->fetchColumn();                                               // prendo solo lo username

    // [REGOLA] L'utente speciale *valenzo2313* non può MAI essere disattivato.
    if ($uname === 'valenzo2313') {                                             // se è lui...
        $_SESSION['flash'] = 'Questo utente non può essere disattivato.';       // messaggio per l’admin
        // PRG redirect: ricarico la dashboard con i filtri correnti e STOP.
        $query = http_build_query([
            'page' => (int)($_GET['page'] ?? 1),
            'sort' => $_GET['sort'] ?? 'cognome',
            'dir'  => $_GET['dir']  ?? 'asc',
            'q'    => $_GET['q']    ?? '',
        ]);
        header("Location: /admin/dashboard.php?$query");
        exit;
    }

    // [SE ARRIVO QUI] Posso procedere col toggle
    $new_state = (int)($_POST['new_state'] ?? 0);                                // 1=attivo, 0=disattivo
    $up = $pdo->prepare("UPDATE utenti SET is_active = :a WHERE id = :id");     // aggiorno flag
    $up->execute([':a' => $new_state, ':id' => $user_id]);                       // esecuzione

    $_SESSION['flash'] = $new_state ? 'Utente attivato.' : 'Utente disattivato.';// feedback
    $query = http_build_query([
        'page' => (int)($_GET['page'] ?? 1),
        'sort' => $_GET['sort'] ?? 'cognome',
        'dir'  => $_GET['dir']  ?? 'asc',
        'q'    => $_GET['q']    ?? '',
    ]);
    header("Location: /admin/dashboard.php?$query");
    exit;
}

// ==========================================================
// 2. UPDATE DATI UTENTE (nome, cognome, email, telefono, saldo, password)
//    NOTA: qui NON modifichiamo is_active! (si modifica solo con toggle_active)
// ==========================================================
if ($action === 'update_user' && $user_id > 0) {
    // [INPUT] Recupero i campi dal form
    $nome     = trim($_POST['nome'] ?? '');
    $cognome  = trim($_POST['cognome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    // $is_active = ...  // ⛔️ rimosso: non gestiamo più is_active da questo ramo
    $saldo    = $_POST['crediti'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';

    // [VALIDAZIONI]
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida.';
    }
    if ($phone !== '' && !preg_match('/^\+?[0-9\- ]{7,20}$/', $phone)) {
        $errors[] = 'Numero di telefono non valido.';
    }
    if ($saldo === '' || !is_numeric($saldo)) {
        $errors[] = 'Saldo non valido.';
    }
    if ($new_pass !== '' && strlen($new_pass) < 8) {
        $errors[] = 'La nuova password deve avere almeno 8 caratteri.';
    }

    // [UPDATE] Se non ci sono errori → aggiorno DB
    if (!$errors) {
        // [SQL BASE] Aggiorno SOLO i campi testuali + saldo (SENZA is_active)
        $sql = "UPDATE utenti 
                   SET nome    = :nome,
                       cognome = :cognome,
                       email   = :email,
                       phone   = :phone,
                       crediti = :crediti
                 WHERE id = :id";
        $params = [
            ':nome'    => $nome,
            ':cognome' => $cognome,
            ':email'   => $email,
            ':phone'   => $phone,
            ':crediti' => (float)$saldo,
            ':id'      => $user_id,
        ];

        // [PASSWORD] Se admin ha chiesto reset password → rigenero hash e includo il campo
        if ($new_pass !== '') {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);   // hash sicuro
            $sql = "UPDATE utenti 
                       SET nome          = :nome,
                           cognome       = :cognome,
                           email         = :email,
                           phone         = :phone,
                           crediti       = :crediti,
                           password_hash = :hash
                     WHERE id = :id";
            $params[':hash'] = $hash;                             // aggiungo il parametro
        }

        // [SQL] Eseguo l’UPDATE
        $up = $pdo->prepare($sql);
        $up->execute($params);

        // [UX] Messaggio di conferma
        $flash = 'Modifiche salvate.';

        // [PRG] Redirect per ricaricare la pagina e riflettere i dati aggiornati
        $_SESSION['flash'] = $flash;
        $query = http_build_query([
            'page' => (int)($_GET['page'] ?? 1),
            'sort' => $_GET['sort'] ?? 'cognome',
            'dir'  => $_GET['dir']  ?? 'asc',
            'q'    => $_GET['q']    ?? '',
        ]);
        header("Location: /admin/dashboard.php?$query");
        exit;
    }
}
    }

    // ==========================================================
    // 3. ADMIN VERIFY EMAIL (nuovo)
    // ==========================================================
    if ($action === 'admin_verify_email' && $user_id > 0) {
        // [SQL] Segno verified_at, pulisco il token e attivo l’utente
        $up = $pdo->prepare("UPDATE utenti 
                             SET verified_at = NOW(), verification_token = NULL, is_active = 1 
                             WHERE id = :id");
        $up->execute([':id' => $user_id]);

        // [UX] Messaggio flash
        $flash = 'Email convalidata e utente attivato.';

        // [PRG] Redirect per ricaricare la lista aggiornata
        $_SESSION['flash'] = $flash;
        $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
        header("Location: /admin/dashboard.php?$query");
        exit;
    }

 // ==========================================================
// 4. ELIMINA UTENTE (nuovo)
// ==========================================================
if ($action === 'admin_delete_user' && $user_id > 0) {
    // [SICUREZZA] Evita che l'admin si cancelli da solo
    if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $user_id) {
        $_SESSION['flash'] = 'Non puoi eliminare il tuo stesso account.';
        $query = http_build_query([
            'page' => $_GET['page'] ?? 1,
            'sort' => $_GET['sort'] ?? 'cognome',
            'dir'  => $_GET['dir']  ?? 'asc',
            'q'    => $_GET['q']    ?? '',
        ]);
        header("Location: /admin/dashboard.php?$query"); 
        exit;
    }

    // [SICUREZZA] Utente speciale "valenzo2313": non eliminabile
    $chk = $pdo->prepare("SELECT username FROM utenti WHERE id = :id LIMIT 1");
    $chk->execute([':id' => $user_id]);
    $uname = $chk->fetchColumn();
    if ($uname === 'valenzo2313') {
        $_SESSION['flash'] = 'L’utente speciale non può essere eliminato.';
        $query = http_build_query([
            'page' => $_GET['page'] ?? 1,
            'sort' => $_GET['sort'] ?? 'cognome',
            'dir'  => $_GET['dir']  ?? 'asc',
            'q'    => $_GET['q']    ?? '',
        ]);
        header("Location: /admin/dashboard.php?$query");
        exit;
    }

    // [SQL] Cancella l'utente dal DB (solo se non è admin stesso e non è speciale)
    $del = $pdo->prepare("DELETE FROM utenti WHERE id = :id");
    $del->execute([':id' => $user_id]);

    // [UX] Messaggio e PRG redirect
    $_SESSION['flash'] = 'Utente eliminato definitivamente.';
    $query = http_build_query([
        'page' => $_GET['page'] ?? 1,
        'sort' => $_GET['sort'] ?? 'cognome',
        'dir'  => $_GET['dir']  ?? 'asc',
        'q'    => $_GET['q']    ?? '',
    ]);
    header("Location: /admin/dashboard.php?$query");
    exit;
}
    // [RIGA] Prendiamo l’azione (per estensioni future) — qui gestiamo "update_user"
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);         // id utente da modificare (hidden nel form)

    // ------------------------------------------
    // NUOVO RAMO: toggle stato attivo/inattivo
    // ------------------------------------------
    // [SCOPO] Cambiare il flag is_active a 1 (attivo) o 0 (disattivo)
    // [SICUREZZA] Richiede csrf valido (già verificato nel tuo POST) e id > 0
    if ($action === 'toggle_active' && $user_id > 0) {
        $new_state = (int)($_POST['new_state'] ?? 0);        // 1 = attivo, 0 = disattivo

        // [SQL] Aggiorna il flag
        $up = $pdo->prepare("UPDATE utenti SET is_active = :a WHERE id = :id");
        $up->execute([':a' => $new_state, ':id' => $user_id]);

        // [UX] Messaggio flash
        $flash = $new_state ? 'Utente attivato.' : 'Utente disattivato.';

        // [PRG] Redirect per ricaricare la lista aggiornata e riflettere SUBITO il cambio di colore
        $_SESSION['flash'] = $flash; // conserva il messaggio dopo il redirect
        $query = http_build_query([
            'page' => (int)($_GET['page'] ?? 1),
            'sort' => $_GET['sort'] ?? 'cognome',
            'dir'  => $_GET['dir']  ?? 'asc',
            'q'    => $_GET['q']    ?? '',
        ]);
        header("Location: /admin/dashboard.php?$query");
        exit;
    }

    if ($action === 'update_user' && $user_id > 0) {  // se è un update e abbiamo un id valido…
        // [RIGA] Prendiamo i campi modificabili
        $nome      = trim($_POST['nome'] ?? '');      // nome (può essere vuoto)
        $cognome   = trim($_POST['cognome'] ?? '');   // cognome (può essere vuoto)
        $email     = trim($_POST['email'] ?? '');     // email (validazione base sotto)
        $phone     = trim($_POST['phone'] ?? '');     // telefono (validazione base sotto)
        $saldo     = $_POST['crediti'] ?? '';         // saldo attuale (string, lo validiamo a numero)
        $new_pass  = $_POST['new_password'] ?? '';    // nuovo valore password (se vogliamo resettarla)

        // (⚠️) PRIMA qui avevi di nuovo un ramo toggle_active duplicato.
        // FIX: rimosso il duplicato per evitare confusione. Il toggle viene gestito
        //      nel ramo dedicato *fuori* da update_user (vedi sopra).

        // [RIGA] Validazioni semplici (puoi rafforzarle se vuoi)
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {      // email formale
            $errors[] = 'Email non valida.';
        }
        if ($phone !== '' && !preg_match('/^\+?[0-9\- ]{7,20}$/', $phone)) {    // telefono semplice
            $errors[] = 'Numero di telefono non valido.';
        }
        if ($saldo === '' || !is_numeric($saldo)) {                             // saldo deve essere numero
            $errors[] = 'Saldo non valido.';
        }
        // [RIGA] Se impostiamo una nuova password, applichiamo policy minima
        if ($new_pass !== '') {
            if (strlen($new_pass) < 8) { $errors[] = 'La nuova password deve avere almeno 8 caratteri.'; }
        }

        if (!$errors) {                                                         // se non ci sono errori…
            // [RIGA] Prepariamo UPDATE dinamico in base a cosa è stato cambiato
            // Costruiamo SET con soli campi rilevanti; password la gestiamo a parte
         $sql = "UPDATE utenti 
        SET nome = :nome, cognome = :cognome, email = :email, phone = :phone, crediti = :crediti 
        WHERE id = :id";
$params = [
    ':nome'    => $nome,
    ':cognome' => $cognome,
    ':email'   => $email,
    ':phone'   => $phone,
    ':crediti' => (float)$saldo,
    ':id'      => $user_id,
];

            // [RIGA] Se è stato immesso un reset password → generiamo hash
     $sql = "UPDATE utenti 
        SET nome = :nome, cognome = :cognome, email = :email, phone = :phone, crediti = :crediti, password_hash = :hash 
        WHERE id = :id";
$params[':hash'] = $hash;

            // [RIGA] Eseguiamo l’UPDATE
            $up = $pdo->prepare($sql);                                         // prepared
            $up->execute($params);                                             // esecuzione

            $flash = 'Modifiche salvate.';                                     // messaggio di conferma

            // [PRG] Redirect per ricaricare la lista aggiornata e riflettere SUBITO le modifiche
            $_SESSION['flash'] = $flash; // conserva il messaggio dopo il redirect
            $query = http_build_query([
                'page' => (int)($_GET['page'] ?? 1),
                'sort' => $_GET['sort'] ?? 'cognome',
                'dir'  => $_GET['dir']  ?? 'asc',
                'q'    => $_GET['q']    ?? '',
            ]);
            header("Location: /admin/dashboard.php?$query");
            exit;
        }
    }

// ------------------------
// Ricerca / Ordinamento / Paginazione (GET)
// ------------------------

// [RIGA] Ricerca libera su nome/cognome/username/email/phone
$q = trim($_GET['q'] ?? '');                                                   // stringa di ricerca (può essere vuota)

// [RIGA] Ordinamento: whitelist per evitare SQL injection
$sort = $_GET['sort'] ?? 'cognome';                                            // campo di default
$dir  = $_GET['dir']  ?? 'asc';                                                // direzione di default

$allowedSort = ['cognome','nome','username','email','phone','crediti'];        // campi permessi
$allowedDir  = ['asc','desc'];                                                 // direzioni permesse

if (!in_array($sort, $allowedSort, true)) { $sort = 'cognome'; }               // fallback
if (!in_array(strtolower($dir), $allowedDir, true)) { $dir = 'asc'; }          // fallback

// [RIGA] Paginazione base
$page = max(1, (int)($_GET['page'] ?? 1));                                     // pagina >= 1
$perPage = 10;                                                                  // 10 righe per pagina
$offset = ($page - 1) * $perPage;                                               // offset SQL

// ------------------------
// Costruzione query lista utenti (sicura)
// ------------------------
$where    = "1=1";          // base: nessun filtro
$bindings = [];             // qui teniamo SOLO i parametri da bindare

// [RICERCA] Se c'è q, crea 5 placeholder diversi (q1..q5) per evitare HY093
if ($q !== '') {
    $where .= " AND (
        nome     LIKE :q1 OR
        cognome  LIKE :q2 OR
        username LIKE :q3 OR
        email    LIKE :q4 OR
        phone    LIKE :q5
    )";
    $like = '%' . $q . '%';           // pattern LIKE
    $bindings[':q1'] = $like;
    $bindings[':q2'] = $like;
    $bindings[':q3'] = $like;
    $bindings[':q4'] = $like;
    $bindings[':q5'] = $like;
}

// [RIGA] Conteggio totale per paginazione
$countSql  = "SELECT COUNT(*) FROM utenti WHERE $where";
$countStmt = $pdo->prepare($countSql);
// bind SOLO ciò che esiste (q1..q5 quando c’è ricerca)
foreach ($bindings as $k => $v) { $countStmt->bindValue($k, $v, PDO::PARAM_STR); }
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

// [RIGA] Lista utenti (bind q1..q5 se presenti, e SEMPRE :lim / :off)
$listSql  = "SELECT id, user_code, nome, cognome, username, email, phone, crediti, is_active, verified_at
             FROM utenti
             WHERE $where
             ORDER BY $sort " . strtoupper($dir) . "
             LIMIT :lim OFFSET :off";
$listStmt = $pdo->prepare($listSql);

// bind ricerca (q1..q5) se presenti
foreach ($bindings as $k => $v) { $listStmt->bindValue($k, $v, PDO::PARAM_STR); }
// bind paginazione SEMPRE
$listStmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
$listStmt->bindValue(':off', (int)$offset,  PDO::PARAM_INT);

// esecuzione (senza array) e fetch
$listStmt->execute();
$users = $listStmt->fetchAll();                                  

// [RIGA] Totale utenti per il badge in alto
$tot_utenti = (int)$pdo->query("SELECT COUNT(*) FROM utenti")->fetchColumn();  // numero totale utenti
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Dashboard</title>

  <!-- [RIGA] CSS condivisi -->
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/dashboard.css">
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?> <!-- header admin -->

<main class="admin-wrap">
  <!-- KPI: numero utenti -->
  <div class="kpi"><strong>Utenti totali:</strong> <?php echo $tot_utenti; ?></div>

  <!-- Messaggi -->
  <?php if ($flash): ?><div class="flash"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="err"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div><?php endif; ?>

  <!-- CARD WRAP: filtro + tabella + paginazione -->
  <div class="card-table">

    <!-- Filtro / Ricerca -->
    <form class="filters" method="get" action="/admin/dashboard.php">
      <input type="text"
             name="q"
             placeholder="Cerca (nome, cognome, user, email, telefono, crediti)"
             value="<?php echo htmlspecialchars($q); ?>">
      <!-- Manteniamo ordinamento e direzione correnti come hidden -->
      <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
      <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir); ?>">
    </form>

    <!-- Lista utenti a STRISCIA (grid allineata) -->
    <div class="rows">

      <!-- Header righe (cliccabile per sort come prima) -->
      <div class="row-head grid-row">
        <div class="cell code">
          <a href="<?php echo sort_url('user_code', $sort, $dir, $page, $q); ?>">
            Codice<?php echo sort_caret('user_code', $sort, $dir); ?>
          </a>
        </div>
        <div class="cell nome">
          <a href="<?php echo sort_url('nome', $sort, $dir, $page, $q); ?>">
            Nome<?php echo sort_caret('nome', $sort, $dir); ?>
          </a>
        </div>
        <div class="cell cognome">
          <a href="<?php echo sort_url('cognome', $sort, $dir, $page, $q); ?>">
            Cognome<?php echo sort_caret('cognome', $sort, $dir); ?>
          </a>
        </div>
        <div class="cell user">
          <a href="<?php echo sort_url('username', $sort, $dir, $page, $q); ?>">
            User<?php echo sort_caret('username', $sort, $dir); ?>
          </a>
        </div>
        <div class="cell email">
          <a href="<?php echo sort_url('email', $sort, $dir, $page, $q); ?>">
            Email<?php echo sort_caret('email', $sort, $dir); ?>
          </a>
        </div>
        <div class="cell phone">
          <a href="<?php echo sort_url('phone', $sort, $dir, $page, $q); ?>">
            Telefono<?php echo sort_caret('phone', $sort, $dir); ?>
          </a>
        </div>
        <div class="cell attivo">Attivo</div>
        <div class="cell saldo">
          <a href="<?php echo sort_url('crediti', $sort, $dir, $page, $q); ?>">
            Saldo €<?php echo sort_caret('crediti', $sort, $dir); ?>
          </a>
        </div>
        <div class="cell pwd">Nuova password</div>
        <div class="cell azioni">Azioni</div>
      </div>
<?php if (empty($users)) : ?>
  <div style="color:#ff6b6b; padding:8px 0;">(debug) Nessun utente trovato per questi filtri.</div>
<?php endif; ?>
      <!-- Righe utente -->
      <?php foreach ($users as $u): ?>
        <form class="user-row grid-row" method="post"
              action="/admin/dashboard.php?page=<?php echo (int)$page; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&q=<?php echo urlencode($q); ?>">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
          <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">

          <div class="cell code"><?php echo htmlspecialchars($u['user_code'] ?? ''); ?></div>

          <div class="cell nome">
            <input type="text" name="nome" value="<?php echo htmlspecialchars($u['nome'] ?? ''); ?>">
          </div>

          <div class="cell cognome">
            <input type="text" name="cognome" value="<?php echo htmlspecialchars($u['cognome'] ?? ''); ?>">
          </div>

          <div class="cell user"><?php echo htmlspecialchars($u['username']); ?></div>

          <div class="cell email">
            <input type="email" name="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>">
          </div>

          <div class="cell phone">
            <input type="tel" name="phone" value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>">
          </div>

          <?php
            $eff_active  = ((int)$u['is_active'] === 1);
            $state_text  = $eff_active ? 'Attivo' : 'Inattivo';
            $state_class = $eff_active ? 'btn-state on' : 'btn-state off';
            $next_state  = $eff_active ? 0 : 1;
            $reason = is_null($u['verified_at'])
                     ? 'Email non verificata (login bloccato finché non verifichi o finché admin non convalida)'
                     : '';
          ?>
          <div class="cell attivo">
            <?php if ($u['username'] === 'valenzo2313'): ?>
              <button type="button" class="btn-state on" title="Utente sempre attivo" disabled>Sempre attivo</button>
              <?php if (is_null($u['verified_at'])): ?>
                <div class="note-warn">Email non verificata</div>
              <?php endif; ?>
            <?php else: ?>
              <input type="hidden" name="new_state" value="<?php echo $next_state; ?>">
              <button type="submit" name="action" value="toggle_active"
                      class="<?php echo $state_class; ?>"
                      title="<?php echo htmlspecialchars($reason); ?>">
                <?php echo $state_text; ?>
              </button>
              <?php if (is_null($u['verified_at'])): ?>
                <div class="note-warn">Email non verificata</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <div class="cell saldo">
            <input type="number" step="0.01" name="crediti" value="<?php echo htmlspecialchars((string)$u['crediti']); ?>">
          </div>

          <div class="cell pwd">
            <input type="password" name="new_password" placeholder="Reset (opzionale)">
          </div>

          <div class="cell azioni">
            <a class="btn" href="/admin/movimenti.php?user_id=<?php echo (int)$u['id']; ?>">Movimenti</a>
            <?php if ($u['username'] !== 'valenzo2313'): ?>
              <button type="button" class="btn btn-delete"
                      data-user-id="<?php echo (int)$u['id']; ?>"
                      data-user-name="<?php echo htmlspecialchars($u['username']); ?>">
                🗑 Elimina
              </button>
            <?php else: ?>
              <button type="button" class="btn" disabled style="opacity:.6;cursor:not-allowed;">Protetto</button>
            <?php endif; ?>
            <button class="btn btn-apply" type="submit" name="action" value="update_user">Applica modifiche</button>
          </div>

        </form>
      <?php endforeach; ?>

    </div><!-- /rows -->

    <!-- Paginazione compatta -->
    <div class="pag compact">
      <?php
        $pages = max(1, (int)ceil($total / $perPage));
        for ($p=1; $p<=$pages; $p++):
          $cls = ($p===$page) ? 'on' : '';
          $url = '/admin/dashboard.php?page='.$p
               .'&sort='.urlencode($sort)
               .'&dir='.urlencode($dir)
               .'&q='.urlencode($q);
      ?>
        <a class="<?php echo $cls; ?>" href="<?php echo $url; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
    </div>

  </div><!-- /card-table -->

  <!-- =======================
       POPUP ELIMINAZIONE (unico)
       ======================= -->
  <div id="deleteModal" class="modal" style="display:none;
       position:fixed; inset:0; background:rgba(0,0,0,.6);
       align-items:center; justify-content:center; z-index:9999;">
    <div class="modal-card" style="background:#111; border:1px solid #333; border-radius:12px; padding:16px; width:420px; color:#fff;">
      <h3 style="margin:0 0 8px; font-size:18px;">Elimina utente</h3>
      <p id="deleteText" style="margin:0 0 12px; color:#ddd;">
        Sei sicuro di voler eliminare definitivamente questo utente?
      </p>
      <div style="display:flex; gap:8px; justify-content:flex-end;">
        <button type="button" id="btnCancel" class="btn">Annulla</button>
        <!-- Il submit “Conferma” invia il form nascosto sotto -->
        <button type="button" id="btnConfirm" class="btn btn-apply" style="background:#e62329;border-color:#e62329;">Conferma</button>
      </div>
    </div>
  </div>

  <!-- Form nascosto che verrà popolare dal popup -->
  <form id="deleteForm" method="post" action="/admin/dashboard.php?page=<?php echo (int)$page; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&q=<?php echo urlencode($q); ?>" style="display:none">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="action" value="admin_delete_user">
    <input type="hidden" name="user_id" id="deleteUserId" value="">
  </form>
</main>

<?php require __DIR__ . '/../footer.php'; ?> <!-- footer -->
    <script>
  // [SCOPO] Aggancia i pulsanti "🗑 Elimina" e gestisce il popup di conferma

  // Elementi del popup e del form
  const modal = document.getElementById('deleteModal');
  const text  = document.getElementById('deleteText');
  const btnC  = document.getElementById('btnCancel');
  const btnOK = document.getElementById('btnConfirm');
  const form  = document.getElementById('deleteForm');
  const hidId = document.getElementById('deleteUserId');

  // Stato selezione corrente
  let currentUserId = null;

  // Apre il popup con i dati dell'utente
  function openDelete(userId, username) {
    currentUserId = userId;
    hidId.value = userId;
    text.textContent = `Sei sicuro di voler eliminare definitivamente l'utente "${username}"?`;
    modal.style.display = 'flex';
  }

  // Chiude il popup
  function closeDelete() {
    modal.style.display = 'none';
    currentUserId = null;
  }

  // Click sul cestino: data-* sugli elementi
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      const uid = btn.getAttribute('data-user-id');
      const un  = btn.getAttribute('data-user-name') || '';
      openDelete(uid, un);
    });
  });

  // Bottoni nel popup
  btnC.addEventListener('click', closeDelete);
  btnOK.addEventListener('click', () => form.submit());

  // Chiudi cliccando fuori dalla card
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeDelete();
  });

  // Esc per chiudere
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.style.display === 'flex') closeDelete();
  });
</script>
    <script>
  const f = document.querySelector('form.filters');
  const q = f.querySelector('input[name="q"]');
  let t = null;
  q.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => f.submit(), 400); // invia dopo 400ms che smetti di scrivere
  });
</script>
</body>
</html>
