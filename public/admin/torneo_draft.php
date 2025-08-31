<?php
/**
 * public/admin/torneo_draft.php
 *
 * SCOPO: Modifica di un torneo in stato 'draft' (bozza), con le stesse funzioni di pending:
 * - Editor meta: current_round_no + lock_at (lock scelte globale)
 * - Eventi: aggiungi da API / aggiungi manuale, blocca/sblocca scelte (is_active), elimina evento
 * - Pubblica ora (draft -> open)
 *
 * Nota: è praticamente il "pendente editor", ma accetta solo tornei in 'draft'.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/guards.php';  require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
$competitions = require $ROOT . '/src/config/competitions.php';
require_once $ROOT . '/src/services/football_api.php';

// generator per event_code globale
function generate_event_code_global(PDO $pdo): string {
  do {
    $code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $q = $pdo->prepare("SELECT 1 FROM tournament_events WHERE event_code = :c LIMIT 1");
    $q->execute([':c' => $code]);
    $exists = (bool)$q->fetchColumn();
  } while ($exists);
  return $code;
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// --- ID TORNEO ---
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); die('ID torneo mancante'); }

// --- CARICO TORNEO ---
$tq = $pdo->prepare("SELECT * FROM tournaments WHERE id = :id LIMIT 1");
$tq->execute([':id'=>$id]);
$torneo = $tq->fetch(PDO::FETCH_ASSOC);
if (!$torneo) { http_response_code(404); die('Torneo non trovato'); }
if ($torneo['status'] !== 'draft') {
  $_SESSION['flash'] = 'Il torneo non è in bozza (draft).';
  header('Location: /admin/crea_torneo.php'); exit;
}

$roundType = $torneo['round_type'] ?? 'matchday';
$league_id = (int)$torneo['league_id'];
$season    = (string)$torneo['season'];
$matchday  = $torneo['matchday'] ? (int)$torneo['matchday'] : null;
$round_lbl = $torneo['round_label'] ?? null;

$current_round_no = (int)($torneo['current_round_no'] ?? 1);
$lock_at          = $torneo['lock_at']; // DATETIME o NULL

// ===============================
// POST: azioni admin
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
    http_response_code(400); die('CSRF non valido');
  }
  $action = $_POST['action'] ?? '';

  // meta torneo
  if ($action === 'update_meta') {
    $new_round_no = (int)($_POST['current_round_no'] ?? $current_round_no);
    $new_lock_at  = trim($_POST['lock_at'] ?? '');
    $lockValue    = ($new_lock_at === '') ? null : $new_lock_at;

    $pdo->prepare("UPDATE tournaments SET current_round_no=:r, lock_at=:l, updated_at=NOW() WHERE id=:id")
        ->execute([':r'=>$new_round_no, ':l'=>$lockValue, ':id'=>$id]);
    $_SESSION['flash'] = 'Meta torneo aggiornati.';
    header('Location: /admin/torneo_draft.php?id='.$id); exit;
  }

  // aggiungi fixture API
  if ($action === 'add_fixture_api') {
    $fxid = (int)($_POST['fixture_id'] ?? 0);
    if ($fxid <= 0) { $_SESSION['flash'] = 'Fixture ID non valido.'; header('Location: /admin/torneo_draft.php?id='.$id); exit; }

    $resp = fb_fixture_by_id($fxid);
    if (!$resp['ok']) {
      $_SESSION['flash'] = 'Errore API (fixture_id='.$fxid.'): '.$resp['error'].' (HTTP '.$resp['status'].')';
      header('Location: /admin/torneo_draft.php?id='.$id); exit;
    }
    $one = fb_extract_one_fixture_minimal($resp['data']);
    if (!$one || !$one['fixture_id']) {
      $_SESSION['flash'] = 'Fixture non trovato in API (id='.$fxid.').';
      header('Location: /admin/torneo_draft.php?id='.$id); exit;
    }

    $eventCode = generate_event_code_global($pdo);
    $pdo->prepare("
      INSERT IGNORE INTO tournament_events
        (tournament_id, event_code, round_no, fixture_id, home_team_id, home_team_name, away_team_id, away_team_name, kickoff, is_active, pick_locked)
      VALUES (:tid, :ecode, :rnd, :fid, :hid, :hname, :aid, :aname, NULL, 1, 0)
    ")->execute([
      ':tid'=>$id, ':ecode'=>$eventCode, ':rnd'=>$current_round_no ?: 1,
      ':fid'=>(int)$one['fixture_id'], ':hid'=>$one['home_id'], ':hname'=>$one['home_name'],
      ':aid'=>$one['away_id'], ':aname'=>$one['away_name']
    ]);

    $_SESSION['flash'] = 'Fixture '.$one['fixture_id'].' aggiunto (API).';
    header('Location: /admin/torneo_draft.php?id='.$id); exit;
  }

  // aggiungi evento manuale
  if ($action === 'add_fixture_manual') {
    $hname = trim($_POST['home_team_name'] ?? '');
    $aname = trim($_POST['away_team_name'] ?? '');
    if ($hname === '' || $aname === '') {
      $_SESSION['flash'] = 'Inserisci nomi squadra casa e trasferta.';
      header('Location: /admin/torneo_draft.php?id='.$id); exit;
    }

    $eventCode = generate_event_code_global($pdo);
    $pdo->prepare("
      INSERT INTO tournament_events
        (tournament_id, event_code, round_no, fixture_id, home_team_id, home_team_name, away_team_id, away_team_name, kickoff, is_active, pick_locked)
      VALUES (:tid, :ecode, :rnd, NULL, NULL, :hname, NULL, :aname, NULL, 1, 0)
    ")->execute([
      ':tid'=>$id, ':ecode'=>$eventCode, ':rnd'=>$current_round_no ?: 1,
      ':hname'=>$hname, ':aname'=>$aname
    ]);

    $_SESSION['flash'] = 'Evento manuale aggiunto.';
    header('Location: /admin/torneo_draft.php?id='.$id); exit;
  }

  // blocca/sblocca scelte per evento (is_active)
  if ($action === 'toggle_event_active') {
    $row_id = (int)($_POST['row_id'] ?? 0);
    if ($row_id <= 0) { $_SESSION['flash'] = 'Evento non valido.'; header('Location: /admin/torneo_draft.php?id='.$id); exit; }

    $rq = $pdo->prepare("SELECT is_active FROM tournament_events WHERE id=:rid AND tournament_id=:tid");
    $rq->execute([':rid'=>$row_id, ':tid'=>$id]);
    $row = $rq->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $_SESSION['flash'] = 'Evento non trovato.'; header('Location: /admin/torneo_draft.php?id='.$id); exit; }

    $new = ((int)$row['is_active'] === 1) ? 0 : 1;
    $pdo->prepare("UPDATE tournament_events SET is_active=:n WHERE id=:rid AND tournament_id=:tid")
        ->execute([':n'=>$new, ':rid'=>$row_id, ':tid'=>$id]);

    $_SESSION['flash'] = 'Evento #'.$row_id.' — '.($new ? 'scelte sbloccate' : 'scelte bloccate').'.';
    header('Location: /admin/torneo_draft.php?id='.$id); exit;
  }

  // elimina evento
  if ($action === 'delete_event') {
    $row_id = (int)($_POST['row_id'] ?? 0);
    if ($row_id <= 0) { $_SESSION['flash'] = 'Evento non valido.'; header('Location: /admin/torneo_draft.php?id='.$id); exit; }
    $pdo->prepare("DELETE FROM tournament_events WHERE id=:rid AND tournament_id=:tid")->execute([':rid'=>$row_id, ':tid'=>$id]);
    $_SESSION['flash'] = 'Evento #'.$row_id.' eliminato.';
    header('Location: /admin/torneo_draft.php?id='.$id); exit;
  }

  // PUBBLICA ORA (bozza -> open) direttamente da questa pagina
  if ($action === 'publish_now') {
    $ok = $pdo->prepare("UPDATE tournaments SET status='open', updated_at=NOW() WHERE id=:id AND status='draft'");
    $ok->execute([':id'=>$id]);
    $_SESSION['flash'] = 'Torneo pubblicato (open).';
    header('Location: /admin/crea_torneo.php'); exit;
  }
    // elimina intero torneo (solo se è in draft)
  if ($action === 'delete_tournament') {
    // prima elimino gli eventi, poi il torneo
    $pdo->prepare("DELETE FROM tournament_events WHERE tournament_id=:tid")->execute([':tid'=>$id]);
    $pdo->prepare("DELETE FROM tournaments WHERE id=:id AND status='draft'")->execute([':id'=>$id]);

    $_SESSION['flash'] = 'Torneo #'.$id.' eliminato definitivamente.';
    header('Location: /admin/crea_torneo.php'); exit;
  }
}

// ===============================
// CARICO eventi per la tabella (come pending semplificato)
// ===============================
$ev = $pdo->prepare("
  SELECT id, event_code, fixture_id, home_team_name, away_team_name, is_active
  FROM tournament_events
  WHERE tournament_id = :tid
  ORDER BY id ASC
");
$ev->execute([':tid'=>$id]);
$events = $ev->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Bozza torneo #<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',(int)$id)); ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width: 1100px; margin: 20px auto; padding: 0 16px; color:#fff;}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .meta{font-size:14px;color:#c9c9c9}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    .btn-primary{background:#00c074;border-color:#00c074}
    .btn-warn{background:#e62329;border-color:#e62329}
    .flash{color:#00d07e;margin:8px 0}
    .err{color:#ff6b6b;margin:8px 0}
    .muted{color:#aaa}
    .row-actions{display:flex; gap:8px}
    input[type=datetime-local],input[type=text]{height:32px;border:1px solid rgba(255,255,255,.25);border-radius:8px;background:#0a0a0b;color:#fff;padding:0 10px}
  </style>
</head>
<body>

<?php require $ROOT . '/header_admin.php'; ?>

<div class="wrap">
  <h1>
    Bozza torneo #<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',(int)$id)); ?>
    <span class="muted" style="font-size:12px; margin-left:6px;">(ID DB: <?php echo (int)$id; ?>)</span>
  </h1>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>

  <!-- META TORNEO -->
  <div class="card">
    <h3 style="margin-top:0">Meta torneo</h3>
    <form method="post" action="" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="update_meta">

      <label>Round corrente
        <input type="number" name="current_round_no" min="1" value="<?php echo (int)$current_round_no; ?>">
      </label>

      <label>Lock scelte (data/ora)
        <input type="datetime-local" name="lock_at"
               value="<?php echo $torneo['lock_at'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($torneo['lock_at']))) : ''; ?>">
      </label>

      <button class="btn" type="submit">Salva meta</button>

      <!-- Pubblica ora direttamente da qui -->
      <form method="post" action="" onsubmit="return confirm('Pubblicare questo torneo? Diventerà OPEN.');" style="display:inline;">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="publish_now">
        <button class="btn btn-primary" type="submit" style="margin-left:8px">Pubblica ora</button>
      </form>
    </form>
  </div>

  <!-- TABELLA EVENTI -->
  <div class="card">
    <h3 style="margin-top:0">Eventi del torneo</h3>

    <?php if (!$events): ?>
      <div class="muted">Nessun evento registrato al momento.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID (5 cifre)</th>
            <th>Fixture ID</th>
            <th>Partita</th>
            <th>Scelte abilitate</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $e): ?>
            <tr>
              <td><?php echo htmlspecialchars($e['event_code']); ?></td>
              <td><?php echo $e['fixture_id'] ? (int)$e['fixture_id'] : '-'; ?></td>
              <td><?php echo htmlspecialchars(($e['home_team_name'] ?? '??').' vs '.($e['away_team_name'] ?? '??')); ?></td>
              <td><?php echo ((int)$e['is_active']===1) ? 'Sì' : 'No'; ?></td>
              <td class="row-actions">
                <form method="post" action="" style="display:inline">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="toggle_event_active">
                  <input type="hidden" name="row_id" value="<?php echo (int)$e['id']; ?>">
                  <button class="btn" type="submit"><?php echo ((int)$e['is_active']===1)?'Blocca scelte':'Sblocca scelte'; ?></button>
                </form>

                <form method="post" action="" onsubmit="return confirm('Eliminare definitivamente questo evento?');" style="display:inline">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="delete_event">
                  <input type="hidden" name="row_id" value="<?php echo (int)$e['id']; ?>">
                  <button class="btn btn-warn" type="submit">Elimina</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- AGGIUNTA EVENTI -->
  <div class="card">
    <h3 style="margin-top:0">Aggiungi fixture (API) o evento manuale</h3>
    <div style="display:flex; gap:24px; flex-wrap:wrap;">
      <form method="post" action="" style="display:flex; gap:8px; align-items:center;">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="add_fixture_api">
        <label class="muted">Fixture ID</label>
        <input name="fixture_id" type="number" min="1" required>
        <button class="btn" type="submit">Aggiungi da API</button>
      </form>

      <form method="post" action="" style="display:flex; gap:8px; align-items:center;">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="add_fixture_manual">
        <label class="muted">Casa</label>
        <input name="home_team_name" type="text" required>
        <label class="muted">Trasferta</label>
        <input name="away_team_name" type="text" required>
        <button class="btn" type="submit">Aggiungi manuale</button>
      </form>
    </div>
    <div class="muted" style="margin-top:6px">Gli eventi manuali non hanno fixture_id; puoi comunque attivarli/disattivarli ed eliminarli.</div>
  </div>

  <a class="btn" href="/admin/crea_torneo.php">Torna</a>
</div>

</body>
</html>
