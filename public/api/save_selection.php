<?php
// public/api/save_selection.php
// Salva / aggiorna la selezione (vita -> evento, side) dell’utente

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // generate_unique_code8()

function out($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

// Guardie base
if (empty($_SESSION['user_id']))                   out(['ok'=>false,'error'=>'not_logged'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')         out(['ok'=>false,'error'=>'bad_method'], 405);

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf))  out(['ok'=>false,'error'=>'bad_csrf'], 400);

// Input grezzi
$user_id       = (int)$_SESSION['user_id'];
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$event_id      = isset($_POST['event_id'])      ? (int)$_POST['event_id']      : 0;
$life_index    = isset($_POST['life_index'])    ? (int)$_POST['life_index']    : -1;
$side          = strtolower((string)($_POST['side'] ?? ''));

if ($tournament_id <= 0 || $event_id <= 0 || $life_index < 0 || !in_array($side, ['home','away'], true)) {
  out(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
  // Torneo: open e prima del lock
  $tq = $pdo->prepare("SELECT status, lock_at FROM tournaments WHERE id = ? LIMIT 1");
  $tq->execute([$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t)                                   out(['ok'=>false,'error'=>'not_found'], 404);
  if ($t['status'] !== 'open')               out(['ok'=>false,'error'=>'locked'], 400);
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) out(['ok'=>false,'error'=>'locked'], 400);

  // Iscrizione e range vita
  $eq = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id = ? AND tournament_id = ? LIMIT 1");
  $eq->execute([$user_id, $tournament_id]);
  $en = $eq->fetch(PDO::FETCH_ASSOC);
  if (!$en)                                  out(['ok'=>false,'error'=>'not_enrolled'], 400);
  $lives = (int)$en['lives'];
  if ($life_index >= $lives)                  out(['ok'=>false,'error'=>'life_out_of_range'], 400);

  // Evento valido e attivo nel torneo
  $evq = $pdo->prepare("SELECT 1 FROM tournament_events WHERE id = ? AND tournament_id = ? AND is_active = 1 LIMIT 1");
  $evq->execute([$event_id, $tournament_id]);
  if (!$evq->fetchColumn())                   out(['ok'=>false,'error'=>'bad_event'], 400);

  // Upsert manuale con ? (no nominati)
  $pdo->beginTransaction();

  // Esiste già su (user,tournament,life_index)?
  $selq = $pdo->prepare("SELECT id, selection_code FROM tournament_selections WHERE user_id = ? AND tournament_id = ? AND life_index = ? LIMIT 1");
  $selq->execute([$user_id, $tournament_id, $life_index]);
  $row = $selq->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    $up = $pdo->prepare("UPDATE tournament_selections SET event_id = ?, side = ? WHERE id = ? LIMIT 1");
    $up->execute([$event_id, $side, (int)$row['id']]);
    $selCode = $row['selection_code'];
  } else {
    $selCode = generate_unique_code8($pdo, 'tournament_selections', 'selection_code', 8);
    $in = $pdo->prepare("
      INSERT INTO tournament_selections
        (tournament_id, user_id, life_index, event_id, side, selection_code, created_at)
      VALUES
        (?, ?, ?, ?, ?, ?, NOW())
    ");
    $in->execute([$tournament_id, $user_id, $life_index, $event_id, $side, $selCode]);
  }

  $pdo->commit();

  out(['ok'=>true, 'selection_code'=>$selCode]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // In dev mostro il dettaglio per chiudere subito i problemi
  if (!defined('APP_ENV') || APP_ENV !== 'production') {
    out(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage()], 500);
  }
  out(['ok'=>false,'error'=>'exception'], 500);
}
