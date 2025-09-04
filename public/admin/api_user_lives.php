<?php
// admin/api_user_lives.php — Aggiorna vite utente (POST)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/tournament_admin_utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Metodo non consentito'; exit; }
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { http_response_code(400); echo 'CSRF'; exit; }

$tid = (int)($_POST['tournament_id'] ?? 0);
$uid = (int)($_POST['user_id'] ?? 0);
$delta = (int)($_POST['delta'] ?? 0);

$msg = ta_adjust_user_lives($pdo, $tid, $uid, $delta);
$_SESSION['flash'] = $msg;
$qs = http_build_query(['tournament_id'=>$tid]);
header('Location: /admin/torneo_gestione_utenti.php?'.$qs);
exit;
