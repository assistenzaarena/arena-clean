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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {   // [POST] azioni dashboard
    // --- CSRF ---
    $posted_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
        http_response_code(400);
        die('CSRF non valido');
    }

    // --- action & user_id ---
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // ==========================================================
    // 1) TOGGLE ATTIVO/DISATTIVO
    // ==========================================================
    if ($action === 'toggle_active' && $user_id > 0) {
        // blocco utente speciale
        $chk = $pdo->prepare("SELECT username FROM utenti WHERE id = :id LIMIT 1");
        $chk->execute([':id' => $user_id]);
        if ($chk->fetchColumn() === 'valenzo2313') {
            $_SESSION['flash'] = 'Questo utente non può essere disattivato.';
            $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
            header("Location: /admin/dashboard.php?$query"); exit;
        }

        $new_state = (int)($_POST['new_state'] ?? 0);
        $up = $pdo->prepare("UPDATE utenti SET is_active = :a WHERE id = :id");
        $up->execute([':a'=>$new_state, ':id'=>$user_id]);

        $_SESSION['flash'] = $new_state ? 'Utente attivato.' : 'Utente disattivato.';
        $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
        header("Location: /admin/dashboard.php?$query"); exit;
    }

    // ==========================================================
    // 2) UPDATE DATI UTENTE (NO is_active qui!)
    // ==========================================================
    if ($action === 'update_user' && $user_id > 0) {
        $nome     = trim($_POST['nome'] ?? '');
        $cognome  = trim($_POST['cognome'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $saldo    = $_POST['crediti'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';

        // validazioni
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email non valida.'; }
        if ($phone !== '' && !preg_match('/^\+?[0-9\- ]{7,20}$/', $phone)) { $errors[] = 'Numero di telefono non valido.'; }
        if ($saldo === '' || !is_numeric($saldo)) { $errors[] = 'Saldo non valido.'; }
        if ($new_pass !== '' && strlen($new_pass) < 8) { $errors[] = 'La nuova password deve avere almeno 8 caratteri.'; }

        if (!$errors) {
            // base
            $sql = "UPDATE utenti
                       SET nome=:nome, cognome=:cognome, email=:email, phone=:phone, crediti=:crediti
                     WHERE id=:id";
            $params = [
                ':nome'=>$nome, ':cognome'=>$cognome, ':email'=>$email,
                ':phone'=>$phone, ':crediti'=>(float)$saldo, ':id'=>$user_id
            ];

            // reset password opzionale
            if ($new_pass !== '') {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $sql = "UPDATE utenti
                           SET nome=:nome, cognome=:cognome, email=:email, phone=:phone, crediti=:crediti, password_hash=:hash
                         WHERE id=:id";
                $params[':hash'] = $hash;
            }

            $up = $pdo->prepare($sql);
            $up->execute($params);

            $_SESSION['flash'] = 'Modifiche salvate.';
            $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
            header("Location: /admin/dashboard.php?$query"); exit;
        }
    }

    // ==========================================================
    // 3) ADMIN VERIFY EMAIL (convalida manuale)
    // ==========================================================
    if ($action === 'admin_verify_email' && $user_id > 0) {
        $up = $pdo->prepare("UPDATE utenti
                             SET verified_at = NOW(), verification_token = NULL, is_active = 1
                             WHERE id = :id");
        $up->execute([':id'=>$user_id]);

        $_SESSION['flash'] = 'Email convalidata e utente attivato.';
        $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
        header("Location: /admin/dashboard.php?$query"); exit;
    }

    // ==========================================================
    // 4) ELIMINA UTENTE (con protezioni)
    // ==========================================================
    if ($action === 'admin_delete_user' && $user_id > 0) {
        // non posso eliminare me stesso
        if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $user_id) {
            $_SESSION['flash'] = 'Non puoi eliminare il tuo stesso account.';
            $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
            header("Location: /admin/dashboard.php?$query"); exit;
        }
        // non posso eliminare l’utente speciale
        $chk = $pdo->prepare("SELECT username FROM utenti WHERE id = :id LIMIT 1");
        $chk->execute([':id'=>$user_id]);
        if ($chk->fetchColumn() === 'valenzo2313') {
            $_SESSION['flash'] = 'L’utente speciale non può essere eliminato.';
            $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
            header("Location: /admin/dashboard.php?$query"); exit;
        }

        $del = $pdo->prepare("DELETE FROM utenti WHERE id = :id");
        $del->execute([':id'=>$user_id]);

        $_SESSION['flash'] = 'Utente eliminato definitivamente.';
        $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
        header("Location: /admin/dashboard.php?$query"); exit;
    }
} // <-- unica graffa di chiusura del blocco POST

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
