<?php
/**
 * admin/squadre_lega.php
 *
 * Pannello "Catalogo squadre" per una lega:
 * - Colonna sinistra: Canon team (ID canonico) con display name (rinomina/elimina)
 * - Colonna destra: Alias rilevati (team_id grezzi dagli eventi) con mappatura su canon
 *
 * Dipendenze DB:
 *   - admin_team_canon_map(league_id INT, team_id INT, canon_team_id INT, PRIMARY KEY(league_id, team_id))
 *   - (auto) admin_team_canon(
 *        canon_team_id INT PK AUTO_INCREMENT,
 *        league_id INT NOT NULL,
 *        display_name VARCHAR(100) NOT NULL,
 *        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 *        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *        UNIQUE KEY uq_canon (league_id, display_name)
 *     )
 *
 * Sicurezza: CSRF + require_admin
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = dirname(__DIR__);

require_once $ROOT . '/src/guards.php'; require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

/* ------------------------------------------------------------------
   0) Ensure tabella admin_team_canon (creazione soft se manca)
------------------------------------------------------------------ */
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_team_canon (
      canon_team_id INT NOT NULL AUTO_INCREMENT,
      league_id INT NOT NULL,
      display_name VARCHAR(100) NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (canon_team_id),
      UNIQUE KEY uq_canon (league_id, display_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
} catch (Throwable $e) {
  // Non blocco la pagina; mostrerò un avviso più sotto se necessario
}

/* ------------------------------------------------------------------
   1) Lettura leghe (menu)
------------------------------------------------------------------ */
$leagueId = isset($_GET['league_id']) ? (int)$_GET['league_id'] : 0;
$leagues = [];
try {
  // Prendo le leghe esistenti dai tornei (così non devo toccare altro)
  $q = $pdo->query("SELECT DISTINCT league_id, league_name FROM tournaments WHERE league_id IS NOT NULL ORDER BY league_id ASC");
  $leagues = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e){ $leagues = []; }

if ($leagueId === 0 && $leagues) {
  $leagueId = (int)$leagues[0]['league_id'];
}

/* ------------------------------------------------------------------
   2) POST handler (azioni)
------------------------------------------------------------------ */
function back_to_self() {
  $qs = http_build_query(['league_id' => (int)($_GET['league_id'] ?? 0)]);
  header('Location: /admin/squadre_lega.php?' . $qs); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($csrf, $posted_csrf)) {
    $_SESSION['flash'] = ['type'=>'err','msg'=>'CSRF non valido.'];
    back_to_self();
  }
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'create_canon') {
      $lid = (int)($_POST['league_id'] ?? 0);
      $name = trim($_POST['display_name'] ?? '');
      if ($lid<=0 || $name==='') throw new RuntimeException('Dati canon non validi.');
      $st = $pdo->prepare("INSERT INTO admin_team_canon (league_id, display_name) VALUES (?, ?)");
      $st->execute([$lid, $name]);
      $_SESSION['flash'] = ['type'=>'ok','msg'=>'Canon creato (#'.$pdo->lastInsertId().').'];

    } elseif ($action === 'rename_canon') {
      $canonId = (int)($_POST['canon_team_id'] ?? 0);
      $name = trim($_POST['display_name'] ?? '');
      if ($canonId<=0 || $name==='') throw new RuntimeException('Dati rinomina non validi.');
      $st = $pdo->prepare("UPDATE admin_team_canon SET display_name=? WHERE canon_team_id=?");
      $st->execute([$name, $canonId]);
      $_SESSION['flash'] = ['type'=>'ok','msg'=>'Canon rinominato.'];

    } elseif ($action === 'delete_canon') {
      $canonId = (int)($_POST['canon_team_id'] ?? 0);
      if ($canonId<=0) throw new RuntimeException('Canon non valido.');
      // blocca se esistono alias mappati
      $chk = $pdo->prepare("SELECT COUNT(*) FROM admin_team_canon_map WHERE canon_team_id=?");
      $chk->execute([$canonId]);
      if ((int)$chk->fetchColumn() > 0) throw new RuntimeException('Ci sono alias mappati a questo canon. Rimuovili prima.');
      $st = $pdo->prepare("DELETE FROM admin_team_canon WHERE canon_team_id=?");
      $st->execute([$canonId]);
      $_SESSION['flash'] = ['type'=>'ok','msg'=>'Canon eliminato.'];

    } elseif ($action === 'map_alias') {
      $lid = (int)($_POST['league_id'] ?? 0);
      $teamId = (int)($_POST['team_id'] ?? 0);
      $canonId = (int)($_POST['canon_team_id'] ?? 0);
      if ($lid<=0 || $teamId<=0 || $canonId<=0) throw new RuntimeException('Mappatura non valida.');
      $st = $pdo->prepare("INSERT INTO admin_team_canon_map (league_id, team_id, canon_team_id)
                           VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE canon_team_id=VALUES(canon_team_id)");
      $st->execute([$lid, $teamId, $canonId]);
      $_SESSION['flash'] = ['type'=>'ok','msg'=>'Alias mappato.'];

    } elseif ($action === 'unmap_alias') {
      $lid = (int)($_POST['league_id'] ?? 0);
      $teamId = (int)($_POST['team_id'] ?? 0);
      if ($lid<=0 || $teamId<=0) throw new RuntimeException('Dati alias non validi.');
      $st = $pdo->prepare("DELETE FROM admin_team_canon_map WHERE league_id=? AND team_id=?");
      $st->execute([$lid, $teamId]);
      $_SESSION['flash'] = ['type'=>'ok','msg'=>'Alias scollegato.'];

    } else {
      $_SESSION['flash'] = ['type'=>'err','msg'=>'Azione non riconosciuta.'];
    }
  } catch (Throwable $e) {
    $_SESSION['flash'] = ['type'=>'err','msg'=>'Errore: '.$e->getMessage()];
  }
  back_to_self();
}

