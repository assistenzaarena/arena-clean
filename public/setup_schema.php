<?php
// [SCOPO] Crea tabella utenti e inserisce un utente demo. Esegui UNA volta, poi cancella.

// Import: config + PDO ($pdo)
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    // Tabella utenti (id, username unico, password hash, crediti)
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS utenti (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  crediti INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    $pdo->exec($sql);
    echo "✅ Tabella 'utenti' pronta.\n";

    // Inserisci utente demo se non esiste
    $check = $pdo->prepare("SELECT id FROM utenti WHERE username = :u");
    $check->execute([':u' => 'demo']);
    if (!$check->fetch()) {
        $hash = password_hash('demo123', PASSWORD_DEFAULT);
        $ins = $pdo->prepare("INSERT INTO utenti (username, password_hash, crediti) VALUES (:u, :p, :c)");
        $ins->execute([':u' => 'demo', ':p' => $hash, ':c' => 100]);
        echo "✅ Utente demo creato (username=demo, password=demo123, crediti=100).\n";
    } else {
        echo "ℹ️ Utente demo già presente, nessun inserimento.\n";
    }

    echo "🏁 Setup completato.\n";
} catch (Throwable $e) {
    if (APP_ENV !== 'production') echo "❌ Errore: " . $e->getMessage() . "\n";
    http_response_code(500);
}
