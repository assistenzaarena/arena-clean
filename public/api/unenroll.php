<?php
// public/api/unenroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

// Must be logged in
if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false, 'error'=>'not_logged']); exit;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false, 'error'=>'bad_method']); exit;
}

// CSRF (se stai usando il token globale in window.CSRF)
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  echo json_encode(['ok'=>false, 'error'=>'bad_csrf']); exit;
}

// Input
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id = (int)$_SESSION['user_id'];

if ($tournament_id <= 0) {
  echo json_encode(['ok'=>false, 'error'=>'bad_params']); exit;
}

try {
  // Il torneo deve essere ancora OPEN e non oltre il lock
  $tq = $pdo->prepare("SELECT status, lock_at FROM tournaments WHERE id = :id LIMIT 1");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t) {
    echo json_encode(['ok'=>false, 'error'=>'not_found']); exit;
  }
  if ($t['status'] !== 'open') {
    echo json_encode(['ok'=>false, 'error'=>'not_open']); exit;
  }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    echo json_encode(['ok'=>false, 'error'=>'locked']); exit;
  }

  // Elimina l’iscrizione se presente
  $del = $pdo->prepare("DELETE FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $del->execute([':u'=>$user_id, ':t'=>$tournament_id]);

  echo json_encode(['ok'=>true, 'redirect'=>'/lobby.php']);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>'exception']); // logga in server se vuoi $e->getMessage()
}
