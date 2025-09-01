<?php
// public/api/select_team.php
// Restituisce selezioni correnti (life_index, side, event_id) dell’utente per un torneo

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

function out($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

if (empty($_SESSION['user_id']))                   out(['ok'=>false,'error'=>'not_logged'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')         out(['ok'=>false,'error'=>'bad_method'], 405);

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf))  out(['ok'=>false,'error'=>'bad_csrf'], 400);

$user_id       = (int)$_SESSION['user_id'];
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
if ($tournament_id <= 0) out(['ok'=>false,'error'=>'bad_params'], 400);

try {
  // carico selections utente per il torneo
  $q = $pdo->prepare("
    SELECT life_index, event_id, side
    FROM tournament_selections
    WHERE user_id = ? AND tournament_id = ?
    ORDER BY life_index ASC
  ");
  $q->execute([$user_id, $tournament_id]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  out(['ok'=>true, 'picks'=>$rows]);

} catch (Throwable $e) {
  if (!defined('APP_ENV') || APP_ENV !== 'production') {
    out(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage()], 500);
  }
  out(['ok'=>false,'error'=>'exception'], 500);
}
