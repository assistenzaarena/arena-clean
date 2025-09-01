<?php
// public/api/finalize_round.php
// [SCOPO] Forza FINALIZZA o RIAPRI scelte del round corrente, indipendentemente dal lock.
// [ACCESSO] Solo admin. Sicuro con CSRF. Transazioni per consistenza.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';     // require_admin()
require_once $ROOT . '/src/utils.php';      // generate_unique_code8()

function respond(array $js, int $http = 200) {
  http_response_code($http);
  echo json_encode($js);
  exit;
}

// ---------- Guardie ----------
require_admin();                                            // solo admin
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['ok'=>false,'error'=>'bad_method'], 405);

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) respond(['ok'=>false,'error'=>'bad_csrf'], 403);

// Input
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$action        = isset($_POST['action']) ? strtolower((string)$_POST['action']) : 'finalize'; // 'finalize' | 'reopen'
if ($tournament_id <= 0 || !in_array($action, ['finalize','reopen'], true)) {
  respond(['ok'=>false,'error'=>'bad_params'], 400);
}

try {
  // 1) Carico torneo (solo verifica esistenza e che sia in stato gestibile)
  $st = $pdo->prepare("SELECT id, name, status, current_round_no FROM tournaments WHERE id=? LIMIT 1");
  $st->execute([$tournament_id]);
  $T = $st->fetch(PDO::FETCH_ASSOC);
  if (!$T) respond(['ok'=>false,'error'=>'not_found'], 404);
  if (($T['status'] ?? '') !== 'open') {
    // se vuoi permettere anche su draft/pending, togli questo controllo
    respond(['ok'=>false,'error'=>'not_open'], 400);
  }

  if ($action === 'finalize') {
    // ============================
    // FORZA FINALIZZA (congela)
    // ============================
    // Logica: per ogni vita esistente, prendo l’ultima scelta (se c’è) e la congelo (locked_at+finalized_at).
    // Se una vita non ha scelta → la lasciamo “senza pick” (verrà gestita nel calcolo round).

    // Elenco iscritti con vite
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
        // ultima selezione per quella vita
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
          // nessuna scelta fatta: la vita rimane senza pick
          continue;
        }

        // garantisco selection_code
        $code = $S['selection_code'];
        if (!$code) {
          $code = generate_unique_code8($pdo, 'tournament_selections', 'selection_code', 8);
          $u1 = $pdo->prepare("UPDATE tournament_selections SET selection_code=? WHERE id=?");
          $u1->execute([$code, (int)$S['id']]);
        }

        // congelo
        $u2 = $pdo->prepare("UPDATE tournament_selections SET locked_at=NOW(), finalized_at=NOW() WHERE id=?");
        $u2->execute([(int)$S['id']]);

        $frozen++;
      }
    }

    $pdo->commit();
    respond(['ok'=>true, 'frozen'=>$frozen, 'msg'=>'Scelte finalizzate (forzate).']);

  } else {
    // ============================
    // FORZA RIAPRI
    // ============================
    // Logica: sblocca le scelte del round corrente — rimuove i “lock” dalle selezioni finalize
    // per questo torneo (solo quelle già finalizzate), così gli utenti possono cambiare.
    // Non tocchiamo lo stato del torneo (rimane open).

    $pdo->beginTransaction();

    // Sblocca le selezioni (solo quelle finalizzate)
    $u = $pdo->prepare("
      UPDATE tournament_selections
      SET locked_at=NULL, finalized_at=NULL
      WHERE tournament_id=? AND finalized_at IS NOT NULL
    ");
    $u->execute([$tournament_id]);
    $unfrozen = $u->rowCount();

    $pdo->commit();
    respond(['ok'=>true, 'unfrozen'=>$unfrozen, 'msg'=>'Scelte riaperte (forzate).']);
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // error_log('[finalize_round] '.$e->getMessage());
  respond(['ok'=>false,'error'=>'exception'], 500);
}
