<?php
// Importiamo la config per leggere parametri DB
require_once __DIR__ . '/config.php'; // __DIR__ garantisce percorso corretto indipendente dalla CWD

try {
    // Creiamo DSN (Data Source Name) per PDO: definisce tipo DB, host e nome DB
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4'; // utf8mb4 per supportare emoji/caratteri completi

    // Istanza PDO con opzioni di sicurezza e performance
    $pdo = new PDO(
        $dsn,                // Connessione al database
        DB_USER,             // Username DB
        DB_PASS,             // Password DB
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,            // Errori come eccezioni: più sicuro per gestire problemi
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch in array associativo: più leggibile
            PDO::ATTR_EMULATE_PREPARES => false,                    // Disabilita prepare emulate: use real prepared statements
        ]
    );
} catch (PDOException $e) {
    // In produzione, non mostriamo dettagli sensibili; in dev potresti loggare.
    if (APP_ENV !== 'production') {
        die('Errore connessione DB: ' . htmlspecialchars($e->getMessage())); // Mostra errore solo in dev
    }
    // In produzione: messaggio generico per non dare info a potenziali attaccanti
    http_response_code(500); // Status 500 per indicare errore server
    die('Si è verificato un errore interno.'); // Messaggio generico
}
