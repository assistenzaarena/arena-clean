<?php
// public/regolamento.php — Regolamento Piattaforma
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Regolamento — ARENA Survivor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    .page-wrap{max-width:960px;margin:30px auto;padding:0 16px;color:#fff}
    h1{font-size:28px;font-weight:900;margin:0 0 16px}
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
  <h1>Regolamento della Piattaforma</h1>
  <p class="note">Titolare del Servizio: <strong>Valenzo srls</strong> — P. IVA <strong>17381361009</strong></p>

  <div class="card">
    <h2>1. Scopo e ambito</h2>
    <p>Questo Regolamento disciplina l’uso della piattaforma di intrattenimento digitale a tema sportivo (“Piattaforma”). Le attività proposte sono finalizzate all’<strong>esperienza utente</strong> e all’aggregazione della community sportiva. Non si tratta di servizi finanziari, né di sistemi di investimento.</p>

    <h2>2. Accesso e Account</h2>
    <ul>
      <li>L’accesso richiede la creazione di un account personale. È vietato mantenere più account, impersonare terzi o fornire dati non veritieri.</li>
      <li>L’uso è riservato a maggiorenni (18+). L’utente è responsabile della custodia delle proprie credenziali.</li>
    </ul>

    <h2>3. Crediti e riconoscimenti</h2>
    <ul>
      <li>I “Crediti” sono <strong>unità virtuali</strong> utilizzate nella Piattaforma: <strong>non hanno valore monetario</strong>, non sono convertibili in denaro, non trasferibili fra utenti e non rimborsabili salvo diversa previsione di legge.</li>
      <li>Eventuali “riconoscimenti” o “benefit” sono collegati all’esperienza digitale e sono attribuiti a discrezione della Piattaforma. La loro natura non è finanziaria.</li>
    </ul>

    <h2>4. Svolgimento delle Attività</h2>
    <ul>
      <li>Le attività proposte (es. sessioni, eventi, round) possono prevedere interazioni, scelte tematiche o progressioni virtuali. Le regole specifiche sono descritte di volta in volta nelle relative pagine.</li>
      <li>In caso di <strong>imprevisti tecnici, aggiornamenti o esigenze organizzative</strong>, la Piattaforma può modificare, sospendere o chiudere un’attività, a propria discrezione e senza indennizzo.</li>
      <li>Eventuali informazioni sportive o dati di terzi sono forniti “come disponibili” e possono essere rettificate.</li>
    </ul>

    <h2>5. Condotta e Fair Play</h2>
    <ul>
      <li>È vietato l’uso di bot, script, exploit, multiaccount, pratiche di manipolazione o qualsiasi comportamento che alteri l’equità dell’esperienza.</li>
      <li>È vietato pubblicare o inviare contenuti offensivi, illeciti, diffamatori, discriminatori o che violino diritti di terzi.</li>
    </ul>

    <h2>6. Moderazione e Sanzioni</h2>
    <ul>
      <li>La Piattaforma può adottare in ogni momento le misure ritenute opportune: avvisi, limitazioni, sospensioni o chiusure di account, azzeramenti di progressi/crediti virtuali, esclusioni da attività.</li>
      <li>In presenza di violazioni gravi o ripetute, la Piattaforma può procedere al <strong>ban definitivo</strong>, senza obbligo di rimborso.</li>
    </ul>

    <h2>7. Interruzioni, manutenzioni, aggiornamenti</h2>
    <p>La Piattaforma può essere oggetto di manutenzioni o aggiornamenti che incidano temporaneamente sulla fruizione. L’utente accetta tali evenienze come parte del normale funzionamento del servizio digitale.</p>

    <h2>8. Segnalazioni e Reclami</h2>
    <p>Per segnalazioni o contestazioni scrivere a <a href="mailto:assistenza.arena@gmail.com">assistenza.arena@gmail.com</a>. La Piattaforma valuta ogni richiesta e può richiedere ulteriori informazioni. Le decisioni operative adottate per sicurezza, legalità, equità o integrità del servizio sono <strong>insindacabili</strong> nei limiti consentiti dalla legge.</p>

    <h2>9. Aggiornamenti</h2>
    <p>Il presente Regolamento può essere aggiornato per motivi tecnici/legali. Le modifiche sono comunicate mediante pubblicazione. L’uso continuato della Piattaforma implica accettazione delle modifiche.</p>
  </div>

  <p class="note">Testi correlati: <a href="/termini.php">Termini e condizioni</a> • <a href="/condizioni-generali.php">Condizioni generali</a> • <a href="/privacy.php">Privacy</a> • <a href="/cookie-policy.php">Cookie</a> • <a href="/assistenza.php">Assistenza</a></p>
</div>

<?php if (file_exists($ROOT.'/footer.php')) { require $ROOT.'/footer.php'; } ?>
</body>
</html>
