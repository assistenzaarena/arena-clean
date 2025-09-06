<?php
// /partials/guest_mobile_header.php
// Dati base per i link login/registrazione
$LOGIN_URL = '/login.php';
$REGISTER_URL = '/registrazione.php';
?>
<link rel="stylesheet" href="/assets/guest_mobile.css">
<meta name="viewport" content="width=device-width, initial-scale=1">

<div class="g-head">
  <div class="g-logo">
    <img src="/assets/logo_arena.png" alt="ARENA">
    <div class="g-brand">ARENA</div>
  </div>
  <div class="g-spacer"></div>
  <a class="g-btn g-btn--primary" href="<?php echo htmlspecialchars($LOGIN_URL); ?>">Accedi</a>
  <button class="g-burger" aria-label="menu" data-g-open>&#9776;</button>
</div>

<script src="/assets/guest_mobile.js" defer></script>
