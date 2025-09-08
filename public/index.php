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
    .hero {
      text-align: center;
      padding: 48px 16px;
    }
    .hero img {
      width: 1200px;
      max-width: 100%; /* su schermi piccoli si ridimensiona */
      height: auto;     /* mantiene proporzioni */
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.6);
      margin: 40px auto;
      display: block;
    }
    .hero h1 {
      font-size: clamp(28px, 5vw, 56px);
      margin: 32px 0 16px;
      font-weight: 900;
      color: #fff;
    }
    .hero p {
      font-size: clamp(16px, 3vw, 20px);
      color: #ddd;
      margin: 0 0 20px;
      line-height: 1.5;
    }
    .hero small {
      display: block;
      font-size: 14px;
      color: #aaa;
      max-width: 700px;
      margin: 0 auto 40px;
      line-height: 1.6;
    }

    /* Mobile: aumenta spaziatura e adatta font */
    @media (max-width: 768px) {
      .hero {
        padding: 24px 12px;
      }
      .hero img {
        width: 100%; /* prende tutta la larghezza su mobile */
        border-radius: 8px;
      }
      .hero h1 {
        font-size: 24px;
      }
      .hero p {
        font-size: 16px;
      }
    }
  </style>
</head>
<body>

  <div class="hero">
    <img src="/assets/arena_1200x400.png" alt="Arena Home">
    <h1>Sarai tu il campione dell’Arena?</h1>
    <p>Unisciti alla sfida, scegli la tua squadra e sopravvivi turno dopo turno.</p>
    <small>
      ARENA è la nuova piattaforma di tornei calcistici a eliminazione. Ogni giornata
      scegli una squadra diversa: chi resiste fino alla fine vince. Preparati a vivere
      un’esperienza unica, tra adrenalina, strategia e passione per il calcio.
    </small>

    <h1>🔥 Sfida gli amici. Vinci la gloria. Conquista l’Arena.</h1>
    <p>
      Non importa da dove inizi, conta solo come sopravvivi.  
      L’Arena non perdona, ma premia chi resta in piedi fino all’ultimo.
    </p>
    <small>
      Registrati, allenati e preparati: presto arriveranno i tornei ufficiali, ricchi premi,
      classifiche live e un’esperienza di gioco mai vista prima.  
      La battaglia calcistica definitiva sta per cominciare.  
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
