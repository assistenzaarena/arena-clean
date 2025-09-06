<?php
// admin/map_alias_quick.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = dirname(__DIR__, 2);
require_once $ROOT.'/src/guards.php'; require_admin();
require_once $ROOT.'/src/config.php';
require_once $ROOT.'/src/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/gestisci_tornei.php')); exit;
}
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  $_SESSION['flash'] = 'CSRF non valido.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/gestisci_tornei.php')); exit;
}

$league_id = (int)($_POST['league_id'] ?? 0);
$team_id   = (int)($_POST['team_id'] ?? 0);
$canon_id  = (int)($_POST['canon_team_id'] ?? 0);

if ($league_id<=0 || $team_id<=0 || $canon_id<=0) {
  $_SESSION['flash'] = 'Parametri mancanti.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/gestisci_tornei.php')); exit;
}

$pdo->prepare("
  INSERT INTO admin_team_canon_map (league_id, team_id, canon_team_id)
  VALUES (?,?,?)
  ON DUPLICATE KEY UPDATE canon_team_id=VALUES(canon_team_id)
")->execute([$league_id, $team_id, $canon_id]);

$_SESSION['flash'] = 'Mappatura alias aggiornata (#'.$team_id.' → canon #'.$canon_id.').';
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/gestisci_tornei.php')); exit;
