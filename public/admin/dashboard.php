<?php
// =======================
// admin/dashboard.php
// =======================

// [RIGA] Importa le "guardie" salendo di UN livello: /admin -> / (webroot) -> /src
require_once __DIR__ . '/../src/guards.php';   // (non ../../) ora punta a /var/www/html/src/guards.php
require_admin();                                // [RIGA] Consente accesso solo ad admin loggati

// [RIGA] DB e config: anche questi con ../ perché i file stanno in /src
require_once __DIR__ . '/../src/config.php';    // costanti app/env
require_once __DIR__ . '/../src/db.php';        // $pdo connessione

// [RIGA] Esempio di dato per la dashboard: numero utenti totali
$tot_utenti = (int)$pdo->query("SELECT COUNT(*) FROM utenti")->fetchColumn();

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Dashboard</title>

  <!-- [RIGA] CSS globali e header admin -->
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">

  <style>
    /* [RIGA] Stile minimo per layout dashboard */
    .admin-wrap { max-width:1280px; margin:24px auto; padding:0 20px; color:#fff; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; }
    .card { background:#111; border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:16px; }
    .card h3 { margin:0 0 6px; font-size:16px; font-weight:800; }
    .card .num { font-size:28px; font-weight:900; }
    .actions a { display:inline-block; margin-right:10px; color:#fff; border:1px solid rgba(255,255,255,.25); padding:8px 12px; border-radius:8px; text-decoration:none; }
    .actions a:hover { border-color:#fff; }
  </style>
</head>
<body>

<?php
// [RIGA] Include header admin: anche questo salendo di UN livello
require __DIR__ . '/../header_admin.php';
?>

<main class="admin-wrap">
  <h1 style="margin:0 0 16px;">Dashboard Admin</h1>

  <section class="grid">
    <div class="card">
      <h3>Utenti totali</h3>
      <div class="num"><?php echo $tot_utenti; ?></div>
    </div>
  </section>

  <section style="margin-top:20px;">
    <div class="card">
      <h3>Azioni rapide</h3>
      <div class="actions">
        <a href="/admin/utenti.php">Gestisci utenti</a>
        <a href="/admin/movimenti.php">Movimenti</a>
        <a href="/admin/tornei.php">Tornei</a>
      </div>
    </div>
  </section>
</main>

<?php
// [RIGA] Include footer unico: anche questo con ../
require __DIR__ . '/../footer.php';
?>

</body>
</html>
