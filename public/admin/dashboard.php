<?php
require_once __DIR__.'/../../src/guards.php'; require_admin();
require_once __DIR__.'/../../src/config.php'; require_once __DIR__.'/../../src/db.php';
$tot=(int)$pdo->query("SELECT COUNT(*) FROM utenti")->fetchColumn();
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Admin — Dashboard</title>
<link rel="stylesheet" href="/assets/base.css"><link rel="stylesheet" href="/assets/header_admin.css">
<style>.wrap{max-width:1280px;margin:24px auto;padding:0 20px;color:#fff}.card{background:#111;border:1px solid #222;border-radius:12px;padding:16px;display:inline-block}</style>
</head><body>
<?php require __DIR__.'/../header_admin.php'; ?>
<main class="wrap">
  <h1>Dashboard Admin</h1>
  <div class="card">Utenti totali: <strong><?=$tot?></strong></div>
</main>
<?php require __DIR__.'/../footer.php'; ?>
</body></html>
