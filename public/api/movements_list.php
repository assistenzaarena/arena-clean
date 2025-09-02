<?php
// public/api/movements_list.php
// Restituisce i movimenti crediti dell'utente loggato in forma paginata.
// Input:  GET page (default 1), limit (default 12, max 15)
// Output: { ok:true, items:[...], page:int, limit:int, total:int, pages:int }

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

function out($arr, $code = 200){ http_response_code($code); echo json_encode($arr); exit; }

if (empty($_SESSION['user_id'])) out(['ok'=>false,'error'=>'not_logged'], 401);
$uid = (int)$_SESSION['user_id'];

// paginazione
$page  = max(1, (int)($_GET['page']  ?? 1));
$limit = (int)($_GET['limit'] ?? 12);
if ($limit < 10) $limit = 10;
if ($limit > 15) $limit = 15;
$off   = ($page-1)*$limit;

try {
  // totale per pagine
  $qc = $pdo->prepare("SELECT COUNT(*) FROM credit_movements WHERE user_id=?");
  $qc->execute([$uid]);
  $total = (int)$qc->fetchColumn();
  $pages = ($total > 0) ? (int)ceil($total / $limit) : 1;
  if ($page > $pages) { $page = $pages; $off = ($page-1)*$limit; }

  // lettura pagina
  $sql = "
    SELECT cm.id, cm.type, cm.amount, cm.created_at,
           t.tournament_code, t.name AS tournament_name
    FROM credit_movements cm
    LEFT JOIN tournaments t ON t.id = cm.tournament_id
    WHERE cm.user_id = ?
    ORDER BY cm.created_at DESC, cm.id DESC
    LIMIT ? OFFSET ?
  ";
  $q = $pdo->prepare($sql);
  $q->bindValue(1, $uid, PDO::PARAM_INT);
  $q->bindValue(2, $limit, PDO::PARAM_INT);
  $q->bindValue(3, $off,   PDO::PARAM_INT);
  $q->execute();

  $map = [
    'recharge'  => 'Ricarica crediti',
    'withdraw'  => 'Riscossione crediti',
    'payout'    => 'Accredito vincita torneo',
    'enroll'    => 'Pagamento iscrizione torneo',
    'buy_life'  => 'Acquisto vita',
    'unenroll'  => 'Rimborso disiscrizione',
  ];

  $items = [];
  while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    $type = (string)$r['type'];
    $label = $map[$type] ?? ucfirst(str_replace('_',' ', $type));
    $amt = (int)$r['amount']; // addebiti negativi, accrediti positivi
    $sign = $amt >= 0 ? 'in' : 'out';

    $ts = $r['created_at'] ?: null;
    $ts_h = $ts ? date('d/m/Y H:i', strtotime($ts)) : null;

    $items[] = [
      'id'              => (int)$r['id'],
      'ts'              => $ts,
      'ts_h'            => $ts_h,
      'type'            => $type,
      'type_label'      => $label,
      'amount'          => $amt,
      'sign'            => $sign,
      'tournament_code' => $r['tournament_code'] ? (string)$r['tournament_code'] : null,
      'tournament_name' => $r['tournament_name'] ? (string)$r['tournament_name'] : null,
    ];
  }

  out(['ok'=>true, 'items'=>$items, 'page'=>$page, 'limit'=>$limit, 'total'=>$total, 'pages'=>$pages]);

} catch (Throwable $e) {
  error_log('[movements_list] '.$e->getMessage());
  out(['ok'=>false,'error'=>'exception'], 500);
}
