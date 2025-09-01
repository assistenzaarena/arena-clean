<?php
// public/admin/set_event_result.php
// [SCOPO] Aggiornare l'esito di un evento del torneo (admin only).
//         Supporta JSON (default) oppure redirect con flash se arriva redirect=1.
// [ESITI] result_status: 'home_win', 'away_win', 'draw', 'postponed', 'void'

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__, 1); // /var/www/html/public/admin -> /var/www/html
require_once $ROOT . '/src/guards.php';    // require_admin()
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

require_admin(); // solo admin

// ---------- Helper risposta ----------
function respond_json(array $payload, int $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}
function redirect_back_with_flash(string $msg, string $type = 'ok', string $fallback = '/admin/gestisci_tornei.php') {
  $_SESSION['flash'] = $msg;
  $_SESSION['flash_type'] = ($type === 'error') ? 'error' : 'ok';
  $back = $_SERVER['HTTP_REFERER'] ?? $fallback;
  header('Location: ' . $back);
  exit;
}

// ---------- Modalità di risposta ----------
$wantRedirect = !empty($_POST['redirect']) || !empty($_GET['redirect']);

// ---------- Metodo e CSRF ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $wantRedirect
    ? redirect_back_with_flash('Metodo non consentito.', 'error')
    : respond_json(['ok'=>false,'error'=>'bad_method'], 405);
}
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  $wantRedirect
    ? redirect_back_with_flash('Sessione scaduta, ricarica la pagina.', 'error')
    : respond_json(['ok'=>false,'error'=>'bad_csrf'], 403);
}

// ---------- Input ----------
$event_id  = isset($_POST['event_id'])  ? (int)$_POST['event_id']  : 0;
$tour_id   = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$status    = isset($_POST['result_status']) ? strtolower(trim((string)$_POST['result_status'])) : '';

$allowed = ['home_win','away_win','draw','postponed','void'];
if ($event_id <= 0 || $tour_id <= 0 || !in_array($status, $allowed, true)) {
  $wantRedirect
    ? redirect_back_with_flash('Parametri non validi.', 'error')
    : respond_json(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
  // 1) Verifica che l’evento appartenga al torneo
  $st = $pdo->prepare("SELECT id FROM tournament_events WHERE id = ? AND tournament_id = ? LIMIT 1");
  $st->execute([$event_id, $tour_id]);
  if (!$st->fetchColumn()) {
    $wantRedirect
      ? redirect_back_with_flash('Evento non trovato per questo torneo.', 'error')
      : respond_json(['ok'=>false,'error'=>'event_not_found'], 404);
  }

  // 2) Aggiorna lo stato risultato + result_at coerente
  //    - esiti finali (home_win, away_win, draw, void) => result_at = NOW()
  //    - postponed => result_at = NULL
  $finalOutcomes = ['home_win','away_win','draw','void'];
  if (in_array($status, $finalOutcomes, true)) {
    $up = $pdo->prepare("UPDATE tournament_events SET result_status = ?, result_at = NOW() WHERE id = ? LIMIT 1");
    $up->execute([$status, $event_id]);
  } else { // postponed
    $up = $pdo->prepare("UPDATE tournament_events SET result_status = ?, result_at = NULL WHERE id = ? LIMIT 1");
    $up->execute([$status, $event_id]);
  }

  // 3) Risposta
  if ($wantRedirect) {
    redirect_back_with_flash('Risultato aggiornato.', 'ok');
  } else {
    respond_json(['ok'=>true, 'event_id'=>$event_id, 'result_status'=>$status]);
  }

} catch (Throwable $e) {
  // log nominale
  error_log('[set_event_result] '.$e->getMessage());
  if ($wantRedirect) {
    redirect_back_with_flash('Errore interno, riprova.', 'error');
  } else {
    respond_json(['ok'=>false,'error'=>'exception'], 500);
  }
}
