<?php
/**
 * admin/torneo_round.php
 *
 * [SCOPO]
 *   Pagina admin "solo lettura" per consultare i round di un torneo in corso:
 *   - Dropdown "Round" per scegliere 1..current_round_no
 *   - Tabella eventi filtrata per round (round_no)
 *   - Stato scelte (aperte/bloccate) e lock_at del torneo
 *
 * [ACCESSO] solo admin.
 * [MODIFICA DATI] nessuna (read-only) — lo faremo nello step 5C per calcolo/ ricalcolo.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/guards.php';    require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// -------------------------
// INPUT: id torneo + round
// -------------------------
$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$round         = isset($_GET['round']) ? (int)$_GET['round'] : 0;

if ($tournament_id <= 0) {
  http_response_code(400);
  die('ID torneo mancante');
}

// ----------------------------------------------------
// CARICO TORNEO: deve essere in corso (status = open)
// ----------------------------------------------------
$tq = $pdo->prepare("SELECT id, name, tournament_code, status, current_round_no, lock_at, choices_locked
                     FROM tournaments
                     WHERE id = :id
                     LIMIT 1");
$tq->execute([':id'=>$tournament_id]);
$T = $tq->fetch(PDO::FETCH_ASSOC);

if (!$T) {
  http_response_code(404);
  die('Torneo non trovato');
}
if (($T['status'] ?? '') !== 'open') {
  $_SESSION['flash']      = 'Il torneo non è in corso (open).';
  $_SESSION['flash_type'] = 'error';
  header('Location: /admin/gestisci_tornei.php'); exit;
}

// Se non specificato, mostro l’ultimo round disponibile (current_round_no)
$currRound = max(1, (int)($T['current_round_no'] ?? 1));
if ($round <= 0) $round = $currRound;

// --------------------------------------
// EVENTI filtrati per round selezionato
// --------------------------------------
$ev = $pdo->prepare("
  SELECT id, fixture_id,
         home_team_name, away_team_name,
         kickoff, is_active, pick_locked,
         result_status, result_at
  FROM tournament_events
  WHERE tournament_id = :tid AND round_no = :rnd
  ORDER BY kickoff IS NULL, kickoff ASC, id ASC
");
$ev->execute([':tid'=>$tournament_id, ':rnd'=>$round]);
$events = $ev->fetchAll(PDO::FETCH_ASSOC);

// Label stato scelte torneo
$scelteLabel = ((int)($T['choices_locked'] ?? 0) === 1)
  ? '<span style="color:#e62329;">Bloccate</span>'
  : '<span style="color:#00c074;">Aperte</span>';

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Round torneo #<?php echo htmlspecialchars($T['tournament_code'] ?? sprintf('%05d',$tournament_id)); ?>
    — <?php echo htmlspecialchars($T['name']); ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1100px;margin:20px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    .muted{color:#aaa}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .kpi{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:12px 14px;display:inline-block;margin-bottom:12px}
  </style>
</head>
<body>
<?php require $ROOT . '/header_admin.php'; ?>

<div class="wrap">
  <h1 style="margin:0 0 12px;">
    Round torneo #<?php echo htmlspecialchars($T['tournament_code'] ?? sprintf('%05d',$tournament_id)); ?>
    — <?php echo htmlspecialchars($T['name']); ?>
  </h1>

  <!-- KPI stato -->
  <div class="kpi">
    <strong>Stato scelte:</strong> <?php echo $scelteLabel; ?>
    &nbsp; • &nbsp;
    <strong>Lock scelte:</strong> <?php echo $T['lock_at'] ? date('d/m/Y H:i', strtotime($T['lock_at'])) : '—'; ?>
    &nbsp; • &nbsp;
    <strong>Round corrente:</strong> <?php echo (int)$currRound; ?>
  </div>

  <!-- Selettore round -->
  <div class="card" style="display:flex;align-items:center;gap:12px;">
    <form method="get" action="/admin/torneo_round.php" style="display:flex;align-items:center;gap:10px;">
      <input type="hidden" name="id" value="<?php echo (int)$tournament_id; ?>">
      <label>Round
        <select name="round">
          <?php for($r=1; $r <= $currRound; $r++): ?>
            <option value="<?php echo $r; ?>" <?php echo ($r===$round?'selected':''); ?>>
              <?php echo $r; ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>
      <button class="btn" type="submit">Vai</button>
    </form>

    <a class="btn" href="/admin/torneo_open.php?id=<?php echo (int)$tournament_id; ?>" style="margin-left:auto;">Torna a gestione</a>
  </div>

  <!-- Tabella eventi del round selezionato -->
  <div class="card">
    <h3 style="margin:0 0 10px;">Eventi — Round <?php echo (int)$round; ?></h3>

    <?php if (!$events): ?>
      <div class="muted">Nessun evento per questo round.</div>
    <?php else: ?>
      <table>
        <thead>
        <tr>
          <th>ID</th>
          <th>Fixture</th>
          <th>Partita</th>
          <th>Kickoff</th>
          <th>Attivo</th>
          <th>Risultato</th>
          <th>Risultato al</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $e): ?>
          <tr>
            <td><?php echo (int)$e['id']; ?></td>
            <td><?php echo $e['fixture_id'] ? (int)$e['fixture_id'] : '—'; ?></td>
            <td><?php echo htmlspecialchars(($e['home_team_name'] ?? '??').' vs '.($e['away_team_name'] ?? '??')); ?></td>
            <td><?php echo $e['kickoff'] ? htmlspecialchars($e['kickoff']) : '—'; ?></td>
            <td><?php echo ((int)$e['is_active']===1) ? 'Sì' : 'No'; ?></td>
            <td><?php
              $rs = $e['result_status'] ?? 'pending';
              $labels = [
                'pending'=>'—',
                'home_win'=>'Casa vince',
                'away_win'=>'Trasferta vince',
                'draw'=>'Pareggio',
                'postponed'=>'Rinviata',
                'void'=>'Annullata',
              ];
              echo htmlspecialchars($labels[$rs] ?? $rs);
            ?></td>
            <td><?php echo $e['result_at'] ? htmlspecialchars($e['result_at']) : '—'; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
