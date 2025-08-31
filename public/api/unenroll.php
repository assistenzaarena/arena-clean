<?php
// public/api/unenroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$wantsRedirect = !empty($_POST['redirect']);
if (!$wantsRedirect) {
  header('Content-Type: application/json; charset=utf-8');
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // per generate_unique_code8

// Deve essere loggato
if (empty($_SESSION['user_id'])) {
  if ($wantsRedirect) { $_SESSION['flash'] = 'Devi effettuare l’accesso.'; header('Location:/login.php'); exit; }
  echo json_encode(['ok'=>false,'error'=>'not_logged']); exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  if ($wantsRedirect) { $_SESSION['flash'] = 'Metodo non valido.'; header('Location:/lobby.php'); exit; }
  echo json_encode(['ok'=>false,'error'=>'bad_method']); exit;
}

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  if ($wantsRedirect) { $_SESSION['flash'] = 'Sessione scaduta. Riprova.'; header('Location:/lobby.php'); exit; }
  echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
}

$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id = (int)$_SESSION['user_id'];
if ($tournament_id <= 0) {
  if ($wantsRedirect) { $_SESSION['flash'] = 'Parametri non validi.'; header('Location:/lobby.php'); exit; }
  echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

try {
  // Torneo deve essere OPEN e prima del lock
  $tq = $pdo->prepare("SELECT status, lock_at, cost_per_life FROM tournaments WHERE id=:id LIMIT 1");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t) {
    if ($wantsRedirect) { $_SESSION['flash']='Torneo non trovato.'; header('Location:/lobby.php'); exit; }
    echo json_encode(['ok'=>false,'error'=>'not_found']); exit;
  }
  if ($t['status'] !== 'open') {
    if ($wantsRedirect) { $_SESSION['flash']='Il torneo non è più aperto.'; header('Location:/torneo.php?id='.$tournament_id); exit; }
    echo json_encode(['ok'=>false,'error'=>'not_open']); exit;
  }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    if ($wantsRedirect) { $_SESSION['flash']='Scelte bloccate: non è più possibile disiscriversi.'; header('Location:/torneo.php?id='.$tournament_id); exit; }
    echo json_encode(['ok'=>false,'error'=>'locked']); exit;
  }

  // Carico iscrizione (per calcolare rimborso di tutte le vite)
  $eq = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $eq->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $enr = $eq->fetch(PDO::FETCH_ASSOC);
  if (!$enr) {
    if ($wantsRedirect) { $_SESSION['flash']='Non risultavi iscritto.'; header('Location:/lobby.php'); exit; }
    echo json_encode(['ok'=>false,'error'=>'not_enrolled']); exit;
  }

  $lives = (int)$enr['lives'];
  $refund = $lives * (int)$t['cost_per_life']; // rimborso totale (tutte le vite)

  $pdo->beginTransaction();

  // 1) Elimino iscrizione
  $del = $pdo->prepare("DELETE FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $del->execute([':u'=>$user_id, ':t'=>$tournament_id]);

  // 2) Rimborso saldo
  if ($refund > 0) {
    $upd = $pdo->prepare("UPDATE utenti SET crediti = crediti + :r WHERE id=:u");
    $upd->execute([':r'=>$refund, ':u'=>$user_id]);
  }

  // 3) Log movimento (rimborso)
  if ($refund > 0) {
    $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
    $mov = $pdo->prepare("
      INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, sign)
      VALUES (:m, :u, :t, 'unenroll', :a, +1)
    ");
    $mov->execute([':m'=>$movCode, ':u'=>$user_id, ':t'=>$tournament_id, ':a'=>$refund]);
  }

  $pdo->commit();

  if ($wantsRedirect) {
    $_SESSION['flash'] = 'Disiscrizione eseguita. Rimborso: '.$refund.' crediti.';
    header('Location: /lobby.php'); exit;
  }

  echo json_encode(['ok'=>true, 'redirect'=>'/lobby.php']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  if ($wantsRedirect) {
    $_SESSION['flash'] = 'Errore di sistema. Riprova.';
    header('Location:/torneo.php?id='.$tournament_id); exit;
  }
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
