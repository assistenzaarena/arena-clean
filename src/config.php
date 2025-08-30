<?php
// [SCOPO] Definire i parametri di connessione leggendo le variabili d'ambiente, senza hardcodare nulla.
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // [RIGA] Modalità app: 'dev' (debug) o 'production' (silenzioso)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');  // [RIGA] Host del DB (solo hostname, senza porta)
define('DB_PORT', getenv('DB_PORT') ?: '3306');       // [RIGA] Porta del DB (separata per PDO: ;port=XXXX)
define('DB_NAME', getenv('DB_NAME') ?: 'arena');      // [RIGA] Nome del database
define('DB_USER', getenv('DB_USER') ?: 'root');       // [RIGA] Username del database
define('DB_PASS', getenv('DB_PASS') ?: '');           // [RIGA] Password del database
define('API_FOOTBALL_KEY', getenv('API_FOOTBALL_KEY') ?: '0ae0df75f9c2401537ea2ad2076992c8'); // imposta dal pannello Railway/Variables
