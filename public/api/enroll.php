<?php
/**
 * public/api/enroll.php
 *
 * [SCOPO] Iscrive l'utente loggato a un torneo "open".
 *         Restituisce JSON: { ok: true, redirect: "/torneo.php?id=123" } oppure { ok:false, error:"..." }.
 *
 * Requisiti:
 *  - utente loggato ($_SESSION['user_id'])
 *  - POST: csrf, tournament_id
 *  - tabella tournaments (status='open'), tournament_enrollments
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

// -------- include base (path assoluti a /var/www/html) --------
$ROOT = dirname(__DIR__, 1);           // /var/www/html/public
$APP  = dirname($ROOT);                // /var/www/html
require_once $APP . '/src/config.php';
require_once $APP . '/src/db.php';

// -------- guardie minime --------
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }

// CSRF (se non lo vuoi ora, commenta le 3 righe seguenti)
$csrf_post = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf_post)) {
    echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
}

$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
if ($tournament_id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

try {
    // 1) Verifica torneo "open" e non lock globale scaduto (se lock_at settato)
    $q = $pdo->prepare("SELECT id, status, lock_at FROM tournaments WHERE id=:id LIMIT 1");
    $q->execute([':id'=>$tournament_id]);
    $t = $q->fetch(PDO::FETCH_ASSOC);
    if (!$t) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
    if ($t['status'] !== 'open') { echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }

    if (!empty($t['lock_at'])) {
        $lockTs = strtotime($t['lock_at']);
        if ($lockTs !== false && time() >= $lockTs) {
            echo json_encode(['ok'=>false,'error'=>'locked']); exit;
        }
    }

    // 2) Crea tabella enrollments se non esiste (safe - una volta sola)
    //    Puoi rimuovere questo blocco dopo il primo deploy.
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS tournament_enrollments (
        id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        registration_code CHAR(5) NOT NULL,     -- codice univoco (5 cifre)
        user_id          INT UNSIGNED NOT NULL,
        tournament_id    INT UNSIGNED NOT NULL,
        lives            INT UNSIGNED NOT NULL DEFAULT 1,
        created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_tournament (user_id, tournament_id),
        UNIQUE KEY uq_registration_code (registration_code),
        KEY idx_tournament (tournament_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3) Evita doppie iscrizioni
    $q = $pdo->prepare("SELECT id FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
    $q->execute([':u'=>$uid, ':t'=>$tournament_id]);
    if ($q->fetch()) {
        echo json_encode(['ok'=>true,'redirect'=>'/torneo.php?id='.$tournament_id]); exit;
    }

    // 4) Genera registration_code univoco globale a 5 cifre
    $code = null;
    for ($i=0; $i<10; $i++) {
        $try = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $c   = $pdo->prepare("SELECT 1 FROM tournament_enrollments WHERE registration_code=:c LIMIT 1");
        $c->execute([':c'=>$try]);
        if (!$c->fetch()) { $code = $try; break; }
    }
    if ($code === null) { echo json_encode(['ok'=>false,'error'=>'code_gen_failed']); exit; }

    // 5) Inserisci iscrizione (1 vita di default)
    $ins = $pdo->prepare("
      INSERT INTO tournament_enrollments (registration_code, user_id, tournament_id, lives)
      VALUES (:code, :uid, :tid, 1)
    ");
    $ins->execute([
      ':code' => $code,
      ':uid'  => $uid,
      ':tid'  => $tournament_id,
    ]);

    echo json_encode(['ok'=>true, 'redirect'=>'/torneo.php?id='.$tournament_id]);
} catch (Throwable $e) {
    // Per debug (temporaneo): restituisci l’eccezione
    // echo json_encode(['ok'=>false,'error'=>'exception','msg'=>$e->getMessage()]); exit;
    echo json_encode(['ok'=>false,'error'=>'server_error']); exit;
}
