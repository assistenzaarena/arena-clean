<?php
// public/api/save_selection.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // per generate_unique_code8()

function jexit(array $out, int $code = 200) {
  http_response_code($code);
  echo json_encode($out);
  exit;
}

/* =========================
   Guardie base (login, metodo, CSRF)
   ========================= */
if (empty($_SESSION['user_id'])) {
  jexit(['ok'=>false,'error'=>'not_logged'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jexit(['ok'=>false,'error'=>'bad_method'], 405);
}
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  jexit(['ok'=>false,'error'=>'bad_csrf'], 400);
}

/* =========================
   Parametri input
   ========================= */
$user_id       = (int)$_SESSION['user_id'];
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$event_id      = isset($_POST['event_id'])      ? (int)$_POST['event_id']      : 0;
$life_index    = isset($_POST['life_index'])    ? (int)$_POST['life_index']    : -1;
$side          = strtolower((string)($_POST['side'] ?? ''));

if ($tournament_id <= 0 || $event_id <= 0 || $life_index < 0 || !in_array($side, ['home','away'], true)) {
  jexit(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
  /* =========================
     Torneo: deve essere OPEN e prima del lock
     ========================= */
  $tq = $pdo->prepare("SELECT status, lock_at, max_lives_per_user FROM tournaments WHERE id = ? LIMIT 1");
  $tq->execute([$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t) {
    jexit(['ok'=>false,'error'=>'not_found'], 404);
  }
  if ($t['status'] !== 'open') {
    jexit(['ok'=>false,'error'=>'locked'], 400);
  }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    jexit(['ok'=>false,'error'=>'locked'], 400);
  }

  /* =========================
     Iscrizione: deve esistere e l'indice vita deve essere valido
     ========================= */
  $eq = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id = ? AND tournament_id = ? LIMIT 1");
  $eq->execute([$user_id, $tournament_id]);
  $en = $eq->fetch(PDO::FETCH_ASSOC);
  if (!$en) {
    jexit(['ok'=>false,'error'=>'not_enrolled'], 400);
  }
  $lives = (int)$en['lives'];
  if ($life_index >= $lives) {
    jexit(['ok'=>false,'error'=>'life_out_of_range'], 400);
  }

  /* =========================
     Evento: deve appartenere al torneo ed essere attivo
     ========================= */
  $vq = $pdo->prepare("SELECT 1 FROM tournament_events WHERE id = ? AND tournament_id = ? AND is_active = 1 LIMIT 1");
  $vq->execute([$event_id, $tournament_id]);
  if (!$vq->fetchColumn()) {
    jexit(['ok'=>false,'error'=>'bad_event'], 400);
  }

  /* =========================
     UPSERT su (user_id, tournament_id, life_index) con placeholder '?'
     (per evitare HY093 usiamo SELECT → UPDATE/INSERT separati)
     ========================= */
  $pdo->beginTransaction();

  // Esiste già una selezione per quella vita?
  $sq = $pdo->prepare("
    SELECT id, selection_code
    FROM tournament_selections
    WHERE user_id = ? AND tournament_id = ? AND life_index = ?
    LIMIT 1
  ");
  $sq->execute([$user_id, $tournament_id, $life_index]);
  $row = $sq->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    // UPDATE solo di event_id/side (mantieni selection_code)
    $up = $pdo->prepare("UPDATE tournament_selections SET event_id = ?, side = ? WHERE id = ? LIMIT 1");
    $up->execute([$event_id, $side, (int)$row['id']]);
    $selCode = $row['selection_code'];
    $isNew   = false;
  } else {
    // INSERT nuova riga con selection_code (8 char univoco)
    $selCode = generate_unique_code8($pdo, 'tournament_selections', 'selection_code', 8);
    $in = $pdo->prepare("
      INSERT INTO tournament_selections
        (tournament_id, user_id, life_index, event_id, side, selection_code, created_at)
      VALUES
        (?, ?, ?, ?, ?, ?, NOW())
    ");
    $in->execute([$tournament_id, $user_id, $life_index, $event_id, $side, $selCode]);
    $isNew = true;
  }

  $pdo->commit();

  jexit([
    'ok'              => true,
    'new'             => $isNew,
    'selection_code'  => $selCode
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  // In non-production mostriamo il dettaglio per chiudere subito i problemi
  if (!defined('APP_ENV') || APP_ENV !== 'production') {
    jexit(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage()], 500);
  }
  jexit(['ok'=>false,'error'=>'exception'], 500);
}
