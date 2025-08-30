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
    // ==========================================================
    if ($action === 'update_user' && $user_id > 0) {
        // [INPUT] Recupero i campi dal form
        $nome      = trim($_POST['nome'] ?? '');
        $cognome   = trim($_POST['cognome'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;  // compatibilità: se c’è ancora il vecchio checkbox
        $saldo     = $_POST['crediti'] ?? '';
        $new_pass  = $_POST['new_password'] ?? '';

        // [SICUREZZA] Se sto aggiornando l'utente "valenzo2313", forzo is_active=1 (sempre attivo)
$chk = $pdo->prepare("SELECT username FROM utenti WHERE id = :id LIMIT 1"); // prendo username
$chk->execute([':id' => $user_id]);
if ($chk->fetchColumn() === 'valenzo2313') { $is_active = 1; }              // non permetto di portarlo a 0

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
            $sql = "UPDATE utenti 
                    SET nome = :nome, cognome = :cognome, email = :email, phone = :phone, is_active = :active, crediti = :crediti 
                    WHERE id = :id";
            $params = [
                ':nome'    => $nome,
                ':cognome' => $cognome,
                ':email'   => $email,
                ':phone'   => $phone,
                ':active'  => $is_active,
                ':crediti' => (float)$saldo,
                ':id'      => $user_id,
            ];

            // [PASSWORD] Se admin ha chiesto reset password → rigenero hash
            if ($new_pass !== '') {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $sql = "UPDATE utenti 
                        SET nome = :nome, cognome = :cognome, email = :email, phone = :phone, is_active = :active, crediti = :crediti, password_hash = :hash 
                        WHERE id = :id";
                $params[':hash'] = $hash;
            }

            // [SQL] Eseguo l’update
            $up = $pdo->prepare($sql);
            $up->execute($params);

            // [UX] Messaggio di conferma
            $flash = 'Modifiche salvate.';

            // [PRG] Redirect per ricaricare la pagina e riflettere i dati aggiornati
            $_SESSION['flash'] = $flash;
            $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
            header("Location: /admin/dashboard.php?$query");
            exit;
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
}
    // ==========================================================
    // 4. ELIMINA UTENTE (nuovo)
    // ==========================================================
    if ($action === 'admin_delete_user' && $user_id > 0) {
        // [SICUREZZA] Evita che l'admin si cancelli da solo
        if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $user_id) {
            $_SESSION['flash'] = 'Non puoi eliminare il tuo stesso account.';
            $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
            header("Location: /admin/dashboard.php?$query"); exit;
        }

        // [SQL] Cancella l'utente dal DB
        $del = $pdo->prepare("DELETE FROM utenti WHERE id = :id");
        $del->execute([':id' => $user_id]);

        // [UX] Messaggio e PRG redirect
        $_SESSION['flash'] = 'Utente eliminato definitivamente.';
        $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
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
$where = "1=1";                                                                 // base: nessun filtro
$params = [];                                                                    // parametri bind

if ($q !== '') {                                                                // se c’è ricerca, aggiungiamo filtro LIKE su più campi
    $where .= " AND (nome LIKE :q OR cognome LIKE :q OR username LIKE :q OR email LIKE :q OR phone LIKE :q)";
    $params[':q'] = '%' . $q . '%';                                             // pattern
}

// [RIGA] Conteggio totale per paginazione
// [RIGA] Conteggio totale per paginazione (bind solo se esistono parametri)
$countSql  = "SELECT COUNT(*) FROM utenti WHERE $where";
$countStmt = $pdo->prepare($countSql);
// Se abbiamo il parametro :q (ricerca), lo bindi
foreach ($params as $k => $v) { $countStmt->bindValue($k, $v, PDO::PARAM_STR); }
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

// [RIGA] Lista utenti con ordinamento e paginazione (bind :q, :lim, :off)
$listSql  = "SELECT id, nome, cognome, username, email, phone, crediti, is_active, verified_at
             FROM utenti
             WHERE $where
             ORDER BY $sort " . strtoupper($dir) . "
             LIMIT :lim OFFSET :off";
$listStmt = $pdo->prepare($listSql);
// Bind della ricerca (se presente)
foreach ($params as $k => $v) { $listStmt->bindValue($k, $v, PDO::PARAM_STR); }
// Bind della paginazione
$listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':off', $offset,  PDO::PARAM_INT);
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

  <!-- Filtro / Ricerca / Ordinamento -->
 <!-- Filtro / Ricerca -->
<form class="filters" method="get" action="/admin/dashboard.php">
  <input type="text"
         name="q"
         placeholder="Cerca (nome, cognome, user, email, telefono, crediti)"
         value="<?php echo htmlspecialchars($q); ?>"><!-- ricerca -->
  <!-- Manteniamo ordinamento e direzione correnti come hidden -->
  <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
  <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir); ?>">
</form>

  <!-- Tabella utenti -->
  <table>
