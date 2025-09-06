<?php
// public/index.php — Home (Guest)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__; // /var/www/html/public
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>ARENA — Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="/assets/base.css">

  <style>
    .hero{
      min-height: calc(100vh - 160px);
      display:flex; align-items:center; justify-content:center;
      text-align:center; padding: 24px;
    }
    .hero h1{ font-size: clamp(28px, 4vw, 56px); margin: 0 0 12px; font-weight: 900; }
    .hero p{ font-size: clamp(14px, 2vw, 18px); color:#aaa; margin:0; }

    /* ===== SOLO MOBILE: nascondo header/subheader desktop, mostro i componenti mobile */
    .mobile-only{ display:none; }
    @media (max-width: 900px){
      .hdr{ display:none !important; }     /* header desktop (classe nel tuo header_guest.php) */
      .subhdr{ display:none !important; }  /* subheader desktop */
      .mobile-only{ display:block !important; }
      /* (per ora NON tocco il footer per evitare pagina “vuota”) */
    }
  </style>
</head>
<body>

<?php
/* 1) Header desktop (come l’avevi già) — DEVE stare dopo <body> */
require_once $ROOT . '/../header_guest.php';

/* 2) Header mobile + drawer: partials in /public/partials */
require_once __DIR__ . '/partials/guest_mobile_header.php';
require_once __DIR__ . '/partials/guest_mobile_drawer.php';
?>

  <div class="hero">
    <div>
      <h1>Benvenuti in ARENA</h1>
      <p>La piattaforma dei tornei. Presto qui vedrai banner e call-to-action.</p>
    </div>
  </div>

<?php
// Footer (lasciamolo visibile anche su mobile per ora, poi lo sposteremo nel drawer)
if (file_exists($ROOT . '/footer_public.php')) {
  require_once $ROOT . '/footer_public.php';
} elseif (file_exists($ROOT . '/footer.php')) {
  require_once $ROOT . '/footer.php';
}
?>
</body>
</html>
