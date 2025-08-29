<?php require __DIR__ . '/header.php'; ?>  <!-- include header dinamico -->
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
// ======================================================
// NUOVA FUNZIONE: Gestione ricarica crediti
// ======================================================

// [RIGA] Se il form è stato inviato (metodo POST con campo "ricarica")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ricarica'])) {
    // [RIGA] Convertiamo il valore ricevuto in numero intero (evita injection tipo "10; DROP TABLE")
    $ricarica = (int) $_POST['ricarica'];

    // [RIGA] Aggiorniamo i crediti nel DB SOLO se > 0
    if ($ricarica > 0) {
        $stmt = $pdo->prepare("UPDATE utenti SET crediti = crediti + :r WHERE id = :id");
        $stmt->execute([
            ':r' => $ricarica,
            ':id' => $_SESSION['user_id']
        ]);

        // [RIGA] Aggiorniamo anche la variabile locale, così vediamo subito il saldo aggiornato
        $crediti += $ricarica;
    }
}
?>
<!doctype html>
<html lang="it">
<head><meta charset="utf-8"><title>Area Riservata</title></head>
<body>
<h1>Ciao <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
<p>Saldo crediti attuale: <strong><?php echo $crediti; ?></strong> 💰</p>
    <!-- [NUOVO] Pulsante che porta alla pagina dedicata di ricarica -->
<p>
  <a href="/ricarica.php" style="display:inline-block;padding:8px 12px;background:#b00;color:#fff;text-decoration:none;border-radius:6px;">
    Ricarica
  </a>
</p>
<!-- Motivo: separiamo la UX della ricarica su una pagina dedicata,
     così potremo integrare Stripe senza toccare l’area riservata -->
<p><a href="/logout.php">Logout</a></p>
</body>
</html>
