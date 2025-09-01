<?php
// public/api/save_selection.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // per generate_unique_code8 se serve

function out($arr, $code=200){ http_response_code($code); echo json_encode($arr); exit; }

// --- Guardie base ---
if (empty($_SESSION['user_id'])) out(['ok'=>false,'error'=>'not_logged'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok'=>false,'error'=>'bad_method'], 405);
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) out(['ok'=>false,'error'=>'bad_csrf'], 400);

// --- Input ---
$user_id       = (int)$_SESSION['user_id'];
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$event_id      = isset($_POST['event_id'])      ? (int)$_POST['event_id']      : 0;
$life_index    = isset($_POST['life_index'])    ? (int)$_POST['life_index']    : -1;
$side          = strtolower((string)($_POST['side'] ?? ''));

if ($tournament_id<=0 || $event_id<=0 || $life_index<0 || !in_array($side,['home','away'],true)) {
  out(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
  // 1) Torneo open e non oltre lock
  $tq = $pdo->prepare("SELECT status, lock_at, max_lives_per_user FROM tournaments WHERE id=:id LIMIT 1");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t) out(['ok'=>false,'error'=>'not_found'], 404);
  if ($t['status'] !== 'open') out(['ok'=>false,'error'=>'locked'], 400);
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) out(['ok'=>false,'error'=>'locked'], 400);

  // 2) Iscrizione + vite disponibili
  $eq = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $eq->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $en = $eq->fetch(PDO::FETCH_ASSOC);
  if (!$en) out(['ok'=>false,'error'=>'not_enrolled'], 400);
  $lives = (int)$en['lives'];
  if ($life_index >= $lives) out(['ok'=>false,'error'=>'life_out_of_range'], 400);

  // 3) Evento valido e attivo nel torneo
  $vq = $pdo->prepare("SELECT 1 FROM tournament_events WHERE id=:e AND tournament_id=:t AND is_active=1 LIMIT 1");
  $vq->execute([':e'=>$event_id, ':t'=>$tournament_id]);
  if (!$vq->fetchColumn()) out(['ok'=>false,'error'=>'bad_event'], 400);

  // 4) UPSERT su UNIQUE (user_id, tournament_id, life_index)
  //    Nota: la TUA TABELLAs non ha 'locked' TINYINT: NON lo usiamo.
  //    Colonne reali: tournament_id, user_id, life_index, event_id, side, selection_code, created_at
  $pdo->beginTransaction();

  // Esiste già una selezione per quella vita?
  $sq = $pdo->prepare("
    SELECT id, selection_code
    FROM tournament_selections
    WHERE user_id=:u AND tournament_id=:t AND life_index=:l
    LIMIT 1
  ");
  $sq->execute([':u'=>$user_id, ':t'=>$tournament_id, ':l'=>$life_index]);
  $row = $sq->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    // Aggiorno solo event_id/side (mantengo selection_code)
    $up = $pdo->prepare("
      UPDATE tournament_selections
         SET event_id=:e, side=:s
       WHERE id=:id
       LIMIT 1
    ");
    $up->execute([':e'=>$event_id, ':s'=>$side, ':id'=>$row['id']]);
    $selCode = $row['selection_code'];
    $isNew   = false;
  } else {
    // Inserisco e genero selection_code (8 char univoco) SOLO se la tua colonna è NOT NULL; altrimenti potresti lasciarlo NULL
    // Per sicurezza lo genero sempre:
    $selCode = generate_unique_code8($pdo, 'tournament_selections', 'selection_code', 8);
    $in = $pdo->prepare("
      INSERT INTO tournament_selections
        (tournament_id, user_id, life_index, event_id, side, selection_code, created_at)
      VALUES
        (:t, :u, :l, :e, :s, :c, NOW())
    ");
    $in->execute([
      ':t'=>$tournament_id, ':u'=>$user_id, ':l'=>$life_index,
      ':e'=>$event_id, ':s'=>$side, ':c'=>$selCode
    ]);
    $isNew = true;
  }

  $pdo->commit();

  out([
    'ok' => true,
    'new'=> $isNew,
    'selection_code' => $selCode
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // DEBUG: in sviluppo mostra il dettaglio (se vuoi nasconderlo, togli 'detail')
  out(['ok'=>false,'error'=>'exception','detail'=>$e->getMessage()], 500);
}
