<!-- Header guest: sfondo nero, logo a sinistra, azioni a destra -->
<link rel="stylesheet" href="/assets/header_guest.css">
<link rel="stylesheet" href="/assets/mobile_guest_global.css">

<header class="hdr" role="banner" aria-label="Intestazione sito">
  <div class="hdr__inner">
    <div class="hdr__left">
      <a class="logo" href="/" aria-label="Vai alla home">
        <img class="logo__img" src="/assets/logo_arena.png" alt="Logo Arena" width="56" height="56">
        <span class="logo__text">ARENA</span>
      </a>
    </div>

    <div class="hdr__right" aria-label="Azioni account">
      <a class="btn btn--primary" href="/registrazione">Registrati</a>
      <a class="btn btn--outline" href="/login.php">Login</a>
    </div>
  </div>
</header>
<!-- Sub-header di navigazione principale -->
<nav class="subhdr" role="navigation" aria-label="Navigazione principale">
  <div class="subhdr__inner">
    <ul class="subhdr__menu">
      <li class="subhdr__item"><a class="subhdr__link" href="/">Home</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/il-gioco">Il Gioco</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/contatti">Contatti</a></li>
    </ul>
  </div>
</nav>
<?php
// Include una sola volta i componenti mobile per TUTTE le pagine guest
if (!defined('ARENA_MOBILE_GUEST_INCLUDED')) {
  define('ARENA_MOBILE_GUEST_INCLUDED', 1);
  require __DIR__ . '/public/partials/guest_mobile_header.php';
  require __DIR__ . '/public/partials/guest_mobile_drawer.php';
}
?>
