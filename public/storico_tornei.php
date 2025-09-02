<?php
// public/storico_tornei.php
// Storico tornei chiusi a cui l’utente ha partecipato: card rosse + popup dettagli scelte

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__;
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';
require_once $ROOT . '/src/payouts.php'; // tp_compute_pot()

require_login();
$uid = (int)($_SESSION['user_id'] ?? 0);

// Tornei "closed" a cui l'utente ha partecipato
$tornei = [];
try {
  $q = $pdo->prepare("
    SELECT t.*
    FROM tournaments t
    JOIN tournament_enrollments e ON e.tournament_id = t.id
    WHERE e.user_id = :uid AND t.status = 'closed'
    GROUP BY t.id
    ORDER BY t.closed_at DESC, t.id DESC
  ");
  $q->execute([':uid'=>$uid]);
  $tornei = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $tornei = [];
}

// Helpers
function vite_acquistate(PDO $pdo, int $tid, int $uid): int {
  try {
    $st = $pdo->prepare("
      SELECT COUNT(*) FROM credit_movements
      WHERE tournament_id = ? AND user_id = ? AND type = 'buy_life'
    ");
    $st->execute([$tid, $uid]);
    return (int)$st->fetchColumn();
  } catch (Throwable $e) { return 0; }
}
function payout_utente(PDO $pdo, int $tid, int $uid): array {
  try {
    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS tot, MAX(reason) AS reason FROM tournament_payouts WHERE tournament_id=? AND user_id=?");
    $st->execute([$tid, $uid]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return ['amount'=>(int)($r['tot'] ?? 0), 'reason'=>$r['reason'] ?? null];
  } catch (Throwable $e) { return ['amount'=>0,'reason'=>null]; }
}
function esito_label(array $payout): string {
  if (($payout['amount'] ?? 0) > 0) {
    return ($payout['reason']==='winner') ? 'Vincitore' : 'Co-vincitore';
  }
  return 'Eliminato';
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Storico tornei</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_user.css">
  <style>
    .wrap{max-width:1100px;margin:22px auto;padding:0 16px;color:#fff}
    .page-title{font-size:26px;font-weight:900;margin:0 0 14px}
    .card-red {
      background: linear-gradient(180deg, #E62329 0%, #8A0F0F 100%); /* stesso rosso delle card lobby */
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 14px;
      box-shadow: 0 8px 28px rgba(0,0,0,.25);
      margin-bottom: 14px; /* distanza tra le card */
    }

    .card-red:hover {
      transform: translateY(-1px);
      transition: .15s;
    }
    .card-top{padding:14px 16px 10px;border-bottom:1px solid rgba(255,255,255,.15)}
    .t-meta{display:grid;grid-template-columns: repeat(6, minmax(120px,1fr));gap:12px}
    .t-k{font-size:11px;letter-spacing:.4px;color:#ffe3e3;text-transform:uppercase}
    .t-v{font-weight:900;font-size:16px}
    .t-right{text-align:right}
    .badge-win{background:#13b66a;color:#04140c;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:900;display:inline-block}
    .badge-lost{background:#2b2b2b;color:#bbb;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:900;display:inline-block}
    .muted{color:#ddd}
    @media (max-width: 1000px){ .t-meta{grid-template-columns: repeat(2, minmax(140px,1fr));} }

    /* Popup */
    .overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:1000;padding:16px}
    .modal{background:#0f1114;border:1px solid rgba(255,255,255,.15);border-radius:14px;max-width:860px;width:100%;color:#fff;box-shadow:0 24px 60px rgba(0,0,0,.35)}
    .modal-header{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.12);display:flex;justify-content:space-between;align-items:center}
    .modal-title{font-size:18px;font-weight:900}
    .modal-close{background:#333;color:#fff;border:0;border-radius:8px;padding:6px 10px;font-weight:900;cursor:pointer}
    .modal-body{padding:14px 16px}
    .round-table{width:100%;border-collapse:collapse}
    .round-table th,.round-table td{border-bottom:1px solid rgba(255,255,255,.08);padding:8px 6px;text-align:left}
    .tag-fb{font-size:10px;font-weight:900;background:#2b2b2b;color:#ffc76b;border-radius:6px;padding:2px 6px;margin-left:8px;letter-spacing:.5px}
    .pill-win{color:#00d07e;font-weight:900}
    .pill-lose{color:#ff6b6b;font-weight:900}
  </style>
</head>
<body>
<?php require $ROOT . '/header_user.php'; ?>

<div class="wrap">
  <h1 class="page-title">Storico tornei</h1>

  <?php if (empty($tornei)): ?>
    <div class="muted">Non hai ancora tornei chiusi da mostrare.</div>
  <?php else: ?>
    <?php foreach ($tornei as $t): ?>
      <?php
        $tid   = (int)$t['id'];
        $code  = htmlspecialchars($t['tournament_code'] ?? sprintf('%05d',$tid));
        $name  = htmlspecialchars($t['name'] ?? 'Torneo');
        $buyin = (int)($t['cost_per_life'] ?? 0);
        $potInfo = ['pot'=>0];
        try { $potInfo = tp_compute_pot($pdo, $tid); } catch (Throwable $e) {}
        $pot = (int)($potInfo['pot'] ?? 0);
        $livesBought = vite_acquistate($pdo, $tid, $uid);
        $pay = payout_utente($pdo, $tid, $uid);
        $esito = esito_label($pay);
      ?>
      <div class="card-red" data-tid="<?php echo $tid; ?>" data-tname="<?php echo $name; ?>">
        <div class="card-top">
          <div class="t-meta">
            <div>
              <div class="t-k">ID TORNEO</div>
              <div class="t-v">#<?php echo $code; ?></div>
            </div>
            <div>
              <div class="t-k">NOME</div>
              <div class="t-v"><?php echo $name; ?></div>
            </div>
            <div>
              <div class="t-k">BUY-IN</div>
              <div class="t-v"><?php echo number_format($buyin,0,',','.'); ?> crediti</div>
            </div>
            <div>
              <div class="t-k">VITE ACQUISTATE</div>
              <div class="t-v"><?php echo (int)$livesBought; ?></div>
            </div>
            <div>
              <div class="t-k">MONTEPREMI</div>
              <div class="t-v"><?php echo number_format($pot,0,',','.'); ?> crediti</div>
            </div>
            <div class="t-right">
              <div class="t-k">ESITO</div>
              <div class="t-v">
                <?php if (($pay['amount'] ?? 0) > 0): ?>
                  <span class="badge-win"><?php echo htmlspecialchars($esito); ?></span>
                <?php else: ?>
                  <span class="badge-lost"><?php echo htmlspecialchars($esito); ?></span>
                <?php endif; ?>
              </div>
              <div class="t-k" style="margin-top:6px">CREDITI VINTI</div>
              <div class="t-v"><?php echo number_format((int)($pay['amount'] ?? 0),0,',','.'); ?></div>
            </div>
          </div>
        </div>
        <!-- il dettaglio scelte è nel popup: click card -->
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Popup -->
<div id="histOverlay" class="overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Dettaglio scelte</div>
      <button class="modal-close" type="button" id="histClose">Chiudi</button>
    </div>
    <div class="modal-body" id="histBody">
      <!-- tabella dinamica -->
    </div>
  </div>
</div>

<script src="/assets/storico_tornei.js?v=1"></script>
</body>
</html>
