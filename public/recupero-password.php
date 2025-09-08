<?php
// public/recupero-password.php — Pagina recovery temporanea (assistita)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__;
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Recupero password — ARENA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Stili condivisi come login/registrazione -->
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_login.css">
  <link rel="stylesheet" href="/assets/login.css?v=7">

  <style>
    /* wrapper “auth” già usato da login/registrazione → mobile-first */
    .auth{min-height:calc(100vh - 120px); display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32px 16px; gap:16px}

    /* card informativa */
    .info-card{
      width:100%; max-width: 620px;
      background:#0f1114;
      border:1px solid rgba(255,255,255,.12);
      border-radius:12px;
      padding:20px;
      color:#fff;
      box-shadow:0 16px 50px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.04);
    }
    .info-card h1{ margin:0 0 10px; font-weight:900; font-size:26px; }
    .info-card p{ margin:0 0 10px; color:#cfd3d8; line-height:1.6; }

    .call{
      background:linear-gradient(180deg, rgba(226,27,44,.09), rgba(226,27,44,.04));
      border:1px solid rgba(226,27,44,.35);
      border-radius:10px;
      padding:12px 14px;
      color:#ffdede;
      margin-top:8px;
    }
    .actions{ display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
    .btn{
      display:inline-flex; align-items:center; justify-content:center; height:42px; padding:0 18px;
      border-radius:9999px; font-weight:900; text-decoration:none; border:0;
    }
    .btn--primary{ background:#00c074; color:#fff; box-shadow:0 8px 24px rgba(0,192,116,.28); }
    .btn--primary:hover{ background:#00a862; transform:translateY(-1px); }
    .btn--ghost{ background:transparent; color:#fff; border:1px solid rgba(255,255,255,.32); }
    .btn--ghost:hover{ border-color:#fff; transform:translateY(-1px); }

    .muted{ color:#9aa3af; font-size:13px; }
  </style>
</head>
<body>

<?php require $ROOT . '/header_login.php'; ?>

<main class="auth">
  <div class="info-card">
    <h1>Recupero password</h1>
    <p>Stiamo attivando il recupero automatico della password direttamente dalla piattaforma.</p>
    <div class="call">
      <strong>Temporaneamente</strong>, per reimpostare la password contatta l’assistenza:
      <br>
      <a href="mailto:assistenza.arena@gmail.com" style="color:#fff; font-weight:900; text-decoration:underline;">assistenza.arena@gmail.com</a>
    </div>
    <p class="muted" style="margin-top:10px;">Riceverai istruzioni e la conferma di reset nel più breve tempo possibile.</p>

    <div class="actions">
      <a class="btn btn--primary" href="/login.php">Torna al login</a>
      <a class="btn btn--ghost" href="/registrazione.php">Crea un account</a>
    </div>
  </div>
</main>

<?php require $ROOT . '/footer.php'; ?>

</body>
</html>
