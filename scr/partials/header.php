<?php
// Questo partial stampa l'header comune a tutte le pagine
// Scopo: mostrare logo, nav minima e pannello crediti (se utente loggato)

// Includiamo sessione per accedere a eventuale utente loggato
require_once __DIR__ . '/../session.php'; // Necessario per $_SESSION

// Includiamo la connessione DB per poter leggere i crediti (se loggato)
require_once __DIR__ . '/../db.php'; // Necessario per query crediti

// Prepariamo variabili di stato per la UI header
$logged_in = isset($_SESSION['user_id']); // Vero se abbiamo un utente loggato
$crediti = null;                          // Valore predefinito, sarà numerico se loggato

// Se loggato, recuperiamo i crediti in modo sicuro (prepared statement)
if ($logged_in) {
    // Query parametrica: preveniamo SQL injection
    $stmt = $pdo->prepare('SELECT crediti FROM utenti WHERE id = :id'); // :id placeholder
    $stmt->execute([':id' => $_SESSION['user_id']]);                    // Bind sicuro del parametro
    $row = $stmt->fetch();                                              // Otteniamo il risultato
    $crediti = $row ? (int)$row['crediti'] : 0;                         // Se non trovato, fallback 0
}
?>
<header class="site-header"><!-- Contenitore header per stile -->
  <div class="header-inner"><!-- Wrapper interno per layout -->
    <div class="logo"><!-- Area logo -->
      <a href="/"><!-- Link alla home -->
        ARENA <!-- Testo logo semplice (poi lo sostituiremo col tuo elmo SVG/PNG) -->
      </a>
    </div>
    <nav class="main-nav"><!-- Navigazione principale -->
      <a href="/">Home</a><!-- Link Home -->
      <a href="/regole.php">Il Gioco</a><!-- Placeholder pagina regole (arriva dopo) -->
      <a href="/contatti.php">Contatti</a><!-- Placeholder pagina contatti (arriva dopo) -->
    </nav>
    <div class="user-box"><!-- Box a destra per stato utente -->
      <?php if ($logged_in): ?><!-- Se utente loggato, mostra crediti -->
        <span class="crediti-label">Crediti:</span><!-- Etichetta -->
        <span class="crediti-val" id="creditiVal"><?php echo htmlspecialchars((string)$crediti); ?></span><!-- Valore iniziale -->
        <span class="crediti-icona">💰</span><!-- Icona semplice (sostituibile) -->
      <?php else: ?><!-- Se non loggato, mostra link Auth -->
        <a class="btn" href="/login.php">Login</a><!-- Link login (implementiamo dopo) -->
        <a class="btn btn-primary" href="/registrati.php">Registrati</a><!-- Link registrazione -->
      <?php endif; ?><!-- Fine condizione loggato -->
    </div>
  </div>
</header>
