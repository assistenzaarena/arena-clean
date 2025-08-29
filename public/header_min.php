<?php
// deve essere la PRIMA riga del file, nessuno spazio o carattere prima di < ? p h p
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$_SESSION['role'] = $_SESSION['role'] ?? 'guest';
$role = $_SESSION['role'];
?>
<link rel="stylesheet" href="/assets/header.css">
<header class="site-header site-header--<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="site-header__inner">
    <div class="site-header__left">
      <a href="/" class="site-header__logo"><span class="logo__text">ARENA</span></a>
    </div>
    <div class="site-header__auth">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <span>Utente loggato</span>
      <?php else: ?>
        <a class="btn btn--outline" href="/login.php">Login</a>
      <?php endif; ?>
    </div>
  </div>
</header>