<thead>
  <?php
    // Funzione helper inline: costruisce URL con sort/dir aggiornati e mantiene page/q
    function sort_url($field, $currentSort, $currentDir, $page, $q) {
        // Se clicco la colonna già attiva, inverto la direzione; altrimenti metto ASC
        $dir = ($currentSort === $field)
             ? (strtolower($currentDir) === 'asc' ? 'desc' : 'asc')
             : 'asc';
        return '/admin/dashboard.php?' . http_build_query([
            'page' => (int)$page,
            'sort' => $field,
            'dir'  => $dir,
            'q'    => $q,
        ]);
    }
    // Helper per mostrare una piccola freccia ↑↓ sulla colonna attiva
    function sort_caret($field, $currentSort, $currentDir) {
        if ($currentSort !== $field) return '';
        return strtolower($currentDir) === 'asc' ? ' ↑' : ' ↓';
    }
  ?>
  <tr>
    <th><a href="<?php echo sort_url('nome', $sort, $dir, $page, $q); ?>">Nome<?php echo sort_caret('nome', $sort, $dir); ?></a></th>
    <th><a href="<?php echo sort_url('cognome', $sort, $dir, $page, $q); ?>">Cognome<?php echo sort_caret('cognome', $sort, $dir); ?></a></th>
    <th><a href="<?php echo sort_url('username', $sort, $dir, $page, $q); ?>">User<?php echo sort_caret('username', $sort, $dir); ?></a></th>
    <th><a href="<?php echo sort_url('email', $sort, $dir, $page, $q); ?>">Email<?php echo sort_caret('email', $sort, $dir); ?></a></th>
    <th><a href="<?php echo sort_url('phone', $sort, $dir, $page, $q); ?>">Telefono<?php echo sort_caret('phone', $sort, $dir); ?></a></th>
    <th>Attivo</th>
    <th><a href="<?php echo sort_url('crediti', $sort, $dir, $page, $q); ?>">Saldo €<?php echo sort_caret('crediti', $sort, $dir); ?></a></th>
    <th>Nuova password</th>
    <th>Azioni</th>
  </tr>
</thead>
    <tbody>
 <?php foreach ($users as $u): ?>
  <tr>
    <!-- Ogni riga è un form indipendente:
         - submit con name="action" value="toggle_active" per cambiare stato
         - submit con name="action" value="update_user" per salvare i campi -->
    <form method="post" action="/admin/dashboard.php?page=<?php echo (int)$page; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&q=<?php echo urlencode($q); ?>">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><!-- CSRF token -->
      <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>"><!-- id riga -->

      <td>
        <input type="text" name="nome"
               value="<?php echo htmlspecialchars($u['nome'] ?? ''); ?>"><!-- nome editabile -->
      </td>

      <td>
        <input type="text" name="cognome"
               value="<?php echo htmlspecialchars($u['cognome'] ?? ''); ?>"><!-- cognome editabile -->
      </td>

      <td>
        <?php echo htmlspecialchars($u['username']); ?><!-- username non editabile -->
      </td>

      <td>
        <input type="email" name="email"
               value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"><!-- email editabile -->
      </td>

      <td>
        <input type="tel" name="phone"
               value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>"><!-- telefono editabile -->
      </td>

      <?php
        // Stato bottone = SOLO is_active (verde/rosso). La verifica email NON influenza il colore del bottone.
        // N.B. Il LOGIN resta comunque bloccato se verified_at è NULL (controllo in login.php).
        $eff_active  = ((int)$u['is_active'] === 1);                 // true se attivo, false se disattivo
        $state_text  = $eff_active ? 'Attivo' : 'Inattivo';          // label bottone
        $state_class = $eff_active ? 'btn-state on' : 'btn-state off'; // classe CSS bottone
        $next_state  = $eff_active ? 0 : 1;                          // nuovo stato al click
        // Tooltip: se l'email non è verificata, lo spieghiamo ma NON influenziamo il colore
        $reason = is_null($u['verified_at'])
                  ? 'Email non verificata (login bloccato finché non verifichi o finché admin non convalida)'
                  : '';
      ?>
      <td>
        <?php if ($u['username'] === 'valenzo2313'): ?>
          <!-- UTENTE SPECIALE: sempre attivo, toggle disabilitato in UI -->
          <button type="button" class="btn-state on" title="Utente sempre attivo" disabled>Sempre attivo</button>
          <?php if (is_null($u['verified_at'])): ?>
            <div style="font-size:11px;color:#ff9090;margin-top:4px;">Email non verificata</div>
          <?php endif; ?>
        <?php else: ?>
          <!-- hidden con il nuovo stato da applicare al toggle -->
          <input type="hidden" name="new_state" value="<?php echo $next_state; ?>">
          <!-- Pulsante stato: submit con action specifica -->
          <button type="submit"
                  name="action" value="toggle_active"
                  class="<?php echo $state_class; ?>"
                  title="<?php echo htmlspecialchars($reason); ?>">
            <?php echo $state_text; ?>
          </button>
          <?php if (is_null($u['verified_at'])): ?>
            <div style="font-size:11px;color:#ff9090;margin-top:4px;">Email non verificata</div>
          <?php endif; ?>
        <?php endif; ?>
      </td>

      <td>
        <input type="number" step="0.01" name="crediti"
               value="<?php echo htmlspecialchars((string)$u['crediti']); ?>"><!-- saldo editabile -->
      </td>

      <td>
        <input type="password" name="new_password" placeholder="Reset (opzionale)"><!-- reset password -->
      </td>

      <td class="actions">
        <a class="btn" href="/admin/movimenti.php?user_id=<?php echo (int)$u['id']; ?>">Movimenti</a>
        <!-- Salvataggio campi della riga -->
        <button class="btn btn-apply" type="submit" name="action" value="update_user">Applica modifiche</button>
      </td>
    </form>
  </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <!-- Paginazione semplice -->
  <div class="pag">
    <?php
      $pages = max(1, (int)ceil($total / $perPage));                       // numero pagine
      for ($p=1; $p<=$pages; $p++):
        $cls = ($p===$page) ? 'on' : '';
        $url = '/admin/dashboard.php?page='.$p.'&sort='.urlencode($sort).'&dir='.urlencode($dir).'&q='.urlencode($q);
    ?>
      <a class="<?php echo $cls; ?>" href="<?php echo $url; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>
  </div>
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
