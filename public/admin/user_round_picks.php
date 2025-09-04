<?php
// admin/user_round_picks.php — JSON picks per utente
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/tournament_admin_utils.php';

header('Content-Type: application/json; charset=utf-8');

$tid = (int)($_GET['tournament_id'] ?? 0);
$uid = (int)($_GET['user_id'] ?? 0);
try {
  $data = ta_fetch_user_picks($pdo, $tid, $uid);
  echo json_encode(['ok'=>true,'data'=>$data]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'internal']);
}
