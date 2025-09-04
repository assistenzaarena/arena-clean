<?php
// =====================================================================
// /admin/premi_catalogo.php — Gestione Catalogo Premi (CRUD + Upload)
// Requisiti DB: tabella admin_prize_catalog (creata in precedenza)
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/guards.php';  require_admin();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$FLASH = function($msg){ $_SESSION['flash'] = $msg; };
$POP   = function(){ $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; };

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// Config upload
// Path lato filesystem e URL (puntano a /public/uploads/prizes)
// Percorsi corretti: filesystem nella cartella pubblica e URL pubblico
$PUBLIC_ROOT   = realpath(__DIR__ . '/..');            // ← /var/www/html/public
if ($PUBLIC_ROOT === false) {                          // fallback di sicurezza
  $PUBLIC_ROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
}
$UPLOAD_DIR_FS = $PUBLIC_ROOT . '/uploads/prizes';     // fs assoluto
$UPLOAD_DIR_URL= '/uploads/prizes';                    // URL pubblico
$MAX_SIZE = 3 * 1024 * 1024;  // 3 MB
$ALLOWED_EXT = ['jpg','jpeg','png','webp'];
$ALLOWED_MIME = ['image/jpeg','image/png','image/webp'];

// ensure upload dir
if (!is_dir($UPLOAD_DIR_FS)) {
  @mkdir($UPLOAD_DIR_FS, 0755, true);
}
if (!is_writable($UPLOAD_DIR_FS)) {
  $_SESSION['flash'] = 'Attenzione: la cartella '.htmlspecialchars($UPLOAD_DIR_FS).' non è scrivibile dal server.';
  header('Location: /admin/premi_catalogo.php'); exit;
}

// Helpers
function sanitize_filename($name) {
  $name = preg_replace('/[^\w\.\-]+/u', '_', $name);
  $name = trim($name, '_');
  return $name ?: ('file_'.bin2hex(random_bytes(4)));
}
function unique_target_path($dir, $basename) {
  $p = $dir . '/' . $basename;
  if (!file_exists($p)) return $p;
  $noext = pathinfo($basename, PATHINFO_FILENAME);
  $ext = pathinfo($basename, PATHINFO_EXTENSION);
  $i=1;
  do {
    $alt = $dir . '/' . $noext . '_' . $i . ($ext ? ('.'.$ext) : '');
    if (!file_exists($alt)) return $alt;
    $i++;
  } while ($i < 10000);
  return $dir . '/' . $noext . '_' . bin2hex(random_bytes(3)) . ($ext?'.'.$ext:'');
}

