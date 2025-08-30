<?php
require_once __DIR__ . '/../src/guards.php';   // corretto (non ../../)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/totp.php';

if(empty($_SESSION['admin_pending_id'])){
  header("Location: /login.php"); 
  exit;
}

$msg=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $st=$pdo->prepare("SELECT id,username,role,totp_secret,totp_enabled FROM utenti WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$_SESSION['admin_pending_id']]); 
  $u=$st->fetch();
  if(!$u || $u['role']!=='admin' || !$u['totp_enabled']){
    http_response_code(403); 
    die('Accesso negato.');
  }
  if(totp_verify($u['totp_secret'], $_POST['code'] ?? '')){
    $_SESSION['user_id']=(int)$u['id']; 
    $_SESSION['username']=$u['username']; 
    $_SESSION['role']='admin';
    unset($_SESSION['admin_pending_id']); 
    header("Location: /admin/dashboard.php"); 
    exit;
  } else $msg="Codice non valido.";
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Verifica 2FA</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_login.css">
  <style>
    .wrap{max-width:420px;margin:30px auto;padding:0 20px;color:#fff}
    .card{background:#111;border:1px solid #222;border-radius:12px;padding:16px}
    input{width:100%;height:42px;border-radius:8px;border:1px solid #333;background:#000;color:#fff;padding:0 12px;margin:8px 0}
    button{height:40px;border-radius:9999px;background:#00c074;color:#fff;border:0;padding:0 16px;font-weight:800}
    .err{color:#ff6b6b}
  </style>
</head>
<body>
<?php require __DIR__ . '/../header_login.php'; ?> <!-- corretto -->
<main class="wrap">
  <div class="card">
    <h1>Codice 2FA</h1>
    <form method="post">
      <input type="text" name="code" inputmode="numeric" placeholder="123456" autofocus>
      <?php if($msg): ?><div class="err"><?=htmlspecialchars($msg)?></div><?php endif; ?>
      <button type="submit">Verifica</button>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../footer.php'; ?> <!-- corretto -->
</body>
</html>
