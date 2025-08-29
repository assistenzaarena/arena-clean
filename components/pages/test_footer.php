<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Test Footer — ARENA</title>

  <!-- IMPORTANTE: collega il CSS del footer (altrimenti lo vedresti “nudo”) -->
  <link rel="stylesheet" href="/css/footer.css" />

  <!-- Se vuoi vedere il footer nel contesto del sito, puoi collegare anche i CSS generali -->
  <!-- <link rel="stylesheet" href="/css/header_guest.css"> -->
</head>
<body style="min-height:60vh; background:#0f1114; color:#cfd3d8; font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">

  <main style="max-width:960px;margin:32px auto;padding:0 24px;">
    <h1>Pagina di test del footer</h1>
    <p>Se vedi il footer in basso, con i link cliccabili e lo stile scuro, è tutto ok.</p>
    <p>Puoi cancellare questa pagina dopo il test.</p>
  </main>

  <!-- INCLUDE del footer unico -->
  <?php include_once __DIR__ . "/../components/footer.php"; ?>

</body>
</html>
