<?php
/**
 * public/admin/fixtures_preview.php
 *
 * SCOPO: Pagina ADMIN di sola preview per verificare che competizione + stagione +
 *        giornata/round restituiscano l’elenco completo dei fixtures attesi.
 *        (Nessuna scrittura DB in questo step.)
 *
 * USO:
 *   /admin/fixtures_preview.php?comp=serie_a&season=2024/2025&matchday=10
 *   /admin/fixtures_preview.php?comp=ucl&season=2024/2025&round_label=Round of 16
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = dirname(__DIR__); // /var/www/html
require_once $ROOT . '/src/guards.php';  require_admin();
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
$competitions = require $ROOT . '/src/config/competitions.php';
require_once $ROOT . '/src/services/football_api.php';

$comp_key   = $_GET['comp'] ?? '';
$season     = trim($_GET['season'] ?? '');
$matchday   = $_GET['matchday'] ?? '';
$round_lbl  = trim($_GET['round_label'] ?? '');

$err = null; $rows = []; $header = []; $expected = null;

/* ------------------------------------------------------------------
   Adattatore retro-compatibilità:
   Se 'comp' manca ma arriva un 'tournament_id' (e opzionalmente 'round_no'
   o 'round'), ricaviamo comp/season e il round corretto dal DB tornei.
   ------------------------------------------------------------------ */
if (($comp_key === '' || !isset($competitions[$comp_key])) && isset($_GET['tournament_id'])) {
    $tid     = (int)($_GET['tournament_id'] ?? 0);
    // supporta sia ?round_no= che ?round=
    $roundNo = (int)($_GET['round_no'] ?? ($_GET['round'] ?? 0));

    // leggo info torneo
    $st = $pdo->prepare("SELECT league_id, season, current_round_no FROM tournaments WHERE id=? LIMIT 1");
    $st->execute([$tid]);
    if ($T = $st->fetch(PDO::FETCH_ASSOC)) {

        // mappa league_id -> chiave competizione ($comp_key)
        foreach ($competitions as $k => $c) {
            if ((int)$c['league_id'] === (int)$T['league_id']) { $comp_key = $k; break; }
        }

        // stagione: preferisci quella salvata sul torneo
        if ($season === '' && !empty($T['season'])) {
            $season = trim((string)$T['season']);
        }
        if ($season === '' && $comp_key && isset($competitions[$comp_key]['default_season'])) {
            $season = $competitions[$comp_key]['default_season'];
        }

        // round: se non passato, usa il current_round_no del torneo
        if ($roundNo <= 0) { $roundNo = (int)($T['current_round_no'] ?? 1); }

        // imposta il parametro giusto in base al tipo di round della competizione
        $roundType = $competitions[$comp_key]['round_type'] ?? 'matchday';
        if ($roundType === 'matchday') {
            if ($matchday === '' || !ctype_digit((string)$matchday)) {
                $matchday = (string)$roundNo;
            }
            // assicurati che round_label non interferisca
            $round_lbl = '';
        } else {
            if ($round_lbl === '') {
                $tpl = $competitions[$comp_key]['round_label_template'] ?? 'Round %d';
                $round_lbl = str_replace('%d', (string)$roundNo, $tpl);
            }
            // nessuna giornata numerica in questo ramo
            $matchday = '';
        }
    }
}
/* ---------------------------- fine PATCH A ---------------------------- */

