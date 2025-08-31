<?php
// public/api/add_life.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';  // generate_unique_code8()

// Deve essere loggato
if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'error'=>'not_logged']); exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'bad_method']); exit;
}

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
}

// Parametri
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id       = (int)($_SESSION['user_id'] ?? 0);
if ($tournament_id <= 0) {
  echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

try {
  // 1) Torneo: deve essere OPEN e non oltre lock
  $tq = $pdo->prepare("
    SELECT status, lock_at, cost_per_life, max_lives_per_user
    FROM tournaments
    WHERE id = :id
    LIMIT 1
  ");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);

  if (!$t) {
    echo json_encode(['ok'=>false,'error'=>'not_found']); exit;
  }
  if ($t['status'] !== 'open') {
    echo json_encode(['ok'=>false,'error'=>'not_open']); exit;
  }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    echo json_encode(['ok'=>false,'error'=>'locked']); exit;
  }

  $cost = (int)$t['cost_per_life'];             // crediti per vita
  $max  = (int)$t['max_lives_per_user'];        // limite vite per utente

  // 2) Deve essere già iscritto
  $eq = $pdo->prepare("
    SELECT lives
    FROM tournament_enrollments
    WHERE user_id = :u AND tournament_id = :t
    LIMIT 1
  ");
  $eq->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $enroll = $eq->fetch(PDO::FETCH_ASSOC);
  if (!$enroll) {
    echo json_encode(['ok'=>false,'error'=>'not_enrolled']); exit;
  }

  $lives_now = (int)$enroll['lives'];
  if ($max > 0 && $lives_now >= $max) {
    echo json_encode(['ok'=>false,'error'=>'max_reached']); exit;
  }

  // 3) Transazione: addebito + incrementa vite + log
  $pdo->beginTransaction();

  // 3.1) saldo sufficiente + addebito
  $upd = $pdo->prepare("
    UPDATE utenti
    SET crediti = crediti - :c
    WHERE id = :u AND crediti >= :c
  ");
  $upd->execute([':c'=>$cost, ':u'=>$user_id]);
  if ($upd->rowCount() !== 1) {
    $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'insufficient_funds']); exit;
  }

  // 3.2) incrementa vite
  $upLives = $pdo->prepare("
    UPDATE tournament_enrollments
    SET lives = lives + 1
    WHERE user_id = :u AND tournament_id = :t
  ");
  $upLives->execute([':u'=>$user_id, ':t'=>$tournament_id]);

  // 3.3) log movimento (addebito: importo negativo)
  $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
  $mov = $pdo->prepare("
    INSERT INTO credit_movements
      (movement_code, user_id, tournament_id, type, amount, created_at)
    VALUES
      (:mcode, :uid, :tid, 'buy_life', :amount, NOW())
  ");
  $mov->execute([
    ':mcode'  => $movCode,
    ':uid'    => $user_id,
    ':tid'    => $tournament_id,
    ':amount' => -$cost,  // addebito
  ]);

  // Calcolo nuovi valori da rimandare al client
  $credRow = $pdo->prepare("SELECT crediti FROM utenti WHERE id=:u LIMIT 1");
  $credRow->execute([':u'=>$user_id]);
  $newCredits = (int)$credRow->fetchColumn();

  $lRow = $pdo->prepare("
    SELECT lives FROM tournament_enrollments
    WHERE user_id=:u AND tournament_id=:t LIMIT 1
  ");
  $lRow->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $newLives = (int)$lRow->fetchColumn();

  $pdo->commit();

  echo json_encode([
    'ok'            => true,
    'lives'         => $newLives,
    'header_credits'=> $newCredits
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  // in produzione mantieni 'exception' generico; in dev puoi inviare $e->getMessage()
  echo json_encode(['ok'=>false, 'error'=>'exception']); 
}
