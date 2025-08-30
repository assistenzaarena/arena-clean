<?php
// [SCOPO] Verifica email: riceve ?token=... e attiva l'account associato.

// [RIGA] Config + DB
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

// [RIGA] Leggo token da GET
$token = $_GET['token'] ?? '';

// [RIGA] Se token mancante → 400
if ($token === '') {
    http_response_code(400);
    die('Token mancante.');
}

try {
    // [RIGA] Trovo utente con quel token
    $q = $pdo->prepare('SELECT id FROM utenti WHERE verification_token = :t LIMIT 1');
    $q->execute([':t' => $token]);
    $row = $q->fetch();

    if (!$row) {
        // [RIGA] Token non valido
        http_response_code(400);
        die('Token non valido o già usato.');
    }

    // [RIGA] Attivo l'account: set verified_at e pulisco il token
    $u = $pdo->prepare('UPDATE utenti SET verified_at = NOW(), verification_token = NULL WHERE id = :id');
    $u->execute([':id' => $row['id']]);

    // [RIGA] Messaggio finale
    echo 'Account verificato. Ora puoi accedere: <a href="/login.php">Login</a>';
} catch (Throwable $e) {
    http_response_code(500);
    echo (APP_ENV !== 'production') ? 'Errore: ' . htmlspecialchars($e->getMessage()) : 'Errore interno.';
}
