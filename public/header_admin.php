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
      <li class="subhdr__item"><a class="subhdr__link" href="/">Players</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/il-gioco">Crea Tornei</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/contatti">Gestisci Tornei</a></li>
      <li class="subhdr__item"><a class="subhdr__link" href="/contatti">Amministrazione</a></li>
    </ul>
  </div>
</nav>

