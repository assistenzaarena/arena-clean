<?php
// [SCOPO] Pagina protetta: visibile solo se loggato.
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}
?>
<!doctype html>
<html lang="it">
<head><meta charset="utf-8"><title>Area Riservata</title></head>
<body>
<h1>Ciao <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
<p>Benvenuto nell'area riservata. Qui potremo mostrare i crediti, ecc.</p>
<p><a href="/logout.php">Logout</a></p>
</body>
</html>
