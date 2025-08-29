<?php
// ===============================================================
// TEST DI CONNESSIONE AL DATABAS
// Questo file serve solo a verificare che le variabili d'ambiente 
// siano lette correttamente e che la connessione MySQL funzioni.
// ===============================================================

// 1. Importiamo la connessione centralizzata (db.php)
//    In questo file ci sono PDO + config + gestione errori
require_once __DIR__ . '/../src/db.php';

// 2. Stampiamo un messaggio di conferma se siamo arrivati qui
//    Significa che db.php non ha lanciato eccezioni
echo "<h1>✅ Connessione al DB riuscita!</h1>";

// 3. Proviamo a leggere gli utenti già presenti nella tabella
//    Nota: se la tabella è vuota, non vedrai output extra.
try {
    // Query semplice: selezioniamo id, username, crediti
    $stmt = $pdo->query("SELECT id, username, crediti FROM utenti LIMIT 10");

    // Cicliamo i risultati
    echo "<h2>Utenti trovati:</h2><ul>";
    while ($row = $stmt->fetch()) {
        // Usiamo htmlspecialchars per sicurezza XSS (evita output malevolo)
        echo "<li>ID: " . htmlspecialchars($row['id']) . 
             " | Username: " . htmlspecialchars($row['username']) . 
             " | Crediti: " . htmlspecialchars($row['crediti']) . "</li>";
    }
    echo "</ul>";

} catch (Throwable $e) {
    // Se c'è un errore nella query, lo mostriamo solo se non siamo in production
    if (APP_ENV !== 'production') {
        echo "<p style='color:red'>Errore query: " . htmlspecialchars($e->getMessage()) . "</p>";
    } else {
        echo "<p style='color:red'>❌ Errore interno durante la query.</p>";
    }
}
