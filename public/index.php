<?php
// public/index.php — Home (Guest)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__; // <-- punta a /var/www/html

// Header guest (assicurati che il file esista in /var/www/html/header_guest.php)
require_once $ROOT . '/header_guest.php';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>ARENA — Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    .hero {
      min-height: calc(100vh - 160px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      text-align: center;
      padding: 32px 16px;
    }
    .hero img {
      width: 280px; /* molto più piccola e centrata */
      height: auto;
      border-radius: 12px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.5);
      margin-bottom: 24px;
    }
    .hero h1 {
      font-size: clamp(28px, 4vw, 48px);
      margin: 0 0 16px;
      font-weight: 900;
      color: #fff;
    }
    .hero p {
      font-size: clamp(16px, 2vw, 20px);
      color: #ddd;
      margin: 0 0 12px;
      line-height: 1.5;
    }
    .hero small {
      display: block;
      font-size: 14px;
      color: #aaa;
      max-width: 600px;
      margin: 16px auto 0;
      line-height: 1.6;
    }
  </style>
</head>
<body>

  <div class="hero">
    <img src="/assets/home_arena.jpg" alt="Arena Home">
    <h1>Sarai tu il campione dell’Arena?</h1>
    <p>Unisciti alla sfida, scegli la tua squadra e sopravvivi turno dopo turno.</p>
    <small>
      ARENA è la nuova piattaforma di tornei calcistici a eliminazione. Ogni giornata
      scegli una squadra diversa: chi resiste fino alla fine vince. Preparati a vivere
      un’esperienza unica, tra adrenalina, strategia e passione per il calcio.<br><br>
      Rimani connesso: presto troverai tornei, premi e sfide esclusive.
    </small>
  </div>

<?php
// Footer
if (file_exists($ROOT . '/footer_public.php')) {
  require_once $ROOT . '/footer_public.php';
} elseif (file_exists($ROOT . '/footer.php')) {
  require_once $ROOT . '/footer.php';
}
?>
</body>
</html>
