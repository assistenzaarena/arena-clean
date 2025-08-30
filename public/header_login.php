<?php
// [SCOPO] Header minimale per la pagina di login.
//         Logo a sinistra, a destra un solo tasto "Esci" che riporta alla home guest.
?>
<link rel="stylesheet" href="/assets/header_login.css">

<header class="hdr" role="banner" aria-label="Intestazione sito">
  <div class="hdr__inner">
    
    <!-- Logo -->
    <div class="hdr__left">
      <a class="logo" href="/" aria-label="Vai alla home">
        <img class="logo__img" src="/assets/logo_arena.png" alt="Logo Arena" width="56" height="56">
        <span class="logo__text">ARENA</span>
      </a>
    </div>

    <!-- Pulsante "Esci" -->
    <div class="hdr__right" aria-label="Azioni account">
      <a class="btn btn--outline" href="/home_guest.php">Esci</a>
    </div>

  </div>
</header>
