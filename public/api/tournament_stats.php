<?php
// public/api/tournament_stats.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad id']); exit; }

try {
  // carico dati base torneo (serve buy-in/prize%/garantito se volessi calcolare lato server)
  $t = $pdo->prepare("SELECT cost_per_life, prize_percent, guaranteed_prize FROM tournaments WHERE id=:id LIMIT 1");
  $t->execute([':id'=>$id]);
  $torneo = $t->fetch(PDO::FETCH_ASSOC);
  if (!$torneo) { echo json_encode(['ok'=>false,'error'=>'not found']); exit; }

  $buy = (float)$torneo['cost_per_life'];
  $pp  = isset($torneo['prize_percent']) ? (int)$torneo['prize_percent'] : 100;
  $g   = isset($torneo['guaranteed_prize']) ? (float)$torneo['guaranteed_prize'] : 0.0;

  // vite vendute (se esiste la tabella)
  $lives = 0;
  try {
    $q = $pdo->prepare("SELECT COALESCE(SUM(lives),0) FROM tournament_enrollments WHERE tournament_id=:id");
    $q->execute([':id'=>$id]);
    $lives = (int)$q->fetchColumn();
  } catch (Throwable $e) {
    $lives = 0; // tabella non esiste ancora: va bene così
  }

  $pot = max($g, $lives * $buy * ($pp/100));
  echo json_encode(['ok'=>true, 'lives'=>$lives, 'pot'=>round($pot,2)]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'exception']);
}
