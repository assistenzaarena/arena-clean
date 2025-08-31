<?php
// public/api/unenroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Modalità: se arriva redirect=1 dal form, facciamo redirect server-side
$wantsRedirect = !empty($_POST['redirect']);
if (!$wantsRedirect) {
  header('Content-Type: application/json; charset=utf-8');
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

// Must be logged in
if (empty($_SESSION['user_id'])) {
  if ($wantsRedirect) {
    $_SESSION['flash'] = 'Devi effettuare l’accesso.';
    header('Location: /login.php'); exit;
  }
  echo json_encode(['ok'=>false, 'error'=>'not_logged']); exit;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  if ($wantsRedirect) {
    $_SESSION['flash'] = 'Metodo non valido.';
    header('Location: /lobby.php'); exit;
  }
  echo json_encode(['ok'=>false, 'error'=>'bad_method']); exit;
}

// CSRF (se stai usando il token globale in window.CSRF)
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  if ($wantsRedirect) {
    $_SESSION['flash'] = 'Sessione scaduta (CSRF). Riprova.';
    header('Location: /lobby.php'); exit;
  }
  echo json_encode(['ok'=>false, 'error'=>'bad_csrf']); exit;
}

// Input
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id = (int)$_SESSION['user_id'];

if ($tournament_id <= 0) {
  if ($wantsRedirect) {
    $_SESSION['flash'] = 'Parametri non validi.';
    header('Location: /lobby.php'); exit;
  }
  echo json_encode(['ok'=>false, 'error'=>'bad_params']); exit;
}

try {
  // Il torneo deve essere ancora OPEN e non oltre il lock
  $tq = $pdo->prepare("SELECT status, lock_at FROM tournaments WHERE id = :id LIMIT 1");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t) {
    if ($wantsRedirect) {
      $_SESSION['flash'] = 'Torneo non trovato.';
      header('Location: /lobby.php'); exit;
    }
    echo json_encode(['ok'=>false, 'error'=>'not_found']); exit;
  }
  if ($t['status'] !== 'open') {
    if ($wantsRedirect) {
      $_SESSION['flash'] = 'Il torneo non è più aperto.';
      header('Location: /torneo.php?id='.$tournament_id); exit;
    }
    echo json_encode(['ok'=>false, 'error'=>'not_open']); exit;
  }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    if ($wantsRedirect) {
      $_SESSION['flash'] = 'Scelte bloccate: non è più possibile disiscriversi.';
      header('Location: /torneo.php?id='.$tournament_id); exit;
    }
    echo json_encode(['ok'=>false, 'error'=>'locked']); exit;
  }

  // Elimina l’iscrizione se presente
  $del = $pdo->prepare("DELETE FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $del->execute([':u'=>$user_id, ':t'=>$tournament_id]);

  if ($wantsRedirect) {
    // Redirect server-side alla lobby
    $_SESSION['flash'] = 'Disiscrizione effettuata.';
    header('Location: /lobby.php'); exit;
  }

  echo json_encode(['ok'=>true, 'redirect'=>'/lobby.php']);
} catch (Throwable $e) {
  if ($wantsRedirect) {
    $_SESSION['flash'] = 'Errore di sistema. Riprova.';
    header('Location: /torneo.php?id='.$tournament_id); exit;
  }
  echo json_encode(['ok'=>false, 'error'=>'exception']); // logga lato server se vuoi $e->getMessage()
}
