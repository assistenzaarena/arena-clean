<?php
// ==========================================================
// admin/dashboard.php — Pannello gestione utenti stile "griglia"
// Mostra: nome, cognome, username, email, telefono, attivo, saldo (editabile),
// reset password, pulsante “Applica modifiche” e link "Lista movimenti".
// Include: ricerca, ordinamento e paginazione base.
// ==========================================================

// [RIGA] Importiamo le guardie (solo admin) — path corretto salendo di UN livello da /admin
require_once __DIR__ . '/../src/guards.php';         // include funzioni require_login/require_admin
require_admin();                                      // blocca chi non è admin

// [RIGA] DB & config
require_once __DIR__ . '/../src/config.php';         // costanti APP_ENV, ecc.
require_once __DIR__ . '/../src/db.php';             // $pdo (PDO con prepared reali)

// ------------------------
// Protezione basilare CSRF
// ------------------------
// [RIGA] Creiamo un token CSRF in sessione per i POST (per evitare modifiche cross-site)
if (empty($_SESSION['csrf'])) {                       // se non esiste ancora un token…
    $_SESSION['csrf'] = bin2hex(random_bytes(16));    // generiamo 32 hex chars (128 bit)
}
$csrf = $_SESSION['csrf'];                            // copiamo il token da usare nei form

// ------------------------
// Gestione aggiornamenti riga (POST)
// ------------------------
$flash = null;                                        // messaggi flash informativi
$errors = [];                                         // lista errori operativi

if ($_SERVER['REQUEST_METHOD'] === 'POST') {          // se arriva un POST (clic su "Applica modifiche")
    // [RIGA] Verifica CSRF
    $posted_csrf = $_POST['csrf'] ?? '';              // token inviato nel form
    if (!hash_equals($_SESSION['csrf'], $posted_csrf)) { // confronto costante
        http_response_code(400);                      // richiesta non valida
        die('CSRF non valido');                       // blocchiamo
    }

    // [RIGA] Prendiamo l’azione (per estensioni future) — qui gestiamo "update_user"
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);         // id utente da modificare (hidden nel form)

    if ($action === 'update_user' && $user_id > 0) {  // se è un update e abbiamo un id valido…
        // [RIGA] Prendiamo i campi modificabili
        $nome      = trim($_POST['nome'] ?? '');      // nome (può essere vuoto)
        $cognome   = trim($_POST['cognome'] ?? '');   // cognome (può essere vuoto)
        $email     = trim($_POST['email'] ?? '');     // email (validazione base sotto)
        $phone     = trim($_POST['phone'] ?? '');     // telefono (validazione base sotto)
        $is_active = isset($_POST['is_active']) ? 1 : 0; // checkbox on/off → 1/0
        $saldo     = $_POST['crediti'] ?? '';         // saldo attuale (string, lo validiamo a numero)
        $new_pass  = $_POST['new_password'] ?? '';    // nuovo valore password (se vogliamo resettarla)
    // ------------------------------------------
// NUOVO RAMO: toggle stato attivo/inattivo
// ------------------------------------------
if ($action === 'toggle_active' && $user_id > 0) {               // [RIGA] Se è una richiesta di toggle
    $new_state = (int)($_POST['new_state'] ?? 0);                // [RIGA] Nuovo stato richiesto: 1=attivo, 0=disattivo
    $up = $pdo->prepare("UPDATE utenti SET is_active = :a WHERE id = :id"); // [RIGA] Update solo del flag
    $up->execute([':a' => $new_state, ':id' => $user_id]);       // [RIGA] Esecuzione
    $flash = $new_state ? 'Utente attivato.' : 'Utente disattivato.'; // [RIGA] Messaggio di conferma
}                                           

        // [RIGA] Validazioni semplici (puoi rafforzarle se vuoi)
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {      // email formale
            $errors[] = 'Email non valida.';
        }
        if ($phone !== '' && !preg_match('/^\+?[0-9\- ]{7,20}$/', $phone)) {    // telefono semplice
            $errors[] = 'Numero di telefono non valido.';
        }
        if ($saldo === '' || !is_numeric($saldo)) {                             // saldo deve essere numero
            $errors[] = 'Saldo non valido.';
        }
        // [RIGA] Se impostiamo una nuova password, applichiamo policy minima
        if ($new_pass !== '') {
            if (strlen($new_pass) < 8) { $errors[] = 'La nuova password deve avere almeno 8 caratteri.'; }
        }

        if (!$errors) {                                                         // se non ci sono errori…
            // [RIGA] Prepariamo UPDATE dinamico in base a cosa è stato cambiato
            // Costruiamo SET con soli campi rilevanti; password la gestiamo a parte
            $sql = "UPDATE utenti SET nome = :nome, cognome = :cognome, email = :email, phone = :phone, is_active = :active, crediti = :crediti WHERE id = :id";
            $params = [
                ':nome'    => $nome,
                ':cognome' => $cognome,
                ':email'   => $email,
                ':phone'   => $phone,
                ':active'  => $is_active,
                ':crediti' => (float)$saldo,                                     // cast a float
                ':id'      => $user_id,
            ];

            // [RIGA] Se è stato immesso un reset password → generiamo hash
            if ($new_pass !== '') {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);             // hash sicuro
                $sql = "UPDATE utenti SET nome = :nome, cognome = :cognome, email = :email, phone = :phone, is_active = :active, crediti = :crediti, password_hash = :hash WHERE id = :id";
                $params[':hash'] = $hash;                                       // aggiungiamo param
            }

            // [RIGA] Eseguiamo l’UPDATE
            $up = $pdo->prepare($sql);                                         // prepared
            $up->execute($params);                                             // esecuzione

            $flash = 'Modifiche salvate.';                                     // messaggio di conferma
        }
    }
}

