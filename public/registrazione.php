<?php
// [SCOPO] Pagina di registrazione con validazioni server-side, unicità,
//         password policy e verifica email con token.

// [RIGA] Avvio sessione solo se non attiva
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// [RIGA] Config + PDO
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

// [RIGA] Variabili di stato per messaggi
$errors = [];        // array con errori da mostrare sotto il form
$success = null;     // messaggio di successo (es. "controlla la tua email")

// [RIGA] Se form inviato in POST, processiamo i dati
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // [RIGA] Prelevo e normalizzo i campi (trim per rimuovere spazi ai lati)
    $nome      = trim($_POST['nome']      ?? '');
    $cognome   = trim($_POST['cognome']   ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $pass1     = $_POST['password']       ?? '';
    $pass2     = $_POST['password2']      ?? '';
    $accept_tc = isset($_POST['accept_tc']); // checkbox termini

    // ============== VALIDAZIONI BASE =================

    // [RIGA] Campi obbligatori
    if ($nome === '')      { $errors['nome'] = 'Il nome è obbligatorio.'; }
    if ($cognome === '')   { $errors['cognome'] = 'Il cognome è obbligatorio.'; }
    if ($username === '')  { $errors['username'] = 'Lo username è obbligatorio.'; }
    if ($email === '')     { $errors['email'] = 'L’email è obbligatoria.'; }
    if ($phone === '')     { $errors['phone'] = 'Il numero di cellulare è obbligatorio.'; }
    if ($pass1 === '')     { $errors['password'] = 'La password è obbligatoria.'; }
    if ($pass2 === '')     { $errors['password2'] = 'Conferma password obbligatoria.'; }
    if (!$accept_tc)       { $errors['accept_tc'] = 'Devi accettare Termini e Condizioni.'; }

    // [RIGA] Email formale
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email non valida.';
    }

    // [RIGA] Telefono: accettiamo + cifre, spazi e trattini semplici (semplificato)
    if ($phone !== '' && !preg_match('/^\+?[0-9\- ]{7,20}$/', $phone)) {
        $errors['phone'] = 'Numero non valido.';
    }

    // [RIGA] Username: solo lettere, numeri, underscore e punto (ad es.)
    if ($username !== '' && !preg_match('/^[a-zA-Z0-9._]{3,30}$/', $username)) {
        $errors['username'] = 'Username non valido (3-30 caratteri, lettere/numeri/._).';
    }

    // [RIGA] Password policy:
    //  - min 8 caratteri
    //  - almeno 1 maiuscola
    //  - almeno 1 minuscola
    //  - almeno 1 numero
    //  - almeno 1 carattere speciale tra !$%&.,;)
    $policyOk = true;
    if (strlen($pass1) < 8)                          { $policyOk = false; }
    if (!preg_match('/[A-Z]/', $pass1))              { $policyOk = false; }
    if (!preg_match('/[a-z]/', $pass1))              { $policyOk = false; }
    if (!preg_match('/\d/', $pass1))                 { $policyOk = false; }
    if (!preg_match('/[!\$%&\.\,\;\)]/', $pass1))    { $policyOk = false; } // notare escape di $
    if (!$policyOk) {
        $errors['password'] = 'Password non conforme (8+, 1 maiuscola, 1 minuscola, 1 numero, 1 tra !$%&.,; ).';
    }

    // [RIGA] Le due password devono coincidere
    if ($pass1 !== $pass2) {
        $errors['password2'] = 'Le password non coincidono.';
    }
    <?php
// [FUNZIONE] Genera una stringa di 5 cifre (con zeri davanti se servono), es: "04219"
function make_user_code_5(): string {
    // numeri da 0 a 99999, poi zero-pad a 5 cifre
    return str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
}

// [LOGICA] Tenta di generare un codice univoco (max 10 tentativi)
$user_code = null;                                            // inizialmente nessuno
for ($i = 0; $i < 10; $i++) {                                 // al massimo 10 tentativi
    $try = make_user_code_5();                                // genero un candidato
    $chk = $pdo->prepare("SELECT 1 FROM utenti WHERE user_code = :c LIMIT 1"); // controllo collisione
    $chk->execute([':c' => $try]);                            // eseguo
    if (!$chk->fetch()) {                                     // se non esiste già…
        $user_code = $try;                                    // lo accetto
        break;                                                // e finisco
    }
}

