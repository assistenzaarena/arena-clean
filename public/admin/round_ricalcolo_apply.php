<?php
// =====================================================================
// /public/admin/round_ricalcolo_apply.php — Applica ricalcolo (POST only)
// Dipendenze: src/round_recalc_lib.php (rr_apply)
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/round_recalc_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Metodo non consentito'; exit; }

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { http_response_code(400); echo 'CSRF non valido'; exit; }

$tournamentId = (int)($_POST['tournament_id'] ?? 0);
$roundNo      = (int)($_POST['round_no'] ?? 0);
$note         = trim($_POST['note'] ?? '');

if ($tournamentId<=0 || $roundNo<=0) { http_response_code(400); echo 'Parametri mancanti'; exit; }

try {
  $adminId = (int)($_SESSION['user_id'] ?? 0);
  $res = rr_apply($pdo, $tournamentId, $roundNo, $adminId, $note);
  $_SESSION['flash'] = 'Ricalcolo applicato. Utenti aggiornati: '.(int)($res['applied'] ?? 0);
  $_SESSION['flash_type'] = 'ok';
} catch (Throwable $e) {
  error_log('[round_ricalcolo_apply] '.$e->getMessage());
  $_SESSION['flash'] = 'Errore durante l’operazione: '.$e->getMessage();
  $_SESSION['flash_type'] = 'error';
}

header('Location: /admin/round_ricalcolo.php?tournament_id='.$tournamentId.'&round='.$roundNo);
exit;
