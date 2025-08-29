<?php
// [SCOPO] Pagina protetta che mostra username + saldo crediti.

session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

require_once __DIR__ . '/src/config.php'; // costanti DB
require_once __DIR__ . '/src/db.php';     // connessione PDO

// Recuperiamo i crediti dell'utente loggato
$stmt = $pdo->prepare("SELECT crediti FROM utenti WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();
$crediti = $user ? $user['crediti'] : 0;
?>
<!doctype html>
<html lang="it">
<head><meta charset="utf-8"><title>Area Riservata</title></head>
<body>
<h1>Ciao <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
<p>Saldo crediti attuale: <strong><?php echo $crediti; ?></strong> 💰</p>
<p><a href="/logout.php">Logout</a></p>
</body>
</html>