if ($user_code === null) {                                    // se per caso 10 tentativi falliscono
    http_response_code(500);
    die('Impossibile generare un codice utente univoco al momento. Riprova.'); // messaggio chiaro
}

    // ============== UNICITÀ (DB) =================

    if (!$errors) {
        // [RIGA] Verifica username unico
        $q = $pdo->prepare('SELECT 1 FROM utenti WHERE username = :u LIMIT 1');
        $q->execute([':u' => $username]);
        if ($q->fetch()) { $errors['username'] = 'Username già in uso.'; }

        // [RIGA] Verifica email unica
        $q = $pdo->prepare('SELECT 1 FROM utenti WHERE email = :e LIMIT 1');
        $q->execute([':e' => $email]);
        if ($q->fetch()) { $errors['email'] = 'Email già registrata.'; }

        // [RIGA] Verifica telefono unico
        $q = $pdo->prepare('SELECT 1 FROM utenti WHERE phone = :p LIMIT 1');
        $q->execute([':p' => $phone]);
        if ($q->fetch()) { $errors['phone'] = 'Numero già registrato.'; }
    }

    // ============== INSERIMENTO =================

    if (!$errors) {
        // [RIGA] Hash sicuro password
        $hash = password_hash($pass1, PASSWORD_DEFAULT);

        // [RIGA] Token verifica email (esadecimale 32 char)
        $token = bin2hex(random_bytes(16));

    // [RIGA] Insert nuovo utente con NOME e COGNOME inclusi
//        NOTA: prima non li salvavamo, per questo risultavano vuoti in dashboard.
// [RIGA] Insert nuovo utente con NOME e COGNOME inclusi
//        e con is_active = 0 (disattivo finché non verifica l’email o lo attiva admin)
$ins = $pdo->prepare('
    INSERT INTO utenti (
        nome,              -- nome dell’utente
        cognome,           -- cognome dell’utente
        username,          -- username univoco
        password_hash,     -- hash della password
        email,             -- email univoca
        phone,             -- telefono univoco
        crediti,           -- saldo iniziale (0)
        verification_token,-- token verifica email
        verified_at,       -- NULL finché non verifica
        is_active,         -- [NEW] 0 = disattivo
        created_at         -- timestamp creazione
    )
    VALUES (
        :n,                -- bind nome
        :c,                -- bind cognome
        :u,                -- bind username
        :h,                -- bind hash password
        :e,                -- bind email
        :p,                -- bind telefono
        0,                 -- crediti iniziali
        :t,                -- bind token verifica
        NULL,              -- non verificato all’inizio
        0,                 -- [NEW] disattivo all’inizio
        NOW()              -- creato ora
    )
');

// [RIGA] Esecuzione con TUTTI i parametri, inclusi nome e cognome
$ins->execute([
    ':n' => $nome,         // [NEW] nome dal form
    ':c' => $cognome,      // [NEW] cognome dal form
    ':u' => $username,
    ':h' => $hash,
    ':e' => $email,
    ':p' => $phone,
    ':t' => $token,
]);

        // [RIGA] Link di verifica per attivare l’account
        $verifyUrl = sprintf(
            '%s://%s/verify_email.php?token=%s',
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
            $_SERVER['HTTP_HOST'],
            urlencode($token)
        );

        // [RIGA] Invio email: in questa fase di sviluppo mostriamo il link a video se non siamo in production.
        //        Per la produzione, configureremo SMTP e invieremo davvero.
        if (APP_ENV !== 'production') {
            $success = "Registrazione ok. Verifica la tua email: $verifyUrl";
        } else {
            // TODO: invio email reale (SMTP) con $verifyUrl
            $success = "Registrazione ok. Controlla la tua email per attivare l'account.";
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Registrazione</title>
  <!-- [RIGA] CSS base + stesso stile della pagina login -->
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_login.css">
  <link rel="stylesheet" href="/assets/login.css?v=7"><!-- riuso layout/card login -->
    <link rel="stylesheet" href="/assets/registrazione.css?v=1">
  <style>
    /* [RIGA] Micro differenza: titolo e bottone "Registrati" */
    .auth__submit { background:#00c074; }       /* verde */
.auth__submit:hover { background:#00a862; }
  </style>
</head>
<body>

<?php require __DIR__ . '/header_login.php'; ?><!-- [RIGA] Header minimal login -->

<main class="auth"><!-- [RIGA] Wrapper layout verticale già usato dalla login -->

  <div class="auth__card"><!-- [RIGA] Card bianca identica alla login -->
    <h1 class="auth__title">Crea il tuo account</h1>

<?php if ($success): ?>
  <p class="success-msg"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>

<?php if ($errors): ?>
  <ul class="error-list">
    <?php foreach ($errors as $msg): ?>
      <li><?php echo htmlspecialchars($msg); ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

    <form method="post" action=""><!-- [RIGA] Form POST sulla stessa pagina -->

      <div class="auth__group"><!-- [RIGA] Nome -->
        <label class="auth__label">Nome</label>
        <input class="auth__input" type="text" name="nome" value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>">
      </div>

      <div class="auth__group"><!-- [RIGA] Cognome -->
        <label class="auth__label">Cognome</label>
        <input class="auth__input" type="text" name="cognome" value="<?php echo htmlspecialchars($_POST['cognome'] ?? ''); ?>">
      </div>

      <div class="auth__group"><!-- [RIGA] Username -->
        <label class="auth__label">Username</label>
        <input class="auth__input" type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
      </div>

      <div class="auth__group"><!-- [RIGA] Email -->
        <label class="auth__label">Email</label>
        <input class="auth__input" type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>

      <div class="auth__group"><!-- [RIGA] Cellulare -->
        <label class="auth__label">Numero di cellulare</label>
        <input class="auth__input" type="text" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
      </div>

      <div class="auth__group"><!-- [RIGA] Password -->
        <label class="auth__label">Password</label>
        <input class="auth__input" type="password" name="password" autocomplete="new-password">
        <small style="color:#555;">Min 8, 1 maiuscola, 1 minuscola, 1 numero, 1 tra !$%&.,;</small>
      </div>

      <div class="auth__group"><!-- [RIGA] Conferma password -->
        <label class="auth__label">Conferma password</label>
        <input class="auth__input" type="password" name="password2" autocomplete="new-password">
      </div>

     <div class="auth__group" style="display:flex; align-items:center; gap:8px;">
  <input id="accept_tc" type="checkbox" name="accept_tc" <?php echo isset($_POST['accept_tc'])?'checked':''; ?>>
  <label for="accept_tc" class="auth__label" style="margin:0; font-size:13px;">
    Accetto i <a href="/termini-e-condizioni" target="_blank">Termini e Condizioni</a>
  </label>
</div>

      <button class="auth__submit" type="submit">Registrati</button><!-- [RIGA] CTA -->
    </form>
  </div>

  <div class="auth__cta"><!-- [RIGA] Link alla login se già account -->
    <p>Hai già un account?</p>
    <a class="auth__register" href="/login.php">Accedi</a>
  </div>

</main>

<?php require __DIR__ . '/footer.php'; ?><!-- [RIGA] Footer unico -->

</body>
</html>
