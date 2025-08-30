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

    // [RIGA] Query: cerca sia per username sia per email
    $stmt = $pdo->prepare(
        "SELECT id, password_hash, role, totp_enabled
         FROM utenti
         WHERE username = :u1 OR email = :u2
         LIMIT 1"
    );
    $stmt->execute([
        ':u1' => $username, // match su username
        ':u2' => $username  // match su email
    ]);
    $user = $stmt->fetch(); // riga trovata (o false)

    // [RIGA] Verifica credenziali
    if ($user && password_verify($password, $user['password_hash'])) {
        // [RIGA] Sicurezza: rigenera l'ID sessione dopo login
        session_regenerate_id(true);

        // [RIGA] Branch ADMIN con 2FA attiva → chiedi codice
        if (($user['role'] ?? 'user') === 'admin' && !empty($user['totp_enabled'])) {
            $_SESSION['admin_pending_id'] = (int)$user['id'];         // metti in pending
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']); // non completare login
            header("Location: /admin/2fa_verify.php"); exit;          // redirect e STOP
        }

        // [RIGA] Branch ADMIN senza 2FA → forza setup
        if (($user['role'] ?? 'user') === 'admin') {
            $_SESSION['user_id']  = (int)$user['id'];  // completa login per permettere setup
            $_SESSION['username'] = $username;
            $_SESSION['role']     = 'admin';
            header("Location: /admin/2fa_setup.php"); exit;           // redirect e STOP
        }

        // [RIGA] Branch UTENTE normale → login completo
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['username'] = $username;
        $_SESSION['role']     = 'user';
        header("Location: /area_riservata.php"); exit;                // redirect e STOP

    } else {
        // [RIGA] Credenziali errate → memorizza messaggio, ma NON fare output qui
        $error = "Credenziali errate";
    }
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
