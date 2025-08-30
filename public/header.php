<?php
// [SCOPO] Header dinamico che adatta i contenuti in base al ruolo (guest/user/admin).
// [STEP]  Ora implementiamo la versione USER, con:
//         - logo + scritta "ARENA" a sinistra
//         - a destra: pulsante "Ricarica", saldo crediti con tasto refresh, avatar+username, bottone Logout.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// In futuro questi valori arriveranno dal DB
$role     = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? 'DemoUser';   // placeholder
$crediti  = $_SESSION['crediti'] ?? 100;           // placeholder

// Avatar = prima lettera dello username
$avatarChr = $username !== '' ? mb_strtoupper(mb_substr($username, 0, 1)) : '?';
?>
<link rel="stylesheet" href="/assets/header.css">

<header class="hdr" role="banner">
  <div class="hdr__inner">

    <!-- Logo + scritta a sinistra -->
    <div class="hdr__left">
      <a class="logo" href="/" aria-label="Vai alla home">
        <img class="logo__img" src="/assets/logo_arena.png" alt="Logo Arena" width="56" height="56">
        <span class="logo__text">ARENA</span>
      </a>
    </div>

    <!-- Azioni a destra (user) -->
    <?php if ($role === 'user'): ?>
    <div class="hdr__right">

      <!-- Pulsante ricarica -->
      <a class="btn btn--primary" href="/ricarica.php">Ricarica</a>

      <!-- Saldo crediti con refresh -->
      <div class="user-credits">
        <span class="user-credits__label">Crediti:</span>
        <span class="user-credits__value" id="headerCrediti"><?php echo htmlspecialchars((string)$crediti); ?></span>
        <button class="credits-refresh" type="button" aria-label="Aggiorna saldo">
          &#x21bb;
        </button>
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
    <?php endif; ?>

  </div>
</header>
