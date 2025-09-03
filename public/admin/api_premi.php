<?php
// /admin/api_premi.php — POST handler per approva / rifiuta / evadi una richiesta
require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); die('Metodo non consentito');
}

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  http_response_code(400); die('CSRF non valido');
}

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$allowed = ['approve','reject','fulfill'];
if ($id <= 0 || !in_array($action, $allowed, true)) {
  http_response_code(400); die('Parametri non validi');
}

// carico riga corrente
$st = $pdo->prepare("SELECT * FROM admin_prize_requests WHERE id=:id LIMIT 1");
$st->execute([':id'=>$id]);
$r = $st->fetch(PDO::FETCH_ASSOC);
if (!$r) { http_response_code(404); die('Richiesta non trovata'); }

$now = date('Y-m-d H:i:s');
$adminId = (int)($_SESSION['user_id'] ?? 0);

$pdo->beginTransaction();
try {
  if ($action === 'approve' && $r['status']==='pending') {
    $up = $pdo->prepare("UPDATE admin_prize_requests SET status='approved', processed_at=:ts, processed_by=:a WHERE id=:id");
    $up->execute([':ts'=>$now, ':a'=>$adminId, ':id'=>$id]);
    $act = $pdo->prepare("INSERT INTO admin_prize_actions(request_id,admin_id,action) VALUES(:r,:a,'approve')");
    $act->execute([':r'=>$id, ':a'=>$adminId]);
  }
  elseif ($action === 'reject' && in_array($r['status'], ['pending','approved'], true)) {
    $up = $pdo->prepare("UPDATE admin_prize_requests SET status='rejected', processed_at=:ts, processed_by=:a WHERE id=:id");
    $up->execute([':ts'=>$now, ':a'=>$adminId, ':id'=>$id]);
    $act = $pdo->prepare("INSERT INTO admin_prize_actions(request_id,admin_id,action) VALUES(:r,:a,'reject')");
    $act->execute([':r'=>$id, ':a'=>$adminId]);
  }
  elseif ($action === 'fulfill' && $r['status']==='approved') {
    $up = $pdo->prepare("UPDATE admin_prize_requests SET status='fulfilled', processed_at=:ts, processed_by=:a WHERE id=:id");
    $up->execute([':ts'=>$now, ':a'=>$adminId, ':id'=>$id]);
    $act = $pdo->prepare("INSERT INTO admin_prize_actions(request_id,admin_id,action) VALUES(:r,:a,'fulfill')");
    $act->execute([':r'=>$id, ':a'=>$adminId]);
  }

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo 'Errore salvataggio';
  exit;
}

// redirect di comodo al dettaglio
header('Location: /admin/premio_dettaglio.php?id='.(int)$id);
exit;
