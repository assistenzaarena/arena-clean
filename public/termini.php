<?php
// public/termini.php — Termini e Condizioni di Servizio
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Termini e Condizioni — ARENA Survivor</title>
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
  <h1>Termini e Condizioni di Servizio</h1>
  <p class="note">Titolare del Servizio: <strong>Valenzo srls</strong> — P. IVA <strong>17381361009</strong></p>

  <div class="card">
    <h2>1. Definizioni</h2>
    <ul>
      <li><strong>Piattaforma</strong>: l’infrastruttura digitale di intrattenimento sportivo accessibile tramite i domini gestiti dal Titolare.</li>
      <li><strong>Servizio</strong>: le funzionalità e i contenuti resi disponibili tramite la Piattaforma.</li>
      <li><strong>Account</strong>: profilo personale dell’utente, creato con credenziali riservate.</li>
      <li><strong>Crediti</strong>: unità virtuali utilizzate in Piattaforma. Non sono denaro, non generano interessi, non sono convertibili in valuta e non sono rimborsabili salvo obblighi di legge.</li>
      <li><strong>Eventi/Attività</strong>: esperienze digitali a tema sportivo (es. sessioni, round) disponibili sulla Piattaforma.</li>
      <li><strong>Riconoscimenti</strong>: benefit o status virtuali collegati all’esperienza in Piattaforma.</li>
    </ul>

    <h2>2. Oggetto</h2>
    <p>I presenti Termini regolano l’uso della Piattaforma. Il Servizio è fornito “così com’è” per finalità di intrattenimento digitale. Non costituisce strumento finanziario né offre rendimenti economici.</p>

    <h2>3. Registrazione e requisiti</h2>
    <ul>
      <li>La creazione dell’Account richiede maggiore età (18+), dati veritieri e un solo profilo per persona.</li>
      <li>L’utente è responsabile delle attività effettuate con il proprio Account e della custodia delle credenziali.</li>
      <li>Il Titolare può rifiutare, sospendere o revocare l’accesso per ragioni di sicurezza, legali o di corretto uso del Servizio.</li>
    </ul>

    <h2>4. Licenza d’uso e Proprietà intellettuale</h2>
    <ul>
      <li>Il Titolare concede una licenza personale, non esclusiva e revocabile ad utilizzare la Piattaforma per fini personali e non commerciali, nel rispetto del Regolamento e delle presenti Condizioni.</li>
      <li>Marchi, loghi, interfacce e contenuti sono protetti. È vietata qualsiasi riproduzione o uso non autorizzato.</li>
    </ul>

    <h2>5. Crediti, prezzi e pagamenti</h2>
    <ul>
      <li>I Crediti sono strumenti virtuali per fruire di funzionalità/esperienze digitali. <strong>Non rappresentano somme di denaro</strong> e non sono convertibili o trasferibili tra utenti.</li>
      <li>Eventuali ricariche o acquisti digitali possono essere gestiti tramite fornitori di pagamento terzi. Il Titolare non conserva i dati completi delle carte di pagamento.</li>
      <li>Salvo diversa indicazione, i prezzi sono espressi in Euro e possono includere imposte ove dovute.</li>
      <li><strong>Recesso digitale</strong>: per contenuti/servizi digitali forniti con esecuzione immediata e con consenso espresso, l’utente accetta di <strong>rinunciare al diritto di recesso</strong> ai sensi dell’art. 59, lett. o) del Codice del Consumo.</li>
    </ul>

    <h2>6. Condotta dell’utente</h2>
    <ul>
      <li>È vietato: utilizzare bot o automazioni, aggirare limitazioni tecniche, manipolare l’esperienza altrui, pubblicare contenuti illeciti/offensivi, violare diritti di terzi.</li>
      <li>La violazione può comportare limitazioni, sospensioni o chiusura dell’Account, con perdita di progressi/crediti virtuali, senza indennizzo.</li>
    </ul>

    <h2>7. Modifiche del Servizio</h2>
    <p>Il Titolare può modificare, sospendere o cessare il Servizio (o parti di esso) in qualsiasi momento per ragioni tecniche, evolutive, organizzative o legali, anche senza preavviso, senza obbligo di indennizzo.</p>

    <h2>8. Dati di terzi, esattezza delle informazioni</h2>
    <p>Dati o contenuti di terzi (ad es. informazioni sportive) sono forniti “as is” e possono variare o essere corretti. Il Titolare non garantisce la disponibilità o l’accuratezza continua di tali dati.</p>

    <h2>9. Limitazione di responsabilità</h2>
    <ul>
      <li>Nei limiti di legge, il Titolare non risponde di danni indiretti, consequenziali, perdita di profitti o dati, interruzioni di servizio, fatti salvi i casi di dolo o colpa grave.</li>
      <li>L’utente utilizza la Piattaforma sotto propria responsabilità e adotta misure ragionevoli di sicurezza (es. gestione delle credenziali, device aggiornati).</li>
    </ul>

    <h2>10. Manleva</h2>
    <p>L’utente si impegna a tenere indenne il Titolare da pretese di terzi derivanti da uso illecito o non conforme della Piattaforma.</p>

    <h2>11. Sospensione e risoluzione</h2>
    <p>In caso di violazioni, il Titolare può sospendere o chiudere l’Account con effetto immediato, senza pregiudizio di ulteriori rimedi.</p>

    <h2>12. Legge applicabile e foro competente</h2>
    <p>I presenti Termini sono regolati dalla legge italiana. Per le controversie è competente il <strong>foro del luogo in cui ha sede legale il Titolare</strong>, fermo quanto previsto da eventuali norme inderogabili a tutela del consumatore.</p>

    <h2>13. Modifiche ai Termini</h2>
    <p>Il Titolare può aggiornare i Termini. La versione vigente è pubblicata su questa pagina; l’uso continuato della Piattaforma implica accettazione delle modifiche.</p>

    <h2>14. Contatti</h2>
    <p>E-mail di riferimento: <a href="mailto:assistenza.arena@gmail.com">assistenza.arena@gmail.com</a></p>
  </div>

  <p class="note">Vedi anche: <a href="/regolamento.php">Regolamento</a> • <a href="/condizioni-generali.php">Condizioni generali</a> • <a href="/privacy.php">Privacy</a> • <a href="/cookie-policy.php">Cookie</a> • <a href="/assistenza.php">Assistenza</a></p>
</div>

<?php if (file_exists($ROOT.'/footer.php')) { require $ROOT.'/footer.php'; } ?>
</body>
</html>
