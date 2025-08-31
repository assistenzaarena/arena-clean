<?php
/**
 * public/admin/torneo_pending.php
 *
 * STEP 2C: pagina ADMIN per tornei in stato 'pending' con EDITING MANUALE dei fixtures.
 * - Se non ci sono fixtures registrati in tournament_events -> importa da API (sincronizzazione iniziale).
 * - Permette di disattivare/attivare singoli eventi.
 * - Permette di aggiungere un fixture manualmente (by fixture_id API).
 * - Permette di confermare e passare a 'draft'.
 *
 * Sicurezza: require_admin(), CSRF su tutti i POST.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/guards.php';   require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
$competitions = require $ROOT . '/src/config/competitions.php';
require_once $ROOT . '/src/services/football_api.php';

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
if ($torneo['status'] !== 'pending') {
  $_SESSION['flash'] = 'Torneo non in pending.';
  header('Location: /admin/crea_torneo.php'); exit;
}

// Determino tipo round e parametri fetch
$roundType = $torneo['round_type'] ?? 'matchday';
$league_id = (int)$torneo['league_id'];
$season    = (string)$torneo['season'];
$matchday  = $torneo['matchday'] ? (int)$torneo['matchday'] : null;
$round_lbl = $torneo['round_label'] ?? null;

// ===============================
// POST HANDLER: azioni admin
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
    http_response_code(400); die('CSRF non valido');
  }
  $action = $_POST['action'] ?? '';

  // Aggiungi fixture manuale (by fixture_id)
  if ($action === 'add_fixture') {
    $fxid = (int)($_POST['fixture_id'] ?? 0);
    if ($fxid <= 0) { $_SESSION['flash'] = 'Fixture ID non valido.'; header('Location: /admin/torneo_pending.php?id='.$id); exit; }

    $resp = fb_fixture_by_id($fxid);
    if (!$resp['ok']) {
      $_SESSION['flash'] = 'Errore API (fixture_id='.$fxid.'): '.$resp['error'].' (HTTP '.$resp['status'].')';
      header('Location: /admin/torneo_pending.php?id='.$id); exit;
    }
    $one = fb_extract_one_fixture_minimal($resp['data']);
    if (!$one || !$one['fixture_id']) {
      $_SESSION['flash'] = 'Fixture non trovato in API (id='.$fxid.').';
      header('Location: /admin/torneo_pending.php?id='.$id); exit;
    }

    // Inserisco se non esiste già
    $ins = $pdo->prepare("
      INSERT IGNORE INTO tournament_events
        (tournament_id, round_no, fixture_id, home_team_id, home_team_name, away_team_id, away_team_name, kickoff, is_active)
      VALUES (:tid, 1, :fid, :hid, :hname, :aid, :aname, :kickoff, 1)
    ");
    $ins->execute([
      ':tid'    => $id,
      ':fid'    => (int)$one['fixture_id'],
      ':hid'    => $one['home_id'],
      ':hname'  => $one['home_name'],
      ':aid'    => $one['away_id'],
      ':aname'  => $one['away_name'],
      ':kickoff'=> $one['date'] ? date('Y-m-d H:i:s', strtotime($one['date'])) : null,
    ]);

    $_SESSION['flash'] = 'Fixture '.$one['fixture_id'].' aggiunto.';
    header('Location: /admin/torneo_pending.php?id='.$id); exit;
  }

  // Toggle attiva/disattiva evento
  if ($action === 'toggle_event') {
    $row_id = (int)($_POST['row_id'] ?? 0);
    if ($row_id <= 0) { $_SESSION['flash'] = 'Evento non valido.'; header('Location: /admin/torneo_pending.php?id='.$id); exit; }

    // leggo stato attuale
    $rq = $pdo->prepare("SELECT is_active FROM tournament_events WHERE id=:rid AND tournament_id=:tid");
    $rq->execute([':rid'=>$row_id, ':tid'=>$id]);
    $row = $rq->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $_SESSION['flash'] = 'Evento non trovato.'; header('Location: /admin/torneo_pending.php?id='.$id); exit; }

    $new = ((int)$row['is_active'] === 1) ? 0 : 1;
    $up  = $pdo->prepare("UPDATE tournament_events SET is_active=:n WHERE id=:rid AND tournament_id=:tid");
    $up->execute([':n'=>$new, ':rid'=>$row_id, ':tid'=>$id]);

    $_SESSION['flash'] = 'Evento #'.$row_id.' '.($new ? 'attivato' : 'disattivato').'.';
    header('Location: /admin/torneo_pending.php?id='.$id); exit;
  }

  // Conferma e passa a DRAFT
  if ($action === 'confirm_draft') {
    // (opzionale) potresti verificare che ci sia almeno 1 evento attivo
    $chk = $pdo->prepare("SELECT COUNT(*) FROM tournament_events WHERE tournament_id=:tid AND is_active=1");
    $chk->execute([':tid'=>$id]);
    $activeCount = (int)$chk->fetchColumn();

    if ($activeCount === 0) {
      $_SESSION['flash'] = 'Nessun evento attivo: attiva almeno una partita prima di confermare.';
      header('Location: /admin/torneo_pending.php?id='.$id); exit;
    }

    $pdo->prepare("UPDATE tournaments SET status='draft', updated_at=NOW() WHERE id=:id AND status='pending'")
        ->execute([':id'=>$id]);

    $_SESSION['flash'] = 'Torneo #'.$id.' confermato: stato bozza (draft).';
    header('Location: /admin/crea_torneo.php'); exit;
  }
}

// ===============================
// SYNC INIZIALE: se non ci sono righe in tournament_events per il torneo,
// importa dall’API la lista fixtures (come in 2B) e le inserisce (attive).
// ===============================
$evcount = $pdo->prepare("SELECT COUNT(*) FROM tournament_events WHERE tournament_id=:tid");
$evcount->execute([':tid'=>$id]);
$hasRows = ((int)$evcount->fetchColumn() > 0);

$err_fetch = null;

if (!$hasRows) {
  try {
    if ($roundType === 'matchday') {
      $resp = fb_fixtures_matchday($league_id, $season, (int)$matchday, 'Regular Season - %d');
    } else {
      $resp = fb_fixtures_round_label($league_id, $season, (string)$round_lbl);
    }
    if (!$resp['ok']) {
      $err_fetch = 'Errore API: '.$resp['error'].' (HTTP '.$resp['status'].')';
    } else {
      $list = fb_extract_fixtures_minimal($resp['data']);
      if ($list) {
        $ins = $pdo->prepare("
          INSERT IGNORE INTO tournament_events
            (tournament_id, round_no, fixture_id, home_team_id, home_team_name, away_team_id, away_team_name, kickoff, is_active)
          VALUES (:tid, 1, :fid, :hid, :hname, :aid, :aname, :kick, 1)
        ");
        foreach ($list as $fx) {
          $ins->execute([
            ':tid'  => $id,
            ':fid'  => (int)$fx['fixture_id'],
            ':hid'  => $fx['home_id'],
            ':hname'=> $fx['home_name'],
            ':aid'  => $fx['away_id'],
            ':aname'=> $fx['away_name'],
            ':kick' => $fx['date'] ? date('Y-m-d H:i:s', strtotime($fx['date'])) : null,
          ]);
        }
      }
    }
  } catch (Throwable $e) {
    $err_fetch = 'Eccezione fetch: '.$e->getMessage();
  }
}

// ===============================
// CARICO elenco eventi per la tabella
// ===============================
$ev = $pdo->prepare("
  SELECT id, fixture_id, home_team_name, away_team_name, kickoff, is_active
  FROM tournament_events
  WHERE tournament_id = :tid
  ORDER BY kickoff ASC, id ASC
");
$ev->execute([':tid'=>$id]);
$events = $ev->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Pending torneo #<?php echo (int)$id; ?></title>
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
  </style>
</head>
<body>

<?php require $ROOT . '/header_admin.php'; ?>

<div class="wrap">
  <h1>Pending torneo #<?php echo (int)$id; ?></h1>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>
  <?php if ($err_fetch): ?>
    <div class="err"><?php echo htmlspecialchars($err_fetch); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="meta">
      <div><b>Nome:</b> <?php echo htmlspecialchars($torneo['name']); ?></div>
      <div><b>Competizione:</b> <?php echo htmlspecialchars($torneo['league_name']); ?> (ID <?php echo (int)$torneo['league_id']; ?>)</div>
      <div><b>Stagione:</b> <?php echo htmlspecialchars($season); ?></div>
      <div><b>Round:</b>
        <?php if ($roundType==='matchday'): ?>
          Giornata <?php echo (int)$matchday; ?>
        <?php else: ?>
          <?php echo htmlspecialchars($round_lbl); ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h3 style="margin-top:0">Fixtures del torneo</h3>

    <?php if (!$events): ?>
      <div class="muted">Nessun evento registrato al momento.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID riga</th>
            <th>Fixture ID</th>
            <th>Data/Ora</th>
            <th>Partita</th>
            <th>Stato</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $e): ?>
            <tr>
              <td><?php echo (int)$e['id']; ?></td>
              <td><?php echo (int)$e['fixture_id']; ?></td>
              <td><?php echo htmlspecialchars($e['kickoff'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($e['home_team_name'].' vs '.$e['away_team_name']); ?></td>
              <td><?php echo ((int)$e['is_active']===1) ? 'Attivo' : 'Disattivo'; ?></td>
              <td class="row-actions">
                <form method="post" action="" style="display:inline">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="toggle_event">
                  <input type="hidden" name="row_id" value="<?php echo (int)$e['id']; ?>">
                  <button class="btn" type="submit">
                    <?php echo ((int)$e['is_active']===1) ? 'Disattiva' : 'Attiva'; ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 style="margin-top:0">Aggiungi fixture manualmente</h3>
    <form method="post" action="" style="display:flex; gap:8px; align-items:center;">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="add_fixture">
      <label for="fixture_id" class="muted">Fixture ID</label>
      <input id="fixture_id" name="fixture_id" type="number" min="1" required style="height:32px; border:1px solid rgba(255,255,255,.25); border-radius:8px; background:#0a0a0b; color:#fff; padding:0 10px">
      <button class="btn" type="submit">Aggiungi</button>
    </form>
    <div class="muted" style="margin-top:6px">Inserisci l’ID esatto della partita (API-FOOTBALL). La importeremo automaticamente.</div>
  </div>

  <form method="post" action="" style="display:inline">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="action" value="confirm_draft">
    <button class="btn btn-primary" type="submit">Conferma e passa a DRAFT</button>
  </form>
  <a class="btn" href="/admin/crea_torneo.php" style="margin-left:8px">Torna</a>
</div>

</body>
</html>
