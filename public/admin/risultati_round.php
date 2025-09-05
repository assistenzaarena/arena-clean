<?php
// =====================================================================
// /public/admin/risultati_round.php — Modifica risultati partite del round
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/guards.php';  require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$tid   = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$round = isset($_GET['round']) ? (int)$_GET['round'] : 0;

if ($tid <= 0) { http_response_code(400); die('ID torneo mancante'); }

// round corrente fallback
if ($round <= 0) {
  $st = $pdo->prepare("SELECT current_round_no FROM tournaments WHERE id=? LIMIT 1");
  $st->execute([$tid]);
  $round = (int)($st->fetchColumn() ?: 1);
}

// eventi del round
$ev = $pdo->prepare("
  SELECT id, fixture_id, home_team_name, away_team_name, result_status, result_at
  FROM tournament_events
  WHERE tournament_id = :tid AND round_no = :rnd
  ORDER BY kickoff IS NULL, kickoff ASC, id ASC
");
$ev->execute([':tid'=>$tid, ':rnd'=>$round]);
$rows = $ev->fetchAll(PDO::FETCH_ASSOC);

// map etichette
$labels = [
  'pending'=>'—',
  'home_win'=>'1 (Casa vince)',
  'draw'    =>'X (Pareggio)',
  'away_win'=>'2 (Trasferta vince)',
  'postponed'=>'Rinviata',
  'void'    =>'Annullata',
];
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Modifica risultati — Torneo #<?php echo (int)$tid; ?> — Round <?php echo (int)$round; ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1100px;margin:20px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    select{height:32px;border:1px solid rgba(255,255,255,.25);border-radius:8px;background:#0a0a0b;color:#fff;padding:0 8px}
    .muted{color:#bdbdbd}
  </style>
</head>
<body>
<?php require $ROOT . '/header_admin.php'; ?>

<div class="wrap">
  <h1 style="margin:0 0 12px;">Modifica risultati — Torneo #<?php echo (int)$tid; ?> — Round <?php echo (int)$round; ?></h1>

  <div class="card" style="display:flex;gap:10px;align-items:center;">
    <form method="get" action="/admin/risultati_round.php" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="tournament_id" value="<?php echo (int)$tid; ?>">
      <label>Round <input name="round" type="number" min="1" value="<?php echo (int)$round; ?>" style="height:32px;border:1px solid rgba(255,255,255,.25);border-radius:8px;background:#0a0a0b;color:#fff;padding:0 8px"></label>
      <button class="btn" type="submit">Vai</button>
    </form>
    <a class="btn" href="/admin/round_ricalcolo.php?tournament_id=<?php echo (int)$tid; ?>&round=<?php echo (int)$round; ?>" style="margin-left:auto;">← Torna al ricalcolo</a>
  </div>

  <div class="card">
    <?php if (!$rows): ?>
      <div class="muted">Nessun evento per questo round.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID evento</th>
            <th>Fixture</th>
            <th>Partita</th>
            <th>Risultato</th>
            <th>Ultimo aggiornamento</th>
            <th>Salva</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $e): $fid = 'f_'.$e['id']; ?>
            <tr>
              <td><?php echo (int)$e['id']; ?></td>
              <td><?php echo $e['fixture_id'] ? (int)$e['fixture_id'] : '—'; ?></td>
              <td><?php echo htmlspecialchars(($e['home_team_name'] ?? '??').' vs '.($e['away_team_name'] ?? '??')); ?></td>
              <td>
                <form id="<?php echo $fid; ?>" method="post" action="/admin/set_event_result.php">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="tournament_id" value="<?php echo (int)$tid; ?>">
                  <input type="hidden" name="round_no" value="<?php echo (int)$round; ?>">
                  <input type="hidden" name="event_id" value="<?php echo (int)$e['id']; ?>">
                  <input type="hidden" name="redirect" value="1">
                  <select name="result_status">
                    <?php foreach (['pending','home_win','draw','away_win','postponed','void'] as $rs): ?>
                      <option value="<?php echo $rs; ?>" <?php echo ($e['result_status']===$rs?'selected':''); ?>>
                        <?php echo $labels[$rs] ?? $rs; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td><?php echo $e['result_at'] ? htmlspecialchars($e['result_at']) : '—'; ?></td>
              <td><button class="btn" type="submit" form="<?php echo $fid; ?>">Salva</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="muted" style="margin-top:8px">
    Suggerimento: dopo aver salvato più risultati, torna al ricalcolo per vedere le differenze sulle vite.
  </div>
</div>
</body>
</html>
