<?php
// public/api/add_life.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // generate_unique_code8()

/**
 * Risposta JSON standard
 */
function respond(array $payload, int $http = 200) {
  http_response_code($http);
  echo json_encode($payload);
  exit;
}

/* ===== 0) PRE-CONTROLLI ===== */
if (empty($_SESSION['user_id'])) {
  respond(['ok'=>false, 'error'=>'not_logged']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(['ok'=>false, 'error'=>'bad_method']);
}

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  respond(['ok'=>false, 'error'=>'bad_csrf']);
}

$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$user_id       = (int)$_SESSION['user_id'];
if ($tournament_id <= 0) {
  respond(['ok'=>false, 'error'=>'bad_params']);
}

/* ===== 1) LEGGO TORNEO E VINCOLI ===== */
try {
  $tq = $pdo->prepare("
      SELECT status, lock_at, cost_per_life, max_lives_per_user
      FROM tournaments
      WHERE id = :id
      LIMIT 1
  ");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);

  if (!$t)                       respond(['ok'=>false, 'error'=>'not_found']);
  if ($t['status'] !== 'open')   respond(['ok'=>false, 'error'=>'not_open']);
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    respond(['ok'=>false, 'error'=>'locked']); // dopo lock non si può comprare
  }

  $cost   = (int)$t['cost_per_life'];
  $maxPer = (int)$t['max_lives_per_user'];

  /* ===== 2) LEGGO ISCRIZIONE UTENTE ===== */
  $eq = $pdo->prepare("
      SELECT lives
      FROM tournament_enrollments
      WHERE user_id=:u AND tournament_id=:t
      LIMIT 1
  ");
  $eq->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $enroll = $eq->fetch(PDO::FETCH_ASSOC);

  if (!$enroll) {
    // per vite aggiuntive DEVI essere iscritto
    respond(['ok'=>false, 'error'=>'not_enrolled', 'msg'=>'Non sei iscritto a questo torneo.']);
  }

  $currentLives = (int)$enroll['lives'];
  if ($currentLives >= $maxPer) {
    respond(['ok'=>false, 'error'=>'max_reached', 'msg'=>'Hai già raggiunto il limite di vite.']);
  }

  /* ===== 3) TRANSAZIONE: addebito, update vite, log movimento ===== */
  $pdo->beginTransaction();

  // 3.1 Addebito crediti (solo se hai saldo sufficiente)
  $upd = $pdo->prepare("
      UPDATE utenti
      SET crediti = crediti - :c
      WHERE id = :u AND crediti >= :c
  ");
  $upd->execute([':c'=>$cost, ':u'=>$user_id]);
  if ($upd->rowCount() !== 1) {
    $pdo->rollBack();
    respond(['ok'=>false, 'error'=>'insufficient_funds', 'msg'=>'Crediti insufficienti.']);
  }

  // 3.2 Aggiorno vite
  $updLives = $pdo->prepare("
      UPDATE tournament_enrollments
      SET lives = lives + 1
      WHERE user_id = :u AND tournament_id = :t
      LIMIT 1
  ");
  $updLives->execute([':u'=>$user_id, ':t'=>$tournament_id]);

  // 3.3 Log movimento (addebito): type = 'buy_life', amount = cost (positivo nel log = importo del movimento)
  // N.B. Il segno lo gestiamo semantico: importo positivo = valore del movimento;
  //      sappiamo che 'buy_life' è un addebito. In report potrai mostrare “- amount”.
  $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
  $mov = $pdo->prepare("
      INSERT INTO credit_movements
          (movement_code, user_id, tournament_id, type, amount, created_at)
      VALUES
          (:code, :uid, :tid, 'buy_life', :amount, NOW())
  ");
  $mov->execute([
    ':code'   => $movCode,
    ':uid'    => $user_id,
    ':tid'    => $tournament_id,
    ':amount' => $cost
  ]);

  $pdo->commit();

  // 3.4 Rileggo vite e crediti header (opzionale, utile alla UI)
  $rLives = $pdo->prepare("
      SELECT lives FROM tournament_enrollments
      WHERE user_id=:u AND tournament_id=:t LIMIT 1
  ");
  $rLives->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $newLives = (int)($rLives->fetchColumn() ?: ($currentLives + 1));

  $rCred = $pdo->prepare("SELECT crediti FROM utenti WHERE id=:u LIMIT 1");
  $rCred->execute([':u'=>$user_id]);
  $headerCredits = (int)$rCred->fetchColumn();

  respond([
    'ok'             => true,
    'lives'          => $newLives,
    'header_credits' => $headerCredits
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  // se vuoi vedere l’errore reale in sviluppo, scommenta:
  // respond(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()], 500);
  respond(['ok'=>false, 'error'=>'exception'], 500);
}
