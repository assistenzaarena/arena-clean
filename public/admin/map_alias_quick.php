<?php
// admin/map_alias_quick.php — mappa in 1 click un team_id grezzo su un canon
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/guards.php';  require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); echo 'Metodo non consentito'; exit;
}
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  $_SESSION['flash'] = ['type'=>'err','msg'=>'CSRF non valido.'];
  header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/gestisci_tornei.php')); exit;
}

$league_id = (int)($_POST['league_id'] ?? 0);
$team_id   = (int)($_POST['team_id'] ?? 0);
$canon_id  = (int)($_POST['canon_team_id'] ?? 0);

if ($league_id<=0 || $team_id<=0 || $canon_id<=0) {
  $_SESSION['flash'] = ['type'=>'err','msg'=>'Dati mappatura non validi.'];
  header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/gestisci_tornei.php')); exit;
}

try {
  $st = $pdo->prepare("
    INSERT INTO admin_team_canon_map (league_id, team_id, canon_team_id)
    VALUES (?,?,?)
    ON DUPLICATE KEY UPDATE canon_team_id=VALUES(canon_team_id)
  ");
  $st->execute([$league_id, $team_id, $canon_id]);
  $_SESSION['flash'] = ['type'=>'ok','msg'=>"Alias $team_id mappato su canon #$canon_id."];
} catch (Throwable $e) {
  error_log('[map_alias_quick] '.$e->getMessage());
  $_SESSION['flash'] = ['type'=>'err','msg'=>'Errore salvataggio mappatura.'];
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/gestisci_tornei.php'));
exit;
