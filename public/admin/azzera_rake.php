<?php
// ==========================================================
// admin/azzera_rake.php — Handler POST per azzerare la rake
// ==========================================================

require_once __DIR__ . '/../src/guards.php';
require_admin();

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

// Consente solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die('Metodo non consentito');
}

// CSRF
$posted_csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $posted_csrf)) {
    http_response_code(400);
    die('CSRF non valido');
}

// Azzeramento
try {
    $pdo->exec("TRUNCATE TABLE admin_rake_ledger");
    $_SESSION['flash'] = 'Rake azzerata con successo.';
} catch (Throwable $e) {
    $_SESSION['flash'] = 'Errore durante l’azzeramento della rake.';
}

// Redirect di ritorno alla pagina amministrazione
header('Location: /admin/amministrazione.php');
exit;
