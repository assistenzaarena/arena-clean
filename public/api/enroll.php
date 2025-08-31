<?php
// public/api/enroll.php (robusto, senza HY093, auto-detect registration_code)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';   // generate_unique_code / generate_unique_code8

function jexit(array $p){ echo json_encode($p); exit; }

// Requisiti base
if (empty($_SESSION['user_id']))         jexit(['ok'=>false,'error'=>'not_logged']);
if ($_SERVER['REQUEST_METHOD']!=='POST') jexit(['ok'=>false,'error'=>'bad_method']);

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) jexit(['ok'=>false,'error'=>'bad_csrf']);

// Parametri
$tid = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($tid <= 0 || $uid <= 0) jexit(['ok'=>false,'error'=>'bad_params']);

try {
  // 1) Torneo: OPEN e prima del lock
  try {
    $tq = $pdo->prepare("SELECT status, lock_at, cost_per_life FROM tournaments WHERE id = ? LIMIT 1");
    $tq->execute([$tid]);
    $t = $tq->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    jexit(['ok'=>false,'error'=>'exception','stage'=>'sel_tournament','msg'=>$e->getMessage()]);
  }
  if (!$t)                            jexit(['ok'=>false,'error'=>'not_found']);
  if (($t['status'] ?? '') !== 'open') jexit(['ok'=>false,'error'=>'not_open']);
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time())
                                       jexit(['ok'=>false,'error'=>'locked']);

  $cost = (int)$t['cost_per_life'];

  // 2) Già iscritto?
  try {
    $ck = $pdo->prepare("SELECT 1 FROM tournament_enrollments WHERE user_id = ? AND tournament_id = ? LIMIT 1");
    $ck->execute([$uid, $tid]);
    if ($ck->fetchColumn()) jexit(['ok'=>false,'error'=>'already_enrolled']);
  } catch (Throwable $e) {
    jexit(['ok'=>false,'error'=>'exception','stage'=>'ck_enroll','msg'=>$e->getMessage()]);
  }

  // 3) Rilevo lo schema della colonna registration_code (esiste? lunghezza?)
  $hasReg = false;
  $regLen = null;
  try {
    $col = $pdo->prepare("
      SELECT CHARACTER_MAXIMUM_LENGTH AS L
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'tournament_enrollments'
        AND COLUMN_NAME  = 'registration_code'
      LIMIT 1
    ");
    $col->execute();
    $row = $col->fetch(PDO::FETCH_ASSOC);
    if ($row) { $hasReg = true; $regLen = (int)$row['L']; }
  } catch (Throwable $e) {
    // ignora: se fallisce il check, procederemo come se non esistesse
  }

  // 4) Inizio transazione
  $pdo->beginTransaction();

  // 4.1) Addebito saldo (solo se sufficiente)
  try {
    $upd = $pdo->prepare("UPDATE utenti SET crediti = crediti - ? WHERE id = ? AND crediti >= ?");
    $upd->execute([$cost, $uid, $cost]);
    if ($upd->rowCount() !== 1) {
      $pdo->rollBack();
      jexit(['ok'=>false,'error'=>'insufficient_funds']);
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jexit(['ok'=>false,'error'=>'exception','stage'=>'upd_user','msg'=>$e->getMessage()]);
  }

  // 4.2) Insert iscrizione (lives=1) con/ senza registration_code in base allo schema
  try {
    if ($hasReg) {
      // decido il formato del codice in base alla lunghezza colonna
      if ($regLen >= 8) {
        $regCode = generate_unique_code8($pdo, 'tournament_enrollments', 'registration_code', 8);
      } else {
        $regCode = generate_unique_code($pdo, 'tournament_enrollments', 'registration_code'); // 5 cifre
      }
      $ins = $pdo->prepare("
        INSERT INTO tournament_enrollments (user_id, tournament_id, registration_code, lives, created_at)
        VALUES (?, ?, ?, 1, NOW())
      ");
      $ins->execute([$uid, $tid, $regCode]);
    } else {
      $ins = $pdo->prepare("
        INSERT INTO tournament_enrollments (user_id, tournament_id, lives, created_at)
        VALUES (?, ?, 1, NOW())
      ");
      $ins->execute([$uid, $tid]);
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jexit(['ok'=>false,'error'=>'exception','stage'=>'ins_enroll','msg'=>$e->getMessage()]);
  }

  // 4.3) Log movimento (amount negativo = addebito) — no colonna 'sign'
  try {
    $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
    $mov = $pdo->prepare("
      INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at)
      VALUES (?, ?, ?, 'enroll', ?, NOW())
    ");
    $mov->execute([$movCode, $uid, $tid, -$cost]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jexit(['ok'=>false,'error'=>'exception','stage'=>'ins_movement','msg'=>$e->getMessage()]);
  }

  // 5) Commit e redirect
  $pdo->commit();
  jexit(['ok'=>true,'redirect'=>'/torneo.php?id='.$tid]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  jexit(['ok'=>false,'error'=>'exception','stage'=>'outer','msg'=>$e->getMessage()]);
}
