<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}// avvia sessione prima di ogni output
require __DIR__ . '/header_login.php';?>
<?php
// [SCOPO] Pagina di login: mostra un form e autentica l'utente demo (o futuri utenti da DB).

require_once __DIR__ . '/src/config.php'; // costanti APP_ENV, DB_*
require_once __DIR__ . '/src/db.php';     // connessione PDO
                         // abilitiamo sessioni
?>
<?php
// Se l'utente ha inviato il form (metodo POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {                          // [RIGA] Eseguiamo solo quando il form viene inviato
    $username = trim($_POST['username'] ?? '');                       // [RIGA] Username/email (riduciamo spazi ai lati)
    $password = $_POST['password'] ?? '';                            // [RIGA] Password (non usiamo trim)
?>
<?php
// [RIGA] Query pulita: cerca sia per username che per email
$stmt = $pdo->prepare(
    "SELECT id, password_hash, role, totp_enabled
     FROM utenti
     WHERE username = :u OR email = :u
     LIMIT 1"
); // [RIGA] Così se l’utente scrive l’email funziona uguale
$stmt->execute([':u' => $username]); // [RIGA] Bind unico: usiamo lo stesso dato per username/email
$user = $stmt->fetch();              // [RIGA] Riga utente (o false se non trovata)

    // [RIGA] Verifica credenziali
    if ($user && password_verify($password, $user['password_hash'])) {// [RIGA] Hash combacia → credenziali ok
        session_regenerate_id(true);                                  // [RIGA] Anti session fixation

        // [RIGA] Caso ADMIN con 2FA già attiva → chiedi codice a 6 cifre
        if (($user['role'] ?? 'user') === 'admin' && !empty($user['totp_enabled'])) {
            $_SESSION['admin_pending_id'] = (int)$user['id'];         // [RIGA] Mettiamo l'id in “pending”
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']); // [RIGA] Non completiamo il login ora
            header("Location: /admin/2fa_verify.php");                // [RIGA] Vai alla pagina di verifica TOTP
            exit;
        }

        // [RIGA] Caso ADMIN ma 2FA NON attiva → forza setup 2FA
        if (($user['role'] ?? 'user') === 'admin') {
            $_SESSION['user_id']  = (int)$user['id'];                 // [RIGA] Logghiamo l’admin per permettere il setup
            $_SESSION['username'] = $username;
            $_SESSION['role']     = 'admin';
            header("Location: /admin/2fa_setup.php");                 // [RIGA] Vai a generare QR e attivare 2FA
            exit;
        }

        // [RIGA] Caso UTENTE normale → login completo
        $_SESSION['user_id']  = (int)$user['id'];                     // [RIGA] Salviamo id
        $_SESSION['username'] = $username;                            // [RIGA] Salviamo username
        $_SESSION['role']     = 'user';                               // [RIGA] Ruolo standard
        header("Location: /area_riservata.php");                      // [RIGA] Vai all’area privata
        exit;

    } else {
        $error = "Credenziali errate";                                // [RIGA] Errore generico per sicurezza
    }
} // fine POST
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8"><title>Login</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_login.css">
  <link rel="stylesheet" href="/assets/login.css?v=7">
</head>
<body>
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
