<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: text/plain; charset=utf-8');

$user = $_GET['user'] ?? '';
$pwd  = $_GET['pwd']  ?? '';

if ($user==='' || $pwd==='') {
  echo "Usa: /tools/test_verify.php?user=USERNAME&pwd=PasswordInChiaro\n";
  exit;
}

$stmt = $pdo->prepare("SELECT username,email,password_hash FROM utenti WHERE username=:u1 OR email=:u2 LIMIT 1");
$stmt->execute([':u1'=>$user, ':u2'=>$user]);
$row = $stmt->fetch();

if(!$row){ echo "Utente non trovato\n"; exit; }

echo "Username: {$row['username']}\n";
echo "Email   : {$row['email']}\n";
echo "Hash    : {$row['password_hash']}\n";

$ok = password_verify($pwd, $row['password_hash']);
echo "Verify  : " . ($ok ? "TRUE" : "FALSE") . "\n";
