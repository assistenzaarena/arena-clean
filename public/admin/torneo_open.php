<?php
/**
 * admin/torneo_open.php
 * Editor di un torneo PUBBLICATO/IN CORSO (status='open').
 * Funzioni: meta (round, lock), add fixture (API/manuale), toggle attivo, elimina evento.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/guards.php';    require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
$competitions = require $ROOT . '/src/config/competitions.php';
require_once $ROOT . '/src/services/football_api.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); die('ID mancante'); }

// carico torneo e verifico status
$tq = $pdo->prepare("SELECT * FROM tournaments WHERE id=:id LIMIT 1");
$tq->execute([':id'=>$id]);
$torneo = $tq->fetch(PDO::FETCH_ASSOC);
if (!$torneo) { http_response_code(404); die('Torneo non trovato'); }
if (($torneo['status'] ?? '') !== 'open') {
  $_SESSION['flash'] = 'Il torneo non è in corso (open).';
  header('Location: /admin/gestisci_tornei.php'); exit;
}

$roundType = $torneo['round_type'] ?? 'matchday';
$league_id = (int)$torneo['league_id'];
$season    = (string)$torneo['season'];
$matchday  = $torneo['matchday'] ? (int)$torneo['matchday'] : null;
$round_lbl = $torneo['round_label'] ?? null;
$current_round_no = (int)($torneo['current_round_no'] ?? 1);

// =============== POST HANDLER ===============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'], $posted_csrf)) { http_response_code(400); die('CSRF non valido'); }

  $action = $_POST['action'] ?? '';

  // meta torneo
  if ($action === 'update_meta') {
    $new_round_no = (int)($_POST['current_round_no'] ?? $current_round_no);
    $new_lock_at  = trim($_POST['lock_at'] ?? '');
    $lockValue    = ($new_lock_at === '') ? null : $new_lock_at;

    $pdo->prepare("UPDATE tournaments SET current_round_no=:r, lock_at=:l, updated_at=NOW() WHERE id=:id")
        ->execute([':r'=>$new_round_no, ':l'=>$lockValue, ':id'=>$id]);

    $_SESSION['flash'] = 'Meta torneo aggiornati.';
    header('Location: /admin/torneo_open.php?id='.$id); exit;
  }

  // aggiungi fixture da API
  if ($action === 'add_fixture_api') {
    $fxid = (int)($_POST['fixture_id'] ?? 0);
    if ($fxid <= 0) { $_SESSION['flash']='Fixture ID non valido.'; header('Location: /admin/torneo_open.php?id='.$id); exit; }

    $resp = fb_fixture_by_id($fxid);
    if (!$resp['ok']) {
      $_SESSION['flash'] = 'Errore API: '.$resp['error'].' (HTTP '.$resp['status'].')';
      header('Location: /admin/torneo_open.php?id='.$id); exit;
    }
    $one = fb_extract_one_fixture_minimal($resp['data']);
    if (!$one || !$one['fixture_id']) {
      $_SESSION['flash'] = 'Fixture non trovato in API.';
      header('Location: /admin/torneo_open.php?id='.$id); exit;
    }

    $pdo->prepare("
      INSERT IGNORE INTO tournament_events
        (tournament_id, round_no, fixture_id, home_team_id, home_team_name, away_team_id, away_team_name, kickoff, is_active, pick_locked)
      VALUES (:tid, :rnd, :fid, :hid, :hname, :aid, :aname, NULL, 1, 0)
    ")->execute([
      ':tid'=>$id, ':rnd'=>$current_round_no ?: 1,
      ':fid'=>(int)$one['fixture_id'],
      ':hid'=>$one['home_id'], ':hname'=>$one['home_name'],
      ':aid'=>$one['away_id'], ':aname'=>$one['away_name']
    ]);

    $_SESSION['flash'] = 'Fixture '.$one['fixture_id'].' aggiunto (API).';
    header('Location: /admin/torneo_open.php?id='.$id); exit;
  }

  // aggiungi evento manuale
  if ($action === 'add_fixture_manual') {
    $hname = trim($_POST['home_team_name'] ?? '');
    $aname = trim($_POST['away_team_name'] ?? '');
    if ($hname === '' || $aname === '') {
      $_SESSION['flash'] = 'Inserisci nomi squadra casa e trasferta.';
      header('Location: /admin/torneo_open.php?id='.$id); exit;
    }

    $pdo->prepare("
      INSERT INTO tournament_events
        (tournament_id, round_no, fixture_id, home_team_id, home_team_name, away_team_id, away_team_name, kickoff, is_active, pick_locked)
      VALUES (:tid, :rnd, NULL, NULL, :hname, NULL, :aname, NULL, 1, 0)
    ")->execute([
      ':tid'=>$id, ':rnd'=>$current_round_no ?: 1,
      ':hname'=>$hname, ':aname'=>$aname
    ]);

    $_SESSION['flash'] = 'Evento manuale aggiunto.';
    header('Location: /admin/torneo_open.php?id='.$id); exit;
  }

  // toggle attivo/disattivo
  if ($action === 'toggle_event_active') {
    $row_id = (int)($_POST['row_id'] ?? 0);
    if ($row_id <= 0) { $_SESSION['flash']='Evento non valido.'; header('Location: /admin/torneo_open.php?id='.$id); exit; }

    $rq = $pdo->prepare("SELECT is_active FROM tournament_events WHERE id=:rid AND tournament_id=:tid");
    $rq->execute([':rid'=>$row_id, ':tid'=>$id]);
    $row = $rq->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $_SESSION['flash']='Evento non trovato.'; header('Location: /admin/torneo_open.php?id='.$id); exit; }

    $new = ((int)$row['is_active'] === 1) ? 0 : 1;
    $pdo->prepare("UPDATE tournament_events SET is_active=:n WHERE id=:rid AND tournament_id=:tid")
        ->execute([':n'=>$new, ':rid'=>$row_id, ':tid'=>$id]);
    $_SESSION['flash'] = 'Evento #'.$row_id.' '.($new ? 'attivato' : 'disattivato').'.';
    header('Location: /admin/torneo_open.php?id='.$id); exit;
  }

  // elimina evento
  if ($action === 'delete_event') {
    $row_id = (int)($_POST['row_id'] ?? 0);
    if ($row_id <= 0) { $_SESSION['flash']='Evento non valido.'; header('Location: /admin/torneo_open.php?id='.$id); exit; }
    $pdo->prepare("DELETE FROM tournament_events WHERE id=:rid AND tournament_id=:tid")->execute([':rid'=>$row_id, ':tid'=>$id]);
    $_SESSION['flash'] = 'Evento #'.$row_id.' eliminato.';
    header('Location: /admin/torneo_open.php?id='.$id); exit;
  }
}

// =============== SYNC INIZIALE SE NESSUN EVENTO ===============
$has = $pdo->prepare("SELECT COUNT(*) FROM tournament_events WHERE tournament_id=:tid");
$has->execute([':tid'=>$id]);
$hasRows = ((int)$has->fetchColumn() > 0);

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
            (tournament_id, round_no, fixture_id, home_team_id, home_team_name, away_team_id, away_team_name, kickoff, is_active, pick_locked)
          VALUES (:tid, :rnd, :fid, :hid, :hname, :aid, :aname, NULL, 1, 0)
        ");
        foreach ($list as $fx) {
          $ins->execute([
            ':tid'=>$id, ':rnd'=>$current_round_no ?: 1,
            ':fid'=> ($fx['fixture_id'] ? (int)$fx['fixture_id'] : null),
            ':hid'=> $fx['home_id'], ':hname'=> $fx['home_name'],
            ':aid'=> $fx['away_id'], ':aname'=> $fx['away_name'],
          ]);
        }
      }
    }
  } catch (Throwable $e) {
    $err_fetch = 'Eccezione fetch: '.$e->getMessage();
  }
}

// =============== CARICO EVENTI PER TABELLA ===============
$ev = $pdo->prepare("
  SELECT id, fixture_id, home_team_name, away_team_name, is_active
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
  <title>Gestione torneo (in corso) #<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',$id)); ?></title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1100px;margin:20px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .flash{color:#00d07e;margin:8px 0}
    .err{color:#ff6b6b;margin:8px 0}
    .muted{color:#aaa}
    .row-actions{display:flex; gap:8px}
  </style>
  <!-- ESPONGO CSRF PER L'ESEMPIO AJAX -->
  <script>window.CSRF = "<?php echo htmlspecialchars($csrf); ?>";</script>
</head>
<body>
<?php require $ROOT . '/header_admin.php'; ?>

<div class="wrap">
  <h1 style="margin:0 0 12px;">Gestione torneo (in corso) #<?php echo htmlspecialchars($torneo['tournament_code'] ?? sprintf('%05d',$id)); ?> — <?php echo htmlspecialchars($torneo['name']); ?></h1>

  <?php if ($flash): ?><div class="flash"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
  <?php if ($err_fetch): ?><div class="err"><?php echo htmlspecialchars($err_fetch); ?></div><?php endif; ?>

  <div class="card">
    <h3 style="margin:0 0 10px;">Meta torneo</h3>
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
    </form>
    <div class="muted" style="margin-top:6px">Il lock scelte blocca globalmente il torneo al momento indicato.</div>

    <!-- (AGGIUNTA RICHIESTA) Link Round (storico) -->
    <a class="btn" href="/admin/torneo_round.php?id=<?php echo (int)$id; ?>" style="margin-top:8px;">Round (storico)</a>
  </div>

  <div class="card">
    <h3 style="margin:0 0 10px;">Fixtures del torneo</h3>

    <?php if (!$events): ?>
      <div class="muted">Nessun evento registrato al momento.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Fixture ID</th>
            <th>Partita</th>
            <th>Scelte abilitate</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $e): ?>
            <tr>
              <td><?php echo (int)$e['id']; ?></td>
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
                  <button class="btn" type="submit" style="background:#e62329;border-color:#e62329;">Elimina</button>
                </form>

                <!-- =========================
                     (AGGIUNTA 1) Form risultato evento
                     ========================= -->
                <form method="post" action="/admin/set_event_result.php" style="display:inline; margin-left:6px;">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="tournament_id" value="<?php echo (int)$id; ?>">
                  <input type="hidden" name="event_id" value="<?php echo (int)$e['id']; ?>">
                  <select name="result_status">
                    <option value="home_win">Casa vince</option>
                    <option value="away_win">Trasferta vince</option>
                    <option value="draw">Pareggio</option>
                    <option value="postponed">Rinviata</option>
                    <option value="void">Annullata</option>
                  </select>
                  <input type="hidden" name="redirect" value="1">
                  <button class="btn" type="submit">Aggiorna</button>
                </form>
                <!-- ========================= -->

              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 style="margin:0 0 10px;">Aggiungi fixture (API) o evento manuale</h3>
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
  
  <div class="card">
    <h3 style="margin:0 0 10px;">Calcolo round corrente</h3>
    <p class="muted" style="margin:0 0 10px;">
      Esegue il calcolo del round corrente (conferma vincitori/perdenti in base ai risultati impostati).
      Non cambia il lock né lo stato “scelte aperte/chiuse”.
    </p>
    <form method="post"
          action="/api/compute_round.php"
          style="display:inline-flex; gap:8px; align-items:center;">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="tournament_id" value="<?php echo (int)$id; ?>">
      <button class="btn" type="submit">Calcola round adesso</button>
    </form>
  </div>
  <div>
    
    <a class="btn" href="/admin/gestisci_tornei.php">Torna alla lista</a>
  </div>
</div>

<!-- =========================
     (AGGIUNTA 2) Helper AJAX opzionale
     ========================= -->
<script>
  // Esempio opzionale: aggiorna risultato via fetch senza ricaricare
  // Usa: aggiornaRisultato(<?php echo (int)$id; ?>, EVENT_ID, 'home_win'|'away_win'|'draw'|'postponed'|'void')
  function aggiornaRisultato(tid, evId, outcome) {
    fetch('/admin/set_event_result.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'csrf='+encodeURIComponent(window.CSRF)
          +'&tournament_id='+encodeURIComponent(tid)
          +'&event_id='+encodeURIComponent(evId)
          +'&result_status='+encodeURIComponent(outcome)
    })
    .then(r=>r.json())
    .then(js=>{
      if(js && js.ok){ alert('Risultato aggiornato'); }
      else{ alert('Errore: '+(js && js.error ? js.error : 'sconosciuto')); }
    })
    .catch(()=>alert('Errore di rete'));
  }
</script>

</body>
</html>
