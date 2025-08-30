<?php
// /tools/make_hash.php?pwd=LaTuaPasswordInChiaro
$pwd = $_GET['pwd'] ?? '';
header('Content-Type: text/plain; charset=utf-8');
if ($pwd === '') { echo "Usa: ?pwd=Spiraleovale2030!"; exit; }
echo password_hash($pwd, PASSWORD_DEFAULT);
