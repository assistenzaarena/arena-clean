<?php
// [SCOPO] Header comune. NON apriamo il DB se l'utente non è logato,
//         per evitare timeouts e blocchi dei worker Apache.

// [RIGA] Sessione per capire se c'è un utente
require_once __DIR__ . '/../session.php'; // Serve per $_SESSION

// [RIGA] Flag login + valore crediti (null = non mostrabile)
$logged_in = isset($_SESSION['user_id']); // true se utente loggato
$crediti   = null;                        // inizialmente ignoto

// [RIGA] SOLO SE loggato, allora carichiamo il DB e leggiamo i crediti
if ($logged_in) {
  // Includiamo la connessione PDO SOLO quando serve
  require_once __DIR__ . '/../db.php'; // Evita di connettersi per utenti guest

  try {
    // Query parametrica per i crediti
    $stmt = $pdo->prepare('SELECT crediti FROM utenti WHERE id = :id'); // placeholder sicuro
    $stmt->execute([':id' => $_SESSION['user_id']]);                    // bind parametro
    $row = $stmt->fetch();                                              // leggi riga
    $crediti = $row ? (int)$row['crediti'] : 0;                         // cast a int
  } catch (Throwable $e) {
    // In produzione non mostriamo errori: al massimo nascondiamo i crediti
    $crediti = null; // fallback silenzioso
  }
}
?>
<header class="site-header">
  <div class="header-inner">
    <div class="logo"><a href="/">ARENA</a></div>
    <nav class="main-nav">
      <a href="/">Home</a>
      <a href="/regole.php">Il Gioco</a>
      <a href="/contatti.php">Contatti</a>
    </nav>
    <div class="user-box">
      <?php if ($logged_in): ?>
        <span class="crediti-label">Crediti:</span>
        <span class="crediti-val" id="creditiVal">
          <?php echo $crediti === null ? '—' : htmlspecialchars((string)$crediti); ?>
        </span>
        <span class="crediti-icona">💰</span>
      <?php else: ?>
        <a class="btn" href="/login.php">Login</a>
        <a class="btn btn-primary" href="/registrati.php">Registrati</a>
      <?php endif; ?>
    </div>
  </div>
</header>
