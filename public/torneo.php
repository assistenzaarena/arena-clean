<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__;
require_once $ROOT.'/src/config.php';
require_once $ROOT.'/src/db.php';
require_once $ROOT.'/src/guards.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?><!doctype html>
<html lang="it"><head><meta charset="utf-8"><title>Torneo</title></head>
<body style="color:#fff;background:#0f1114;font-family:system-ui,Arial,sans-serif">
<h1 style="margin:24px">Torneo #<?php echo (int)$id; ?></h1>
<p style="margin:24px">Placeholder pagina torneo. (Arriva nello Step 3C)</p>
<a href="/lobby.php" style="margin:24px;display:inline-block;color:#0c8">← Torna alla lobby</a>
</body></html>
