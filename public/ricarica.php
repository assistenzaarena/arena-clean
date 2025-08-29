<?php
// [SCOPO] Pagina dedicata per inserire l'importo da ricaricare e avviare il "Deposita".
// [NOTE]  Qui NON facciamo ancora pagamenti: al submit reindirizziamo a un placeholder.
//         Nel prossimo step sostituiremo il placeholder con Stripe Checkout.

// [RIGA] Avviamo la sessione per poter, eventualmente, legare l'operazione all'utente loggato
session_start(); // Necessario se in futuro vorremo leggere $_SESSION['user_id']

// [RIGA] Header HTML minimo (niente include per isolare il test)
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Ricarica crediti</title>
</head>
<body>
  <h1>Ricarica crediti</h1>
  <p>Inserisci l'importo che vuoi depositare e premi "Deposita".</p>

  <!-- [RIGA] Form dedicato alla ricarica: per ora invia al placeholder -->
  <form method="get" action="/stripe_placeholder.php">
    <!-- [RIGA] Campo importo: number, minimo 1, step 1 -->
    <label>
      Importo (€):
      <input type="number" name="amount" min="1" step="1" required>
    </label>

    <!-- [RIGA] Bottone che avvia la "simulazione" del checkout -->
    <button type="submit">Deposita</button>
  </form>

  <!-- [RIGA] Link per tornare indietro all'area riservata -->
  <p style="margin-top:16px;">
    <a href="/area_riservata.php">← Torna all'area riservata</a>
  </p>
</body>
</html>
