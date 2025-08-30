<?php
// [SCOPO] Header per utente loggato (USER).
// [STRUTTURA] Logo + scritta ARENA a sinistra.
//             A destra: tasto "Ricarica", saldo crediti con refresh, avatar + username, bottone Logout.
// [USO]       Includilo nelle pagine dell’utente: <?php require __DIR__ . '/header_user.php'; ?>

// Qui non carichiamo il DB, usiamo valori fittizi per il test
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$username = $_SESSION['username'] ?? 'DemoUser';   // username di test
$crediti  = $_SESSION['crediti'] ?? 100;           // saldo fittizio
$avatarChr = mb_strtoupper(mb_substr($username, 0, 1)); // prima lettera come avatar
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
      <li class="subhdr__item"><a class="subhdr__link" href="/">Home</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/il-gioco">Il Gioco</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/contatti">Contatti</a></li>
    </ul>
  </div>
</nav>
