<?php
// admin/round_recalc_apply.php — Applica ricalcolo round (POST-only)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/tournament_admin_utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); echo 'Metodo non consentito'; exit;
}
$posted = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $posted)) {
  http_response_code(400); echo 'CSRF non valido'; exit;
}

$tid   = (int)($_POST['tournament_id'] ?? 0);
$round = (int)($_POST['round_no'] ?? 0);

$msgs = ta_apply_round_recalc($pdo, $tid, $round);
$_SESSION['flash'] = $msgs;

$qs = http_build_query(['tournament_id'=>$tid,'round_no'=>$round]);
header('Location: /admin/round_recalc.php?'.$qs);
exit;