// ------------------------
// Ricerca / Ordinamento / Paginazione (GET)
// ------------------------

// [RIGA] Ricerca libera su nome/cognome/username/email/phone
$q = trim($_GET['q'] ?? '');                                                   // stringa di ricerca (può essere vuota)

// [RIGA] Ordinamento: whitelist per evitare SQL injection
$sort = $_GET['sort'] ?? 'cognome';                                            // campo di default
$dir  = $_GET['dir']  ?? 'asc';                                                // direzione di default

$allowedSort = ['cognome','nome','username','email','phone','crediti'];        // campi permessi
$allowedDir  = ['asc','desc'];                                                 // direzioni permesse

if (!in_array($sort, $allowedSort, true)) { $sort = 'cognome'; }               // fallback
if (!in_array(strtolower($dir), $allowedDir, true)) { $dir = 'asc'; }          // fallback

// [RIGA] Paginazione base
$page = max(1, (int)($_GET['page'] ?? 1));                                     // pagina >= 1
$perPage = 10;                                                                  // 10 righe per pagina
$offset = ($page - 1) * $perPage;                                               // offset SQL

// ------------------------
// Costruzione query lista utenti (sicura)
// ------------------------
$where = "1=1";                                                                 // base: nessun filtro
$params = [];                                                                    // parametri bind

if ($q !== '') {                                                                // se c’è ricerca, aggiungiamo filtro LIKE su più campi
    $where .= " AND (nome LIKE :q OR cognome LIKE :q OR username LIKE :q OR email LIKE :q OR phone LIKE :q)";
    $params[':q'] = '%' . $q . '%';                                             // pattern
}

// [RIGA] Conteggio totale per paginazione
$countSql = "SELECT COUNT(*) FROM utenti WHERE $where";
$total = (int)$pdo->prepare($countSql)->execute($params) ? (int)$pdo->prepare($countSql)->fetchColumn() : 0;

// [RIGA] Lista utenti con ordinamento e paginazione — NOTA: sort/dir sono whitelisted sopra
// DOPO (aggiungo verified_at)
$listSql = "SELECT id, nome, cognome, username, email, phone, crediti, is_active, verified_at
            FROM utenti
            WHERE $where
            ORDER BY $sort " . strtoupper($dir) . "
            LIMIT :lim OFFSET :off";
