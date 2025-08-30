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

// [FIX FLASH] Se c'è un messaggio flash in sessione (post-redirect), lo recuperiamo e lo puliamo.
if (isset($_SESSION['flash'])) {                      // ← aggiunta minima per conservare il messaggio dopo il redirect
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

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

    // ------------------------------------------
    // NUOVO RAMO: toggle stato attivo/inattivo
    // ------------------------------------------
    // [SCOPO] Cambiare il flag is_active a 1 (attivo) o 0 (disattivo)
    // [SICUREZZA] Richiede csrf valido (già verificato nel tuo POST) e id > 0
    if ($action === 'toggle_active' && $user_id > 0) {
        $new_state = (int)($_POST['new_state'] ?? 0);        // 1 = attivo, 0 = disattivo

        // [SQL] Aggiorna il flag
        $up = $pdo->prepare("UPDATE utenti SET is_active = :a WHERE id = :id");
        $up->execute([':a' => $new_state, ':id' => $user_id]);

        // [UX] Messaggio flash
        $flash = $new_state ? 'Utente attivato.' : 'Utente disattivato.';

        // [PRG] Redirect per ricaricare la lista aggiornata e riflettere SUBITO il cambio di colore
        $_SESSION['flash'] = $flash; // conserva il messaggio dopo il redirect
        $query = http_build_query([
            'page' => (int)($_GET['page'] ?? 1),
            'sort' => $_GET['sort'] ?? 'cognome',
            'dir'  => $_GET['dir']  ?? 'asc',
            'q'    => $_GET['q']    ?? '',
        ]);
        header("Location: /admin/dashboard.php?$query");
        exit;
    }

    if ($action === 'update_user' && $user_id > 0) {  // se è un update e abbiamo un id valido…
        // [RIGA] Prendiamo i campi modificabili
        $nome      = trim($_POST['nome'] ?? '');      // nome (può essere vuoto)
        $cognome   = trim($_POST['cognome'] ?? '');   // cognome (può essere vuoto)
        $email     = trim($_POST['email'] ?? '');     // email (validazione base sotto)
        $phone     = trim($_POST['phone'] ?? '');     // telefono (validazione base sotto)
        $is_active = isset($_POST['is_active']) ? 1 : 0; // checkbox on/off → 1/0 (lasciata per compatibilità; non usata se hai solo bottone)
        $saldo     = $_POST['crediti'] ?? '';         // saldo attuale (string, lo validiamo a numero)
        $new_pass  = $_POST['new_password'] ?? '';    // nuovo valore password (se vogliamo resettarla)

        // (⚠️) PRIMA qui avevi di nuovo un ramo toggle_active duplicato.
        // FIX: rimosso il duplicato per evitare confusione. Il toggle viene gestito
        //      nel ramo dedicato *fuori* da update_user (vedi sopra).

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

            // [PRG] Redirect per ricaricare la lista aggiornata e riflettere SUBITO le modifiche
            $_SESSION['flash'] = $flash; // conserva il messaggio dopo il redirect
            $query = http_build_query([
                'page' => (int)($_GET['page'] ?? 1),
                'sort' => $_GET['sort'] ?? 'cognome',
                'dir'  => $_GET['dir']  ?? 'asc',
                'q'    => $_GET['q']    ?? '',
            ]);
            header("Location: /admin/dashboard.php?$query");
            exit;
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
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// [RIGA] Lista utenti con ordinamento e paginazione — NOTA: sort/dir sono whitelisted sopra
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
  <link rel="stylesheet" href="/assets/dashboard.css">
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
        <!-- Ogni riga è un form indipendente:
             - submit con name="action" value="toggle_active" per cambiare stato
             - submit con name="action" value="update_user" per salvare i campi -->
        <form method="post" action="/admin/dashboard.php?page=<?php echo (int)$page; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&q=<?php echo urlencode($q); ?>">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><!-- CSRF token -->
          <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>"><!-- id riga -->

          <td>
            <input type="text" name="nome"
                   value="<?php echo htmlspecialchars($u['nome'] ?? ''); ?>"><!-- nome editabile -->
          </td>

          <td>
            <input type="text" name="cognome"
                   value="<?php echo htmlspecialchars($u['cognome'] ?? ''); ?>"><!-- cognome editabile -->
          </td>

          <td>
            <?php echo htmlspecialchars($u['username']); ?><!-- username non editabile -->
          </td>

          <td>
            <input type="email" name="email"
                   value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"><!-- email editabile -->
          </td>

          <td>
            <input type="tel" name="phone"
                   value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>"><!-- telefono editabile -->
          </td>

          <?php
            // Stato effettivo:
            //  - "Attivo" SOLO se is_active=1 **e** verified_at NON è NULL (email verificata)
            //  - Altrimenti "Inattivo" (bottone rosso)
            $eff_active = ((int)$u['is_active'] === 1) && !is_null($u['verified_at']); // true/false
            $state_text  = $eff_active ? 'Attivo' : 'Inattivo';                        // label
            $state_class = $eff_active ? 'btn-state on' : 'btn-state off';             // classe CSS
            $next_state  = $eff_active ? 0 : 1;                                        // nuovo stato dopo click
            $reason      = $eff_active ? '' : (is_null($u['verified_at']) ? 'Email non verificata' : 'Disattivato'); // tooltip
          ?>
          <td>
            <!-- hidden con il nuovo stato da applicare al toggle -->
            <input type="hidden" name="new_state" value="<?php echo $next_state; ?>">
            <!-- Pulsante stato: submit con action specifica -->
            <button type="submit"
                    name="action" value="toggle_active"
                    class="<?php echo $state_class; ?>"
                    title="<?php echo htmlspecialchars($reason); ?>">
              <?php echo $state_text; ?>
            </button>
            <?php if (is_null($u['verified_at'])): ?>
              <div style="font-size:11px;color:#ff9090;margin-top:4px;">Email non verificata</div>
            <?php endif; ?>
          </td>

          <td>
            <input type="number" step="0.01" name="crediti"
                   value="<?php echo htmlspecialchars((string)$u['crediti']); ?>"><!-- saldo editabile -->
          </td>

          <td>
            <input type="password" name="new_password" placeholder="Reset (opzionale)"><!-- reset password -->
          </td>

          <td class="actions">
            <a class="btn" href="/admin/movimenti.php?user_id=<?php echo (int)$u['id']; ?>">Movimenti</a><!-- link lista movimenti -->
            <!-- Salvataggio campi della riga -->
            <button class="btn btn-apply" type="submit" name="action" value="update_user">Applica modifiche</button>
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
