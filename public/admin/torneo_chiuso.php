<?php
// /admin/torneo_chiuso.php — Dettaglio di un torneo chiuso (KPI + per-utente + scelte)
require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); die('ID torneo mancante'); }

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// Dati torneo
$st = $pdo->prepare("SELECT * FROM tournaments WHERE id=:id LIMIT 1");
$st->execute([':id'=>$id]);
$t = $st->fetch(PDO::FETCH_ASSOC);
if (!$t) { http_response_code(404); die('Torneo non trovato'); }

// KPI base
$enr = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(lives),0) AS lives FROM tournament_enrollments WHERE tournament_id=:t");
$enr->execute([':t'=>$id]);
$agg = $enr->fetch(PDO::FETCH_ASSOC);
$participants = (int)($agg['n'] ?? 0);
$lives        = (int)($agg['lives'] ?? 0);

$buyin = (float)($t['cost_per_life'] ?? 0);
$pp    = (int)($t['prize_percent'] ?? 100);
$g     = (float)($t['guaranteed_prize'] ?? 0);
$gross = $lives * $buyin;
$pot   = max($g, $gross * ($pp / 100));

// Numero round effettivi selezionati
$rmax = $pdo->prepare("SELECT COALESCE(MAX(round_no),1) FROM tournament_selections WHERE tournament_id=:t");
$rmax->execute([':t'=>$id]);
$rounds = max(1, (int)$rmax->fetchColumn());

// Utenti iscritti
$usr = $pdo->prepare("
  SELECT e.user_id, e.lives,
         u.username, u.nome, u.cognome, u.email, u.phone
  FROM tournament_enrollments e
  JOIN utenti u ON u.id = e.user_id
  WHERE e.tournament_id = :t
  ORDER BY u.cognome, u.nome, u.username
");
$usr->execute([':t'=>$id]);
$users = $usr->fetchAll(PDO::FETCH_ASSOC);

// Selezioni (per comporre la griglia scelte round x vita)
$sel = $pdo->prepare("
  SELECT s.user_id, s.round_no, s.life_index, s.team_id, s.event_id,
         ev.home_team_id, ev.away_team_id, ev.home_team_name, ev.away_team_name
  FROM tournament_selections s
  LEFT JOIN tournament_events ev ON ev.id = s.event_id
  WHERE s.tournament_id = :t
  ORDER BY s.user_id, s.round_no, s.life_index
");
$sel->execute([':t'=>$id]);
$rows = $sel->fetchAll(PDO::FETCH_ASSOC);

// Indicizzazione selezioni: picks[user_id][life_index][round_no] = nome squadra
$picks = [];
foreach ($rows as $r) {
  $uid = (int)$r['user_id'];
  $li  = (int)$r['life_index'];
  $rn  = (int)$r['round_no'];
  $name = null;
  if (!empty($r['home_team_id']) && (int)$r['team_id'] === (int)$r['home_team_id']) $name = $r['home_team_name'];
  if (!empty($r['away_team_id']) && (int)$r['team_id'] === (int)$r['away_team_id']) $name = $r['away_team_name'];
  if ($name === null) $name = 'Team #'.(int)$r['team_id'];
  $picks[$uid][$li][$rn] = $name;
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Torneo chiuso</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/admin_extra.css">
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wide">
  <div class="hstack">
    <h1 style="margin:0;"><?php echo htmlspecialchars($t['name'] ?? 'Torneo'); ?></h1>
    <span class="badge gray">#<?php echo htmlspecialchars($t['tournament_code'] ?? sprintf('%05d',$id)); ?></span>
    <span class="spacer"></span>
    <a class="btn btn-ghost" href="/admin/tornei_chiusi.php">Torna all’elenco</a>
  </div>

  <!-- KPI -->
  <div class="grid four">
    <div class="card"><div class="kpi"><strong>Partecipanti:</strong> <?php echo $participants; ?></div></div>
    <div class="card"><div class="kpi"><strong>Vite vendute:</strong> <?php echo $lives; ?></div></div>
    <div class="card"><div class="kpi"><strong>Buy‑in:</strong> <?php echo number_format($gross,0,',','.'); ?></div></div>
    <div class="card"><div class="kpi"><strong>Montepremi:</strong> <?php echo number_format($pot,0,',','.'); ?></div></div>
  </div>

  <!-- Per-utente -->
  <div class="card">
    <h3 style="margin-top:0;">Partecipanti e scelte</h3>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Utente</th>
            <th>Contatti</th>
            <th class="num">Vite</th>
            <?php for ($rn=1; $rn<=$rounds; $rn++): ?>
              <th>Round <?php echo $rn; ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$users): ?>
            <tr><td colspan="<?php echo 3+$rounds; ?>" class="muted">Nessun partecipante.</td></tr>
          <?php else: foreach ($users as $u):
            $uid = (int)$u['user_id'];
            $L   = max(0,(int)$u['lives']);
          ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($u['username']); ?></strong><br>
                <span class="muted"><?php echo htmlspecialchars(trim(($u['cognome']??'').' '.($u['nome']??''))); ?></span>
              </td>
              <td class="muted">
                <?php echo htmlspecialchars($u['email'] ?? ''); ?><br>
                <?php echo htmlspecialchars($u['phone'] ?? ''); ?>
              </td>
              <td class="num"><?php echo $L; ?></td>

              <?php for ($rn=1; $rn<=$rounds; $rn++): ?>
                <td>
                  <?php
                    if ($L<=0) { echo '<span class="muted">—</span>'; continue; }
                    // mostro una lista vita→squadra
                    for ($li=0; $li<$L; $li++){
                      $nm = $picks[$uid][$li][$rn] ?? null;
                      echo '<div>'.($nm ? htmlspecialchars($nm) : '<span class="muted">—</span>').'</div>';
                    }
                  ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../footer.php'; ?>
</body>
</html>