$listStmt = $pdo->prepare($listSql);                                           // prepared
foreach ($params as $k=>$v) { $listStmt->bindValue($k, $v, PDO::PARAM_STR); }  // bind per ricerca
$listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);                        // bind LIMIT
$listStmt->bindValue(':off', $offset,  PDO::PARAM_INT);                        // bind OFFSET
$listStmt->execute();                                                          // esecuzione
$users = $listStmt->fetchAll();                                                // array utenti

// [RIGA] Totale utenti per il badge in alto
$tot_utenti = (int)$pdo->query("SELECT COUNT(*) FROM utenti")->fetchColumn();  // numero totale utenti
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Dashboard</title>

  <!-- [RIGA] CSS condivisi -->
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">

  <style>
    /* [RIGA] Stili minimi per la tabella tipo “console” */
    .admin-wrap { max-width: 1280px; margin: 24px auto; padding: 0 20px; color:#fff; }
    .kpi { background:#111; border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:14px 16px; margin-bottom:16px; display:inline-block; }
    .filters { display:flex; gap:12px; align-items:center; margin: 12px 0; }
    .filters input[type=text]{ height:36px; border-radius:8px; border:1px solid rgba(255,255,255,.25); background:#0a0a0b; color:#fff; padding:0 10px; }
    .filters select{ height:36px; border-radius:8px; border:1px solid rgba(255,255,255,.25); background:#0a0a0b; color:#fff; }
    table { width:100%; border-collapse: separate; border-spacing: 0 8px; }
    thead th { text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:.03em; color:#c9c9c9; }
    tbody tr { background:#111; border:1px solid rgba(255,255,255,.08); }
    tbody td { padding:10px; vertical-align:middle; }
    tbody td input[type=text], tbody td input[type=number], tbody td input[type=email], tbody td input[type=tel], tbody td input[type=password] {
      width:100%; height:34px; border-radius:8px; border:1px solid rgba(255,255,255,.2); background:#0a0a0b; color:#fff; padding:0 10px;
    }
    .pill { display:inline-flex; align-items:center; justify-content:center; height:28px; padding:0 10px; border-radius:9999px; font-size:12px; font-weight:800; }
    .pill-on { background:#00c074; color:#0b1; }
    .pill-off{ background:#333; color:#bbb; }
    .btn { display:inline-flex; align-items:center; justify-content:center; height:32px; padding:0 12px; border:1px solid rgba(255,255,255,.25); border-radius:8px; color:#fff; text-decoration:none; font-weight:800; }
    .btn:hover { border-color:#fff; }
    .btn-apply { background:#e62329; border-color:#e62329; }
    .btn-apply:hover { background:#c61e28; border-color:#c61e28; }
    .actions { display:flex; gap:8px; }
    .pag { margin-top: 12px; display:flex; gap:6px; }
    .pag a { color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,.25); padding:4px 8px; border-radius:6px; }
    .pag .on { background:#fff; color:#000; }
    .err { color:#ff6b6b; margin-bottom:8px; }
    .flash { color:#00d07e; margin-bottom:8px; }
  </style>
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?> <!-- header admin -->

<main class="admin-wrap">
  <!-- KPI: numero utenti -->
  <div class="kpi"><strong>Utenti totali:</strong> <?php echo $tot_utenti; ?></div>

  <!-- Messaggi -->
  <?php if ($flash): ?><div class="flash"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="err"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div><?php endif; ?>

  <!-- Filtro / Ricerca / Ordinamento -->
  <form class="filters" method="get" action="/admin/dashboard.php">
    <input type="text" name="q" placeholder="Cerca (nome, cognome, user, email, telefono)" value="<?php echo htmlspecialchars($q); ?>"><!-- ricerca -->
    <label>Ordina per:
      <select name="sort">
        <option value="cognome"  <?php if($sort==='cognome') echo 'selected'; ?>>Cognome</option>
        <option value="nome"     <?php if($sort==='nome') echo 'selected'; ?>>Nome</option>
        <option value="username" <?php if($sort==='username') echo 'selected'; ?>>Username</option>
        <option value="email"    <?php if($sort==='email') echo 'selected'; ?>>Email</option>
        <option value="phone"    <?php if($sort==='phone') echo 'selected'; ?>>Telefono</option>
        <option value="crediti"  <?php if($sort==='crediti') echo 'selected'; ?>>Saldo</option>
      </select>
    </label>
    <label>Direzione:
      <select name="dir">
        <option value="asc"  <?php if($dir==='asc')  echo 'selected'; ?>>Asc</option>
        <option value="desc" <?php if($dir==='desc') echo 'selected'; ?>>Desc</option>
      </select>
    </label>
    <button class="btn" type="submit">Applica filtri</button>
  </form>

  <!-- Tabella utenti -->
  <table>
    <thead>
      <tr>
        <th>Nome</th>
        <th>Cognome</th>
        <th>User</th>
        <th>Email</th>
        <th>Telefono</th>
        <th>Attivo</th>
        <th style="width:120px;">Saldo €</th>
        <th style="width:160px;">Nuova password</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <!-- Ogni riga è un form indipendente: così "Applica modifiche" agisce solo su quel record -->
        <form method="post" action="/admin/dashboard.php?page=<?php echo (int)$page; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&q=<?php echo urlencode($q); ?>">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><!-- CSRF token -->
          <input type="hidden" name="action" value="update_user"><!-- azione -->
          <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>"><!-- id riga -->

          <td><input type="text" name="nome" value="<?php echo htmlspecialchars($u['nome'] ?? ''); ?>"></td><!-- nome editabile -->
          <td><input type="text" name="cognome" value="<?php echo htmlspecialchars($u['cognome'] ?? ''); ?>"></td><!-- cognome editabile -->
          <td><?php echo htmlspecialchars($u['username']); ?></td><!-- username non editabile -->
          <td><input type="email" name="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"></td><!-- email editabile -->
          <td><input type="tel" name="phone" value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>"></td><!-- telefono editabile -->

      <?php
// Stato effettivo: attivo SOLO se is_active=1 E verified_at NON è NULL
$eff_active = ((int)$u['is_active'] === 1) && !is_null($u['verified_at']);
$state_text  = $eff_active ? 'Attivo' : 'Inattivo';
$state_class = $eff_active ? 'btn-state on' : 'btn-state off';
$next_state  = $eff_active ? 0 : 1;
?>
<td>
  <form method="post" action="/admin/dashboard.php?page=<?php echo (int)$page; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&q=<?php echo urlencode($q); ?>" style="display:inline">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="action" value="toggle_active">
    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
    <input type="hidden" name="new_state" value="<?php echo $next_state; ?>">
    <button type="submit" class="<?php echo $state_class; ?>"><?php echo $state_text; ?></button>
  </form>
  <?php if (is_null($u['verified_at'])): ?>
    <div style="font-size:11px;color:#ff9090;margin-top:4px;">Email non verificata</div>
  <?php endif; ?>
</td>

          <td><input type="number" step="0.01" name="crediti" value="<?php echo htmlspecialchars((string)$u['crediti']); ?>"></td><!-- saldo editabile -->

          <td><input type="password" name="new_password" placeholder="Reset (opzionale)"></td><!-- reset password -->

          <td class="actions">
            <a class="btn" href="/admin/movimenti.php?user_id=<?php echo (int)$u['id']; ?>">Movimenti</a><!-- link lista movimenti -->
            <button class="btn btn-apply" type="submit">Applica modifiche</button><!-- applica SOLO questa riga -->
          </td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Paginazione semplice -->
  <div class="pag">
    <?php
      $pages = max(1, (int)ceil($total / $perPage));                       // numero pagine
      for ($p=1; $p<=$pages; $p++):
        $cls = ($p===$page) ? 'on' : '';
        $url = '/admin/dashboard.php?page='.$p.'&sort='.urlencode($sort).'&dir='.urlencode($dir).'&q='.urlencode($q);
    ?>
      <a class="<?php echo $cls; ?>" href="<?php echo $url; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>
  </div>

</main>

<?php require __DIR__ . '/../footer.php'; ?> <!-- footer -->

</body>
</html>
