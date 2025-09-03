<?php
// admin/azzera_rake.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';   require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Metodo non consentito';
  exit;
}

$posted_csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $posted_csrf)) {
  http_response_code(400);
  echo 'CSRF non valido';
  exit;
}

try {
  // Azzeriamo proprio la tabella (se preferisci storicizzare, poi cambiamo strategia)
  $pdo->exec("TRUNCATE TABLE admin_rake_ledger");

  $_SESSION['flash'] = 'Rake azzerata con successo.';
} catch (Throwable $e) {
  $_SESSION['flash'] = 'Errore durante l’azzeramento della rake.';
}

// Torna alla pagina di amministrazione
header('Location: /admin/amministrazione.php');
exit;
