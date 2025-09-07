<?php
// public/privacy.php — Informativa Privacy & Sicurezza (GDPR)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Privacy e Sicurezza — ARENA Survivor</title>
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
    .tag{display:inline-block;padding:2px 8px;border:1px solid rgba(255,255,255,.15);border-radius:999px;font-size:12px;margin-left:6px;color:#bbb}
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
  <h1>Privacy e Sicurezza</h1>
  <p class="note">Titolare del trattamento: <strong>Valenzo srls</strong> — P. IVA <strong>17381361009</strong> — Contatti privacy: <a href="mailto:assistenza.arena@gmail.com">assistenza.arena@gmail.com</a></p>

  <div class="card">
    <h2>1. Tipologie di dati trattati</h2>
    <ul>
      <li><strong>Dati di Account</strong>: username, e‑mail, eventuale telefono e dati necessari alla registrazione/gestione profilo.</li>
      <li><strong>Dati tecnici</strong>: log di accesso, indirizzo IP, identificativi device/browser, eventi di sicurezza.</li>
      <li><strong>Dati di fruizione</strong>: interazioni con funzionalità, preferenze, progressi e impostazioni.</li>
      <li><strong>Pagamenti</strong>: gestiti da fornitori terzi; la Piattaforma non conserva i dati completi di carta.</li>
    </ul>

    <h2>2. Finalità e basi giuridiche</h2>
    <ul>
      <li><strong>Erogazione del Servizio</strong> (art. 6.1.b GDPR — contratto): creare e gestire l’Account, consentire l’uso delle funzionalità.</li>
      <li><strong>Adempimenti legali</strong> (art. 6.1.c): es. obblighi contabili/fiscali ove applicabili.</li>
      <li><strong>Sicurezza e prevenzione abusi</strong> (art. 6.1.f — legittimo interesse): monitoraggio log, anti‑abuso, tutela integrità del Servizio.</li>
      <li><strong>Comunicazioni operative</strong> (art. 6.1.b): notifiche tecniche su cambi, aggiornamenti o sicurezza.</li>
      <li><strong>Marketing/Analytics</strong> (art. 6.1.a — consenso): ove impiegati strumenti non essenziali, previo consenso tramite banner cookie.</li>
    </ul>

    <h2>3. Conservazione</h2>
    <p>I dati sono conservati per il tempo necessario alle finalità indicate e/o secondo termini di legge. I log di sicurezza sono conservati per periodi proporzionati alle esigenze di tutela e difesa.</p>

    <h2>4. Destinatari e trasferimenti</h2>
    <ul>
      <li>Fornitori tecnici (hosting, pagamento, sicurezza, e‑mail). I rapporti sono regolati da contratti conformi al GDPR (es. art. 28).</li>
      <li>Eventuali trasferimenti extra‑UE avvengono con garanzie adeguate (es. clausole contrattuali standard, misure supplementari).</li>
    </ul>

    <h2>5. Misure di sicurezza</h2>
    <p>Applichiamo misure tecniche e organizzative adeguate alla natura dei trattamenti (controlli accessi, logging, cifratura in transito, principle of least privilege). Nessuna misura garantisce sicurezza assoluta, ma ci impegniamo al miglioramento continuo.</p>

    <h2>6. Cookie e tecnologie simili</h2>
    <p>I cookie tecnici sono necessari al funzionamento. Strumenti di analytics/marketing non essenziali sono attivati solo previo consenso ove previsto. Per dettagli e gestione preferenze vedi <a href="/cookie-policy.php">Cookie policy</a>.</p>

    <h2>7. Diritti dell’interessato</h2>
    <p>L’utente può esercitare i diritti previsti dagli artt. 15–22 GDPR (accesso, rettifica, cancellazione, limitazione, portabilità, opposizione, revoca del consenso) scrivendo a <a href="mailto:assistenza.arena@gmail.com">assistenza.arena@gmail.com</a>. È possibile proporre reclamo al Garante per la protezione dei dati personali.</p>

    <h2>8. Minori</h2>
    <p>La Piattaforma è rivolta a maggiorenni (18+). Dati relativi a minori non sono consapevolmente raccolti.</p>

    <h2>9. Aggiornamenti</h2>
    <p>Questa informativa può essere aggiornata. La versione vigente è pubblicata su questa pagina.</p>
  </div>

  <p class="note">Vedi anche: <a href="/termini.php">Termini</a> • <a href="/cookie-policy.php">Cookie policy</a> • <a href="/assistenza.php">Assistenza</a></p>
</div>

<?php if (file_exists($ROOT.'/footer.php')) { require $ROOT.'/footer.php'; } ?>
</body>
</html>
