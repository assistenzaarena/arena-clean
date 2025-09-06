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
    .hero{
      min-height: calc(100vh - 160px);
      display:flex; align-items:center; justify-content:center;
      text-align:center; padding: 24px;
    }
    .hero h1{ font-size: clamp(28px, 4vw, 56px); margin: 0 0 12px; font-weight: 900; }
    .hero p{ font-size: clamp(14px, 2vw, 18px); color:#aaa; margin:0; }

    /* ===== MOBILE ONLY: nascondi header desktop sotto 900px ===== */
    @media (max-width: 900px){
      header, .header, #header { display:none !important; }
    }
  </style>
</head>
<body>
<?php
// == DIAGNOSTICA & INCLUDE ROBUSTO ==
function _inc_partial($fname){
  $tries = [
    __DIR__ . '/arena-clean/partials/' . $fname,   // se partial sta in /var/www/html/arena-clean/partials
    __DIR__ . '/partials/' . $fname,               // se sta direttamente in /var/www/html/partials
    __DIR__ . '/public/arena-clean/partials/' . $fname,
    __DIR__ . '/../arena-clean/partials/' . $fname,
    (__DIR__ . '/../partials/' . $fname),
  ];
  foreach ($tries as $p) {
    if (is_file($p)) { require_once $p; return true; }
  }
  // stampa diagnostica (temporanea)
  echo '<pre style="color:#ff7076;background:#200;padding:8px;border-radius:6px">';
  echo "Partial NON trovato: {$fname}\n";
  echo "Sto eseguendo da __DIR__ = " . __DIR__ . "\n";
  echo "Ho provato:\n - " . implode("\n - ", $tries) . "</pre>";
  return false;
}

// header + drawer (guest)
_inc_partial('guest_mobile_header.php');
_inc_partial('guest_mobile_drawer.php');
?>

  <div class="hero">
    <div>
      <h1>Benvenuti in ARENA</h1>
      <p>La piattaforma dei tornei. Presto qui vedrai banner e call-to-action.</p>
    </div>
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
