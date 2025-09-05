<?php
// =====================================================================
// /public/admin/set_event_result.php
// Aggiorna l'esito di un evento del torneo (admin). Supporta redirect.
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/guards.php';  require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

// Helpers
function back_with_flash(string $msg, string $type='ok', string $fallback='/admin/gestisci_tornei.php'){
  $_SESSION['flash'] = $msg;
  $_SESSION['flash_type'] = ($type==='error'?'error':'ok');
  $ref = $_SERVER['HTTP_REFERER'] ?? $fallback;
  header('Location: '.$ref);
  exit;
}

$wantRedirect = !empty($_POST['redirect']) || !empty($_GET['redirect']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $wantRedirect ? back_with_flash('Metodo non consentito.','error') : (http_response_code(405) && exit('bad method'));
}

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  $wantRedirect ? back_with_flash('Sessione scaduta, ricarica la pagina.','error') : (http_response_code(403) && exit('bad csrf'));
}

// Input
$eventId   = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$tourId    = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$status    = isset($_POST['result_status']) ? strtolower(trim((string)$_POST['result_status'])) : '';
$roundFrom = isset($_POST['round_no']) ? (int)$_POST['round_no'] : (isset($_POST['round'])?(int)$_POST['round']:0);

$allowed = ['pending','home_win','draw','away_win','postponed','void'];
if ($eventId<=0 || $tourId<=0 || !in_array($status, $allowed, true)) {
  $wantRedirect ? back_with_flash('Parametri non validi.','error') : (http_response_code(400) && exit('bad params'));
}

try {
  // verifica appartenenza evento
  $st = $pdo->prepare("SELECT id FROM tournament_events WHERE id=? AND tournament_id=? LIMIT 1");
  $st->execute([$eventId, $tourId]);
  if (!$st->fetchColumn()) {
    $wantRedirect ? back_with_flash('Evento non trovato per questo torneo.','error') : (http_response_code(404) && exit('not found'));
  }

  // update risultato
  $up = $pdo->prepare("UPDATE tournament_events SET result_status=?, result_at=NOW() WHERE id=? LIMIT 1");
  $up->execute([$status, $eventId]);

  if ($wantRedirect) {
    // redirect diretto al ricalcolo del round corretto
    $_SESSION['flash'] = 'Risultato aggiornato.';
    $_SESSION['flash_type'] = 'ok';
    $url = '/admin/round_ricalcolo.php?tournament_id='.$tourId;
    if ($roundFrom > 0) { $url .= '&round='.$roundFrom; }
    // opzionale: aggiungi &calc=1 per forzare un recalcolo in memoria se previsto
    $url .= '&from=result_save';
    header('Location: '.$url);
    exit;
  }

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true, 'event_id'=>$eventId, 'result_status'=>$status]); exit;

} catch (Throwable $e) {
  error_log('[set_event_result] '.$e->getMessage());
  if ($wantRedirect) back_with_flash('Errore interno, riprova.','error');
  http_response_code(500); echo 'exception'; exit;
}