// Validazioni input minime
if ($comp_key === '' || !isset($competitions[$comp_key])) {
    $err = 'Competizione mancante o non valida (param "comp").';
} elseif ($season === '') {
    $err = 'Stagione mancante (param "season").';
} else {
    $comp = $competitions[$comp_key];
    $header = [
        'competition' => $comp['name'],
        'league_id'   => $comp['league_id'],
        'round_type'  => $comp['round_type'],
        'season'      => $season,
    ];

    if ($comp['round_type'] === 'matchday') {
        if ($matchday === '' || !ctype_digit((string)$matchday) || (int)$matchday < 1) {
            $err = 'Giornata mancante o non valida (param "matchday").';
        } else {
            $expected = $comp['expected_matches_per_matchday'] ?? null;
            // Nota: round label pattern più diffuso
            $resp = fb_fixtures_matchday((int)$comp['league_id'], $season, (int)$matchday, 'Regular Season - %d');
            if (!$resp['ok']) {
                $err = 'Errore fetch: '.$resp['error'].' (HTTP '.$resp['status'].')';
            } else {
                $rows = fb_extract_fixtures_minimal($resp['data']);
            }
        }
    } else { // round_label
        if ($round_lbl === '') {
            $err = 'Round label mancante (param "round_label").';
        } else {
            $resp = fb_fixtures_round_label((int)$comp['league_id'], $season, $round_lbl);
            if (!$resp['ok']) {
                $err = 'Errore fetch: '.$resp['error'].' (HTTP '.$resp['status'].')';
            } else {
                $rows = fb_extract_fixtures_minimal($resp['data']);
            }
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Preview Fixtures</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width: 1100px; margin: 20px auto; padding: 0 16px; color:#fff;}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .meta{font-size:14px;color:#c9c9c9}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{text-align:left;color:#c9c9c9;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
    .ok{color:#00d07e}.ko{color:#ff6b6b}
    .hint{color:#aaa;font-size:12px}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;text-decoration:none;font-weight:800}
    .btn:hover{border-color:#fff}
  </style>
</head>
<body>

<?php require $ROOT . '/header_admin.php'; ?>

<div class="wrap">
  <h1>Preview fixtures</h1>

  <?php if ($err): ?>
    <div class="card ko"><?php echo htmlspecialchars($err); ?></div>
  <?php else: ?>
    <div class="card">
      <div class="meta">
        <div><b>Competizione:</b> <?php echo htmlspecialchars($header['competition']); ?> (ID: <?php echo (int)$header['league_id']; ?>)</div>
        <div><b>Stagione:</b> <?php echo htmlspecialchars($header['season']); ?></div>
        <div><b>Round type:</b> <?php echo htmlspecialchars($header['round_type']); ?></div>
        <?php if ($header['round_type']==='matchday'): ?>
          <div><b>Giornata:</b> <?php echo (int)$matchday; ?></div>
        <?php else: ?>
          <div><b>Round label:</b> <?php echo htmlspecialchars($round_lbl); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div style="margin-bottom:8px;">
        <?php
          $count = count($rows);
          if ($header['round_type']==='matchday' && $expected !== null) {
              echo ($count === (int)$expected)
                  ? '<span class="ok">Completezza OK</span> — '.$count.' partite su '.$expected
                  : '<span class="ko">Incomplete</span> — '.$count.' partite su '.$expected;
          } else {
              echo $count.' partite trovate.';
          }
        ?>
      </div>

      <?php if ($rows): ?>
        <table>
          <thead>
            <tr>
              <th>Fixture ID</th>
              <th>Data</th>
              <th>Casa</th>
              <th>Trasferta</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo htmlspecialchars((string)$r['fixture_id']); ?></td>
                <td><?php echo htmlspecialchars($r['date']); ?></td>
                <td><?php echo htmlspecialchars($r['home_name']); ?></td>
                <td><?php echo htmlspecialchars($r['away_name']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="hint">Nessuna partita trovata per i parametri indicati.</div>
      <?php endif; ?>
    </div>

   <?php
// link di ritorno al ricalcolo ultimo round, se mi hai passato un tournament_id
$backToRecalc = '';
if (!empty($_GET['tournament_id'])) {
  $backToRecalc = '/admin/round_ricalcolo.php?tournament_id='.(int)$_GET['tournament_id'];
  // se vuoi, conserva il round selezionato
  if (!empty($_GET['round'])) {
    $backToRecalc .= '&round='.(int)$_GET['round'];
  } elseif (!empty($_GET['round_no'])) {
    $backToRecalc .= '&round='.(int)$_GET['round_no'];
  }
}
?>
<?php if ($backToRecalc): ?>
  <a class="btn" href="<?php echo $backToRecalc; ?>">← Torna al ricalcolo</a>
<?php endif; ?>
   
    <a class="btn" href="/admin/crea_torneo.php">← Torna a Crea Tornei</a>
  <?php endif; ?>
</div>

</body>
</html>
