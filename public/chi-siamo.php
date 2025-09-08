<?php
// public/chi-siamo.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Chi siamo — ARENA Survivor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    .page-wrap {
      max-width: 960px;
      margin: 30px auto;
      padding: 0 16px;
      color: #fff;
    }
    h1 {
      font-size: 28px;
      font-weight: 900;
      margin-bottom: 16px;
    }
    h2 {
      font-size: 20px;
      font-weight: 700;
      margin-top: 24px;
      margin-bottom: 12px;
    }
    p {
      line-height: 1.6;
      margin-bottom: 14px;
    }
    ul { margin: 0 0 18px 20px; }

    /* Responsive per mobile */
    @media (max-width: 600px) {
      .page-wrap { padding: 0 12px; }
      h1 { font-size: 22px; }
      h2 { font-size: 18px; }
      p, ul { font-size: 15px; }
    }
  </style>
</head>
<body>
<?php
// Header dinamico: se l'utente è loggato mostra header_user, altrimenti header_guest
if (file_exists($ROOT . '/header_user.php') && !empty($_SESSION['user_id'])) {
  require $ROOT . '/header_user.php';
} else {
  require $ROOT . '/header_guest.php';
}
?>

<div class="page-wrap">
  <h1>Chi siamo</h1>

  <p><strong>Arena Survivor</strong> è una piattaforma digitale indipendente che offre
     un’esperienza di intrattenimento innovativa e interattiva per gli appassionati di sport e competizioni.</p>

  <p>La nostra missione è garantire un ambiente sicuro, regolamentato e rispettoso delle regole,
     in cui ogni utente possa accedere a contenuti e funzionalità in modo trasparente.</p>

  <h2>I nostri principi fondamentali</h2>
  <ul>
    <li><strong>Trasparenza</strong> — Tutti i servizi, le regole di utilizzo e le modalità operative
        sono resi disponibili in modo chiaro e consultabile.</li>
    <li><strong>Correttezza</strong> — La piattaforma tutela la regolarità delle esperienze offerte.
        Qualsiasi comportamento in violazione delle regole può comportare la sospensione o l’esclusione dell’account.</li>
    <li><strong>Sicurezza</strong> — La tutela dei dati personali e il rispetto delle normative vigenti
        costituiscono una priorità. Arena Survivor opera in conformità al Regolamento UE 2016/679 (GDPR).</li>
  </ul>

  <p>L’utilizzo della piattaforma è riservato a utenti maggiorenni ed è subordinato
     all’accettazione dei Termini e Condizioni, i quali regolano in modo esaustivo i rapporti
     tra utenti e gestore del servizio.</p>
</div>

<?php
if (file_exists($ROOT . '/footer.php')) {
  require $ROOT . '/footer.php';
}
?>
</body>
</html>
