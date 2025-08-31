<?php
// public/api/user_credits.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($uid <= 0) {
  echo json_encode(['ok'=>false,'error'=>'not_logged']); 
  exit;
}

try {
  $q = $pdo->prepare("SELECT crediti FROM utenti WHERE id = :id LIMIT 1");
  $q->execute([':id'=>$uid]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    echo json_encode(['ok'=>false,'error'=>'not_found']); 
    exit;
  }
  echo json_encode(['ok'=>true,'credits'=>(float)$row['crediti']]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
