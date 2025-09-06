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

/* === HELPER event_code: genera e tronca alla lunghezza colonna === */
function gen_event_code(PDO $pdo): string {
  $sql = "SELECT CHARACTER_MAXIMUM_LENGTH
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'tournament_events'
            AND COLUMN_NAME = 'event_code'
          LIMIT 1";
  $len = (int)($pdo->query($sql)->fetchColumn() ?: 12);
  $raw = strtoupper(bin2hex(random_bytes(8))); // 16 char
  return substr($raw, 0, max(1, $len));
}

/* === FUNZIONE: normalizza ID squadra verso canon (AGGIUNTA) === */
function canonize_team_id(PDO $pdo, int $league_id, int $team_id, string $name): int {
  if ($league_id <= 0 || $team_id <= 0) return $team_id;

  // già canon?
  $st = $pdo->prepare("SELECT canon_team_id FROM admin_team_canon WHERE league_id=? AND canon_team_id=? LIMIT 1");
  $st->execute([$league_id, $team_id]);
  if ($st->fetchColumn()) return $team_id;

  // alias mappato?
  $st = $pdo->prepare("SELECT canon_team_id FROM admin_team_canon_map WHERE league_id=? AND team_id=? LIMIT 1");
  $st->execute([$league_id, $team_id]);
  $canon = (int)($st->fetchColumn() ?: 0);
  if ($canon > 0) return $canon;

  // match per nome normalizzato
  $norm = mb_strtolower(str_replace(' ', '', (string)$name));
  if ($norm !== '') {
    $st = $pdo->prepare("
      SELECT canon_team_id FROM admin_team_canon
      WHERE league_id=? AND LOWER(REPLACE(display_name,' ',''))=?
      LIMIT 1
    ");
    $st->execute([$league_id, $norm]);
    $canon = (int)($st->fetchColumn() ?: 0);
    if ($canon > 0) {
      $pdo->prepare("INSERT IGNORE INTO admin_team_canon_map (league_id, team_id, canon_team_id) VALUES (?,?,?)")
          ->execute([$league_id, $team_id, $canon]);
      return $canon;
    }
  }

  // crea canon + mappa
  $pdo->prepare("INSERT INTO admin_team_canon (league_id, display_name) VALUES (?, ?)")
      ->execute([$league_id, $name ?: ('Team '.$team_id)]);
  $canon = (int)$pdo->lastInsertId();
  $pdo->prepare("INSERT IGNORE INTO admin_team_canon_map (league_id, team_id, canon_team_id) VALUES (?,?,?)")
      ->execute([$league_id, $team_id, $canon]);

  return $canon;
}

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

/* === AGGIUNTA: league_id per pulsante “Risolvi ID” + canon list per autocomplete === */
$league_id_for_map = (int)($torneo['league_id'] ?? 0);
$canonList = [];
$canonById = [];
try {
  $st = $pdo->prepare("SELECT canon_team_id, display_name FROM admin_team_canon WHERE league_id=? ORDER BY display_name ASC");
  $st->execute([$league_id_for_map]);
  $canonList = $st->fetchAll(PDO::FETCH_ASSOC);
  foreach ($canonList as $c) { $canonById[(int)$c['canon_team_id']] = (string)$c['display_name']; }
} catch (Throwable $e) { $canonList = []; }

/* mappature esistenti (alias -> canon) per questa lega */
$aliasMap = [];
try {
  $st = $pdo->prepare("SELECT team_id, canon_team_id FROM admin_team_canon_map WHERE league_id=?");
  $st->execute([$league_id_for_map]);
  foreach ($st as $r) { $aliasMap[(int)$r['team_id']] = (int)$r['canon_team_id']; }
} catch (Throwable $e) {}

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

    // NEW: genera un event_code univoco (tronca alla lunghezza colonna)
    $eventCode = gen_event_code($pdo);

   $ins = $pdo->prepare("
  INSERT IGNORE INTO tournament_events
    (tournament_id, round_no, fixture_id,
     home_team_id, home_team_name, away_team_id, away_team_name,
     kickoff, is_active, pick_locked, result_status, result_at, event_code)
  VALUES (:tid, :rnd, :fid,
          :hid, :hname, :aid, :aname,
          NULL, 1, 0, 'pending', NULL, :ecode)
");
foreach ($list as $fx) {
  $ins->execute([
    ':tid'   => $id,
    ':rnd'   => $current_round_no ?: 1,
    ':fid'   => ($fx['fixture_id'] ? (int)$fx['fixture_id'] : null),
    ':hid'   => $fx['home_id'],
    ':hname' => $fx['home_name'],
    ':aid'   => $fx['away_id'],
    ':aname' => $fx['away_name'],
    ':ecode' => gen_event_code($pdo),
  ]);
}

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

    // NEW: genera un event_code univoco (tronca alla lunghezza colonna)
    $eventCode = gen_event_code($pdo);

    $pdo->prepare("
      INSERT INTO tournament_events
        (tournament_id, round_no, fixture_id,
         home_team_id, home_team_name, away_team_id, away_team_name,
         kickoff, is_active, pick_locked, result_status, result_at, event_code)
      VALUES (:tid, :rnd, NULL,
              NULL, :hname, NULL, :aname,
              NULL, 1, 0, 'pending', NULL, :ecode)
    ")->execute([
      ':tid'=>$id, ':rnd'=>$current_round_no ?: 1,
      ':hname'=>$hname, ':aname'=>$aname,
      ':ecode'=>$eventCode,
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

// =============== SYNC INIZIALE SE NESSUN EVENTO (a livello torneo) ===============
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
          $home_canon = canonize_team_id($pdo, (int)$league_id, (int)($fx['home_id'] ?? 0), (string)($fx['home_name'] ?? ''));
          $away_canon = canonize_team_id($pdo, (int)$league_id, (int)($fx['away_id'] ?? 0), (string)($fx['away_name'] ?? ''));

          $ins->execute([
            ':tid'=>$id, ':rnd'=>$current_round_no ?: 1,
            ':fid'=> ($fx['fixture_id'] ? (int)$fx['fixture_id'] : null),
            ':hid'=> $home_canon, ':hname'=> $fx['home_name'],
            ':aid'=> $away_canon, ':aname'=> $fx['away_name'],
          ]);
        }
      }
    }
  } catch (Throwable $e) {
    $err_fetch = 'Eccezione fetch: '.$e->getMessage();
  }
}

