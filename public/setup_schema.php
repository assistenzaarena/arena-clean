<?php
// [SCOPO] Creare/aggiornare lo schema minimo del DB (tabella utenti) e inserire un utente demo.
// [USO]   Esegui questa pagina UNA SOLA VOLTA. Poi cancellala o proteggila.

// [RIGA] Importiamo la connessione PDO ($pdo) leggendo le variabili d'ambiente già configurate
require_once __DIR__ . '/src/config.php';   // Carichiamo le costanti DB_* (host, port, name, ecc.)
require_once __DIR__ . '/src/db.php';       // Istanzia $pdo con DSN corretto (porta separata)

// [RIGA] Forziamo l'output testuale per leggere facilmente i risultati nel browser
header('Content-Type: text/plain; charset=utf-8'); // Output in testo semplice

try {
    // [RIGA] SQL: creiamo tabella utenti SOLO se non esiste già
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS utenti (
  id INT AUTO_INCREMENT PRIMARY KEY,              -- ID univoco
  username VARCHAR(50) NOT NULL UNIQUE,           -- username unico
  password_hash VARCHAR(255) NOT NULL,            -- hash sicuro (password_hash)
  crediti INT NOT NULL DEFAULT 0,                 -- saldo crediti dell'utente
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- data creazione
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    // [RIGA] Eseguiamo lo schema (PDO->exec perché non ci aspettiamo un resultset)
    $pdo->exec($sql); // Se fallisce, lancia eccezione e finiamo nel catch
    echo "✅ Tabella 'utenti' pronta.\n"; // Feedback positivo

    // [RIGA] Inseriamo un utente demo SOLO se non esiste già (username 'demo')
    $check = $pdo->prepare("SELECT id FROM utenti WHERE username = :u"); // Prepared contro injection
    $check->execute([':u' => 'demo']);                                   // Bind valore 'demo'
    if (!$check->fetch()) {                                              // Se non esiste alcuna riga
        // [RIGA] Creiamo un hash sicuro per la password 'demo123'
        $hash = password_hash('demo123', PASSWORD_DEFAULT); // Mai salvare password in chiaro

        // [RIGA] Inseriamo utente demo con 100 crediti
        $ins = $pdo->prepare("INSERT INTO utenti (username, password_hash, crediti) VALUES (:u, :p, :c)");
        $ins->execute([':u' => 'demo', ':p' => $hash, ':c' => 100]); // Bind valori sicuri
        echo "✅ Utente demo creato (username=demo, password=demo123, crediti=100).\n"; // Conferma
    } else {
        echo "ℹ️  Utente demo già presente, nessun inserimento.\n"; // Nulla da fare
    }

    echo "🏁 Setup completato.\n"; // Fine

} catch (Throwable $e) {
    // [RIGA] In dev mostriamo l'errore, in production restiamo generici
    if (APP_ENV !== 'production') {
        echo "❌ Errore: " . $e->getMessage() . "\n"; // Dettaglio utile al debug
    } else {
        echo "❌ Errore interno durante il setup.\n"; // Messaggio neutro in produzione
    }
    http_response_code(500); // Segnaliamo errore server
    exit; // Interrompiamo
}