// ---------------------------------------------------------------------
// POST HANDLER
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'] ?? '', $posted_csrf)) {
    http_response_code(400); $FLASH('CSRF non valido.'); header('Location: /admin/premi_catalogo.php'); exit;
  }
  $action = $_POST['action'] ?? '';

  // Upload image (common)
  $handleImageUpload = function($fileField, $oldUrl = null) use ($UPLOAD_DIR_FS, $UPLOAD_DIR_URL, $ALLOWED_EXT, $ALLOWED_MIME, $MAX_SIZE, $PUBLIC_ROOT) {
    if (empty($_FILES[$fileField]['name'])) return $oldUrl; // no change

    if (!is_uploaded_file($_FILES[$fileField]['tmp_name'])) {
      throw new RuntimeException('Upload non valido.');
    }
    $tmp  = $_FILES[$fileField]['tmp_name'];
    $size = (int)$_FILES[$fileField]['size'];
    $mime = mime_content_type($tmp) ?: '';

    if ($size <= 0 || $size > $MAX_SIZE) {
      throw new RuntimeException('Immagine troppo grande (max 3 MB).');
    }
    if (!in_array($mime, $ALLOWED_MIME, true)) {
      throw new RuntimeException('Formato immagine non consentito (usa JPG/PNG/WebP).');
    }

    $ext = strtolower(pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXT, true)) {
      // prova a dedurre da mime
      $ext = ($mime === 'image/jpeg') ? 'jpg' : (($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : ''));
      if (!$ext) throw new RuntimeException('Estensione non valida.');
    }

    $base = sanitize_filename(pathinfo($_FILES[$fileField]['name'], PATHINFO_FILENAME));
    $targetFs = unique_target_path($UPLOAD_DIR_FS, $base.'.'.$ext);

    if (!@move_uploaded_file($tmp, $targetFs)) {
      throw new RuntimeException('Salvataggio immagine fallito.');
    }

    // opzionale: rimuovi la vecchia immagine (sempre rispetto a /public)
    if (!empty($oldUrl)) {
      $oldFs = realpath($PUBLIC_ROOT . $oldUrl);
      if ($oldFs && strpos($oldFs, $PUBLIC_ROOT) === 0) {
        @unlink($oldFs);
      }
    }

    // return URL (relativo a /public)
    $abs  = realpath($targetFs);
    $rel  = $abs ? str_replace($PUBLIC_ROOT, '', $abs) : '';
    if ($rel === '' || $rel === false) {
      $rel = $UPLOAD_DIR_URL . '/' . basename($targetFs);
    }
    return $rel;
  };

  try {
    if ($action === 'create') {
      $name   = trim($_POST['name'] ?? '');
      $desc   = trim($_POST['description'] ?? '');
      $cost   = (int)($_POST['credits_cost'] ?? 0);
      $active = isset($_POST['is_active']) ? 1 : 0;

      if ($name === '' || $cost < 0) throw new RuntimeException('Nome e costo crediti sono obbligatori.');

      $imgUrl = null;
      if (!empty($_FILES['image']['name'])) {
        $imgUrl = $handleImageUpload('image', null);
      }

      $ins = $pdo->prepare("
        INSERT INTO admin_prize_catalog (name, description, credits_cost, image_url, is_active)
        VALUES (?,?,?,?,?)
      ");
      $ins->execute([$name, $desc, $cost, $imgUrl, $active]);
      $FLASH('Premio creato.');
      header('Location: /admin/premi_catalogo.php'); exit;
    }

    if ($action === 'update') {
      $id     = (int)($_POST['id'] ?? 0);
      $name   = trim($_POST['name'] ?? '');
      $desc   = trim($_POST['description'] ?? '');
      $cost   = (int)($_POST['credits_cost'] ?? 0);
      $active = isset($_POST['is_active']) ? 1 : 0;

      if ($id<=0 || $name === '' || $cost < 0) throw new RuntimeException('Dati non validi.');

      // leggi old image
      $st = $pdo->prepare("SELECT image_url FROM admin_prize_catalog WHERE id=? LIMIT 1");
      $st->execute([$id]); $oldUrl = $st->fetchColumn();

      $newUrl = $oldUrl;
      if (!empty($_FILES['image']['name'])) {
        $newUrl = $handleImageUpload('image', $oldUrl ?: null);
      }

      $up = $pdo->prepare("
        UPDATE admin_prize_catalog
        SET name=?, description=?, credits_cost=?, image_url=?, is_active=?, updated_at=CURRENT_TIMESTAMP
        WHERE id=?
      ");
      $up->execute([$name, $desc, $cost, $newUrl, $active, $id]);
      $FLASH('Premio aggiornato.');
      header('Location: /admin/premi_catalogo.php'); exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new RuntimeException('ID mancante.');

      $st = $pdo->prepare("SELECT image_url FROM admin_prize_catalog WHERE id=? LIMIT 1");
      $st->execute([$id]); $oldUrl = $st->fetchColumn();

      $del = $pdo->prepare("DELETE FROM admin_prize_catalog WHERE id=? LIMIT 1");
      $del->execute([$id]);

      // opzionale: rimuovi la vecchia immagine (sempre rispetto a /public)
      if ($oldUrl) {
        $oldFs = realpath($PUBLIC_ROOT . $oldUrl);
        if ($oldFs && strpos($oldFs, $PUBLIC_ROOT) === 0) {
          @unlink($oldFs);
        }
      }
      $FLASH('Premio eliminato.');
      header('Location: /admin/premi_catalogo.php'); exit;
    }

    $FLASH('Azione non riconosciuta.');
    header('Location: /admin/premi_catalogo.php'); exit;

  } catch (Throwable $e) {
    $FLASH('Errore: '.$e->getMessage());
    header('Location: /admin/premi_catalogo.php'); exit;
  }
}

// ---------------------------------------------------------------------
// GET: elenco
// ---------------------------------------------------------------------
$items = [];
try {
  $q = $pdo->query("SELECT * FROM admin_prize_catalog ORDER BY is_active DESC, updated_at DESC, id DESC");
  $items = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $items = [];
}

$flash = $POP();
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Catalogo premi</title>
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_admin.css">
  <style>
    .wrap{max-width:1280px;margin:24px auto;padding:0 16px;color:#fff}
    .card{background:#111;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:0 8px 28px rgba(0,0,0,.18)}
    .hstack{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .spacer{flex:1 1 auto}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 12px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;font-weight:800;background:#202326;text-decoration:none;cursor:pointer}
    .btn:hover{border-color:#fff}
    .btn-ok{background:#00c074;border-color:#00c074}
    .btn-danger{background:#e62329;border-color:#e62329}
    .muted{color:#bdbdbd}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media (max-width: 900px){ .grid{grid-template-columns:1fr} }
    input[type=text], input[type=number], textarea, input[type=file]{
      width:100%; background:#0a0a0b; color:#fff; border:1px solid rgba(255,255,255,.2); border-radius:8px; padding:8px;
    }
    label{font-size:12px;color:#c9c9c9}

    /* ====== TABELLA ELENCO (con overflow protetto) ====== */
    .tbl-wrap{ overflow-x:auto; }
    .tbl{ width:100%; border-collapse:separate; border-spacing:0 8px; }
    .tbl th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:#c9c9c9;text-align:left;padding:8px 10px;white-space:nowrap}
    .tbl td{background:#111;border:1px solid rgba(255,255,255,.12);padding:10px 12px;vertical-align:middle}

    /* Gli input dentro la cella Azioni non devono “uscire” */
    .tbl td .hstack{ gap:6px; flex-wrap:wrap; }
    .tbl td textarea{ width:600px; max-width:100%; }
    .thumb{width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid rgba(255,255,255,.15);background:#0f1114}
    .flash{padding:10px 12px;border-radius:8px;margin-bottom:12px;background:rgba(0,192,116,.1);border:1px solid rgba(0,192,116,.4);color:#00c074}
  </style>
</head>
<body>
<?php require __DIR__ . '/../header_admin.php'; ?>

<main class="wrap">
  <div class="hstack">
    <h1 style="margin:0">Catalogo premi</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/amministrazione.php">Torna Amministrazione</a>
  </div>

  <?php if ($flash): ?>
    <div class="flash"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>

  <!-- Aggiungi -->
  <section class="card">
    <h2 style="margin:0 0 10px">Aggiungi premio</h2>
    <form method="post" action="/admin/premi_catalogo.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="create">
      <div class="grid">
        <div>
          <label>Nome *</label>
          <input type="text" name="name" required>
        </div>
        <div>
          <label>Costo in crediti *</label>
          <input type="number" name="credits_cost" min="0" step="1" required>
        </div>
        <div style="grid-column:1/-1">
          <label>Descrizione</label>
          <textarea name="description" rows="3" placeholder="Dettagli (opzionale)"></textarea>
        </div>
        <div>
          <label>Immagine (JPG/PNG/WebP, max 3MB)</label>
          <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" id="is_active" name="is_active" checked>
          <label for="is_active" style="margin:0;">Attivo</label>
        </div>
      </div>
      <div class="hstack" style="justify-content:flex-end;margin-top:12px">
        <button class="btn btn-ok" type="submit">Crea</button>
      </div>
    </form>
  </section>

  <!-- Elenco -->
  <section class="card">
    <h2 style="margin:0 0 10px">Elenco premi</h2>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Foto</th>
            <th>Nome</th>
            <th>Crediti</th>
            <th>Stato</th>
            <th>Ultimo aggiornamento</th>
            <th class="num">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$items): ?>
            <tr><td colspan="6" class="muted">Nessun premio a catalogo.</td></tr>
          <?php else: foreach ($items as $it): ?>
            <tr>
              <td style="width:80px">
                <?php if (!empty($it['image_url'])): ?>
                  <img class="thumb" src="<?php echo htmlspecialchars($it['image_url']); ?>" alt="thumb">
                <?php else: ?>
                  <div class="thumb" style="display:flex;align-items:center;justify-content:center;color:#666;font-size:12px;">N/A</div>
                <?php endif; ?>
              </td>
              <td>
                <strong><?php echo htmlspecialchars($it['name']); ?></strong><br>
                <span class="muted"><?php echo htmlspecialchars($it['description'] ?? ''); ?></span>
              </td>
              <td><?php echo number_format((int)$it['credits_cost'], 0, ',', '.'); ?></td>
              <td><?php echo ((int)$it['is_active']===1) ? 'Attivo' : 'Disattivo'; ?></td>
              <td><?php echo htmlspecialchars($it['updated_at'] ?? $it['created_at'] ?? ''); ?></td>
              <td class="num" style="white-space:nowrap">
                <!-- Modifica -->
                <form method="post" action="/admin/premi_catalogo.php" enctype="multipart/form-data" style="display:inline-block;vertical-align:top;">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">

                  <div class="hstack" style="gap:6px; align-items:flex-start; flex-wrap:wrap;">
                    <input type="text" name="name" value="<?php echo htmlspecialchars($it['name']); ?>" placeholder="Nome" style="width:180px">
                    <input type="number" name="credits_cost" value="<?php echo (int)$it['credits_cost']; ?>" min="0" step="1" style="width:140px">
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="width:220px">
                    <label style="display:flex;align-items:center;gap:6px;">
                      <input type="checkbox" name="is_active" <?php echo ((int)$it['is_active']===1)?'checked':''; ?>> Attivo
                    </label>
                    <button class="btn btn-ok" type="submit">Salva</button>
                  </div>
                  <div style="margin-top:6px;">
                    <textarea name="description" rows="2" placeholder="Descrizione (opzionale)" style="width:600px;max-width:100%;"><?php echo htmlspecialchars($it['description'] ?? ''); ?></textarea>
                  </div>
                </form>

                <!-- Elimina -->
                <form method="post" action="/admin/premi_catalogo.php" onsubmit="return confirm('Eliminare definitivamente questo premio?');" style="display:inline-block;margin-left:6px;">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                  <button class="btn btn-danger" type="submit">Elimina</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../footer.php'; ?>
</body>
</html>
