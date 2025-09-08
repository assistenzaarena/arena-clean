<?php
// public/ricarica.php — Placeholder elegante "Coming soon" (solo utenti loggati)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__; // /var/www/html

require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';
require_login(); // <<< solo utenti loggati

// opzionale: per coerenza con il resto delle pagine user
$csrf = $_SESSION['csrf'] ?? null;
if (empty($csrf)) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Ricarica crediti — ARENA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Stili condivisi -->
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_user.css">

  <style>
    /* wrapper centrale tipo pagine auth ma per area user */
    .recharge-wrap{
      min-height: calc(100vh - 140px);
      display:flex; align-items:center; justify-content:center;
      padding: 32px 16px;
    }
    .recharge-card{
      width:100%; max-width: 720px;
      background:#0f1114;
      border:1px solid rgba(255,255,255,.12);
      border-radius: 14px;
      padding: 24px;
      color:#fff;
      box-shadow: 0 16px 50px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.04);
    }
    .recharge-card h1{
      margin:0 0 10px; font-weight:900; font-size:28px;
    }
    .recharge-card p{
      margin: 0 0 10px; color:#cfd3d8; line-height:1.6;
    }
    .pill{
      display:inline-block; padding:4px 10px; border-radius:9999px;
      background:#1c2128; border:1px solid #2a3038; color:#dbe2ea; font-size:12px; font-weight:800;
      letter-spacing:.04em; text-transform:uppercase;
    }
    .box{
      margin-top:14px; background:#0b0e12; border:1px solid rgba(255,255,255,.08);
      border-radius:12px; padding:14px;
    }

    .cta-row{ display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
    .btn-cta{
      display:inline-flex; align-items:center; justify-content:center;
      height:42px; padding:0 18px; border-radius:9999px; font-weight:900; text-decoration:none; border:0;
      background:#00c074; color:#fff; box-shadow:0 8px 24px rgba(0,192,116,.28);
    }
    .btn-cta:hover{ background:#00a862; transform: translateY(-1px); }
    .btn-ghost{ background:transparent; color:#fff; border:1px solid rgba(255,255,255,.32); }
    .btn-ghost:hover{ border-color:#fff; transform: translateY(-1px); }

    /* mini grid dei punti */
    .grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    @media (max-width: 780px){ .grid{ grid-template-columns:1fr; } }
    .item{ background:#12161b; border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:12px; }
    .item h3{ margin:0 0 6px; font-size:16px; }
    .muted{ color:#9aa3af; font-size:13px; }
  </style>
</head>
<body>

<?php require $ROOT . '/header_user.php'; ?>

<main class="recharge-wrap">
  <section class="recharge-card">
    <span class="pill">Coming soon</span>
    <h1>Ricarica crediti</h1>
    <p>Stiamo ultimando l’integrazione dei pagamenti sicuri. A breve potrai ricaricare i tuoi crediti direttamente dall’Arena in pochi secondi.</p>

    <div class="grid">
      <div class="item">
        <h3>Pagamenti sicuri</h3>
        <p class="muted">Provider certificati, protocolli moderni e protezione anti-frode.</p>
      </div>
      <div class="item">
        <h3>Ricarica istantanea</h3>
        <p class="muted">Appena attiva, l’accredito sarà immediato sul tuo saldo.</p>
      </div>
    </div>

    <div class="box">
      <p style="margin:0;">Nel frattempo, continua a giocare e tieni d’occhio questa pagina: le ricariche arriveranno a breve.</p>
    </div>

    <div class="cta-row">
      <a class="btn-cta" href="/lobby.php">Vai alla Lobby</a>
      <a class="btn-cta btn-ghost" href="/storico_tornei.php">Storico tornei</a>
    </div>
  </section>
</main>

<?php require $ROOT . '/footer.php'; ?>

</body>
</html>
