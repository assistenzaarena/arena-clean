<?php
// public/api/select_team.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT.'/src/config.php';
require_once $ROOT.'/src/db.php';
require_once $ROOT.'/src/utils.php';

if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'bad_method']); exit; }

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit; }

$uid  = (int)$_SESSION['user_id'];
$tid  = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$life = isset($_POST['life_no']) ? (int)$_POST['life_no'] : 0;
$event= isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$side = ($_POST['team_side'] ?? '') === 'home' ? 'home' : (($_POST['team_side'] ?? '') === 'away' ? 'away' : '');

if ($tid<=0 || $event<=0 || $side==='') { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
  // Torneo open e non oltre lock
  $tq = $pdo->prepare("SELECT status, lock_at, max_lives_per_user FROM tournaments WHERE id=:id LIMIT 1");
  $tq->execute([':id'=>$tid]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
  if ($t['status']!=='open') { echo json_encode(['ok'=>false,'error'=>'locked']); exit; }
  if (!empty($t['lock_at']) && strtotime($t['lock_at'])<=time()) { echo json_encode(['ok'=>false,'error'=>'locked']); exit; }

  // Deve essere iscritto e life_no valido
  $lq = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $lq->execute([':u'=>$uid, ':t'=>$tid]);
  $lives = (int)$lq->fetchColumn();
  if ($lives<=0) { echo json_encode(['ok'=>false,'error'=>'not_enrolled']); exit; }
  if ($life<0 || $life >= $lives) { echo json_encode(['ok'=>false,'error'=>'bad_life']); exit; }

  // L'event_id deve appartenere al torneo ed essere attivo
  $eq = $pdo->prepare("SELECT id FROM tournament_events WHERE id=:e AND tournament_id=:t AND is_active=1 LIMIT 1");
  $eq->execute([':e'=>$event, ':t'=>$tid]);
  if (!$eq->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'bad_event']); exit; }

  // UPSERT su tournament_selections (una per vita/round)
  $round = 1; // se in futuro gestirai round>1, calcolalo qui
  // prova update
  $up = $pdo->prepare("
    UPDATE tournament_selections
       SET event_id=:e, team_side=:s
     WHERE tournament_id=:t AND user_id=:u AND round_no=:r AND life_no=:l AND locked=0
  ");
  $up->execute([':e'=>$event,':s'=>$side,':t'=>$tid,':u'=>$uid,':r'=>$round,':l'=>$life]);

  if ($up->rowCount()===0) {
    // insert
    $code = generate_unique_code8($pdo,'tournament_selections','selection_code',8);
    $ins = $pdo->prepare("
      INSERT INTO tournament_selections
        (selection_code, tournament_id, user_id, round_no, life_no, event_id, team_side)
      VALUES
        (:c, :t, :u, :r, :l, :e, :s)
    ");
    $ins->execute([':c'=>$code,':t'=>$tid,':u'=>$uid,':r'=>$round,':l'=>$life,':e'=>$event,':s'=>$side]);
  }

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
