<?php
// public/api/add_life.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // generate_unique_code8()

// --- helper risposta JSON uniforme
function respond(array $p, int $code = 200) {
    http_response_code($code);
    echo json_encode($p);
    exit;
}

// --- 1) must be logged
if (empty($_SESSION['user_id'])) {
    respond(['ok'=>false, 'error'=>'not_logged'], 401);
}

// --- 2) solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok'=>false, 'error'=>'bad_method'], 405);
}

// --- 3) CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    respond(['ok'=>false, 'error'=>'bad_csrf'], 403);
}

// --- 4) input
$user_id      = (int)$_SESSION['user_id'];
$tournament_id= isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
if ($tournament_id <= 0) {
    respond(['ok'=>false, 'error'=>'bad_params'], 400);
}

try {
    // --- 5) torneo open + lock non passato
    $tq = $pdo->prepare("
        SELECT status, lock_at, cost_per_life, max_lives_per_user
        FROM tournaments
        WHERE id = :tid
        LIMIT 1
    ");
    $tq->execute([':tid'=>$tournament_id]);
    $t = $tq->fetch(PDO::FETCH_ASSOC);

    if (!$t)                          { respond(['ok'=>false, 'error'=>'not_found'], 404); }
    if ($t['status'] !== 'open')      { respond(['ok'=>false, 'error'=>'not_open'], 409); }
    if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
        respond(['ok'=>false, 'error'=>'locked'], 409);
    }

    $cost = (int)$t['cost_per_life'];
    $maxL = (int)$t['max_lives_per_user'];

    // --- 6) deve essere iscritto + leggo vite attuali
    $eq = $pdo->prepare("
        SELECT lives
        FROM tournament_enrollments
        WHERE user_id = :u AND tournament_id = :t
        LIMIT 1
    ");
    $eq->execute([':u'=>$user_id, ':t'=>$tournament_id]);
    $en = $eq->fetch(PDO::FETCH_ASSOC);
    if (!$en) {
        respond(['ok'=>false, 'error'=>'not_enrolled'], 409);
    }

    $currLives = (int)$en['lives'];
    if ($maxL > 0 && $currLives >= $maxL) {
        respond(['ok'=>false, 'error'=>'lives_limit'], 409);
    }

    // --- 7) transazione: addebito, +1 vita, log movimento
    $pdo->beginTransaction();

    // 7.1 addebito SOLO se saldo sufficiente
    $upd = $pdo->prepare("
        UPDATE utenti
        SET crediti = crediti - :c
        WHERE id = :u AND crediti >= :c
    ");
    $upd->execute([':c'=>$cost, ':u'=>$user_id]);

    if ($upd->rowCount() !== 1) {
        $pdo->rollBack();
        respond(['ok'=>false, 'error'=>'insufficient_funds'], 409);
    }

    // 7.2 incremento vite
    $inc = $pdo->prepare("
        UPDATE tournament_enrollments
        SET lives = lives + 1
        WHERE user_id = :u AND tournament_id = :t
        LIMIT 1
    ");
    $inc->execute([':u'=>$user_id, ':t'=>$tournament_id]);
    if ($inc->rowCount() !== 1) {
        $pdo->rollBack();
        respond(['ok'=>false, 'error'=>'enroll_update_failed'], 500);
    }

    // 7.3 log movimento (importo negativo = addebito)
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
        ':amount' => -$cost,    // addebito
    ]);

    // 7.4 nuove vite e nuovo saldo crediti
    $lv = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
    $lv->execute([':u'=>$user_id, ':t'=>$tournament_id]);
    $newLives = (int)$lv->fetchColumn();

    $cr = $pdo->prepare("SELECT crediti FROM utenti WHERE id=:u LIMIT 1");
    $cr->execute([':u'=>$user_id]);
    $newCredits = (int)$cr->fetchColumn();

    $pdo->commit();

    respond(['ok'=>true, 'lives'=>$newLives, 'header_credits'=>$newCredits]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    // se vuoi ispezionare l’errore, loggalo sul server: error_log($e->getMessage());
    respond(['ok'=>false, 'error'=>'exception'], 500);
}
