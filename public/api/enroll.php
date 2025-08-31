<?php
// public/api/enroll.php
// Risponde SEMPRE JSON, anche in errore. Niente chiusura "?>".

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__); // /var/www/html
// include robusti (se uno fallisce, catturiamo nell'eccezione)
try {
    require_once $ROOT . '/src/config.php';
    require_once $ROOT . '/src/db.php';
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'bootstrap']); exit;
}

// Helper: risposta JSON e stop
function jexit(array $payload) {
    echo json_encode($payload);
    exit;
}

try {
    // Solo POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jexit(['ok'=>false,'error'=>'method_not_allowed']);
    }

    // CSRF (la lobby scrive window.CSRF; noi verifichiamo)
    $csrf = $_POST['csrf'] ?? '';
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
        jexit(['ok'=>false,'error'=>'bad_csrf']);
    }

    // Utente loggato
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid <= 0) {
        jexit(['ok'=>false,'error'=>'not_logged']);
    }

    // Parametri
    $tid = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
    if ($tid <= 0) {
        jexit(['ok'=>false,'error'=>'bad_params']);
    }

    // Carico torneo
    $t = $pdo->prepare("
        SELECT id, status, lock_at
        FROM tournaments
        WHERE id = :id LIMIT 1
    ");
    $t->execute([':id'=>$tid]);
    $torneo = $t->fetch(PDO::FETCH_ASSOC);
    if (!$torneo) {
        jexit(['ok'=>false,'error'=>'not_found']);
    }

    // Deve essere OPEN
    if (($torneo['status'] ?? '') !== 'open') {
        jexit(['ok'=>false,'error'=>'not_open']);
    }

    // Se c’è lock_at e siamo oltre → blocco
    if (!empty($torneo['lock_at'])) {
        $lockTs = strtotime($torneo['lock_at']);
        if ($lockTs !== false && time() >= $lockTs) {
            jexit(['ok'=>false,'error'=>'locked']);
        }
    }

    // Già iscritto?
    $chk = $pdo->prepare("
        SELECT 1 FROM tournament_enrollments
        WHERE tournament_id = :tid AND user_id = :uid
        LIMIT 1
    ");
    $chk->execute([':tid'=>$tid, ':uid'=>$uid]);
    if ($chk->fetchColumn()) {
        // Già iscritto → vai diretto alla pagina torneo
        jexit(['ok'=>true,'already_enrolled'=>true,'redirect'=>'/torneo.php?id='.$tid]);
    }

    // Genera registration_code univoco globale a 5 cifre
    $code = null;
    for ($i=0; $i<10; $i++) {
        $try = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $q = $pdo->prepare("SELECT 1 FROM tournament_enrollments WHERE registration_code = :c LIMIT 1");
        $q->execute([':c'=>$try]);
        if (!$q->fetchColumn()) { $code = $try; break; }
    }
    if ($code === null) {
        jexit(['ok'=>false,'error'=>'code_gen_failed']);
    }

    // INSERT iscrizione (lives = 1 iniziale)
    $ins = $pdo->prepare("
        INSERT INTO tournament_enrollments
            (tournament_id, user_id, registration_code, lives, created_at)
        VALUES
            (:tid, :uid, :code, 1, NOW())
    ");
    $ins->execute([
        ':tid'  => $tid,
        ':uid'  => $uid,
        ':code' => $code,
    ]);

    // Risposta OK + redirect alla pagina torneo
    jexit(['ok'=>true,'enrolled'=>true,'registration_code'=>$code,'redirect'=>'/torneo.php?id='.$tid]);

} catch (Throwable $e) {
    // NIENTE "Errore di rete": rispondiamo sempre JSON
    jexit(['ok'=>false,'error'=>'exception']);
}
