<?php
// public/contatti.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Contatti — ARENA Survivor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    .page-wrap{max-width:960px;margin:30px auto;padding:0 16px;color:#fff}
    h1{font-size:28px;font-weight:900;margin-bottom:16px}
    h2{font-size:18px;font-weight:800;margin-top:22px;margin-bottom:10px;color:#cfcfcf}
    p{line-height:1.6;margin-bottom:12px}
    .contact-card{
      background:#0f1114;border:1px solid rgba(255,255,255,.12);
      border-radius:12px;padding:16px;margin:14px 0;color:#fff
    }
    .mailto{
      display:inline-flex;align-items:center;gap:10px;
      padding:10px 14px;border:1px solid rgba(255,255,255,.25);
      border-radius:10px;text-decoration:none;color:#fff;font-weight:900
    }
    .mailto:hover{border-color:#fff}
    .hint{color:#9aa3af;font-size:13px;margin-top:6px}
    ul{margin:0 0 16px 18px}
    li{margin:4px 0}
    /* Responsive */
    @media (max-width:600px){
      .page-wrap{padding:0 12px}
      h1{font-size:22px}
      p,li{font-size:15px}
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
  <h1>Contatti</h1>

  <div class="contact-card">
    <h2>Supporto utenti</h2>
    <p>Per richieste di assistenza, segnalazioni tecniche o informazioni sull’utilizzo della piattaforma, scrivici all’indirizzo:</p>
    <p>
      <a class="mailto" href="mailto:assistenza.arena@gmail.com" rel="nofollow noopener">
        ✉️ assistenza.arena@gmail.com
      </a>
    </p>
    <p class="hint">Indica sempre oggetto, ID utente (se disponibile) e una breve descrizione della richiesta.</p>
  </div>

  <div class="contact-card">
    <h2>Tempi di risposta</h2>
    <p>Le richieste vengono elaborate in ordine di arrivo. Faremo il possibile per risponderti nel più breve tempo utile.</p>
  </div>

  <div class="contact-card">
    <h2>Indicazioni utili</h2>
    <ul>
      <li>Per domande frequenti consulta la pagina <a href="/faq.php">FAQ</a>.</li>
      <li>Per segnalazioni relative a comportamenti non conformi, utilizza <a href="/segnalazione-abusi.php">Segnalazione abusi</a>.</li>
      <li>Per informazioni sulla gestione dei dati personali consulta <a href="/privacy.php">Privacy e sicurezza</a> e <a href="/cookie-policy.php">Cookie policy</a>.</li>
      <li>Le regole di utilizzo della piattaforma sono descritte nei <a href="/termini.php">Termini e condizioni</a>.</li>
    </ul>
  </div>
</div>

<?php
if (file_exists($ROOT . '/footer.php')) {
  require $ROOT . '/footer.php';
}
?>
</body>
</html>
