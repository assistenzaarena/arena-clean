<?php
// public/api/save_selection.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';

// Utente loggato
require_login();
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { echo json_encode(['ok'=>false,'error'=>'not_logged']); exit; }

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'bad_method']); exit;
}

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
}

// Parametri
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$event_id      = isset($_POST['event_id'])      ? (int)$_POST['event_id']      : 0;
$life_index    = isset($_POST['life_index'])    ? (int)$_POST['life_index']    : -1;
$side          = $_POST['side'] ?? '';

if ($tournament_id <= 0 || $event_id <= 0 || $life_index < 0 || ($side !== 'home' && $side !== 'away')) {
  echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

try {
  // Verifica che il torneo sia open e non lockato
  $tq = $pdo->prepare("SELECT status, lock_at FROM tournaments WHERE id=:id LIMIT 1");
  $tq->execute([':id'=>$tournament_id]);
  $t = $tq->fetch(PDO::FETCH_ASSOC);
  if (!$t)                             { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
  if ($t['status'] !== 'open')         { echo json_encode(['ok'=>false,'error'=>'locked']);    exit; }
  if (!empty($t['lock_at']) && strtotime($t['lock_at']) <= time()) {
    echo json_encode(['ok'=>false,'error'=>'locked']); exit;
  }

  // L'utente deve essere iscritto
  $ck = $pdo->prepare("SELECT lives FROM tournament_enrollments WHERE user_id=:u AND tournament_id=:t LIMIT 1");
  $ck->execute([':u'=>$user_id, ':t'=>$tournament_id]);
  $livesRow = $ck->fetch(PDO::FETCH_ASSOC);
  if (!$livesRow) { echo json_encode(['ok'=>false,'error'=>'not_enrolled']); exit; }

  $userLives = (int)$livesRow['lives'];
  if ($life_index >= $userLives) { echo json_encode(['ok'=>false,'error'=>'bad_params']); exit; }

  // Recupero i dati dell'evento per capire che squadra è stata scelta
  $eq = $pdo->prepare("
      SELECT home_team_name, away_team_name
      FROM tournament_events
      WHERE id = :e AND tournament_id = :t AND is_active = 1
      LIMIT 1
  ");
  $eq->execute([':e'=>$event_id, ':t'=>$tournament_id]);
  $ev = $eq->fetch(PDO::FETCH_ASSOC);
  if (!$ev) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

  $team_name = ($side === 'home') ? (string)$ev['home_team_name'] : (string)$ev['away_team_name'];

  // Determino logo (coerente con torneo.php -> team_logo_path)
  $team_logo = team_logo_local($team_name);

  // UPSERT della scelta (una scelta per utente+torneo+evento+vita)
  $sql = "
    INSERT INTO tournament_selections
      (user_id, tournament_id, event_id, life_index, side, team_name, team_logo, created_at, updated_at)
    VALUES
      (:u, :t, :e, :l, :s, :n, :logo, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
      side=:s, team_name=:n, team_logo=:logo, updated_at=NOW()
  ";
  $st = $pdo->prepare($sql);
  $st->execute([
    ':u'    => $user_id,
    ':t'    => $tournament_id,
    ':e'    => $event_id,
    ':l'    => $life_index,
    ':s'    => $side,
    ':n'    => $team_name,
    ':logo' => $team_logo,
  ]);

  echo json_encode([
    'ok'        => true,
    'life'      => $life_index,
    'team_name' => $team_name,
    'team_logo' => $team_logo,
  ]);
} catch (Throwable $e) {
  // Se vuoi: error_log($e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'exception']); // risposta compatibile con il tuo showMsg
  exit;
}

/**
 * Funzione helper: calcola il path locale del logo come in torneo.php (alias inclusi)
 */
function team_logo_local(string $name): string {
  $base = strtolower($name);
  $base = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$base);
  $base = preg_replace('/[^a-z0-9]+/','', $base);

  static $alias = [
    'juventus'      => 'juve',
    'internazionale'=> 'inter',
    'inter'         => 'inter',
    'acmilan'       => 'milan',
    'milan'         => 'milan',
    'asroma'        => 'roma',
    'roma'          => 'roma',
    'hellasverona'  => 'hellasverona',
    'verona'        => 'hellasverona',
    'atalanta'      => 'atalanta',
    'bologna'       => 'bologna',
    'cagliari'      => 'cagliari',
    'como'          => 'como',
    'cremonese'     => 'cremonese',
    'fiorentina'    => 'fiorentina',
    'genoa'         => 'genoa',
    'lazio'         => 'lazio',
    'lecce'         => 'lecce',
    'napoli'        => 'napoli',
    'parma'         => 'parma',
    'pisa'          => 'pisa',
    'sassuolo'      => 'sassuolo',
    'torino'        => 'torino',
    'udinese'       => 'udinese',
  ];

  if ($base === 'ac' && stripos($name, 'milan') !== false)     $base = 'acmilan';
  if ($base === 'as' && stripos($name, 'roma')  !== false)     $base = 'asroma';
  if (strpos($base,'hellas')!==false || strpos($base,'verona')!==false) $base = 'hellasverona';

  $slug = $alias[$base] ?? $base;
  return "/assets/logos/{$slug}.webp";
}
