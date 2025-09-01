<?php
// public/api/finalize_round.php
// [SCOPO] Finalizza (congela) o riapre le scelte del round corrente.
// [ACCESSO] Solo admin.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';   // require_admin()
require_once $ROOT . '/src/utils.php';    // generate_unique_code8()

// ---------------- helpers ----------------
function jrespond(array $js, int $http = 200) {
  http_response_code($http);
  echo json_encode($js);
  exit;
}
function redirect_with_flash(string $msg, bool $ok = true) {
  // Piccolo flash in sessione da mostrare in gestisci_tornei.php
  $_SESSION['flash'] = $msg;
  $_SESSION['flash_type'] = $ok ? 'success' : 'error';
  header('Location: /admin/gestisci_tornei.php');
  exit;
}

// -------------- guard --------------
require_admin(); // solo admin
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jrespond(['ok'=>false,'error'=>'bad_method'], 405); }

$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { jrespond(['ok'=>false,'error'=>'bad_csrf'], 403); }

// redirect richiesto? (se i form passano redirect=1 → facciamo redirect + flash)
$wantRedirect = !empty($_POST['redirect']);
$action = $_POST['action'] ?? 'finalize'; // 'finalize' | 'reopen'

// -------------- input --------------
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
if ($tournament_id <= 0) {
  $wantRedirect ? redirect_with_flash('Parametri mancanti.', false) : jrespond(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
  // carico torneo
  $st = $pdo->prepare("SELECT id, name, status, lock_at, current_round_no FROM tournaments WHERE id=? LIMIT 1");
  $st->execute([$tournament_id]);
  $T = $st->fetch(PDO::FETCH_ASSOC);
  if (!$T) {
    $wantRedirect ? redirect_with_flash('Torneo non trovato.', false) : jrespond(['ok'=>false,'error'=>'not_found'], 404);
  }
  if (($T['status'] ?? '') !== 'open') {
    $wantRedirect ? redirect_with_flash('Il torneo non è in corso (open).', false) : jrespond(['ok'=>false,'error'=>'not_open'], 400);
  }

  if ($action === 'reopen') {
    // ---------------------- R I A P R I ----------------------
    // Sblocca selections (rimuove lock & finalization) e rimuove lock_at del torneo
    $pdo->beginTransaction();

    // sblocca tutte le selezioni del torneo (round corrente; non stiamo tracciando il n° round per singola selection → sblocchiamo tutto del torneo)
    $u1 = $pdo->prepare("UPDATE tournament_selections SET locked_at=NULL, finalized_at=NULL WHERE tournament_id=?");
    $u1->execute([$tournament_id]);

    // rimuovi lock del torneo (admin riapre manualmente)
    $u2 = $pdo->prepare("UPDATE tournaments SET lock_at=NULL, updated_at=NOW() WHERE id=?");
    $u2->execute([$tournament_id]);

    $pdo->commit();

    if ($wantRedirect) redirect_with_flash('Scelte riaperte correttamente.');
    jrespond(['ok'=>true, 'msg'=>'Scelte riaperte.']);
  }

  // ---------------------- F I N A L I Z Z A ----------------------
  // NB: qui NON imponiamo più che il lock sia passato — l’admin può forzare.
  // Congeliamo l’ultima selezione presente per ciascuna vita di ogni iscritto.

  // elenco iscritti
  $en = $pdo->prepare("SELECT user_id, lives FROM tournament_enrollments WHERE tournament_id=?");
  $en->execute([$tournament_id]);
  $enrollments = $en->fetchAll(PDO::FETCH_ASSOC);

  $pdo->beginTransaction();

  $frozen = 0;
  foreach ($enrollments as $row) {
    $uid   = (int)$row['user_id'];
    $lives = max(0, (int)$row['lives']);
    if ($lives <= 0) continue;

    for ($life=0; $life<$lives; $life++) {
      // ultima selezione per questa vita
      $sel = $pdo->prepare("
        SELECT id, selection_code
        FROM tournament_selections
        WHERE tournament_id=? AND user_id=? AND life_index=?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
      ");
      $sel->execute([$tournament_id, $uid, $life]);
      $S = $sel->fetch(PDO::FETCH_ASSOC);
      if (!$S) { continue; } // nessuna scelta per quella vita

      $code = $S['selection_code'];
      if (!$code) {
        $code = generate_unique_code8($pdo, 'tournament_selections', 'selection_code', 8);
        $u1 = $pdo->prepare("UPDATE tournament_selections SET selection_code=? WHERE id=?");
        $u1->execute([$code, (int)$S['id']]);
      }
      // congela: lock + finalized
      $u2 = $pdo->prepare("UPDATE tournament_selections SET locked_at=NOW(), finalized_at=NOW() WHERE id=?");
      $u2->execute([(int)$S['id']]);
      $frozen++;
    }
  }

  // aggiorna timestamp torneo
  $u3 = $pdo->prepare("UPDATE tournaments SET updated_at=NOW() WHERE id=?");
  $u3->execute([$tournament_id]);

  $pdo->commit();

  if ($wantRedirect) redirect_with_flash("Scelte finalizzate. Vite congelate: {$frozen}.");
  jrespond(['ok'=>true, 'frozen'=>$frozen, 'msg'=>'Scelte finalizzate.']);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // log facoltativo: error_log('[finalize_round] '.$e->getMessage());
  if ($wantRedirect) redirect_with_flash('Errore interno durante l’operazione.', false);
  jrespond(['ok'=>false,'error'=>'exception'], 500);
}
