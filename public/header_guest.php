<?php
// [SCOPO] Header per utenti non loggati (guest): sfondo nero, logo a sinistra,
//         pulsanti "Registrati" e "Login" a destra, con "Recupero password" sotto al Login.
// [USO]   Includi questo file in cima alle pagine dove vuoi mostrare l'header guest:
//         <?php require __DIR__ . '/header_guest.php'; ?>
// [NOTE]  Questo file NON apre sessioni e NON fa query: è puramente grafico/strutturale.
?>
<!-- [RIGA] Collego il CSS specifico dell’header guest -->
<link rel="stylesheet" href="/assets/header_guest.css">

<!-- [RIGA] Inizio del banner principale dell’header -->
<header class="hdr" role="banner" aria-label="Intestazione sito">
  <!-- [RIGA] Contenitore interno centrato che gestisce il layout a 2 colonne -->
  <div class="hdr__inner">
    <!-- [RIGA] Colonna sinistra: logo che linka alla home -->
    <div class="hdr__left">
      <!-- [RIGA] Link al root del sito -->
      <a class="logo" href="/" aria-label="Vai alla home">
        <!-- [RIGA] Immagine logo; sostituisci il path se usi un nome diverso -->
        <img class="logo__img" src="/assets/logo_arena.png" alt="Logo Arena" width="56" height="56">
        <!-- [RIGA] Wordmark testuale, utile se l’immagine non carica -->
        <span class="logo__text">ARENA</span>
      </a>
    </div>

    <!-- [RIGA] Colonna destra: pulsanti azione -->
    <div class="hdr__right" aria-label="Azioni account">
      <!-- [RIGA] CTA principale: Registrati -->
      <a class="btn btn--primary" href="/registrazione">Registrati</a>
      <!-- [RIGA] CTA secondaria: Login -->
      <a class="btn btn--outline" href="/login.php">Login</a>
      <!-- [RIGA] Micro-link sotto il login: recupero password -->
      <a class="link--tiny" href="/recupero-password">Recupero password</a>
    </div>
  </div>
</header>
