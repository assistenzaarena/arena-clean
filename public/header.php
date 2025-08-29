<?php
// [SCOPO] Header unico che si adatta a GUEST / USER / ADMIN                 // Spiega l’obiettivo del file
// [USO]   Includi questo file all’inizio delle pagine:                       // Istruzione d’uso
//         <?php require __DIR__ . '/header.php'; ?>                          // Percorso relativo dalla cartella public
// [NOTE]  Ricalca struttura e comportamenti dei frammenti che mi hai passato // Coerenza con i tuoi file forniti

// ========= STATO SESSIONE / RUOLO =========================================  // Sezione: determinazione stato utente
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }                                                             // Apriamo/continuiamo la sessione per leggere stato utente
$logged_in = !empty($_SESSION['user_id']);                                    // Boolean: vero se c’è un utente autenticato
$role      = $_SESSION['role'] ?? 'guest';                                    // Ruolo: 'admin' | 'user' | default 'guest'
$username  = $_SESSION['username'] ?? '';                                     // Username da mostrare quando loggato
$avatarChr = $username !== '' ? mb_strtoupper(mb_substr($username, 0, 1)) : ''; // Iniziale avatar (prima lettera maiuscola)

// ========= SALDO CREDITI (solo se loggato) ================================= // Sezione: calcolo crediti solo per user/admin
$crediti = null;                                                              // Inizializziamo il saldo a null (guest non ha saldo)
if ($logged_in) {                                                             // Se è loggato…
  require_once __DIR__ . '/src/config.php';                                   // …carichiamo i parametri DB (env) – niente hardcode
  require_once __DIR__ . '/src/db.php';                                       // …apriamo la connessione PDO ($pdo)
  $stmt = $pdo->prepare('SELECT crediti FROM utenti WHERE id = :id');         // Query parametrica (sicura) per leggere il saldo
  $stmt->execute([':id' => $_SESSION['user_id']]);                            // Eseguiamo bindando l’ID utente dalla sessione
  $row = $stmt->fetch();                                                      // Recuperiamo la riga (se esiste)
  $crediti = $row ? (int)$row['crediti'] : 0;                                 // Cast a int; fallback 0 se non trovato
}

// ========= CLASSE DI RUOLO PER STILE/COMPORTAMENTO ========================= // Sezione: classi CSS in base al ruolo
$roleClass = $role === 'admin' ? 'site-header--admin'                          // Se admin → classe admin
           : ($role === 'user' ? 'site-header--user' : 'site-header--guest');  // Se user → classe user, altrimenti guest

