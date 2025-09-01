<?php
/**
 * public/api/save_selection.php
 * Salva/aggiorna la selezione (vita → evento → side) per l'utente loggato.
 * Versione DIAGNOSTICA: logga errori e restituisce messaggi espliciti.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

// === LOAD CORE ===
$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';
require_once $ROOT . '/src/utils.php';

function respond(array $arr, int $http = 200) {
  http_response_code($http);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

// === CSRF ===
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  error_log('[save_selection] bad_csrf');
  respond(['ok'=>false,'error'=>'bad_csrf'], 400);
}

// === LOGIN ===
if (empty($_SESSION['user_id'])) {
  error_log('[save_selection] not_logged');
  respond(['ok'=>false,'error'=>'not_logged'], 401);
}
$user_id = (int)$_SESSION['user_id'];

// === PARAMS ===
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$event_id      = isset($_POST['event_id'])      ? (int)$_POST['event_id']      : 0;
$life_index    = isset($_POST['life_index'])    ? (int)$_POST['life_index']    : -1;
$side          = isset($_POST['side'])          ? trim(strtolower($_POST['side'])) : '';

if ($tournament_id <= 0 || $event_id <= 0 || $life_index < 0 || !in_array($side, ['home','away'], true)) {
  error_log('[save_selection] bad_params: tid='.$tournament_id.' ev='.$event_id.' life='.$life_index.' side='.$side);
  respond(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
  // === TORNEO ===
  $tq = $pdo->prepare("SELECT status, lock_at, max_lives_per_user FROM tournaments WHERE id=:id LIMIT 1");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t) {
    error_log("[save_selection] not_found tournament id=$tournament_id");
    respond(['ok'=>false,'error'=>'not_found'], 404);
  }
  if (($t['status'] ?? '') !== 'open') {
    error_log("[save_selection] not_open tournament id=$tournament_id");
    respond(['ok'=>false,'error'=>'not_open'], 400);
  }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    error_log("[save_selection] locked tournament id=$tournament_id");
    respond(['ok'=>false,'error'=>'locked'], 400);
  }
  $maxLives = isset($t['max_lives_per_user']) ? (int)$t['max_lives_per_user'] : 1;
  if ($life_index >= $maxLives) {
    error_log("[save_selection] life_index out of range life_index=$life_index max=$maxLives");
    respond(['ok'=>false,'error'=>'life_out_of_range'], 400);
  }

  // === EVENTO: appartenenza + attivo ===
  $eq = $pdo->prepare("
      SELECT 1
      FROM tournament_events
      WHERE id=:eid AND tournament_id=:tid AND is_active=1
      LIMIT 1
  ");
  $eq->execute([':eid'=>$event_id, ':tid'=>$tournament_id]);
  if (!$eq->fetchColumn()) {
    error_log("[save_selection] bad_event event=$event_id tournament=$tournament_id");
    respond(['ok'=>false,'error'=>'bad_event'], 400);
  }

  // === ISCRIZIONE ===
  $enq = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $enq->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $rowEnroll = $enq->fetch(PDO::FETCH_ASSOC);
  if (!$rowEnroll) {
    error_log("[save_selection] not_enrolled user=$user_id tournament=$tournament_id");
    respond(['ok'=>false,'error'=>'not_enrolled'], 400);
  }
  $userLives = (int)$rowEnroll['lives'];
  if ($life_index >= $userLives) {
    error_log("[save_selection] life_index >= userLives (life=$life_index lives=$userLives)");
    respond(['ok'=>false,'error'=>'life_out_of_range'], 400);
  }

  // === TRANSAZIONE SAVE ===
  $pdo->beginTransaction();

  // Esiste già una selezione per (user, tournament, life_index)?
  $selq = $pdo->prepare("
      SELECT id, selection_code
      FROM tournament_selections
      WHERE user_id=:u AND tournament_id=:t AND life_index=:l
      LIMIT 1
  ");
  $selq->execute([':u'=>$user_id, ':t'=>$tournament_id, ':l'=>$life_index]);
  $existing = $selq->fetch(PDO::FETCH_ASSOC);

  if ($existing) {
    // UPDATE di evento/side (mantiene selection_code)
    $upd = $pdo->prepare("
      UPDATE tournament_selections
         SET event_id = :e, side = :s
       WHERE id = :id
       LIMIT 1
    ");
    $upd->execute([
      ':e'  => $event_id,
      ':s'  => $side,
      ':id' => (int)$existing['id'],
    ]);
    $selectionCode = $existing['selection_code'];
    $savedNew = false;
  } else {
    // INSERT con nuovo selection_code
    $selectionCode = generate_unique_code8($pdo, 'tournament_selections', 'selection_code', 8);
    $ins = $pdo->prepare("
      INSERT INTO tournament_selections
        (tournament_id, user_id, life_index, event_id, side, selection_code, created_at)
      VALUES
        (:t, :u, :l, :e, :s, :c, NOW())
    ");
    $ins->execute([
      ':t' => $tournament_id,
      ':u' => $user_id,
      ':l' => $life_index,
      ':e' => $event_id,
      ':s' => $side,
      ':c' => $selectionCode,
    ]);
    $savedNew = true;
  }

  $pdo->commit();

  // Risposta diagnostica (ok)
  respond([
    'ok'             => true,
    'saved'          => true,
    'new'            => $savedNew,
    'selection_code' => $selectionCode,
    // echo parametri per debug
    'echo' => [
      'tournament_id' => $tournament_id,
      'event_id'      => $event_id,
      'life_index'    => $life_index,
      'side'          => $side,
    ],
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  // LOG dettagliato
  error_log('[save_selection] exception: '.$e->getMessage().' // line '.$e->getLine());
  respond(['ok'=>false,'error'=>'exception'], 500);
}