/* ------------------------------------------------------------------
   3) Dati pagina (canon e alias)
------------------------------------------------------------------ */
// lista canon per lega
$canon = [];
try {
  $st = $pdo->prepare("SELECT canon_team_id, display_name
                       FROM admin_team_canon
                       WHERE league_id=?
                       ORDER BY display_name ASC, canon_team_id ASC");
  $st->execute([$leagueId]);
  $canon = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $canon = []; }
$canonIndex = [];
foreach ($canon as $c) { $canonIndex[(int)$c['canon_team_id']] = $c['display_name']; }

// alias rilevati dagli eventi del torneo per quella lega
$aliases = [];
try {
  $st = $pdo->prepare("
    SELECT te.home_team_id AS team_id, te.home_team_name AS team_name
    FROM tournament_events te
    JOIN tournaments t ON t.id=te.tournament_id
    WHERE t.league_id=? AND te.home_team_id IS NOT NULL AND te.home_team_id>0
    UNION
    SELECT te.away_team_id AS team_id, te.away_team_name AS team_name
    FROM tournament_events te
    JOIN tournaments t ON t.id=te.tournament_id
    WHERE t.league_id=? AND te.away_team_id IS NOT NULL AND te.away_team_id>0
  ");
  $st->execute([$leagueId, $leagueId]);
  $aliases = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e){ $aliases = []; }

// mapping esistente
$map = [];
try {
  $st = $pdo->prepare("SELECT team_id, canon_team_id FROM admin_team_canon_map WHERE league_id=?");
  $st->execute([$leagueId]);
  foreach ($st as $r) $map[(int)$r['team_id']] = (int)$r['canon_team_id'];
} catch (Throwable $e) {}

// normalizza alias per vista (gruppi univoci)
$aliasUniq = [];
foreach ($aliases as $a) {
  $tid = (int)$a['team_id']; if ($tid<=0) continue;
  $name = (string)$a['team_name'];
  if (!isset($aliasUniq[$tid])) $aliasUniq[$tid] = $name;
}

/* ------------------------------------------------------------------
   4) HTML
------------------------------------------------------------------ */
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Catalogo squadre</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1200px;margin:20px auto;padding:0 16px;color:#fff}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    @media(max-width: 980px){ .grid{grid-template-columns:1fr} }
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px}
    .muted{color:#bbb}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
    input[type=text],select{height:34px;border:1px solid rgba(255,255,255,.25);border-radius:8px;background:#0a0a0b;color:#fff;padding:0 10px}
    .list{max-height:520px;overflow:auto;border:1px solid rgba(255,255,255,.08);border-radius:10px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.08)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .flash.ok{background:rgba(0,192,116,.12);border:1px solid rgba(0,192,116,.4);color:#00c074;padding:10px 12px;border-radius:8px;margin-bottom:12px}
    .flash.err{background:rgba(230,35,41,.12);border:1px solid rgba(230,35,41,.4);color:#ff6b6b;padding:10px 12px;border-radius:8px;margin-bottom:12px}
  </style>
</head>
<body>
<?php require $ROOT . '/header_admin.php'; ?>
<div class="wrap">
  <h1 style="margin:0 0 12px;">Catalogo squadre</h1>

  <?php if ($flash): ?>
    <div class="flash <?php echo htmlspecialchars($flash['type'] ?? 'ok'); ?>">
      <?php echo htmlspecialchars($flash['msg'] ?? ''); ?>
    </div>
  <?php endif; ?>

  <form method="get" action="/admin/squadre_lega.php" style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">
    <label class="muted">Lega</label>
    <select name="league_id">
      <?php foreach ($leagues as $L): ?>
        <option value="<?php echo (int)$L['league_id']; ?>" <?php echo ($leagueId===(int)$L['league_id']?'selected':''); ?>>
          <?php echo (int)$L['league_id']; ?> — <?php echo htmlspecialchars($L['league_name'] ?? ('League '.$L['league_id'])); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Apri</button>
  </form>

  <?php if ($leagueId>0): ?>
  <div class="grid">
    <!-- Canon -->
    <section class="card">
      <h3 style="margin:0 0 10px;">Canon (ID ufficiali) — Lega <?php echo (int)$leagueId; ?></h3>

      <form method="post" action="/admin/squadre_lega.php?league_id=<?php echo (int)$leagueId; ?>" style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="create_canon">
        <input type="hidden" name="league_id" value="<?php echo (int)$leagueId; ?>">
        <input type="text" name="display_name" placeholder="Nuovo canon (es. Juventus)" required>
        <button class="btn" type="submit">Crea</button>
      </form>

      <div class="list">
        <table>
          <thead><tr><th>ID</th><th>Nome</th><th>Azioni</th></tr></thead>
          <tbody>
            <?php if (!$canon): ?>
              <tr><td colspan="3" class="muted">Nessun canon creato per questa lega.</td></tr>
            <?php else: foreach ($canon as $c): ?>
              <tr>
                <td>#<?php echo (int)$c['canon_team_id']; ?></td>
                <td><?php echo htmlspecialchars($c['display_name']); ?></td>
                <td style="white-space:nowrap;display:flex;gap:6px;">
                  <form method="post" action="/admin/squadre_lega.php?league_id=<?php echo (int)$leagueId; ?>" style="display:inline-flex;gap:6px;align-items:center;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="rename_canon">
                    <input type="hidden" name="canon_team_id" value="<?php echo (int)$c['canon_team_id']; ?>">
                    <input type="text" name="display_name" placeholder="Nuovo nome">
                    <button class="btn" type="submit">Rinomina</button>
                  </form>
                  <form method="post" action="/admin/squadre_lega.php?league_id=<?php echo (int)$leagueId; ?>" onsubmit="return confirm('Eliminare questo canon? Solo se non ha alias mappati.');" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="delete_canon">
                    <input type="hidden" name="canon_team_id" value="<?php echo (int)$c['canon_team_id']; ?>">
                    <button class="btn" type="submit" style="background:#e62329;border-color:#e62329;">Elimina</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Alias -->
    <section class="card">
      <h3 style="margin:0 0 10px;">Alias rilevati negli eventi — Lega <?php echo (int)$leagueId; ?></h3>
      <div class="list">
        <table>
          <thead><tr><th>team_id</th><th>Nome evento</th><th>Mappato su</th><th>Azioni</th></tr></thead>
          <tbody>
            <?php if (!$aliasUniq): ?>
              <tr><td colspan="4" class="muted">Nessun alias rilevato dai tornei di questa lega.</td></tr>
            <?php else: foreach ($aliasUniq as $tid => $name): ?>
              <tr>
                <td><?php echo (int)$tid; ?></td>
                <td><?php echo htmlspecialchars($name); ?></td>
                <td>
                  <?php
                    $cId = $map[$tid] ?? null;
                    echo $cId ? ('#'.$cId.' — '.htmlspecialchars($canonIndex[$cId] ?? '—')) : '<span class="muted">—</span>';
                  ?>
                </td>
                <td style="white-space:nowrap;">
                  <form method="post" action="/admin/squadre_lega.php?league_id=<?php echo (int)$leagueId; ?>" style="display:inline-flex;gap:6px;align-items:center;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="map_alias">
                    <input type="hidden" name="league_id" value="<?php echo (int)$leagueId; ?>">
                    <input type="hidden" name="team_id" value="<?php echo (int)$tid; ?>">
                    <select name="canon_team_id" required>
                      <option value="">— scegli canon —</option>
                      <?php foreach ($canon as $c): ?>
                        <option value="<?php echo (int)$c['canon_team_id']; ?>" <?php echo ($cId===(int)$c['canon_team_id']?'selected':''); ?>>
                          #<?php echo (int)$c['canon_team_id']; ?> — <?php echo htmlspecialchars($c['display_name']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn" type="submit">Applica</button>
                  </form>
                  <?php if ($cId): ?>
                  <form method="post" action="/admin/squadre_lega.php?league_id=<?php echo (int)$leagueId; ?>" style="display:inline;margin-left:6px;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="unmap_alias">
                    <input type="hidden" name="league_id" value="<?php echo (int)$leagueId; ?>">
                    <input type="hidden" name="team_id" value="<?php echo (int)$tid; ?>">
                    <button class="btn" type="submit">Scollega</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
  <?php endif; ?>

  <div style="margin-top:14px;">
    <a class="btn" href="/admin/gestisci_tornei.php">← Torna a Gestisci Tornei</a>
  </div>
</div>
</body>
</html>
