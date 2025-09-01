<?php
// public/api/finalize_round.php
// [SCOPO] Finalizza (congela) o riapre le scelte del round corrente.
//         Azione decisa da POST[action] = 'finalize' | 'reopen' (default: finalize)
// [ACCESSO] Solo admin.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';     // require_admin()
require_once $ROOT . '/src/utils.php';      // generate_unique_code8()

// ---------- helper risposta ----------
function respond(array $js, int $http = 200) {
  http_response_code($http);
  echo json_encode($js);
  exit;
}

// ---------- guard ----------
require_admin(); // solo admin può operare
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(['ok'=>false,'error'=>'bad_method'], 405); }
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { respond(['ok'=>false,'error'=>'bad_csrf'], 403); }

// ---------- input ----------
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$action        = strtolower(trim((string)($_POST['action'] ?? 'finalize'))); // 'finalize' | 'reopen'
if ($tournament_id <= 0 || !in_array($action, ['finalize','reopen'], true)) {
  respond(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
  // 1) carico torneo
  $st = $pdo->prepare("SELECT id, name, status, lock_at, current_round_no FROM tournaments WHERE id=? LIMIT 1");
  $st->execute([$tournament_id]);
  $T = $st->fetch(PDO::FETCH_ASSOC);
  if (!$T)                         respond(['ok'=>false,'error'=>'not_found'], 404);
  if (($T['status'] ?? '')!=='open') respond(['ok'=>false,'error'=>'not_open'], 400);

  $nowLocked = (!empty($T['lock_at']) && strtotime($T['lock_at']) <= time());
  $roundNo   = (int)($T['current_round_no'] ?? 1);

  if ($action === 'finalize') {
    // Deve essere passato il lock
    if (!$nowLocked) {
      respond(['ok'=>false,'error'=>'not_locked_yet'], 400);
    }

    // 2) elenco iscritti con loro #vite
    $en = $pdo->prepare("SELECT user_id, lives FROM tournament_enrollments WHERE tournament_id=?");
    $en->execute([$tournament_id]);
    $enrollments = $en->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    $frozen = 0; // quante vite finalizzate
    foreach ($enrollments as $row) {
      $uid   = (int)$row['user_id'];
      $lives = max(0, (int)$row['lives']);
      if ($lives <= 0) continue;

      // per ogni vita, prendo l’ULTIMA selezione inserita (se esiste) e la congelo
      for ($life=0; $life<$lives; $life++) {
        $sel = $pdo->prepare("
          SELECT id, selection_code
          FROM tournament_selections
          WHERE tournament_id=? AND user_id=? AND life_index=?
          ORDER BY created_at DESC, id DESC
          LIMIT 1
        ");
        $sel->execute([$tournament_id, $uid, $life]);
        $S = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$S) {
          // nessuna scelta per questa vita → rimane senza pick (gestione eliminazioni in step successivo)
          continue;
        }

        $code = $S['selection_code'];
        if (!$code) {
          $code = generate_unique_code8($pdo, 'tournament_selections', 'selection_code', 8);
          $u1 = $pdo->prepare("UPDATE tournament_selections SET selection_code=? WHERE id=?");
          $u1->execute([$code, (int)$S['id']]);
        }
        // congelo: locked_at + finalized_at adesso
        $u2 = $pdo->prepare("UPDATE tournament_selections SET locked_at=NOW(), finalized_at=NOW() WHERE id=?");
        $u2->execute([(int)$S['id']]);

        $frozen++;
      }
    }

    // opzionale: aggiorno updated_at del torneo
    $pdo->prepare("UPDATE tournaments SET updated_at=NOW() WHERE id=?")->execute([$tournament_id]);

    $pdo->commit();
    respond(['ok'=>true, 'mode'=>'finalize', 'frozen'=>$frozen, 'msg'=>'Scelte finalizzate per il round corrente.']);

  } else {
    // action === 'reopen'
    // Re-open: rimuove i lock delle selezioni già finalizzate e consente di reimpostare un nuovo lock_at in UI.
    // Per sicurezza, si permette il reopen solo se il torneo è 'open' (già verificato).
    $pdo->beginTransaction();

    // 1) sblocca tutte le selezioni finalizzate del torneo (round corrente; il modello attuale non ha la colonna round_no)
    $u = $pdo->prepare("UPDATE tournament_selections
                        SET locked_at=NULL, finalized_at=NULL
                        WHERE tournament_id=? AND finalized_at IS NOT NULL");
    $u->execute([$tournament_id]);

    // 2) azzera lock_at per permettere all'admin di impostarne uno nuovo
    $pdo->prepare("UPDATE tournaments SET lock_at=NULL, updated_at=NOW() WHERE id=?")->execute([$tournament_id]);

    $pdo->commit();
    respond(['ok'=>true, 'mode'=>'reopen', 'msg'=>'Scelte riaperte. Imposta un nuovo lock per proseguire.']);
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // log server, se vuoi: error_log('[finalize_round] '.$e->getMessage());
  respond(['ok'=>false,'error'=>'exception'], 500);
}
