<?php
// public/api/enroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';

// Utente deve essere loggato
if (!is_logged_in()) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }

// Solo POST + CSRF
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
}

$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id       = (int)($_SESSION['user_id'] ?? 0);
if ($tournament_id <= 0 || $user_id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

// Funzione per codice a 5 cifre univoco globale
function generate_registration_code(PDO $pdo): string {
  do {
    $code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $q = $pdo->prepare("SELECT 1 FROM tournament_enrollments WHERE registration_code = :c LIMIT 1");
    $q->execute([':c'=>$code]);
  } while ($q->fetchColumn());
  return $code;
}

try {
  // 1) Torneo esistente, open e non lockato
  $t = $pdo->prepare("SELECT status, lock_at FROM tournaments WHERE id=:id LIMIT 1");
  $t->execute([':id'=>$tournament_id]);
  $torneo = $t->fetch(PDO::FETCH_ASSOC);
  if (!$torneo) { echo json_encode(['ok'=>false,'error'=>'tournament_not_found']); exit; }
  if ($torneo['status'] !== 'open') { echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }
  if (!empty($torneo['lock_at']) && strtotime($torneo['lock_at']) <= time()) {
    echo json_encode(['ok'=>false,'error'=>'locked']); exit;
  }

  // 2) Non già iscritto
  $ex = $pdo->prepare("SELECT 1 FROM tournament_enrollments WHERE tournament_id=:tid AND user_id=:uid LIMIT 1");
  $ex->execute([':tid'=>$tournament_id, ':uid'=>$user_id]);
  if ($ex->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'already_enrolled']); exit; }

  // 3) Inserisci iscrizione con codice (5 cifre)
  $code = generate_registration_code($pdo);
  $ins = $pdo->prepare("
    INSERT INTO tournament_enrollments (registration_code, tournament_id, user_id, lives)
    VALUES (:code, :tid, :uid, 1)
  ");
  $ins->execute([':code'=>$code, ':tid'=>$tournament_id, ':uid'=>$user_id]);

  echo json_encode(['ok'=>true, 'registration_code'=>$code, 'redirect'=>"/torneo.php?id={$tournament_id}"]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