// ========= INIZIO MARCATURA HEADER ========================================= // Sezione: markup dell’header (ricalco dei tuoi frammenti)
?>
<link rel="stylesheet" href="/assets/header.css">                               <!-- Collega il CSS unico per l’header -->
<header role="banner" class="site-header <?php echo $roleClass; ?>"             <!-- Header: classe base + variante per ruolo -->
        data-role="<?php echo htmlspecialchars((string)$role, ENT_QUOTES, 'UTF-8'); ?>">                   <!-- Data-attribute utile per JS/test -->
  <div class="site-header__inner">                                              <!-- Wrapper interno per layout 3 aree -->

    <div class="site-header__left">                                             <!-- Colonna sinistra: toggle (mobile) + logo -->
      <button class="site-header__toggle"                                       <!-- Bottone toggle per aprire nav mobile -->
              aria-controls="mobile-menu" aria-expanded="false">Menu</button>    <!-- Aria-controls/expanded per accessibilità -->
      <a href="/" class="site-header__logo" aria-label="Vai alla Home">         <!-- Logo: link alla home -->
        <img class="logo__img" src="/assets/logo_arena.png" alt="Arena — elmo"  <!-- Immagine elmo (sostituisci percorso se diverso) -->
             width="56" height="56" />                                          <!-- Dimensioni logo coerenti -->
        <span class="logo__text" aria-hidden="true">ARENA</span>                <!-- Wordmark “ARENA”, nascosto ai lettori screen -->
      </a>
    </div>

    <div class="site-header__auth"                                              <!-- Colonna destra: azioni -->
         aria-label="<?php echo $logged_in ? 'Area utente' : 'Azioni account'; ?>"><!-- Label accessibile differenziata -->
      <?php if (!$logged_in): ?>                                                <!-- BLOCCO GUEST (non loggato) -->
        <a class="btn btn--primary" href="/registrazione">Registrati</a>        <!-- CTA primaria: Registrati -->
        <a class="btn btn--outline" href="/login.php">Login</a>                 <!-- CTA secondaria: Login -->
        <a class="auth__recovery" href="/recupero-password">Recupero password</a><!-- Link recovery piccolo -->
      <?php else: ?>                                                            <!-- BLOCCO LOGGATO (user o admin) -->
        <?php if ($role === 'admin'): ?>                                        <!-- Se ADMIN: azioni admin dedicate -->
          <a class="btn btn--primary" href="/admin">Pannello Admin</a>          <!-- Link rapido al pannello amministratore -->
        <?php else: ?>                                                          <!-- Se USER: azioni utente -->
          <a class="btn btn--primary" href="/ricarica.php" title="Ricarica crediti">Ricarica</a><!-- CTA ricarica -->
        <?php endif; ?>                                                         <!-- Fine differenziazione admin/user -->

        <div class="user-credits" aria-label="Saldo crediti">                   <!-- Pill crediti (stile uniforme) -->
          <span class="user-credits__label">Crediti</span>                      <!-- Etichetta -->
          <span class="user-credits__value" id="headerCrediti">                 <!-- Valore saldo (id per eventuale refresh JS) -->
            <?php echo htmlspecialchars((string)$crediti); ?>                   <!-- Stampa saldo dal DB -->
          </span>
          <button class="credits-refresh" type="button"                          <!-- Pulsante refresh (JS sotto): opzionale -->
                  aria-label="Aggiorna saldo crediti">
            <svg class="credits-refresh__icon" width="14" height="14"            <!-- Iconcina “refresh” -->
                 viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor"
                d="M17.65 6.35A7.95 7.95 0 0 0 12 4a8 8 0 1 0 7.75 10h-2.1a6 6 0 1 1-1.9-6.1L14 10h6V4l-2.35 2.35z"/>
            </svg>
          </button>
        </div>

        <div class="user-display" aria-label="Profilo utente">                  <!-- Avatar + nome utente -->
          <a class="user-avatar__link" href="<?php echo $role==='admin' ? '/admin' : '/profilo'; ?>" 
             aria-label="<?php echo $role==='admin' ? 'Apri pannello admin' : 'Apri il profilo utente'; ?>">
            <span class="user-avatar__circle"><?php echo htmlspecialchars($avatarChr); ?></span><!-- Cerchio con iniziale -->
          </a>
          <span class="user-display__name"><?php echo htmlspecialchars($username); ?></span><!-- Nome utente -->
        </div>

        <form class="logout-form" action="/logout.php" method="post">           <!-- Logout sempre come form POST -->
          <button class="btn btn--outline" type="submit">Logout</button>        <!-- Bottone Logout -->
        </form>
      <?php endif; ?>                                                           <!-- Fine blocco loggato -->
    </div>
  </div>

  <nav class="site-header__nav" aria-label="Navigazione principale">            <!-- NAV MOBILE (tendina dentro header) -->
    <ul class="primary-menu" id="mobile-menu">                                  <!-- Lista voci principale -->
      <?php if (!$logged_in): ?>                                                <!-- Voci per GUEST (mobile) -->
        <li class="primary-menu__item"><a class="primary-menu__link" href="/">Home</a></li><!-- Home -->
        <li class="primary-menu__item"><a class="primary-menu__link" href="/il-gioco">Il Gioco</a></li><!-- Regole -->
        <li class="primary-menu__item"><a class="primary-menu__link" href="/contatti">Contatti</a></li><!-- Contatti -->
      <?php else: ?>                                                            <!-- Voci per USER/ADMIN (mobile) -->
        <li class="primary-menu__item user-mobile">                              <!-- Sezione utente in cima -->
          <a class="user-avatar__link" href="<?php echo $role==='admin' ? '/admin' : '/profilo'; ?>">
            <span class="user-avatar__circle"><?php echo htmlspecialchars($avatarChr); ?></span><!-- Avatar -->
            <span class="user-display__name"><?php echo htmlspecialchars($username); ?></span><!-- Nome -->
          </a>
        </li>
        <?php if ($role === 'admin'): ?>                                        <!-- Menu admin -->
          <li class="primary-menu__item"><a class="primary-menu__link" href="/admin">Dashboard</a></li><!-- Admin dashboard -->
          <li class="primary-menu__item"><a class="primary-menu__link" href="/admin/utenti">Utenti</a></li><!-- Gestione utenti -->
          <li class="primary-menu__item"><a class="primary-menu__link" href="/admin/movimenti">Movimenti</a></li><!-- Movimenti -->
        <?php else: ?>                                                          <!-- Menu user -->
          <li class="primary-menu__item"><a class="primary-menu__link" href="/lobby">Lobby</a></li><!-- Lobby -->
          <li class="primary-menu__item"><a class="primary-menu__link" href="/storico-tornei">Storico Tornei</a></li><!-- Storico -->
          <li class="primary-menu__item"><a class="primary-menu__link" href="/regolamento">Regolamento</a></li><!-- Regole -->
          <li class="primary-menu__item"><a class="primary-menu__link" href="/movimenti">Lista Movimenti</a></li><!-- Movimenti -->
        <?php endif; ?>                                                         <!-- Fine differenziazione -->
        <li class="primary-menu__item logout-mobile">                            <!-- Logout in fondo -->
          <form class="logout-form" action="/logout.php" method="post">
            <button class="btn btn--outline" type="submit">Logout</button>
          </form>
        </li>
      <?php endif; ?>                                                           <!-- Fine voci mobile -->
    </ul>
  </nav>
