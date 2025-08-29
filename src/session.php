<?php
// Gestione sessioni in modo sicur

// Importiamo costanti per il nome della sessione
require_once __DIR__ . '/config.php'; // Serve SESSION_NAME

// Se una sessione non è già attiva, la configuriamo e la avviamo
if (session_status() === PHP_SESSION_NONE) {
    // Impostiamo parametri cookie sicuri prima di session_start
    session_set_cookie_params([
        'lifetime' => 0,                // Sessione valida finché il browser è aperto (niente persistente lato client)
        'path' => '/',                  // Valida su tutto il dominio
        'domain' => '',                 // Vuoto = dominio corrente
        'secure' => true,               // TRUE: cookie solo via HTTPS (Railway usa HTTPS), aumenta sicurezza
        'httponly' => true,             // TRUE: JS non può leggere il cookie (mitiga XSS)
        'samesite' => 'Lax',            // Lax: riduce rischio CSRF mantenendo usabilità base
    ]);

    // Impostiamo un nome di sessione custom per evitare conflitti
    session_name(SESSION_NAME); // Usa il nome definito in config

    // Avviamo la sessione
    session_start(); // Necessario per usare $_SESSION

    // Protezione base contro fixation: se non esiste un token interno, creiamolo
    if (empty($_SESSION['_init'])) {
        $_SESSION['_init'] = time();        // Timestamp di inizio sessione (audit/debug)
        $_SESSION['_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'; // Leghiamo la sessione all'UA (debole ma utile)
        // In un vero login, rigenereremo l’ID: session_regenerate_id(true)
    }
}
