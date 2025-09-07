<?php
// public/cookie-policy.php — Cookie Policy
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__;
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Cookie Policy — ARENA Survivor</title>
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
    .btn{display:inline-block;padding:8px 12px;border:1px solid rgba(255,255,255,.25);border-radius:10px;text-decoration:none;color:#fff}
    .btn:hover{border-color:#fff}
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
  <h1>Cookie Policy</h1>
  <p class="note">Titolare: <strong>Valenzo srls</strong> — P. IVA <strong>17381361009</strong></p>

  <div class="card">
    <h2>1. Cosa sono i cookie</h2>
    <p>I cookie sono piccoli file di testo che il sito invia al dispositivo dell’utente, dove vengono memorizzati, per poi essere ritrasmessi alla visita successiva. Si distinguono per finalità e durata.</p>

    <h2>2. Tipologie utilizzate</h2>
    <ul>
      <li><strong>Tecnici/necessari</strong>: servono al funzionamento del sito (es. sessione, autenticazione, sicurezza). Sono installati senza consenso.</li>
      <li><strong>Preferenze</strong>: memorizzano scelte dell’utente (es. lingua). Possono richiedere consenso quando non strettamente necessari.</li>
      <li><strong>Statistici/Analytics</strong>: raccolgono informazioni aggregate sull’uso del sito. Se non anonimizzati, richiedono consenso.</li>
      <li><strong>Marketing/Profilazione</strong>: non essenziali; richiedono consenso esplicito prima dell’attivazione.</li>
    </ul>

    <h2>3. Gestione preferenze</h2>
    <p>Puoi gestire i cookies tramite il banner iniziale (quando presente) o le impostazioni del browser. La disattivazione dei cookie tecnici può compromettere alcune funzionalità.</p>

    <h2>4. Terze parti</h2>
    <p>Alcuni cookie possono essere impostati da fornitori terzi (es. strumenti di analisi). Le relative informative sono disponibili sui siti dei terzi.</p>

    <h2>5. Conservazione</h2>
    <p>I cookie hanno una durata variabile (di sessione o persistenti). I tempi sono indicati nelle impostazioni del browser o del banner.</p>

    <h2>6. Aggiornamenti</h2>
    <p>La presente policy può essere aggiornata in base a evoluzioni tecniche o normative.</p>

    <p><a href="/privacy.php" class="btn">Leggi l’Informativa Privacy</a></p>
  </div>
</div>

<?php if (file_exists($ROOT.'/footer.php')) { require $ROOT.'/footer.php'; } ?>
</body>
</html>