</header>

<?php
// ========= SUB-NAV DESKTOP (fuori dall’header) ============================== // Barra secondaria sotto l’header (desktop)
?>
<?php if (!$logged_in): ?>                                                     <!-- Sub-nav per GUEST -->
<nav class="site-subnav" aria-label="Navigazione principale">                   <!-- Contenitore sub-nav -->
  <div class="subnav__inner">                                                   <!-- Wrapper centrato -->
    <ul class="subnav-menu">                                                    <!-- Lista voci -->
      <li class="subnav-menu__item"><a class="subnav-menu__link" href="/">Home</a></li>        <!-- Home -->
      <li class="subnav-menu__item"><a class="subnav-menu__link" href="/il-gioco">Il Gioco</a></li><!-- Gioco -->
      <li class="subnav-menu__item"><a class="subnav-menu__link" href="/contatti">Contatti</a></li><!-- Contatti -->
    </ul>
  </div>
</nav>
<?php else: ?>                                                                  <!-- Sub-nav per USER/ADMIN -->
<nav class="site-subnav" aria-label="Navigazione principale">                   <!-- Contenitore sub-nav -->
  <div class="subnav__inner">                                                   <!-- Wrapper centrato -->
    <ul class="subnav-menu">                                                    <!-- Lista voci -->
      <?php if ($role === 'admin'): ?>                                          <!-- Voci admin -->
        <li class="subnav-menu__item"><a class="subnav-menu__link" href="/admin">Dashboard</a></li><!-- Dashboard -->
        <li class="subnav-menu__item"><a class="subnav-menu__link" href="/admin/utenti">Utenti</a></li><!-- Utenti -->
        <li class="subnav-menu__item"><a class="subnav-menu__link" href="/admin/movimenti">Movimenti</a></li><!-- Movimenti -->
      <?php else: ?>                                                            <!-- Voci user -->
        <li class="subnav-menu__item"><a class="subnav-menu__link" href="/lobby">Lobby</a></li>          <!-- Lobby -->
        <li class="subnav-menu__item"><a class="subnav-menu__link" href="/storico-tornei">Storico Tornei</a></li><!-- Storico -->
        <li class="subnav-menu__item"><a class="subnav-menu__link" href="/regolamento">Regolamento</a></li><!-- Regolamento -->
        <li class="subnav-menu__item"><a class="subnav-menu__link" href="/movimenti">Lista Movimenti</a></li><!-- Movimenti -->
      <?php endif; ?>                                                           <!-- Fine voci role-based -->
    </ul>
  </div>
</nav>
<?php endif; ?>                                                                 <!-- Fine sub-nav -->

<script>
// [SCOPO] Mini-JS per togglare la tendina mobile (apri/chiudi)                // Script inline minimo come nei frammenti originali
(function () {                                                                  // IIFE per non inquinare scope globale
  const header = document.querySelector('.<?php echo $roleClass; ?>');          // Selezioniamo l’header (con classe ruolo)
  const btn    = header?.querySelector('.site-header__toggle');                 // Selettore del bottone toggle
  if (!header || !btn) return;                                                  // Se manca qualcosa, usciamo silenziosamente
  const menuId = btn.getAttribute('aria-controls') || 'mobile-menu';            // Recuperiamo id del menu da aria-controls
  const menu   = header.querySelector('#' + menuId);                            // Peschiamo l’UL della tendina
  btn.addEventListener('click', () => {                                         // Click → toggla stato
    const open = header.classList.toggle('menu-open');                          // Aggiunge/rimuove .menu-open all’header
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');                 // Aggiorna aria-expanded per accessibilità
    if (open && menu && typeof menu.focus === 'function') menu.focus();         // Focus alla tendina quando si apre (usabilità)
  });
})();
</script>
