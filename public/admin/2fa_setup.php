<?php
require_once __DIR__ . '/../src/guards.php';  // corretto (non ../../)
require_admin();

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/totp.php';

$s=$pdo->prepare("SELECT totp_secret,username FROM utenti WHERE id=:id LIMIT 1");
$s->execute([':id'=>$_SESSION['user_id']]); 
$u=$s->fetch(); 
if(!$u){ http_response_code(404); die('Utente non trovato'); }

$secret=$u['totp_secret'] ?: (function($pdo){
  $sec=generate_base32_secret(16);
  $up=$pdo->prepare("UPDATE utenti SET totp_secret=:s WHERE id=:id");
  $up->execute([':s'=>$sec,':id'=>$_SESSION['user_id']]);
  return $sec;
})($pdo);

$issuer=rawurlencode('ARENA'); 
$account=rawurlencode($u['username']);
$uri="otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&period=30&digits=6";
$qr="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=".urlencode($uri);

$msg=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $code=$_POST['code']??'';
  if(totp_verify($secret,$code)){
    $pdo->prepare("UPDATE utenti SET totp_enabled=1 WHERE id=:id")->execute([':id'=>$_SESSION['user_id']]);
    header("Location: /admin/dashboard.php"); 
    exit;
  } else $msg="Codice non valido, riprova.";
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Setup 2FA</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:520px;margin:30px auto;padding:0 20px;color:#fff}
    .card{background:#111;border:1px solid #222;border-radius:12px;padding:16px}
    input{width:100%;height:42px;border-radius:8px;border:1px solid #333;background:#000;color:#fff;padding:0 12px;margin:8px 0}
    button{height:40px;border-radius:9999px;background:#00c074;color:#fff;border:0;padding:0 16px;font-weight:800}
    .err{color:#ff6b6b}
  </style>
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>  <!-- corretto -->
<main class="wrap">
  <div class="card">
    <h1>Attiva 2FA</h1>
    <p>1) Scansiona questo QR con Google Authenticator / Authy</p>
    <img src="<?=$qr?>" alt="QR 2FA">
    <p>Oppure inserisci manualmente il segreto: <code><?=htmlspecialchars($secret)?></code></p>
    <form method="post">
      <label>2) Inserisci il codice</label>
      <input type="text" name="code" inputmode="numeric" placeholder="123456">
      <?php if($msg): ?><div class="err"><?=htmlspecialchars($msg)?></div><?php endif; ?>
      <button type="submit">Attiva</button>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../footer.php'; ?> <!-- corretto -->
</body>
</html>
