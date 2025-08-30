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

// Se l'utente ha inviato il form (metodo POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? ''; // leggiamo username dal form
    $password = $_POST['password'] ?? ''; // leggiamo password dal form

    // Cerchiamo l'utente nel DB
    $stmt = $pdo->prepare("SELECT id, password_hash, crediti FROM utenti WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Password corretta → login riuscito
        session_regenerate_id(true);           // rigenera session ID (anti fixation)
        $_SESSION['user_id'] = $user['id'];    // salviamo ID in sessione
        $_SESSION['username'] = $username;     // salviamo username
        header("Location: /area_riservata.php"); // redirect a pagina protetta
        exit;
    } else {
        $error = "Credenziali errate";
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8"><title>Login</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_login.css">
  <link rel="stylesheet" href="/assets/login.css?v=6">
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
