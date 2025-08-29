<?php
// [SCOP0] File di configurazione centralizzato: legge le variabili d'ambiente
//         e definisce costanti usate dal resto dell'app (DB, sessioni, ecc.).

// [RIGA] Definiamo l'ambiente applicativo: 'production' = silenzioso; 'dev' = verboso per debug
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // Se APP_ENV non c'è, default a 'production'

// [RIGA] Host del database (solo hostname/IP, SENZA la porta)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');  // Es.: "containers-xx.railway.app" o "mysql.railway.internal"

// [RIGA] Porta del database, separata: PDO vuole la porta con ;port=XXXX
define('DB_PORT', getenv('DB_PORT') ?: '3306');       // Su Railway tipicamente 3306

// [RIGA] Nome del database a cui connettersi
define('DB_NAME', getenv('DB_NAME') ?: 'arena');      // Es.: "railway" o quello che hai creato

// [RIGA] Username con cui ci si autentica al DB
define('DB_USER', getenv('DB_USER') ?: 'root');       // Su Railway spesso è 'root' o un utente random

// [RIGA] Password dell’utente DB
define('DB_PASS', getenv('DB_PASS') ?: '');           // NON committare mai password in chiaro nel repo

// [RIGA] Nome del cookie di sessione (evita conflitti con altre app)
define('SESSION_NAME', 'ARENA_SESSID');               // Nome univoco per l'app Arena
