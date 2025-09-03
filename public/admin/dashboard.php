<?php
// ==========================================================
// admin/dashboard.php — Pannello gestione utenti stile "griglia"
// Mostra: nome, cognome, username, email, telefono, attivo, saldo (editabile),
// reset password, pulsante “Applica modifiche” e link "Lista movimenti".
// Include: ricerca, ordinamento e paginazione base.
// ==========================================================

require_once __DIR__ . '/../src/guards.php';
require_admin();

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

// CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Flash & errori
$flash  = null;
$errors = [];

if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ------------------------
// Gestione aggiornamenti riga (POST)
// ------------------------
$action  = '';
$user_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $posted_csrf)) {
        http_response_code(400);
        die('CSRF non valido');
    }

    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // 1) TOGGLE ATTIVO/DISATTIVO
    if ($action === 'toggle_active' && $user_id > 0) {
        $chk = $pdo->prepare("SELECT username FROM utenti WHERE id = :id LIMIT 1");
        $chk->execute([':id' => $user_id]);
        $uname = $chk->fetchColumn();

        if ($uname === 'valenzo2313') {
            $_SESSION['flash'] = 'Questo utente non può essere disattivato.';
            $query = http_build_query([
                'page' => (int)($_GET['page'] ?? 1),
                'sort' => $_GET['sort'] ?? 'cognome',
                'dir'  => $_GET['dir']  ?? 'asc',
                'q'    => $_GET['q']    ?? '',
            ]);
            header("Location: /admin/dashboard.php?$query");
            exit;
        }

        $new_state = (int)($_POST['new_state'] ?? 0);
        $up = $pdo->prepare("UPDATE utenti SET is_active = :a WHERE id = :id");
        $up->execute([':a' => $new_state, ':id' => $user_id]);

        $_SESSION['flash'] = $new_state ? 'Utente attivato.' : 'Utente disattivato.';
        $query = http_build_query([
            'page' => (int)($_GET['page'] ?? 1),
            'sort' => $_GET['sort'] ?? 'cognome',
            'dir'  => $_GET['dir']  ?? 'asc',
            'q'    => $_GET['q']    ?? '',
        ]);
        header("Location: /admin/dashboard.php?$query");
        exit;
    }

    // 2) UPDATE DATI UTENTE
    if ($action === 'update_user' && $user_id > 0) {
        $nome     = trim($_POST['nome'] ?? '');
        $cognome  = trim($_POST['cognome'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $saldo    = $_POST['crediti'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida.';
        }
        if ($phone !== '' && !preg_match('/^\+?[0-9\- ]{7,20}$/', $phone)) {
            $errors[] = 'Numero di telefono non valido.';
        }
        if ($saldo === '' || !is_numeric($saldo)) {
            $errors[] = 'Saldo non valido.';
        }
        if ($new_pass !== '' && strlen($new_pass) < 8) {
            $errors[] = 'La nuova password deve avere almeno 8 caratteri.';
        }

        if (!$errors) {
            // base update
            $sql = "UPDATE utenti 
                       SET nome    = :nome,
                           cognome = :cognome,
                           email   = :email,
                           phone   = :phone,
                           crediti = :crediti
                     WHERE id = :id";
            $params = [
                ':nome'    => $nome,
                ':cognome' => $cognome,
                ':email'   => $email,
                ':phone'   => $phone,
                ':crediti' => (float)$saldo,
                ':id'      => $user_id,
            ];

            // reset password opzionale
            if ($new_pass !== '') {
                $hash   = password_hash($new_pass, PASSWORD_DEFAULT);
                $sql    = "UPDATE utenti 
                           SET nome          = :nome,
                               cognome       = :cognome,
                               email         = :email,
                               phone         = :phone,
                               crediti       = :crediti,
                               password_hash = :hash
                           WHERE id = :id";
                $params[':hash'] = $hash;
            }

            $up = $pdo->prepare($sql);
            $up->execute($params);

            $_SESSION['flash'] = 'Modifiche salvate.';
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

    // 3) ADMIN VERIFY EMAIL
    if ($action === 'admin_verify_email' && $user_id > 0) {
        $up = $pdo->prepare("UPDATE utenti 
                             SET verified_at = NOW(), verification_token = NULL, is_active = 1 
                             WHERE id = :id");
        $up->execute([':id' => $user_id]);

        $_SESSION['flash'] = 'Email convalidata e utente attivato.';
        $query = http_build_query(['page'=>$_GET['page']??1,'sort'=>$_GET['sort']??'cognome','dir'=>$_GET['dir']??'asc','q'=>$_GET['q']??'']);
        header("Location: /admin/dashboard.php?$query");
        exit;
    }

    // 4) ELIMINA UTENTE
    if ($action === 'admin_delete_user' && $user_id > 0) {
        // no auto-delete
        if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $user_id) {
            $_SESSION['flash'] = 'Non puoi eliminare il tuo stesso account.';
            $query = http_build_query([
                'page' => $_GET['page'] ?? 1,
                'sort' => $_GET['sort'] ?? 'cognome',
                'dir'  => $_GET['dir']  ?? 'asc',
                'q'    => $_GET['q']    ?? '',
            ]);
            header("Location: /admin/dashboard.php?$query"); 
            exit;
        }

        // utente speciale protetto
        $chk = $pdo->prepare("SELECT username FROM utenti WHERE id = :id LIMIT 1");
        $chk->execute([':id' => $user_id]);
        $uname = $chk->fetchColumn();
        if ($uname === 'valenzo2313') {
            $_SESSION['flash'] = 'L’utente speciale non può essere eliminato.';
            $query = http_build_query([
                'page' => $_GET['page'] ?? 1,
                'sort' => $_GET['sort'] ?? 'cognome',
                'dir'  => $_GET['dir']  ?? 'asc',
                'q'    => $_GET['q']    ?? '',
            ]);
            header("Location: /admin/dashboard.php?$query");
            exit;
        }

        // elimina
        $del = $pdo->prepare("DELETE FROM utenti WHERE id = :id");
        $del->execute([':id' => $user_id]);

        $_SESSION['flash'] = 'Utente eliminato definitivamente.';
        $query = http_build_query([
            'page' => $_GET['page'] ?? 1,
            'sort' => $_GET['sort'] ?? 'cognome',
            'dir'  => $_GET['dir']  ?? 'asc',
            'q'    => $_GET['q']    ?? '',
        ]);
        header("Location: /admin/dashboard.php?$query");
        exit;
    }
} // <— CHIUSURA CORRETTA del blocco POST

// ------------------------
// Ricerca / Ordinamento / Paginazione (GET)
// ------------------------
$q    = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'cognome';
$dir  = $_GET['dir']  ?? 'asc';

$allowedSort = ['cognome','nome','username','email','phone','crediti'];
$allowedDir  = ['asc','desc'];
if (!in_array($sort, $allowedSort, true)) { $sort = 'cognome'; }
if (!in_array(strtolower($dir), $allowedDir, true)) { $dir = 'asc'; }

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Costruzione query
$where    = "1=1";
$bindings = [];
if ($q !== '') {
    $where .= " AND (
        nome     LIKE :q1 OR
        cognome  LIKE :q2 OR
        username LIKE :q3 OR
        email    LIKE :q4 OR
        phone    LIKE :q5
    )";
    $like = '%'.$q.'%';
    $bindings[':q1'] = $like;
    $bindings[':q2'] = $like;
    $bindings[':q3'] = $like;
    $bindings[':q4'] = $like;
    $bindings[':q5'] = $like;
}

$countSql  = "SELECT COUNT(*) FROM utenti WHERE $where";
$countStmt = $pdo->prepare($countSql);
foreach ($bindings as $k => $v) { $countStmt->bindValue($k, $v, PDO::PARAM_STR); }
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

$listSql  = "SELECT id, user_code, nome, cognome, username, email, phone, crediti, is_active, verified_at
             FROM utenti
             WHERE $where
             ORDER BY $sort " . strtoupper($dir) . "
             LIMIT :lim OFFSET :off";
$listStmt = $pdo->prepare($listSql);
foreach ($bindings as $k => $v) { $listStmt->bindValue($k, $v, PDO::PARAM_STR); }
$listStmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
$listStmt->bindValue(':off', (int)$offset,  PDO::PARAM_INT);
$listStmt->execute();
$users = $listStmt->fetchAll();

// Totale per KPI
$tot_utenti = (int)$pdo->query("SELECT COUNT(*) FROM utenti")->fetchColumn();
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Dashboard</title>

  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <link rel="stylesheet" href="/assets/dashboard.css">
</head>
<body>

<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="admin-wrap">
  <div class="kpi"><strong>Utenti totali:</strong> <?php echo $tot_utenti; ?></div>

  <?php if ($flash): ?><div class="flash"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="err"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div><?php endif; ?>

  <form class="filters" method="get" action="/admin/dashboard.php">
    <input type="text"
           name="q"
           placeholder="Cerca (nome, cognome, user, email, telefono, crediti)"
           value="<?php echo htmlspecialchars($q); ?>">
    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
    <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir); ?>">
  </form>

  <!-- Tabella utenti -->
  <div class="table-wrap">
    <table class="admin-table">
      <colgroup>
        <col class="col-code">
        <col class="col-nome">
        <col class="col-cognome">
        <col class="col-user">
        <col class="col-email">
        <col class="col-phone">
        <col class="col-attivo">
        <col class="col-saldo">
        <col class="col-pass">
        <col class="col-actions">
      </colgroup>

      <thead>
        <?php
          function sort_url($field, $currentSort, $currentDir, $page, $q) {
            $dir = ($currentSort === $field)
                 ? (strtolower($currentDir) === 'asc' ? 'desc' : 'asc')
                 : 'asc';
            return '/admin/dashboard.php?' . http_build_query([
              'page' => (int)$page, 'sort' => $field, 'dir' => $dir, 'q' => $q,
            ]);
          }
          function sort_caret($field, $currentSort, $currentDir) {
            if ($currentSort !== $field) return '';
            return strtolower($currentDir) === 'asc' ? ' ↑' : ' ↓';
          }
        ?>
        <tr>
          <th><a href="<?php echo sort_url('user_code', $sort, $dir, $page, $q); ?>">Codice<?php echo sort_caret('user_code', $sort, $dir); ?></a></th>
          <th><a href="<?php echo sort_url('nome', $sort, $dir, $page, $q); ?>">Nome<?php echo sort_caret('nome', $sort, $dir); ?></a></th>
          <th><a href="<?php echo sort_url('cognome', $sort, $dir, $page, $q); ?>">Cognome<?php echo sort_caret('cognome', $sort, $dir); ?></a></th>
          <th><a href="<?php echo sort_url('username', $sort, $dir, $page, $q); ?>">User<?php echo sort_caret('username', $sort, $dir); ?></a></th>
          <th><a href="<?php echo sort_url('email', $sort, $dir, $page, $q); ?>">Email<?php echo sort_caret('email', $sort, $dir); ?></a></th>
          <th><a href="<?php echo sort_url('phone', $sort, $dir, $page, $q); ?>">Telefono<?php echo sort_caret('phone', $sort, $dir); ?></a></th>
          <th>Attivo</th>
          <th><a href="<?php echo sort_url('crediti', $sort, $dir, $page, $q); ?>">Saldo €<?php echo sort_caret('crediti', $sort, $dir); ?></a></th>
          <th>Nuova password</th>
          <th>Azioni</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach ($users as $u):
          $formId     = 'f_' . (int)$u['id'];
          $effActive  = ((int)$u['is_active'] === 1);
          $stateText  = $effActive ? 'Attivo' : 'Inattivo';
          $stateClass = $effActive ? 'btn-state on' : 'btn-state off';
          $nextState  = $effActive ? 0 : 1;
          $reason     = is_null($u['verified_at']) ? 'Email non verificata (login bloccato finché non verifichi o admin convalida)' : '';
        ?>
        <tr>
          <td><?php echo htmlspecialchars($u['user_code'] ?? ''); ?></td>

          <td><input type="text" name="nome"
                value="<?php echo htmlspecialchars($u['nome'] ?? ''); ?>"
                form="<?php echo $formId; ?>"></td>

          <td><input type="text" name="cognome"
                value="<?php echo htmlspecialchars($u['cognome'] ?? ''); ?>"
                form="<?php echo $formId; ?>"></td>

          <td><?php echo htmlspecialchars($u['username']); ?></td>

          <td><input type="email" name="email"
                value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"
                form="<?php echo $formId; ?>"></td>

          <td><input type="tel" name="phone"
                value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>"
                form="<?php echo $formId; ?>"></td>

          <td>
            <?php if ($u['username'] === 'valenzo2313'): ?>
              <button type="button" class="btn-state on" title="Utente sempre attivo" disabled>Sempre attivo</button>
              <?php if (is_null($u['verified_at'])): ?><div class="note">Email non verificata</div><?php endif; ?>
            <?php else: ?>
              <input type="hidden" name="new_state" value="<?php echo $nextState; ?>" form="<?php echo $formId; ?>">
              <button type="submit" name="action" value="toggle_active"
                      class="<?php echo $stateClass; ?>"
                      title="<?php echo htmlspecialchars($reason); ?>"
                      form="<?php echo $formId; ?>">
                <?php echo $stateText; ?>
              </button>
              <?php if (is_null($u['verified_at'])): ?><div class="note">Email non verificata</div><?php endif; ?>
            <?php endif; ?>
          </td>

          <td><input type="number" step="0.01" name="crediti"
                value="<?php echo htmlspecialchars((string)$u['crediti']); ?>"
                form="<?php echo $formId; ?>"></td>

          <td><input type="password" name="new_password" placeholder="Reset (opzionale)"
                form="<?php echo $formId; ?>"></td>

          <td class="actions">
            <div class="row-actions">
              <a class="btn btn--outline" href="/admin/movimenti.php?user_id=<?php echo (int)$u['id']; ?>">Movimenti</a>

              <?php if ($u['username'] !== 'valenzo2313'): ?>
                <button type="button"
                        class="btn btn--danger btn-delete"
                        data-user-id="<?php echo (int)$u['id']; ?>"
                        data-user-name="<?php echo htmlspecialchars($u['username']); ?>">
                  🗑 Elimina
                </button>
              <?php else: ?>
                <button type="button" class="btn" disabled style="opacity:.6;cursor:not-allowed;">Protetto</button>
              <?php endif; ?>

              <button class="btn btn--ok" type="submit" name="action" value="update_user" form="<?php echo $formId; ?>">
                Applica modifiche
              </button>
            </div>

            <!-- form della riga -->
            <form id="<?php echo $formId; ?>" method="post"
                  action="/admin/dashboard.php?page=<?php echo (int)$page; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&q=<?php echo urlencode($q); ?>">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div><!-- /.table-wrap -->

  <div class="pag">
    <?php
      $pages = max(1, (int)ceil($total / $perPage));
      for ($p=1; $p<=$pages; $p++):
        $cls = ($p===$page) ? 'on' : '';
        $url = '/admin/dashboard.php?page='.$p.'&sort='.urlencode($sort).'&dir='.urlencode($dir).'&q='.urlencode($q);
    ?>
      <a class="<?php echo $cls; ?>" href="<?php echo $url; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>
  </div>

  <!-- Popup eliminazione -->
  <div id="deleteModal" class="modal" style="display:none;
       position:fixed; inset:0; background:rgba(0,0,0,.6);
       align-items:center; justify-content:center; z-index:9999;">
    <div class="modal-card" style="background:#111; border:1px solid #333; border-radius:12px; padding:16px; width:420px; color:#fff;">
      <h3 style="margin:0 0 8px; font-size:18px;">Elimina utente</h3>
      <p id="deleteText" style="margin:0 0 12px; color:#ddd;">
        Sei sicuro di voler eliminare definitivamente questo utente?
      </p>
      <div style="display:flex; gap:8px; justify-content:flex-end;">
        <button type="button" id="btnCancel" class="btn">Annulla</button>
        <button type="button" id="btnConfirm" class="btn btn-apply" style="background:#e62329;border-color:#e62329;">Conferma</button>
      </div>
    </div>
  </div>

  <!-- Form nascosto per il popup eliminazione -->
  <form id="deleteForm" method="post" action="/admin/dashboard.php?page=<?php echo (int)$page; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&q=<?php echo urlencode($q); ?>" style="display:none">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="action" value="admin_delete_user">
    <input type="hidden" name="user_id" id="deleteUserId" value="">
  </form>
</main>

<?php require __DIR__ . '/../footer.php'; ?>

<script>
  // Popup eliminazione
  const modal = document.getElementById('deleteModal');
  const text  = document.getElementById('deleteText');
  const btnC  = document.getElementById('btnCancel');
  const btnOK = document.getElementById('btnConfirm');
  const form  = document.getElementById('deleteForm');
  const hidId = document.getElementById('deleteUserId');

  function openDelete(userId, username) {
    hidId.value = userId;
    text.textContent = `Sei sicuro di voler eliminare definitivamente l'utente "${username}"?`;
    modal.style.display = 'flex';
  }
  function closeDelete() { modal.style.display = 'none'; }

  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      openDelete(btn.getAttribute('data-user-id'), btn.getAttribute('data-user-name') || '');
    });
  });
  btnC.addEventListener('click', closeDelete);
  btnOK.addEventListener('click', () => form.submit());
  modal.addEventListener('click', (e) => { if (e.target === modal) closeDelete(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.style.display === 'flex') closeDelete(); });

  // Ricerca debounced
  const ff = document.querySelector('form.filters');
  const q  = ff.querySelector('input[name="q"]');
  let t = null;
  q.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => ff.submit(), 400);
  });
</script>
</body>
</html>
