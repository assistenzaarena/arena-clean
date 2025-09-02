<?php
// public/admin/close_tournament.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__, 1); // /var/www/html/public/admin -> /var/www/html
require_once $ROOT . '/src/guards.php';    require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/payouts.php';

try {
  $csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
  }

  $tid = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
  if ($tid <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

  $weights = null;
  if (!empty($_POST['weights_json'])) {
    $w = json_decode((string)$_POST['weights_json'], true);
    if (is_array($w)) $weights = array_map('intval', $w); // ex: {"123":2,"456":1}
  }

  $res = tp_close_and_payout($pdo, $tid, $weights);
  echo json_encode(['ok'=>($res['ok']??false)] + $res); exit;

} catch (Throwable $e) {
  error_log('[close_tournament] '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]);
}
