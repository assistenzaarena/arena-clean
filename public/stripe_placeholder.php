<?php
// [SCOPO] Placeholder che simula la partenza verso Stripe Checkout.
// [USO]   Riceve "amount" in GET dalla pagina ricarica.php e mostra cosa succederebbe.
// [NEXT]  Nel prossimo step sostituiremo TUTTO con la creazione di una sessione Stripe vera.

header('Content-Type: text/plain; charset=utf-8'); // Output testuale per chiarezza

// [RIGA] Leggiamo l'importo passato dal form (GET) e normalizziamo a intero
$amount = isset($_GET['amount']) ? (int)$_GET['amount'] : 0; // harden: cast a int

// [RIGA] Validazione base: importo minimo 1
if ($amount < 1) {
    echo "❌ Importo non valido. Torna a /ricarica.php e inserisci un numero >= 1.\n";
    exit;
}

// [RIGA] Stampa esplicativa di ciò che faremo con Stripe nel prossimo step
echo "✅ Importo ricevuto: €{$amount}\n";
echo "➡️  Qui creeremo una Stripe Checkout Session lato server\n";
echo "➡️  e poi faremo redirect al link di pagamento restituito da Stripe.\n";

// [RIGA] Esempio (solo illustrativo) di cosa succederà:
// echo \"Redirect verso: https://checkout.stripe.com/c/session_XXXX\";
