<?php
// public/faq.php — Domande Frequenti
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>FAQ — ARENA Survivor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    .page-wrap{max-width:960px;margin:30px auto;padding:0 16px;color:#fff}
    h1{font-size:28px;font-weight:900;margin:0 0 16px}
    .faq{background:#0f1114;border:1px solid rgba(255,255,255,.12);border-radius:12px}
    .faq-item{padding:14px 16px;border-top:1px solid rgba(255,255,255,.08)}
    .faq-item:first-child{border-top:none}
    .q{font-weight:900}
    .a{color:#d9d9d9;margin-top:6px;line-height:1.65}
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
  <h1>Domande Frequenti (FAQ)</h1>

  <div class="faq">
    <div class="faq-item">
      <div class="q">Che cos’è ARENA Survivor?</div>
      <div class="a">È una piattaforma di <strong>intrattenimento digitale a tema sportivo</strong> che propone esperienze, sessioni ed eventi a progressione virtuale. Non è un servizio finanziario.</div>
    </div>

    <div class="faq-item">
      <div class="q">Cosa sono i “Crediti”?</div>
      <div class="a">Sono unità virtuali per fruire di funzionalità in piattaforma. <strong>Non hanno valore monetario</strong>, non sono convertibili in denaro e non sono trasferibili tra utenti.</div>
    </div>

    <div class="faq-item">
      <div class="q">Come si crea un account?</div>
      <div class="a">Vai su <a href="/registrazione.php">Registrazione</a>, inserisci i dati richiesti e accetta i <a href="/termini.php">Termini</a> e la <a href="/privacy.php">Privacy</a>. L’accesso è riservato a maggiorenni (18+).</div>
    </div>

    <div class="faq-item">
      <div class="q">Posso usare più account?</div>
      <div class="a">No. È consentito un solo account per persona. Multiaccount e condivisione credenziali non sono permessi.</div>
    </div>

    <div class="faq-item">
      <div class="q">I dati sportivi sono sempre aggiornati?</div>
      <div class="a">I dati possono provenire da fornitori terzi e potrebbero subire rettifiche. L’esperienza è offerta “così com’è”.</div>
    </div>

    <div class="faq-item">
      <div class="q">Come funziona l’assistenza?</div>
      <div class="a">Scrivi a <a href="mailto:assistenza.arena@gmail.com">assistenza.arena@gmail.com</a>. Per tempi e livelli di intervento leggi la pagina <a href="/assistenza.php">Assistenza</a>.</div>
    </div>

    <div class="faq-item">
      <div class="q">Come gestisco i cookie?</div>
      <div class="a">Dal banner iniziale (se presente) o dalle impostazioni del browser. Maggiori dettagli nella <a href="/cookie-policy.php">Cookie Policy</a>.</div>
    </div>

    <div class="faq-item">
      <div class="q">Cosa succede in caso di violazioni delle regole?</div>
      <div class="a">La Piattaforma può adottare avvisi, limitazioni, sospensioni o ban, anche con perdita di progressi/crediti virtuali. Vedi <a href="/regolamento.php">Regolamento</a> e <a href="/condizioni-generali.php">Condizioni generali</a>.</div>
    </div>
  </div>
</div>

<?php if (file_exists($ROOT.'/footer.php')) { require $ROOT.'/footer.php'; } ?>
</body>
</html>
