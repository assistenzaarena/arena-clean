-- Creiamo database e tabella utenti di base
-- NOTA: su Railway creerai un DB MySQL e imposterai le variabili d'ambiente. Questo file è documentazione + base da eseguire nel tuo DB.

-- Tabella utenti (semplice, estendibile)
CREATE TABLE IF NOT EXISTS utenti (
  id INT AUTO_INCREMENT PRIMARY KEY,         -- Identificativo univoco dell'utente
  username VARCHAR(50) NOT NULL UNIQUE,      -- Username univoco
  password_hash VARCHAR(255) NOT NULL,       -- Password hashata in modo sicuro (password_hash in PHP)
  crediti INT NOT NULL DEFAULT 0,            -- Crediti dell'utente (mai fidarsi del client)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- Audit base
);

-- Utente demo opzionale (solo per test locale, da NON usare in produzione)
-- INSERT INTO utenti (username, password_hash, crediti) VALUES ('demo', '$2y$10$hashfinto', 100);
