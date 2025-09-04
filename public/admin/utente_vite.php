<?php
// =====================================================================
// /admin/utente_vite.php — Gestione vite per utente (per torneo)
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$tournamentId = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$torneos = [];
$rows = [];
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

try {
  $q = $pdo->query("SELECT id, name, current_round_no FROM tournaments ORDER BY id DESC");
  $torneos = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $torneos = []; }

if ($tournamentId>0) {
  $st = $pdo->prepare("
    SELECT te.user_id, te.lives, u.username
    FROM tournament_enrollments te
    LEFT JOIN utenti u ON u.id = te.user_id
    WHERE te.tournament_id = ?
    ORDER BY te.user_id ASC
  ");
  $st->execute([$tournamentId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Gestione vite per utente</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1280px;margin:24px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px}
    .hstack{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .spacer{flex:1 1 auto}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;font-weight:800;background:#202326;text-decoration:none;cursor:pointer}
    .btn:hover{border-color:#fff}
    .btn-ok{background:#00c074;border-color:#00c074}
    .btn-danger{background:#e62329;border-color:#e62329}
    .tbl{width:100%;border-collapse:separate;border-spacing:0 8px}
    .tbl th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:#c9c9c9;text-align:left;padding:8px 10px;white-space:nowrap}
    .tbl td{background:#111;border:1px solid rgba(255,255,255,.12);padding:8px 10px;vertical-align:middle}
    .muted{color:#bdbdbd}
    input[type=number], input[type=text]{background:#0a0a0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;height:34px;padding:0 10px}
    .flash{padding:10px 12px;border-radius:8px;margin-bottom:12px}
    .ok{background:rgba(0,192,116,.1);border:1px solid rgba(0,192,116,.4);color:#00c074}
    .err{background:rgba(230,35,41,.1);border:1px solid rgba(230,35,41,.4);color:#ff7076}
  </style>
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="wrap">
  <div class="hstack">
    <h1 style="margin:0">Gestione vite per utente</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/gestisci_tornei.php">Torna a Gestisci Tornei</a>
  </div>

  <?php if ($flash): ?>
    <div class="flash <?php echo (stripos($flash,'errore')!==false?'err':'ok'); ?>"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>

  <section class="card">
    <form class="hstack" method="get" action="/admin/utente_vite.php">
      <label class="muted">Torneo</label>
      <select name="tournament_id" style="background:#0a0a0b;color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;height:36px;padding:0 10px;">
        <option value="0">— seleziona —</option>
        <?php foreach ($torneos as $t): ?>
          <option value="<?php echo (int)$t['id']; ?>" <?php echo ($tournamentId===(int)$t['id']?'selected':''); ?>>
            <?php echo '#'.(int)$t['id'].' — '.htmlspecialchars($t['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Apri</button>
    </form>
  </section>

  <?php if ($tournamentId>0): ?>
    <section class="card">
      <h2 style="margin:0 0 8px">Iscritti torneo #<?php echo (int)$tournamentId; ?></h2>
      <?php if (!$rows): ?>
        <p class="muted">Nessun iscritto.</p>
      <?php else: ?>
        <table class="tbl">
          <thead>
            <tr>
              <th>User ID</th>
              <th>Username</th>
              <th>Vite attuali</th>
              <th>Imposta vite</th>
              <th>Δ (+/-)</th>
              <th>Motivo</th>
              <th>OK</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php $formId = 'lf_' . (int)$tournamentId . '_' . (int)$r['user_id']; ?>
              <tr>
                <td><?php echo (int)$r['user_id']; ?></td>
                <td><?php echo htmlspecialchars($r['username'] ?? ''); ?></td>
                <td><?php echo (int)$r['lives']; ?></td>

                <!-- Colonna "Imposta vite": creo il form e lo chiudo nella stessa cella -->
                <td>
                  <form id="<?php echo $formId; ?>" class="hstack" method="post" action="/admin/utente_vite_apply.php">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="tournament_id" value="<?php echo (int)$tournamentId; ?>">
                    <input type="hidden" name="user_id" value="<?php echo (int)$r['user_id']; ?>">
                    <input type="number" name="set_lives" min="0" step="1" placeholder="es. 2" style="width:90px">
                  </form>
                </td>

                <!-- Queste celle usano l'attributo form="ID" per appartenere al form sopra -->
                <td>
                  <input type="number" name="delta" step="1" placeholder="+1 o -1" style="width:90px"
                         form="<?php echo $formId; ?>">
                </td>
                <td>
                  <input type="text" name="reason" placeholder="Motivo" style="width:240px"
                         form="<?php echo $formId; ?>">
                </td>
                <td>
                  <button class="btn btn-ok" type="submit" form="<?php echo $formId; ?>">Salva</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="muted" style="margin-top:8px">
          Se imposti un valore in “Imposta vite” viene ignorato “Δ”. Se lasci “Imposta vite” vuoto, viene applicato il delta (+/-).
        </p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>

</body>
</html>
