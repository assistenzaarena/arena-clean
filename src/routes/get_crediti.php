<?php
// Endpoint che restituisce i crediti dell'utente loggat in JSON
// Motivazione: separiamo la lettura crediti dall'HTML per aggiornamenti dinamici

// Header JSON per indicare il tipo di risposta al client
header('Content-Type: application/json; charset=utf-8'); // Comunichiamo che rispondiamo JSON

// Includiamo sessione per sapere chi è l'utente
require_once __DIR__ . '/../session.php'; // Necessario per $_SESSION

// Includiamo DB per poter fare query
require_once __DIR__ . '/../db.php'; // Necessario per PDO

// Se non loggato, ritorniamo ok=true ma crediti null (coerente con header che mostra "—")
if (empty($_SESSION['user_id'])) {
    // Risposta per utente non autenticato
    echo json_encode([
        'ok' => true,          // Indichiamo che l'endpoint ha funzionato
        'crediti' => null      // Nessun valore perché non loggato
    ]);
    exit; // Fermiamo l'esecuzione
}

// Se loggato, recuperiamo i crediti dal DB in modo sicuro
try {
    // Prepared statement contro SQL injection
    $stmt = $pdo->prepare('SELECT crediti FROM utenti WHERE id = :id'); // Placeholder :id
    $stmt->execute([':id' => $_SESSION['user_id']]);                    // Bind parametro

    // Recuperiamo riga
    $row = $stmt->fetch(); // Otteniamo risultato

    // Se trovato, cast a int; se non trovato, 0
    $crediti = $row ? (int)$row['crediti'] : 0; // Valore coerente

    // Risposta JSON
    echo json_encode([
        'ok' => true,          // Endpoint ok
        'crediti' => $crediti  // Valore numerico dei crediti
    ]);
} catch (Throwable $e) {
    // In caso di errore DB, non esponiamo dettagli in produzione
    http_response_code(500); // Errore server
    echo json_encode([
        'ok' => false,                // Endpoint in errore
        'error' => (APP_ENV !== 'production') ? $e->getMessage() : 'internal_error' // Dettagli solo in dev
    ]);
}
