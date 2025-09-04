<?php
// =====================================================================
// /admin/utente_vite_apply.php — POST update vite per utente (audit)
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Metodo non consentito'; exit; }
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { http_response_code(400); echo 'CSRF non valido'; exit; }

$tournamentId = (int)($_POST['tournament_id'] ?? 0);
$userId       = (int)($_POST['user_id'] ?? 0);
$setLives     = trim($_POST['set_lives'] ?? '');
$delta        = trim($_POST['delta'] ?? '');
$reason       = trim($_POST['reason'] ?? '');

if ($tournamentId<=0 || $userId<=0) { $_SESSION['flash']='Errore: parametri mancanti.'; header('Location: /admin/utente_vite.php?tournament_id='.$tournamentId); exit; }

try {
    // stato attuale
    $st = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE tournament_id=? AND user_id=? LIMIT 1");
    $st->execute([$tournamentId, $userId]);
    $old = $st->fetchColumn();
    if ($old === false) { $_SESSION['flash']='Errore: utente non iscritto al torneo.'; header('Location: /admin/utente_vite.php?tournament_id='.$tournamentId); exit; }
    $old = (int)$old;

    // calcolo nuovo valore
    if ($setLives !== '') {
        $new = max(0, (int)$setLives);
    } else {
        $d = (int)$delta;
        $new = max(0, $old + $d);
    }

    // update + audit
    $pdo->beginTransaction();
    $up = $pdo->prepare("UPDATE tournament_enrollments SET lives=:l WHERE tournament_id=:t AND user_id=:u");
    $up->execute([':l'=>$new, ':t'=>$tournamentId, ':u'=>$userId]);

    $ins = $pdo->prepare("
        INSERT INTO admin_life_adjustments (tournament_id, user_id, prev_lives, new_lives, delta, reason, admin_user_id)
        VALUES (:t,:u,:p,:n,:d,:r,:a)
    ");
    $ins->execute([
        ':t'=>$tournamentId,
        ':u'=>$userId,
        ':p'=>$old,
        ':n'=>$new,
        ':d'=>$new - $old,
        ':r'=>$reason,
        ':a'=>(int)($_SESSION['user_id'] ?? 0),
    ]);
    $pdo->commit();

    $_SESSION['flash'] = 'Vite aggiornate con successo (user #'.$userId.': '.$old.' → '.$new.').';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[utente_vite_apply] '.$e->getMessage());
    $_SESSION['flash'] = 'Errore durante l’aggiornamento vite.';
}

header('Location: /admin/utente_vite.php?tournament_id='.$tournamentId);
exit;
