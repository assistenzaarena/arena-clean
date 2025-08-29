<?php
// Questo file centralizza la configurazione, leggendo da variabili d'ambiente
// Motivazione: non committiamo password o host nel repo, le mettiamo su Railway come "Environment Variables".

// APP_ENV definisce il contesto (production/staging/dev) per attivare/disattivare alcune funzionalità
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // Se non impostato, default production

// Parametri DB letti da env (Railway te li fornisce quando crei l'addon MySQL)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');    // Host del database (su Railway sarà quello del servizio MySQL)
define('DB_NAME', getenv('DB_NAME') ?: 'arena');        // Nome del database
define('DB_USER', getenv('DB_USER') ?: 'root');         // Username DB
define('DB_PASS', getenv('DB_PASS') ?: '');             // Password DB

// Sicurezza sessione: impostiamo un nome cookie separato per chiarezza
define('SESSION_NAME', 'ARENA_SESSID'); // Nome custom evita conflitti con altre app
