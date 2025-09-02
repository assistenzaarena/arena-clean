<?php
// public/api/check_winner.php
// Ritorna se l'utente corrente ha un payout recente per mostrare il popup in lobby.
// Output: { ok:true, show:bool, payout_id:int|null, username:string|null, amount:int|null, tournament_code:string|null, key:string|null }

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

function out($arr, $code=200){ http_response_code($code); echo json_encode($arr); exit; }

if (empty($_SESSION['user_id'])) out(['ok'=>false,'show'=>false,'error'=>'not_logged'], 200);

$user_id = (int)$_SESSION['user_id'];

// finestra di visibilità (giorni)
$days = 14;

try {
  // username (se c'è tabella utenti)
  $u = $pdo->prepare("SELECT username FROM utenti WHERE id=? LIMIT 1");
  $u->execute([$user_id]);
  $username = $u->fetchColumn();
  if (!$username) $username = 'Utente';

  // ultimo payout per l'utente entro $days
  $q = $pdo->prepare("
    SELECT tp.id AS payout_id, tp.tournament_id, tp.amount, t.tournament_code, tp.created_at
    FROM tournament_payouts tp
    JOIN tournaments t ON t.id = tp.tournament_id
    WHERE tp.user_id = ?
      AND tp.created_at >= (NOW() - INTERVAL {$days} DAY)
    ORDER BY tp.id DESC
    LIMIT 1
  ");
  $q->execute([$user_id]);
  $row = $q->fetch(PDO::FETCH_ASSOC);

  if (!$row) out(['ok'=>true,'show'=>false]);

  $payout_id = (int)$row['payout_id'];
  $amount    = (int)$row['amount'];
  $tcode     = (string)($row['tournament_code'] ?? '');
  // chiave per il localStorage del client (evita doppio popup)
  $key = "winner_ack_{$user_id}_{$payout_id}";

  out([
    'ok' => true,
    'show' => true,
    'payout_id' => $payout_id,
    'username' => $username,
    'amount' => $amount,
    'tournament_code' => $tcode,
    'key' => $key,
  ]);

} catch (Throwable $e) {
  error_log('[check_winner] '.$e->getMessage());
  out(['ok'=>false,'show'=>false,'error'=>'exception'], 200);
}
