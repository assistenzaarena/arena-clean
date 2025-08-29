<?php
// [SCOPO] Creare una connessione PDO riutilizzabile per parlare con MySQL.
// Questo file deve essere incluso da altri (es. setup_schema.php) per avere $pdo pronto.

// Importiamo la config (contiene le costanti DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS)
require_once __DIR__ . '/config.php';

try {
    // DSN = stringa di connessione per MySQL
    // - host (senza porta)
    // - port (separata con ;port=)
    // - dbname
    // - charset utf8mb4 per supporto caratteri/emoji completi
    $dsn = 'mysql:host=' . DB_HOST .
           ';port=' . DB_PORT .
           ';dbname=' . DB_NAME .
           ';charset=utf8mb4';

    // Creiamo l'oggetto PDO
    $pdo = new PDO(
        $dsn,        // Stringa DSN
        DB_USER,     // Utente DB
        DB_PASS,     // Password DB
        [
            // Trasforma errori in eccezioni → gestibili meglio
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Risultati come array associativi (es: $row['username'])
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Prepared statements reali (sicuri contro SQL injection)
            PDO::ATTR_EMULATE_PREPARES => false,

            // Timeout breve (in secondi) → evita blocchi eterni se DB non risponde
            PDO::ATTR_TIMEOUT => 3,
        ]
    );

} catch (PDOException $e) {
    // In modalità dev mostriamo l’errore completo, in production restiamo generici
    if (APP_ENV !== 'production') {
        die("❌ Errore connessione DB: " . htmlspecialchars($e->getMessage()));
    }
    http_response_code(500);
    die("❌ Errore interno di connessione.");
}
