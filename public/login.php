<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require __DIR__ . '/header_min.php';   // <-- usa il mini header per il test  <!-- include header dinamico -->
<?php
// [SCOPO] Pagina di login: mostra un form e autentica l'utente demo (o futuri utenti da DB).

require_once __DIR__ . '/src/config.php'; // costanti APP_ENV, DB_*
require_once __DIR__ . '/src/db.php';     // connessione PDO
session_start();                          // abilitiamo sessioni

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
<head><meta charset="utf-8"><title>Login</title></head>
<body>
<h1>Login</h1>
<?php if (!empty($error)) echo "<p style='color:red'>$error</p>"; ?>
<form method="post" action="">
  <label>Username: <input type="text" name="username"></label><br>
  <label>Password: <input type="password" name="password"></label><br>
  <button type="submit">Accedi</button>
</form>
</body>
</html>
