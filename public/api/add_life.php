<?php
// public/api/add_life.php — versione diagnostica definitiva
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// header + no-cache
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php';
require_once $ROOT . '/src/game_rules.php'; // [STEP 2] regole lock (-5' & round)

function out($arr, $code = 200){ http_response_code($code); echo json_encode($arr); exit; }

// ---- Pre-controlli
if (empty($_SESSION['user_id']))           out(['ok'=>false,'error'=>'not_logged'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok'=>false,'error'=>'bad_method'], 405);

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) out(['ok'=>false,'error'=>'bad_csrf'], 403);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$tid     = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
if ($tid <= 0 || $user_id <= 0) out(['ok'=>false,'error'=>'bad_params'], 400);

try {
  // 1) Torneo: open + non lockato
  try {
    // [STEP 2] includo id + current_round_no per la regola
    $st = $pdo->prepare("SELECT id, current_round_no, status, lock_at, cost_per_life, max_lives_per_user FROM tournaments WHERE id = ? LIMIT 1");
    $st->execute([$tid]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { out(['ok'=>false,'error'=>'exception','stage'=>'sel_tournament','msg'=>$e->getMessage()], 500); }

  if (!$t)                                 out(['ok'=>false,'error'=>'not_found'], 404);
  if (($t['status'] ?? '') !== 'open')     out(['ok'=>false,'error'=>'not_open'], 409);

  // [STEP 2] blocco famiglia ENROLL/BUY-LIFE: dal round 2 in poi SEMPRE,
  // nel round 1 a -5' dal primo kickoff del torneo
  if (enroll_family_blocked_now($pdo, $t)) out(['ok'=>false,'error'=>'locked'], 409);

  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time())
                                           out(['ok'=>false,'error'=>'locked'], 409);

  $cost = (int)$t['cost_per_life'];
  $maxL = (int)$t['max_lives_per_user'];

  // 2) Deve essere iscritto + vite correnti
  try {
    $st = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id = ? AND tournament_id = ? LIMIT 1");
    $st->execute([$user_id, $tid]);
    $en = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { out(['ok'=>false,'error'=>'exception','stage'=>'sel_enroll','msg'=>$e->getMessage()], 500); }

  if (!$en)                                out(['ok'=>false,'error'=>'not_enrolled'], 409);
  $curLives = (int)$en['lives'];
  if ($maxL > 0 && $curLives >= $maxL)     out(['ok'=>false,'error'=>'lives_limit'], 409);

  // 3) Transazione: addebito -> +1 vita -> log movimento
  $pdo->beginTransaction();

  // 3.1 addebito
  try {
    $st = $pdo->prepare("UPDATE utenti SET crediti = crediti - ? WHERE id = ? AND crediti >= ?");
    $st->execute([$cost, $user_id, $cost]);
    if ($st->rowCount() !== 1) { $pdo->rollBack(); out(['ok'=>false,'error'=>'insufficient_funds'], 409); }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out(['ok'=>false,'error'=>'exception','stage'=>'upd_user_debit','msg'=>$e->getMessage()], 500);
  }

  // 3.2 incrementa vite
  try {
    $st = $pdo->prepare("UPDATE tournament_enrollments SET lives = lives + 1 WHERE user_id = ? AND tournament_id = ?");
    $st->execute([$user_id, $tid]);
    if ($st->rowCount() !== 1) { $pdo->rollBack(); out(['ok'=>false,'error'=>'enroll_update_failed'], 500); }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out(['ok'=>false,'error'=>'exception','stage'=>'upd_enroll_lives','msg'=>$e->getMessage()], 500);
  }

  // 3.3 log movimento (amount negativo = addebito)
  try {
    $movCode = generate_unique_code8($pdo, 'credit_movements', 'movement_code', 8);
    $st = $pdo->prepare("INSERT INTO credit_movements (movement_code, user_id, tournament_id, type, amount, created_at) VALUES (?, ?, ?, 'buy_life', ?, NOW())");
    $st->execute([$movCode, $user_id, $tid, -$cost]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out(['ok'=>false,'error'=>'exception','stage'=>'ins_movement','msg'=>$e->getMessage()], 500);
  }

  // 4) valori aggiornati per UI
  try {
    $st = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id = ? AND tournament_id = ? LIMIT 1");
    $st->execute([$user_id, $tid]);
    $newLives = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT crediti FROM utenti WHERE id = ? LIMIT 1");
    $st->execute([$user_id]);
    $headerCredits = (int)$st->fetchColumn();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out(['ok'=>false,'error'=>'exception','stage'=>'sel_refresh','msg'=>$e->getMessage()], 500);
  }

  $pdo->commit();
  out(['ok'=>true, 'lives'=>$newLives, 'header_credits'=>$headerCredits]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  out(['ok'=>false,'error'=>'exception','stage'=>'outer','msg'=>$e->getMessage()], 500);
}
