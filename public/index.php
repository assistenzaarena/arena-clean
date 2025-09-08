<?php
// public/index.php — Home (Guest)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__; // <-- punta a /var/www/html

// Header guest
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
    :root { --gap: 40px; }

    .hero {
      text-align: center;
      padding: var(--gap) 16px;
    }

    .hero__imgWrap {
      margin: var(--gap) auto;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(0,0,0,.6);
      width: 710px;
      height: 380px;
    }

    .hero__img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      background:#0f0f0f;
    }

    .hero h1 {
      font-size: clamp(28px, 5vw, 56px);
      margin: 24px 0 12px;
      font-weight: 900;
      color:#fff;
    }
    .hero p {
      font-size: clamp(16px, 2.2vw, 20px);
      color:#ddd;
      margin:0 0 18px;
      line-height:1.5;
    }
    .hero small {
      display:block;
      font-size:14px;
      color:#aaa;
      max-width: 700px;
      margin: 0 auto var(--gap);
      line-height:1.6;
    }

    /* Mobile: immagine full width */
    @media (max-width: 768px){
      :root { --gap: 24px; }
      .hero__imgWrap {
        width: 100%;
        height: auto;
        border-radius: 8px;
      }
      .hero__img {
        width: 100%;
        height: auto;
        object-fit: contain;
      }
    }
  </style>
</head>
<body>

  <div class="hero">
    <div class="hero__imgWrap">
      <img class="hero__img"
           src="/assets/home_arena.jpg"
           alt="Arena Home" loading="eager" decoding="async">
    </div>

    <h1>Sarai tu il campione dell’Arena?</h1>
    <p>Unisciti alla sfida, scegli la tua squadra e sopravvivi turno dopo turno.</p>
    <small>
      ARENA è la nuova piattaforma di tornei calcistici a eliminazione. Ogni giornata
      scegli una squadra diversa: chi resiste fino alla fine vince. Preparati a vivere
      un’esperienza unica, tra adrenalina, strategia e passione per il calcio. <br><br>
      Rimani connesso: presto troverai tornei, premi, classifiche live e sfide esclusive.
    </small>

    <h1>🔥 Partecipa. Vinci la gloria. Conquista l’Arena.</h1>
    <p>Conta solo chi resta in piedi. L’Arena premia i coraggiosi.</p>
    <small>
      
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
