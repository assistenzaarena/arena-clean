<?php
// [HEAD USER] Avvio sessione e variabili di test (qui non tocchiamo il DB)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$username  = $_SESSION['username'] ?? 'DemoUser';          // username fittizio per test
$crediti   = $_SESSION['crediti']  ?? 100;                 // saldo fittizio per test
$avatarChr = mb_strtoupper(mb_substr($username, 0, 1));    // iniziale per l’avatar
?>
<link rel="stylesheet" href="/assets/header_user.css">

<header class="hdr" role="banner">
  <div class="hdr__inner">

    <!-- Logo a sinistra -->
    <div class="hdr__left">
      <a class="logo" href="/" aria-label="Vai alla home">
        <img class="logo__img" src="/assets/logo_arena.png" alt="Logo Arena" width="56" height="56">
        <span class="logo__text">ARENA</span>
      </a>
    </div>

    <!-- Azioni a destra -->
    <div class="hdr__right">

      <!-- Pulsante ricarica -->
      <a class="btn btn--primary" href="/ricarica.php">Ricarica</a>

      <!-- Saldo crediti -->
      <div class="user-credits">
        <span class="user-credits__label">Crediti:</span>
        <span class="user-credits__value" id="headerCrediti"><?php echo htmlspecialchars((string)$crediti); ?></span>
        <button class="credits-refresh" type="button" aria-label="Aggiorna saldo">&#x21bb;</button>
      </div>

      <!-- Avatar + username -->
      <div class="user-display">
        <span class="user-avatar__circle"><?php echo htmlspecialchars($avatarChr); ?></span>
        <span class="user-display__name"><?php echo htmlspecialchars($username); ?></span>
      </div>

      <!-- Logout -->
      <form class="logout-form" action="/logout.php" method="post">
        <button class="btn btn--outline" type="submit">Logout</button>
      </form>

    </div>
  </div>
</header>

<!-- Sub-header (uguale al guest) -->
<nav class="subhdr" role="navigation" aria-label="Navigazione principale">
  <div class="subhdr__inner">
    <ul class="subhdr__menu">
      <li class="subhdr__item"><a class="subhdr__link" href="/">Lobby</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/il-gioco">Storico Tornei</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/contatti">lista movimenti</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/contatti">Dati Utente</a></li>
    </ul>
  </div>
</nav>
