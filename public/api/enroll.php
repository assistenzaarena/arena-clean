<?php
// public/api/enroll.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
try {
    require_once $ROOT . '/src/config.php';
    require_once $ROOT . '/src/db.php';
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'bootstrap']); exit;
}

// Helper
function jexit(array $p){ echo json_encode($p); exit; }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jexit(['ok'=>false,'error'=>'method_not_allowed']);
    }

    // CSRF
    $csrf = $_POST['csrf'] ?? '';
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
        jexit(['ok'=>false,'error'=>'bad_csrf']);
    }

    // User
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) { jexit(['ok'=>false,'error'=>'not_logged']); }

    // Param
    $tid = (int)($_POST['tournament_id'] ?? 0);
    if ($tid <= 0) { jexit(['ok'=>false,'error'=>'bad_params']); }

    // Torneo
    $t = $pdo->prepare("SELECT id,status,lock_at FROM tournaments WHERE id=:id LIMIT 1");
    $t->execute([':id'=>$tid]);
    $torneo = $t->fetch(PDO::FETCH_ASSOC);
    if (!$torneo) { jexit(['ok'=>false,'error'=>'not_found']); }

    if (($torneo['status'] ?? '') !== 'open') { jexit(['ok'=>false,'error'=>'not_open']); }

    if (!empty($torneo['lock_at'])) {
        $lockTs = strtotime($torneo['lock_at']);
        if ($lockTs !== false && time() >= $lockTs) {
            jexit(['ok'=>false,'error'=>'locked']);
        }
    }

    // già iscritto?
    $chk = $pdo->prepare("
        SELECT 1 FROM tournament_enrollments
        WHERE tournament_id=:tid AND user_id=:uid LIMIT 1
    ");
    $chk->execute([':tid'=>$tid, ':uid'=>$uid]);
    if ($chk->fetchColumn()) {
        jexit(['ok'=>true,'already_enrolled'=>true,'redirect'=>'/torneo.php?id='.$tid]);
    }

    // genera registration_code a 5 cifre univoco
    $code = null;
    for ($i=0; $i<10; $i++) {
        $try = str_pad((string)random_int(0,99999),5,'0',STR_PAD_LEFT);
        $q = $pdo->prepare("SELECT 1 FROM tournament_enrollments WHERE registration_code=:c LIMIT 1");
        $q->execute([':c'=>$try]);
        if (!$q->fetchColumn()) { $code = $try; break; }
    }
    if ($code === null) { jexit(['ok'=>false,'error'=>'code_gen_failed']); }

    // INSERT
    $ins = $pdo->prepare("
        INSERT INTO tournament_enrollments
            (tournament_id, user_id, registration_code, lives, created_at)
        VALUES (:tid, :uid, :code, 1, NOW())
    ");
    $ins->execute([
        ':tid'=>$tid, ':uid'=>$uid, ':code'=>$code
    ]);

    jexit(['ok'=>true,'enrolled'=>true,'registration_code'=>$code,'redirect'=>'/torneo.php?id='.$tid]);

} catch (Throwable $e) {
    // qualunque eccezione -> JSON
    jexit(['ok'=>false,'error'=>'exception']);
}
