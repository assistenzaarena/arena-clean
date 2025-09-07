<?php
// public/assistenza.php — Assistenza / Supporto
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Assistenza — ARENA Survivor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    .page-wrap{max-width:960px;margin:30px auto;padding:0 16px;color:#fff}
    h1{font-size:28px;font-weight:900;margin:0 0 16px}
    h2{font-size:18px;font-weight:800;margin:22px 0 10px;color:#cfcfcf}
    p,li{line-height:1.65}
    ul{margin:0 0 16px 18px}
    .card{background:#0f1114;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin:14px 0}
    .mailto{display:inline-block;padding:8px 12px;border:1px solid rgba(255,255,255,.25);border-radius:10px;text-decoration:none;color:#fff;font-weight:900}
    .mailto:hover{border-color:#fff}
    .note{font-size:13px;color:#9aa3af}
    @media (max-width:600px){ .page-wrap{padding:0 12px} h1{font-size:22px} }
  </style>
</head>
<body>
<?php
if (!empty($_SESSION['user_id']) && file_exists($ROOT.'/header_user.php')) {
  require $ROOT.'/header_user.php';
} else {
  require $ROOT.'/header_guest.php';
}
?>

<div class="page-wrap">
  <h1>Assistenza</h1>

  <div class="card">
    <h2>Come contattarci</h2>
    <p>Per qualsiasi supporto operativo o segnalazione scrivi a:</p>
    <p><a class="mailto" href="mailto:assistenza.arena@gmail.com">assistenza.arena@gmail.com</a></p>
    <p class="note">Indica oggetto, username e una descrizione chiara della richiesta.</p>
  </div>

  <div class="card">
    <h2>Tempi e priorità</h2>
    <ul>
      <li><strong>Incidenti critici</strong> (sicurezza, indisponibilità): presa in carico prioritaria.</li>
      <li><strong>Problemi funzionali</strong>: gestione in ordine di arrivo.</li>
      <li><strong>Richieste generiche</strong>: risposta compatibile con i carichi operativi.</li>
    </ul>
  </div>

  <div class="card">
    <h2>Recupero account</h2>
    <ul>
      <li>Se sospetti accessi non autorizzati, <strong>cambia subito la password</strong> e contattaci.</li>
      <li>Per recupero, potremmo richiedere verifiche sull’identità.</li>
    </ul>
  </div>

  <div class="card">
    <h2>Segnalazione abusi</h2>
    <p>Comportamenti scorretti, contenuti illeciti o violazioni del Regolamento possono essere segnalati all’indirizzo di assistenza. La Piattaforma può intervenire con avvisi, limitazioni, sospensioni o ban.</p>
  </div>

  <p class="note">Leggi anche: <a href="/regolamento.php">Regolamento</a> • <a href="/termini.php">Termini</a> • <a href="/privacy.php">Privacy</a> • <a href="/cookie-policy.php">Cookie</a></p>
</div>

<?php if (file_exists($ROOT.'/footer.php')) { require $ROOT.'/footer.php'; } ?>
</body>
</html>
