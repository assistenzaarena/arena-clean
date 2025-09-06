<?php
// public/admin/api/resolve_team_id.php
// - GET  action=suggest  -> suggerisce canon_team_id per una lega, dato q (nome da cercare)
// - POST (senza action)  -> salva team_id su un evento (home/away)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__, 2); // /var/www/html
require_once $ROOT . '/src/guards.php';  require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

function out($arr, $code=200){ http_response_code($code); echo json_encode($arr); exit; }

// Normalizzazione semplice dei nomi
function norm_name($s){
  $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE', $s);
  $s = strtolower(preg_replace('/[^a-z0-9]+/','', $s));
  return $s ?: '';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ========== SUGGERIMENTI ========== */
if ($method === 'GET' && ($_GET['action'] ?? '') === 'suggest') {
  $tid  = (int)($_GET['tournament_id'] ?? 0);
  $lg   = (int)($_GET['league_id'] ?? 0);
  $qraw = trim($_GET['q'] ?? '');
  if ($tid <= 0 || $lg <= 0 || $qraw === '') {
    out(['ok'=>false, 'error'=>'bad_params'], 400);
  }

  $qnorm = norm_name($qraw);

  try {
    $sug = [];
    // 1) canon ufficiali della lega
    $st = $pdo->prepare("
      SELECT canon_team_id AS team_id, display_name AS name
      FROM admin_team_canon
      WHERE league_id = ?
    ");
    $st->execute([$lg]);
    foreach ($st as $r) {
      $name = (string)$r['name'];
      if (strpos(norm_name($name), $qnorm) !== false) {
        $sug[] = ['team_id'=>(int)$r['team_id'], 'name'=>$name];
      }
    }

    // 2) fallback: nomi visti negli eventi del torneo (se non hai popolato canon)
    if (count($sug) === 0) {
      $fb = $pdo->prepare("
        SELECT DISTINCT home_team_name AS name FROM tournament_events WHERE tournament_id=? AND home_team_name IS NOT NULL
        UNION
        SELECT DISTINCT away_team_name AS name FROM tournament_events WHERE tournament_id=? AND away_team_name IS NOT NULL
      ");
      $fb->execute([$tid, $tid]);
      foreach ($fb as $r) {
        $name = (string)$r['name'];
        if ($name !== '' && strpos(norm_name($name), $qnorm) !== false) {
          // non abbiamo team_id canon qui → lo proponiamo come semplice nome, l’admin userà l’ID manuale
          $sug[] = ['team_id'=>null, 'name'=>$name];
        }
      }
    }

    // 3) ordina per qualità (match più corti prima)
    usort($sug, function($a,$b){
      return strlen($a['name']) <=> strlen($b['name']);
    });

    out(['ok'=>true, 'suggestions'=>$sug]);
  } catch (Throwable $e) {
    error_log('[resolve_team_id suggest] '.$e->getMessage());
    out(['ok'=>false, 'error'=>'exception'], 500);
  }
}

/* ========== APPLICA ID A UN EVENTO ========== */
if ($method === 'POST') {
  // CSRF opzionale (se vuoi passarlo anche qui): se lo usi, decommenta sotto
  // $csrf = $_POST['csrf'] ?? '';
  // if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { out(['ok'=>false,'error'=>'bad_csrf'], 403); }

  $tid   = (int)($_POST['tournament_id'] ?? 0);
  $evId  = (int)($_POST['event_id'] ?? 0);
  $side  = strtolower((string)($_POST['side'] ?? ''));
  $team  = (int)($_POST['team_id'] ?? 0);

  if ($tid<=0 || $evId<=0 || !in_array($side, ['home','away'], true) || $team<=0) {
    out(['ok'=>false, 'error'=>'bad_params'], 400);
  }

  try {
    // Verifica esistenza evento
    $chk = $pdo->prepare("SELECT id FROM tournament_events WHERE id=? AND tournament_id=? LIMIT 1");
    $chk->execute([$evId, $tid]);
    if (!$chk->fetchColumn()) out(['ok'=>false,'error'=>'event_not_found'], 404);

    // Aggiorna id lato richiesto
    if ($side === 'home') {
      $up = $pdo->prepare("UPDATE tournament_events SET home_team_id=? WHERE id=? LIMIT 1");
      $up->execute([$team, $evId]);
    } else {
      $up = $pdo->prepare("UPDATE tournament_events SET away_team_id=? WHERE id=? LIMIT 1");
      $up->execute([$team, $evId]);
    }

    out(['ok'=>true]);
  } catch (Throwable $e) {
    error_log('[resolve_team_id apply] '.$e->getMessage());
    out(['ok'=>false, 'error'=>'exception'], 500);
  }
}

/* metodo non supportato */
out(['ok'=>false,'error'=>'bad_method'], 405);
