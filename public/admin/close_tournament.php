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

  // 1) Se l'admin passa i pesi espliciti, usali (JSON tipo {"2":1,"8":1})
  if (!empty($_POST['weights_json'])) {
    $w = json_decode((string)$_POST['weights_json'], true);
    if (is_array($w)) {
      $weights = [];
      foreach ($w as $uid => $val) {
        $weights[(int)$uid] = (int)$val;
      }
    }
  }

  // 2) Se forzi la chiusura (force=1) e non hai passato pesi,
  //    derivali automaticamente:
  //    - prima: sopravvissuti attuali (vite residue)
  //    - se non ci sono: dall'ultimo round disputato (tp_weights_last_round)
  $force = isset($_POST['force']) ? (int)$_POST['force'] : 0;
  if ($force === 1 && (empty($weights) || !is_array($weights))) {
    $surv = tp_get_survivors($pdo, $tid); // user_id => lives>0
    if (!empty($surv)) {
      $weights = $surv; // split per vite residue
    } else {
      $weights = tp_weights_last_round($pdo, $tid); // fallback pulito
    }
  }

  // 3) Esegui chiusura+payout (winner / proportional / forced con pesi)
  $res = tp_close_and_payout($pdo, $tid, $weights);
  echo json_encode(['ok'=>($res['ok']??false)] + $res); exit;

} catch (Throwable $e) {
  error_log('[close_tournament] '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]);
}