// =============== VERIFICA EVENTI DEL ROUND CORRENTE (BANNER ADMIN) ===============
$stCR = $pdo->prepare("SELECT COUNT(*) FROM tournament_events WHERE tournament_id=:tid AND round_no=:r");
$stCR->execute([':tid'=>$id, ':r'=>$current_round_no]);
$eventsCountCurrentRound = (int)$stCR->fetchColumn();

// =============== CARICO EVENTI PER TABELLA (SOLO ROUND CORRENTE) ===============
$ev = $pdo->prepare("
  SELECT id, fixture_id, home_team_name, away_team_name, home_team_id, away_team_id, is_active
  FROM tournament_events
  WHERE tournament_id = :tid
    AND round_no = :r
  ORDER BY id ASC
");
$ev->execute([':tid'=>$id, ':r'=>$current_round_no]);
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
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1);vertical-align:middle}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .flash{color:#00d07e;margin:8px 0}
    .err{color:#ff6b6b;margin:8px 0}
    .muted{color:#aaa}
    .row-actions{display:flex; gap:8px; align-items:center; flex-wrap:wrap}
    .banner-admin{background:#6a0000;color:#fff;padding:10px;border-radius:8px;margin-bottom:12px}

    /* Badge/avvisi canon + quick-map */
    .badge { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:800; }
    .badge--warn { background:#332400; color:#ffc400; border:1px solid rgba(255,196,0,.45); }
    .cell-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .cell-actions form { margin:0; display:inline-flex; gap:6px; align-items:center; }
    select.canon-quick, input.canon-quick { height:32px; border:1px solid rgba(255,255,255,.25); border-radius:8px; background:#0a0a0b; color:#fff; padding:0 8px; }
    .btn--tiny{height:26px;padding:0 10px;border-radius:6px;font-size:12px}

    /* Toast */
    #toast { position:fixed; right:16px; bottom:16px; background:#111; color:#fff; border:1px solid rgba(255,255,255,.16); border-radius:10px; padding:10px 12px; display:none; z-index:9999; box-shadow:0 10px 30px rgba(0,0,0,.35); }
    #toast.ok { border-color:#00c074; }
    #toast.err { border-color:#e62329; }
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

  <?php if ($eventsCountCurrentRound === 0): ?>
    <div class="banner-admin">Necessario intervento admin: nessun evento presente per il <strong>round corrente (<?php echo (int)$current_round_no; ?>)</strong>. Carica o pubblica la giornata successiva.</div>
  <?php endif; ?>

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
            <th>Canon</th>
            <th>Scelte abilitate</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $e): ?>
            <?php
              $homeRaw = (int)($e['home_team_id'] ?? 0);
              $awayRaw = (int)($e['away_team_id'] ?? 0);
              $homeCanon = $homeRaw ? ($aliasMap[$homeRaw] ?? null) : null;
              $awayCanon = $awayRaw ? ($aliasMap[$awayRaw] ?? null) : null;
              $homeName = (string)($e['home_team_name'] ?? '??');
              $awayName = (string)($e['away_team_name'] ?? '??');
              $hasCanonIssue = (!$homeCanon && $homeRaw>0) || (!$awayCanon && $awayRaw>0);
            ?>
            <tr>
              <td><?php echo (int)$e['id']; ?></td>
              <td><?php echo $e['fixture_id'] ? (int)$e['fixture_id'] : '-'; ?></td>
              <td>
                <div style="display:flex;flex-direction:column;gap:2px">
                  <div style="display:flex;align-items:center;gap:8px;">
                    <span><?php echo htmlspecialchars($homeName); ?></span>
                    <?php if ((int)($e['home_team_id'] ?? 0) <= 0): ?>
                      <span class="badge badge--warn" title="ID team mancante">ID mancante</span>
                      <button
                        type="button"
                        class="btn btn--tiny js-resolve-id"
                        data-ev="<?php echo (int)$e['id']; ?>"
                        data-side="home"
                        data-tid="<?php echo (int)$torneo['id']; ?>"
                        data-league="<?php echo (int)$league_id_for_map; ?>"
                        data-name="<?php echo htmlspecialchars($homeName, ENT_QUOTES); ?>"
                      >Risolvi ID</button>
                    <?php endif; ?>
                  </div>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <span><?php echo htmlspecialchars($awayName); ?></span>
                    <?php if ((int)($e['away_team_id'] ?? 0) <= 0): ?>
                      <span class="badge badge--warn" title="ID team mancante">ID mancante</span>
                      <button
                        type="button"
                        class="btn btn--tiny js-resolve-id"
                        data-ev="<?php echo (int)$e['id']; ?>"
                        data-side="away"
                        data-tid="<?php echo (int)$torneo['id']; ?>"
                        data-league="<?php echo (int)$league_id_for_map; ?>"
                        data-name="<?php echo htmlspecialchars($awayName, ENT_QUOTES); ?>"
                      >Risolvi ID</button>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <td>
                <!-- vista canon sintetica + quick map per ciascun lato che manca -->
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                  <!-- HOME -->
                  <div>
                    <div class="muted" style="font-size:11px;">Casa</div>
                    <?php if ($homeRaw>0): ?>
                      <?php if ($homeCanon): ?>
                        #<?php echo (int)$homeCanon; ?> — <?php echo htmlspecialchars($canonById[$homeCanon] ?? $homeName); ?>
                      <?php else: ?>
                        <form method="post" action="/admin/map_alias_quick.php" class="cell-map">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                          <input type="hidden" name="league_id" value="<?php echo (int)$league_id_for_map; ?>">
                          <input type="hidden" name="team_id" value="<?php echo (int)$homeRaw; ?>">
                          <input class="canon-quick" list="canonListDL" name="canon_team_id" placeholder="ID canon" style="width:160px;">
                          <button class="btn" type="submit">Mappa</button>
                        </form>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="muted">—</span>
                    <?php endif; ?>
                  </div>

                  <!-- AWAY -->
                  <div>
                    <div class="muted" style="font-size:11px;">Trasferta</div>
                    <?php if ($awayRaw>0): ?>
                      <?php if ($awayCanon): ?>
                        #<?php echo (int)$awayCanon; ?> — <?php echo htmlspecialchars($canonById[$awayCanon] ?? $awayName); ?>
                      <?php else: ?>
                        <form method="post" action="/admin/map_alias_quick.php" class="cell-map">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                          <input type="hidden" name="league_id" value="<?php echo (int)$league_id_for_map; ?>">
                          <input type="hidden" name="team_id" value="<?php echo (int)$awayRaw; ?>">
                          <input class="canon-quick" list="canonListDL" name="canon_team_id" placeholder="ID canon" style="width:160px;">
                          <button class="btn" type="submit">Mappa</button>
                        </form>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="muted">—</span>
                    <?php endif; ?>
                  </div>

                  <?php if ($hasCanonIssue): ?>
                    <a class="btn" href="/admin/squadre_lega.php?league_id=<?php echo (int)$league_id_for_map; ?>">Risolvi ID</a>
                  <?php endif; ?>
                </div>
              </td>

              <td><?php echo ((int)$e['is_active']===1) ? 'Sì' : 'No'; ?></td>

              <td class="row-actions cell-actions">
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

    <!-- CAMBIO RICHIESTO: da submit tradizionale ad AJAX verso /admin/calc_round.php -->
    <form id="calc-round-form" style="display:inline-flex; gap:8px; align-items:center;">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="tournament_id" value="<?php echo (int)$id; ?>">
      <button class="btn" type="submit">Calcola round adesso</button>
    </form>
  </div>

  <div class="card">
    <h3 style="margin:0 0 10px;">Chiusura torneo</h3>
    <p class="muted" style="margin:0 0 10px;">
      Usa questo pulsante solo quando il torneo deve terminare:
      - se resta 1 solo sopravvissuto → 100% del montepremi a lui;<br>
      - se non resta nessuno → distribuzione proporzionale.
    </p>
    <form method="POST" action="/admin/close_tournament.php"
          onsubmit="return confirm('Confermi chiusura e payout (split automatico se più sopravvissuti)?');"
          style="margin-top:12px;">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
      <input type="hidden" name="tournament_id" value="<?php echo (int)$id; ?>">
      <input type="hidden" name="force" value="1"> <!-- <<< AGGIUNTA: forza split automatico -->
      <button class="btn" type="submit" style="background:#0a7;border:1px solid #0a7;color:#fff;">
        Chiudi torneo &amp; paga (forza split)
      </button>
    </form>
  </div>

  <div>
    <a class="btn" href="/admin/gestisci_tornei.php">Torna alla lista</a>
  </div>
</div>

<!-- Datalist canon per l’autocomplete nei quick-map -->
<datalist id="canonListDL">
  <?php foreach ($canonList as $c): ?>
    <option value="<?php echo (int)$c['canon_team_id']; ?>"><?php echo htmlspecialchars($c['display_name']); ?></option>
  <?php endforeach; ?>
</datalist>

<!-- Toast -->
<div id="toast"></div>

<!-- =========================
     Modal “Risolvi ID” (re-usable)
     ========================= -->
<div id="resolveModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center; padding:16px;">
  <div style="background:#0f1114; border:1px solid rgba(255,255,255,.15); border-radius:12px; color:#fff; width:560px; max-width:calc(100vw - 32px); padding:16px;">
    <h3 style="margin:0 0 10px; font-weight:900;">Risolvi ID squadra</h3>
    <div id="rvInfo" style="color:#ccc; font-size:13px; margin-bottom:10px;"></div>

    <div style="display:flex; gap:8px; align-items:center; margin-bottom:10px;">
      <label style="min-width:140px;">Inserisci ID (numero):</label>
      <input id="rvManualId" type="number" min="1" step="1" style="flex:1; height:34px; border-radius:8px; border:1px solid rgba(255,255,255,.25); background:#0a0a0b; color:#fff; padding:0 10px;">
      <button id="rvApplyManual" class="btn" style="height:34px;">Applica</button>
    </div>

    <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
      <label style="min-width:140px;">Suggerimenti nome:</label>
      <input id="rvSearch" type="text" placeholder="Cerca tra nomi già visti" style="flex:1; height:34px; border-radius:8px; border:1px solid rgba(255,255,255,.25); background:#0a0a0b; color:#fff; padding:0 10px;">
      <button id="rvAsk" class="btn" style="height:34px;">Cerca</button>
    </div>

    <div id="rvSuggest" style="max-height:220px; overflow:auto; border:1px solid rgba(255,255,255,.12); border-radius:8px; padding:8px; background:#12161b; display:none;"></div>

    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
      <button id="rvClose" class="btn" style="height:34px;">Chiudi</button>
    </div>
  </div>
</div>

<<!-- =========================
     Helper JS: toast, calcolo round (AJAX) e “Risolvi ID”
     ========================= -->
<script>
  function showToast(msg, kind){
    var t = document.getElementById('toast'); if(!t) return;
    t.className = ''; t.classList.add(kind==='err'?'err':'ok');
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(function(){ t.style.display='none'; }, 3500);
  }

  // Calcolo round via AJAX
  (function(){
    const form = document.getElementById('calc-round-form');
    if(!form) return;
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const fd = new FormData(form);
      try{
        const resp = await fetch('/admin/calc_round.php', { method:'POST', body: fd, credentials:'same-origin' });
        const js = await resp.json();
        if(!js || js.ok !== true){
          showToast(js && js.msg ? js.msg : 'Errore nel calcolo round','err');
          return;
        }
        const nextMsg = (js.closed ? 'Torneo chiuso.' : ('Si passa al round ' + (js.round + 1) + (js.next_round_loaded===false ? ' (attenzione: eventi non caricati)' : '')));
        showToast('Calcolo round '+js.round+' effettuato. '+nextMsg,'ok');
        setTimeout(function(){
          window.location.href = '/admin/torneo_open.php?id=<?php echo (int)$id; ?>';
        }, 1200);
      }catch(err){
        showToast('Errore di rete durante il calcolo round','err');
      }
    });
  })();

  // Modal “Risolvi ID” con EVENT DELEGATION (funziona anche se il DOM si aggiorna)
  (function(){
    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]; }); }

    var modal  = document.getElementById('resolveModal');
    var info   = document.getElementById('rvInfo');
    var inId   = document.getElementById('rvManualId');
    var inQ    = document.getElementById('rvSearch');
    var btnAsk = document.getElementById('rvAsk');
    var btnApply = document.getElementById('rvApplyManual');
    var btnClose = document.getElementById('rvClose');
    var boxSug = document.getElementById('rvSuggest');

    var ctx = { tid:0, ev:0, side:'home', league:0, name:'' };

    function openModal(data){
      ctx = data || ctx;
      info.textContent = 'Torneo #'+ctx.tid+' — Evento #'+ctx.ev+' — Lato: '+ctx.side.toUpperCase()+' — Nome: "'+ctx.name+'"';
      inId.value = '';
      inQ.value = ctx.name || '';
      boxSug.innerHTML = ''; boxSug.style.display = 'none';
      modal.style.display = 'flex';
    }
    function closeModal(){ modal.style.display = 'none'; }

    // Delegation: intercetta click su qualunque .js-resolve-id
    document.addEventListener('click', function(ev){
      var b = ev.target.closest('.js-resolve-id');
      if (!b) return;
      openModal({
        tid:    parseInt(b.getAttribute('data-tid'),10),
        ev:     parseInt(b.getAttribute('data-ev'),10),
        side:   (b.getAttribute('data-side')||'home'),
        league: parseInt(b.getAttribute('data-league'),10),
        name:   b.getAttribute('data-name')||''
      });
    });

    // Cerca suggerimenti (parse JSON tollerante)
    btnAsk.addEventListener('click', function(){
      var q = (inQ.value||'').trim();
      if (!q) return;
      boxSug.innerHTML = 'Carico...'; boxSug.style.display='block';

      fetch('/api/resolve_team_id.php?action=suggest'
        + '&tournament_id='+encodeURIComponent(ctx.tid)
        + '&league_id='+encodeURIComponent(ctx.league)
        + '&q='+encodeURIComponent(q), { credentials:'same-origin' })
      .then(async function(r){
        // evita eccezioni se il server risponde HTML (redirect/login/notice)
        var ct = r.headers.get('content-type') || '';
        var txt = await r.text();
        if (!r.ok || !ct.includes('application/json')) return null;
        try { return JSON.parse(txt); } catch(e){ return null; }
      })
      .then(function(js){
        if(!js || js.ok !== true){
          boxSug.innerHTML = '<div style="color:#ff7076;">Errore o nessun suggerimento.</div>';
          return;
        }
        if (!js.suggestions || !js.suggestions.length) {
          boxSug.innerHTML = '<div style="color:#aaa;">Nessun suggerimento.</div>';
          return;
        }
        boxSug.innerHTML = js.suggestions.map(function(s){
          return '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 8px;border-bottom:1px solid rgba(255,255,255,.08);">'
            + '<div><b>'+escapeHtml(s.name)+'</b> <span style="color:#aaa;">(ID '+s.team_id+')</span></div>'
            + '<button class="btn btn--tiny" data-nuse="'+s.team_id+'">Usa ID</button>'
          + '</div>';
        }).join('');
      })
      .catch(function(){ boxSug.innerHTML = '<div style="color:#ff7076;">Errore richiesta.</div>'; });
    });

    // Applica suggerimento
    boxSug.addEventListener('click', function(e){
      var x = e.target.closest('[data-nuse]');
      if (!x) return;
      var id = parseInt(x.getAttribute('data-nuse'), 10);
      if (!id) return;
      applyId(id);
    });

    // Applica ID manuale
    btnApply.addEventListener('click', function(){
      var val = parseInt(inId.value, 10);
      if (!val || val<=0) { inId.focus(); return; }
      applyId(val);
    });

    // Salva su server
    function applyId(teamId){
      var fd = new FormData();
      fd.append('tournament_id', String(ctx.tid));
      fd.append('event_id', String(ctx.ev));
      fd.append('side', ctx.side);
      fd.append('team_id', String(teamId));
      fetch('/api/resolve_team_id.php', { method:'POST', body:fd, credentials:'same-origin' })
        .then(r => r.ok ? r.json() : null)
        .then(js => {
          if (!js || !js.ok) { showToast('Errore salvataggio ID', 'err'); return; }
          window.location.reload();
        })
        .catch(function(){ showToast('Errore di rete', 'err'); });
    }

    btnClose.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });
  })();
</script>

</body>
</html>
