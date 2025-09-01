<?php
// public/api/save_selection.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/utils.php'; // per eventuale generate_unique_code8()
/**
 * Requisiti DB (già verificati):
 * - tabella tournament_selections:
 *   id PK AI,
 *   tournament_id INT UNSIGNED NOT NULL,
 *   user_id INT UNSIGNED NOT NULL,
 *   life_index INT UNSIGNED NOT NULL,       (0,1,2,...)
 *   event_id INT UNSIGNED NOT NULL,
 *   side ENUM('home','away') NOT NULL,
 *   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   locked TINYINT(1) NOT NULL DEFAULT 0,
 *   finalized_at DATETIME NULL,
 *   selection_code CHAR(8) UNIQUE NULL,
 *   UNIQUE KEY uk_sel (user_id, tournament_id, life_index)
 */

// ===== helper per risposta coerente =====
function jexit(array $p) {
  echo json_encode($p);
  exit;
}

// ===== login obbligatorio =====
if (empty($_SESSION['user_id'])) {
  jexit(['ok' => false, 'error' => 'not_logged']);
}

// ===== solo POST + CSRF =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jexit(['ok' => false, 'error' => 'bad_method']);
}
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  jexit(['ok' => false, 'error' => 'bad_csrf']);
}

// ===== parametri =====
$user_id      = (int)$_SESSION['user_id'];
$tournament_id= isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$event_id     = isset($_POST['event_id'])      ? (int)$_POST['event_id']      : 0;
$life_index   = isset($_POST['life_index'])    ? (int)$_POST['life_index']    : -1;
$side         = $_POST['side'] ?? '';
$side         = ($side === 'home' || $side === 'away') ? $side : '';

if ($tournament_id <= 0 || $event_id <= 0 || $life_index < 0 || $side === '') {
  jexit(['ok' => false, 'error' => 'bad_params']);
}

try {
  // 1) torneo deve esistere, essere OPEN e non oltre lock
  $tq = $pdo->prepare("SELECT status, lock_at FROM tournaments WHERE id=:id LIMIT 1");
  $tq->execute([':id' => $tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t) {
    jexit(['ok' => false, 'error' => 'not_found']);
  }
  if ($t['status'] !== 'open') {
    jexit(['ok' => false, 'error' => 'locked']); // chiuso ai fini scelte
  }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    jexit(['ok' => false, 'error' => 'locked']);
  }

  // 2) utente deve essere iscritto e life_index < lives
  $eq = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $eq->execute([':u' => $user_id, ':t' => $tournament_id]);
  $en = $eq->fetch(PDO::FETCH_ASSOC);
  if (!$en) {
    jexit(['ok' => false, 'error' => 'not_enrolled']);
  }
  $lives = (int)$en['lives'];
  if ($life_index >= $lives) {
    jexit(['ok' => false, 'error' => 'bad_params']); // life index fuori range
  }

  // 3) evento deve appartenere al torneo ed essere attivo
  $vq = $pdo->prepare("SELECT 1 FROM tournament_events WHERE id=:e AND tournament_id=:t AND is_active=1 LIMIT 1");
  $vq->execute([':e' => $event_id, ':t' => $tournament_id]);
  if (!$vq->fetchColumn()) {
    jexit(['ok' => false, 'error' => 'bad_params']); // evento non valido per il torneo
  }

  // 4) upsert selezione (una riga per vita: uk_sel (user_id,tournament_id,life_index))
  $sql = "
    INSERT INTO tournament_selections
      (tournament_id, user_id, life_index, event_id, side, created_at, locked, finalized_at, selection_code)
    VALUES
      (:t, :u, :l, :e, :s, NOW(), 0, NULL, NULL)
    ON DUPLICATE KEY UPDATE
      event_id = VALUES(event_id),
      side     = VALUES(side)
  ";
  $ins = $pdo->prepare($sql);
  $ins->execute([
    ':t' => $tournament_id,
    ':u' => $user_id,
    ':l' => $life_index,
    ':e' => $event_id,
    ':s' => $side,
  ]);

  // 5) risposta OK (team_logo lo può usare la UI per appiccicare il logo al cuore)
  //    (Se vuoi passare il logo corretto lato server, puoi calcolarlo come in torneo.php)
  jexit(['ok' => true]);

} catch (Throwable $e) {
  // in sviluppo mostra il dettaglio, in produzione no
  if (defined('APP_ENV') && APP_ENV !== 'production') {
    jexit(['ok' => false, 'error' => 'exception', 'error_detail' => $e->getMessage()]);
  }
  jexit(['ok' => false, 'error' => 'exception']);
}
