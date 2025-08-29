<?php
// La home pubblica di Arena
// Scopo: mostrare struttura base, includere header/footer, caricare CSS/JS

// Non serve qui session.php perché header.php già la includ
?>
<!DOCTYPE html> <!-- Dichiara HTML5 -->
<html lang="it"> <!-- Impostiamo lingua italiana per accessibilità/SEO -->
<head>
  <meta charset="utf-8"> <!-- Charset UTF-8 per caratteri italiani/emoticon -->
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Responsive su mobile -->
  <title>Arena — Home</title> <!-- Titolo della pagina -->

  <link rel="stylesheet" href="/assets/style.css"><!-- Colleghiamo CSS globale -->
</head>
<body> <!-- Inizio corpo pagina -->

  <?php require_once __DIR__ . '/../src/partials/header.php'; ?><!-- Includiamo header riutilizzabile -->

  <main class="container"><!-- Contenuto principale -->
    <h1>Benvenuto in Arena</h1><!-- Titolo di benvenuto -->
    <p>Qui costruiremo il gioco. Questa è la base online con crediti dinamici.</p><!-- Spiegazione -->
    <p><strong>Nota:</strong> se non sei loggato vedi “—” come crediti. Dopo implementiamo login/registrazione.</p><!-- Nota didattica -->
  </main>

  <?php require_once __DIR__ . '/../src/partials/footer.php'; ?><!-- Includiamo footer riutilizzabile -->

  <script src="/assets/script.js"></script><!-- Carichiamo JS client -->
</body>
</html>
