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

<?php
// Se l'utente ha inviato il form (metodo POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {                      // [RIGA] Eseguiamo la logica solo quando arriva una POST (submit del form)
    $username = trim($_POST['username'] ?? '');                   // [RIGA] Prendiamo lo username (o email/username), togliendo spazi ai lati
    $password = $_POST['password'] ?? '';                         // [RIGA] Prendiamo la password così com’è (niente trim per non alterare)

    // Cerchiamo l'utente nel DB (con ruolo e stato 2FA)
    $stmt = $pdo->prepare(                                        // [RIGA] Prepared statement per sicurezza (anti-SQL injection)
        "SELECT id, password_hash, role, totp_enabled             // [RIGA] Oltre a id e hash, leggiamo anche role (user/admin) e totp_enabled (0/1)
         FROM utenti
         WHERE username = :u
         LIMIT 1"
    );
    $stmt->execute([':u' => $username]);                          // [RIGA] Bind sicuro del parametro :u
    $user = $stmt->fetch();                                       // [RIGA] Recuperiamo la riga (o false se non trovata)

    // Verifica credenziali
    if ($user && password_verify($password, $user['password_hash'])) { // [RIGA] Se utente esiste e l’hash combacia → password corretta
        session_regenerate_id(true);                              // [RIGA] Anti session fixation: nuovo ID sessione dopo login

        // --- Caso ADMIN con 2FA già attiva: chiedo il codice TOTP prima di completare il login
        if (($user['role'] ?? 'user') === 'admin' && !empty($user['totp_enabled'])) { // [RIGA] Se è admin e ha 2FA abilitata (1)…
            $_SESSION['admin_pending_id'] = (int)$user['id'];     // [RIGA] Mettiamo l’ID in sessione *temporanea* per la verifica 2FA
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']); // [RIGA] Non completiamo il login ora: puliamo eventuali dati
            header("Location: /admin/2fa_verify.php");            // [RIGA] Reindirizziamo alla pagina che chiede il codice a 6 cifre
            exit;                                                 // [RIGA] Stop esecuzione
        }

        // --- Caso ADMIN ma 2FA NON attiva: obbligo setup 2FA
        if (($user['role'] ?? 'user') === 'admin') {              // [RIGA] Se è admin ma non ha totp_enabled=1…
            $_SESSION['user_id']  = (int)$user['id'];             // [RIGA] Logghiamo l’admin per permettere il setup
            $_SESSION['username'] = $username;                    // [RIGA] Nome da mostrare
            $_SESSION['role']     = 'admin';                      // [RIGA] Ruolo esplicito
            header("Location: /admin/2fa_setup.php");             // [RIGA] Lo portiamo alla pagina per generare QR e attivare 2FA
            exit;                                                 // [RIGA] Stop esecuzione
        }

        // --- Caso UTENTE NORMALE: login completo e redirect all’area riservata
        $_SESSION['user_id']  = (int)$user['id'];                 // [RIGA] Salviamo l’ID utente autenticato
        $_SESSION['username'] = $username;                        // [RIGA] Salviamo lo username per l’header/UX
        $_SESSION['role']     = 'user';                           // [RIGA] Ruolo standard
        header("Location: /area_riservata.php");                  // [RIGA] Portiamo l’utente nell’area privata
        exit;                                                     // [RIGA] Stop esecuzione

    } else {                                                      // [RIGA] Se non passa la verifica dell’hash…
        $error = "Credenziali errate";                            // [RIGA] Mostriamo errore generico (sicurezza: non dire cosa è sbagliato)
    }
}                                                                 // [RIGA] Fine gestione POST
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
