<?php
// [SCOPO] Leggere tutti gli utenti dalla tabella e mostrarli a schermo (debug/test).

require_once __DIR__ . '/src/config.php'; // carica costanti DB_*
require_once __DIR__ . '/src/db.php';     // istanzia $pdo

header('Content-Type: text/plain; charset=utf-8'); // output testuale

try {
    // Query semplice: recuperiamo id, username, crediti
    $stmt = $pdo->query("SELECT id, username, crediti FROM utenti ORDER BY id ASC");

    echo "👥 Lista utenti:\n";
    while ($row = $stmt->fetch()) {
        echo "- ID: {$row['id']} | Username: {$row['username']} | Crediti: {$row['crediti']}\n";
    }

} catch (Throwable $e) {
    if (APP_ENV !== 'production') {
        echo "❌ Errore query: " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo "❌ Errore interno.\n";
    }
}
