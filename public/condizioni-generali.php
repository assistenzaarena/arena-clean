<?php
// public/condizioni-generali.php — Condizioni generali di utilizzo
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Condizioni Generali — ARENA Survivor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    .page-wrap{max-width:980px;margin:30px auto;padding:0 16px;color:#fff}
    h1{font-size:28px;font-weight:900;margin:0 0 18px}
    h2{font-size:18px;font-weight:800;margin:22px 0 10px;color:#cfcfcf}
    p,li{line-height:1.65}
    ul{margin:0 0 16px 18px}
    .card{background:#0f1114;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin:14px 0}
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
  <h1>Condizioni Generali di Utilizzo</h1>
  <p class="note">Titolare: <strong>Valenzo srls</strong> — P. IVA <strong>17381361009</strong></p>

  <div class="card">
    <h2>1. Uso corretto della Piattaforma</h2>
    <ul>
      <li>La Piattaforma è offerta per finalità di intrattenimento digitale. Ogni uso professionale/commerciale non autorizzato è vietato.</li>
      <li>È vietato interferire con la sicurezza del Servizio, tentare accessi non autorizzati, sondare vulnerabilità o distribuire malware.</li>
    </ul>

    <h2>2. Integrità dell’esperienza</h2>
    <ul>
      <li>Sono vietati bot, macro, scraping massivo, reverse engineering non consentito e qualsiasi automatismo che alteri le dinamiche del Servizio.</li>
      <li>Multiaccount, condivisione di credenziali, collusioni o schemi coordinati che distorcano l’esperienza sono vietati.</li>
    </ul>

    <h2>3. Contenuti generati dall’utente</h2>
    <ul>
      <li>L’utente garantisce di avere i diritti sui contenuti che pubblica e manleva il Titolare da pretese di terzi.</li>
      <li>Il Titolare può rimuovere contenuti inappropriati o illeciti, secondo ragionevole discrezionalità.</li>
    </ul>

    <h2>4. Sicurezza e prevenzione abusi</h2>
    <ul>
      <li>Il Titolare può applicare sistemi anti-abuso, log e controlli antifrode, nel rispetto della normativa applicabile.</li>
      <li>La violazione delle presenti Condizioni può comportare avvisi, limitazioni, sospensioni, ban e perdita di progressi/crediti virtuali.</li>
    </ul>

    <h2>5. Aggiornamenti, manutenzioni e disponibilità</h2>
    <p>La Piattaforma può essere aggiornata o soggetta a manutenzioni che incidano temporaneamente sulla disponibilità. L’utente accetta tali evenienze.</p>

    <h2>6. Gerarchia documentale</h2>
    <p>In caso di discrepanze, prevalgono i <a href="/termini.php">Termini e Condizioni</a>, quindi il <a href="/regolamento.php">Regolamento</a>, quindi le presenti Condizioni Generali.</p>
  </div>

  <p class="note">Voci utili: <a href="/termini.php">Termini</a> • <a href="/regolamento.php">Regolamento</a> • <a href="/privacy.php">Privacy</a> • <a href="/cookie-policy.php">Cookie</a> • <a href="/assistenza.php">Assistenza</a></p>
</div>

<?php if (file_exists($ROOT.'/footer.php')) { require $ROOT.'/footer.php'; } ?>
</body>
</html>
