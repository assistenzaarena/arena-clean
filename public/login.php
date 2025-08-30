<?php
// =====================================================
// login.php - Gestione login SENZA output prima degli header
// =====================================================

// [RIGA] 1) Avvia la sessione PRIMA di qualsiasi output
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); // necessario per $_SESSION e per session_regenerate_id()
}

// [RIGA] 2) Carica config e DB PRIMA di qualsiasi HTML
require_once __DIR__ . '/src/config.php'; // costanti, APP_ENV, ecc.
require_once __DIR__ . '/src/db.php';     // $pdo (PDO con prepared reali)

// [RIGA] 3) Se il form è stato inviato, gestisci il login (ancora nessun output!)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {             // esegui solo a submit
    $username = trim($_POST['username'] ?? '');          // normalizza input
    $password = $_POST['password'] ?? '';

// [RIGA] CERCA per username O email e leggi TUTTI i campi utili al flusso:
//        - password_hash  → verifica password
//        - role, totp_enabled → ramo admin (2FA)
//        - is_active      → blocco login se disabilitato
//        - verified_at    → blocco login se email non verificata
$stmt = $pdo->prepare(
    "SELECT id, password_hash, role, totp_enabled, is_active, verified_at
     FROM utenti
     WHERE username = :u1 OR email = :u2
     LIMIT 1"
); // prepared sicuro
$stmt->execute([
    ':u1' => $username,   // stesso dato per il match su username
    ':u2' => $username    // e per il match su email
]); // esecuzione
$user = $stmt->fetch();   // riga utente (o false)

if ($user && password_verify($password, $user['password_hash'])) {   // [RIGA] credenziali ok

    // --------------- BARRIERE DI STATO (ROSSO = LOGIN BLOCCATO) ---------------
    // [RIGA] 1) Account disabilitato dall'admin → stop login
    if ((int)$user['is_active'] !== 1) {                              // se flag is_active = 0
        $error = "Account disabilitato. Contatta il supporto.";       // messaggio chiaro all'utente
        // Nessun redirect, nessun session_regenerate_id: si limita a mostrare l'errore
    }
    // [RIGA] 2) Email non verificata → stop login
    elseif (is_null($user['verified_at'])) {                          // se non ha confermato l'email
        $error = "Email non verificata. Controlla la posta e clicca il link di attivazione."; 
    }
    // ---------------------------------------------------------------------------

    // [RIGA] Se abbiamo impostato $error sopra, NON proseguire con il login
    if (!empty($error)) {
        // Esci dal ramo senza eseguire session_regenerate_id() né redirect
        // (la pagina mostrerà il messaggio sopra il form)
    } else {
        // Da qui in poi login consentito: credenziali OK + attivo + email verificata
        session_regenerate_id(true);                                  // anti session fixation

        // --- admin con 2FA già attiva → chiedi codice
        if (($user['role'] ?? 'user') === 'admin' && !empty($user['totp_enabled'])) {
            $_SESSION['admin_pending_id'] = (int)$user['id'];         // id in pending per verifica 2FA
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']); // non completare ora
            header("Location: /admin/2fa_verify.php"); exit;          // vai alla verifica 2FA
        }

        // --- admin senza 2FA → forza setup
        if (($user['role'] ?? 'user') === 'admin') {
            $_SESSION['user_id']  = (int)$user['id'];                 // consenti setup
            $_SESSION['username'] = $username;
            $_SESSION['role']     = 'admin';
            header("Location: /admin/2fa_setup.php"); exit;           // vai al QR
        }

        // --- utente normale → area riservata
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['username'] = $username;
        $_SESSION['role']     = 'user';
        header("Location: /area_riservata.php"); exit;                // dentro
    }

} else {
    $error = "Credenziali errate";                                    // credenziali sbagliate
}

// [RIGA] 4) Da qui in poi puoi stampare HTML (header incluso): non ci sono più header() o session_regenerate_id()
// --------------------------------------------------------------------------------------------------------------
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_login.css">
  <link rel="stylesheet" href="/assets/login.css?v=7">
</head>
<body>

<?php 
// [RIGA] 5) Include dell’header SOLO ORA (dopo la logica e prima del main)
require __DIR__ . '/header_login.php';
?>

<main class="auth">
  <div class="auth__card">
    <h1 class="auth__title">Accedi</h1>

    <?php if (!empty($error)): ?>
      <p style="color:#c01818; margin:8px 0 12px;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post" action="">
      <div class="auth__group">
        <label class="auth__label">Email / Username</label>
        <input class="auth__input" type="text" name="username" autocomplete="username">
      </div>

      <div class="auth__group">
        <label class="auth__label">Password</label>
        <input class="auth__input" type="password" name="password" autocomplete="current-password">
      </div>

      <a class="auth__forgot" href="/recupero-password">Hai dimenticato la password?</a>

      <button class="auth__submit" type="submit">Accedi</button>
    </form>
  </div>

  <div class="auth__cta">
    <p>Non hai un account?</p>
    <a class="auth__register" href="/registrazione.php">Registrati</a>
  </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
