<?php
// [SCOPO] Creare un'istanza PDO riutilizzabile per parlare con MySQL in modo sicuro.

// [RIGA] Importiamo le costanti di configurazione (host, port, name, user, pass, APP_ENV)
require_once __DIR__ . '/config.php'; // __DIR__ = percorso fisso al file corrente, robusto agli include

try {
    // [RIGA] Costruiamo il DSN (Data Source Name) nel formato corretto per PDO MySQL.
    //        NOTA: la PORTA va indicata come parametro separato (;port=XXXX), NON dentro host.
    //        In più impostiamo il charset a utf8mb4 per supportare pienamente emoji/caratteri estesi.
    $dsn = 'mysql:host=' . DB_HOST
         . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME
         . ';charset=utf8mb4';

    // [RIGA] Creiamo l'oggetto PDO con opzioni sicure/performanti.
    $pdo = new PDO(
        $dsn,        // Stringa DSN (contiene host, port, dbname, charset)
        DB_USER,     // Username per autenticarsi al DB
        DB_PASS,     // Password per autenticarsi al DB
        [
            // [RIGA] Trasforma gli errori in eccezioni → gestione chiara, niente warning persi
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // [RIGA] I fetch restituiscono array associativi → più leggibili (es. $row['username'])
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // [RIGA] Disabilita l’emulazione delle prepared → usa prepared “reali” del driver (più sicure)
            PDO::ATTR_EMULATE_PREPARES => false,

            // [RIGA] Timeout breve della connessione (in secondi) → evita che la pagina “rimanga appesa”
            PDO::ATTR_TIMEOUT => 3,
        ]
    );

} catch (PDOException $e) {
    // [RIGA] SE siamo in sviluppo (APP_ENV != production) mostriamo il motivo esatto per debuggare.
    if (APP_ENV !== 'production') {
        // [RIGA] htmlspecialchars per non permettere injection di HTML nel browser
        die('Errore connessione DB: ' . htmlspecialchars($e->getMessage()));
    }

    // [RIGA] IN produzione non esponiamo dettagli → status 500 + messaggio generico
    http_response_code(500);                 // Imposta HTTP 500 (errore server)
    die('Si è verificato un errore interno.'); // Messaggio neutro per non aiutare attaccanti
}
