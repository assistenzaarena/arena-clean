<?php
// [SCOPO] Funzioni di protezione per pagine riservate (login/admin)

// [RIGA] Avvia sessione se non attiva (così $_SESSION è disponibile sempre)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/**
 * require_login()
 * - Se non loggato → redirect a /login.php
 */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }
}

/**
 * require_admin()
 * - Prima richiede login
 * - Se non admin → 403 e stop
 */
function require_admin(): void {
    require_login(); // deve essere loggato
    if (($_SESSION['role'] ?? 'user') !== 'admin') {
        http_response_code(403);
        echo "Accesso negato (solo admin).";
        exit;
    }
}
