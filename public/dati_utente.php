<?php
// public/dati_utente.php
// Pagina “Dati utente”: visualizza e consente di aggiornare email, telefono e password.
// Coerente con stile login/registrazione. Nessun file esistente toccato.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = __DIR__;
require_once $ROOT . '/src/config.php';
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/guards.php';

require_login();
$uid = (int)($_SESSION['user_id'] ?? 0);

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$errors  = [];
$success = null;

// Carico dati utente correnti
$u = $pdo->prepare("SELECT id, user_code, nome, cognome, username, email, phone, crediti FROM utenti WHERE id=? LIMIT 1");
$u->execute([$uid]);
$user = $u->fetch(PDO::FETCH_ASSOC);
if (!$user) { http_response_code(404); die('Utente non trovato'); }

/* === AGGIUNTA MINIMA: verifica se esiste la colonna updated_at in utenti === */
$hasUpdatedAt = false;
try {
  $chk = $pdo->prepare("SELECT 1
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME   = 'utenti'
                          AND COLUMN_NAME  = 'updated_at'
                        LIMIT 1");
  $chk->execute();
  $hasUpdatedAt = (bool)$chk->fetchColumn();
} catch (Throwable $e) { $hasUpdatedAt = false; }
/* === FINE AGGIUNTA === */

// POST handler (aggiornamento)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // CSRF
  $posted_csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($csrf, $posted_csrf)) {
    $errors['csrf'] = 'Sessione scaduta. Ricarica la pagina e riprova.';
  }

  // Campi modificabili
  $email  = trim($_POST['email']  ?? (string)($user['email'] ?? ''));
  $phone  = trim($_POST['phone']  ?? (string)($user['phone'] ?? ''));
  $pass1  = (string)($_POST['password']  ?? '');
  $pass2  = (string)($_POST['password2'] ?? '');

  // Validazioni
  if ($email === '') {
    $errors['email'] = 'L’email è obbligatoria.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Email non valida.';
  }

  if ($phone === '') {
    $errors['phone'] = 'Il numero di cellulare è obbligatorio.';
  } elseif (!preg_match('/^\+?[0-9\- ]{7,20}$/', $phone)) {
    $errors['phone'] = 'Numero di cellulare non valido.';
  }

  // Password (opzionale)
  $changePassword = ($pass1 !== '' || $pass2 !== '');
  if ($changePassword) {
    $policyOk = true;
    if (strlen($pass1) < 8)                       { $policyOk = false; }
    if (!preg_match('/[A-Z]/', $pass1))           { $policyOk = false; }
    if (!preg_match('/[a-z]/', $pass1))           { $policyOk = false; }
    if (!preg_match('/\d/', $pass1))              { $policyOk = false; }
    if (!preg_match('/[!\$%&\.\,\;\)]/', $pass1)) { $policyOk = false; }
    if (!$policyOk) {
      $errors['password'] = 'Password non conforme (8+, 1 maiuscola, 1 minuscola, 1 numero, 1 tra !$%&.,; ).';
    }
    if ($pass1 !== $pass2) {
      $errors['password2'] = 'Le password non coincidono.';
    }
  }

  // Unicità (solo se non ci sono errori formali)
  if (!$errors) {
    // Email unica (escludi me stesso)
    $q = $pdo->prepare('SELECT 1 FROM utenti WHERE email = :e AND id <> :id LIMIT 1');
    $q->execute([':e'=>$email, ':id'=>$uid]);
    if ($q->fetch()) {
      $errors['email'] = 'Email già registrata.';
    }

    // Telefono unico (escludi me stesso)
    $q = $pdo->prepare('SELECT 1 FROM utenti WHERE phone = :p AND id <> :id LIMIT 1');
    $q->execute([':p'=>$phone, ':id'=>$uid]);
    if ($q->fetch()) {
      $errors['phone'] = 'Numero già registrato.';
    }
  }

  // Se tutto ok → aggiorna
  if (!$errors) {
    try {
      if ($changePassword) {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        if ($hasUpdatedAt) {
          $up = $pdo->prepare("UPDATE utenti SET email=?, phone=?, password_hash=?, updated_at=NOW() WHERE id=?");
          $up->execute([$email, $phone, $hash, $uid]);
        } else {
          $up = $pdo->prepare("UPDATE utenti SET email=?, phone=?, password_hash=? WHERE id=?");
          $up->execute([$email, $phone, $hash, $uid]);
        }
      } else {
        if ($hasUpdatedAt) {
          $up = $pdo->prepare("UPDATE utenti SET email=?, phone=?, updated_at=NOW() WHERE id=?");
          $up->execute([$email, $phone, $uid]);
        } else {
          $up = $pdo->prepare("UPDATE utenti SET email=?, phone=? WHERE id=?");
          $up->execute([$email, $phone, $uid]);
        }
      }
      // Aggiorno anche $user per riflettere a video i nuovi valori
      $user['email'] = $email;
      $user['phone'] = $phone;
      $success = 'Dati aggiornati con successo.';
    } catch (Throwable $e) {
      // error_log('[dati_utente] '.$e->getMessage());
      $errors['__'] = 'Errore interno. Riprova.';
    }
  }
}

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Dati utente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Reuso stile login/registrazione per coerenza -->
  <link rel="stylesheet" href="/assets/base.css">
  <link rel="stylesheet" href="/assets/header_user.css">
  <link rel="stylesheet" href="/assets/login.css?v=7">
  <link rel="stylesheet" href="/assets/registrazione.css?v=1">
  <style>
    /* micro-differenze */
    .auth__title { font-size:22px; font-weight:900; margin:0 0 16px; }
    .auth__card  { max-width: 520px; }
    .auth__submit { background:#00c074; }
    .auth__submit:hover { background:#00a862; }
    .meta-box{background:#0f1114;border:1px solid rgba(255,255,255,.15);border-radius:10px;padding:10px 12px;margin-bottom:12px;color:#fff}
    .meta-box dt{font-size:12px;letter-spacing:.4px;color:#bbb;text-transform:uppercase;margin-bottom:4px}
    .meta-box dd{margin:0 0 8px 0;font-weight:900}
    .readonly{background:#15171c;border:1px solid rgba(255,255,255,.12);color:#cfcfcf}
    .error-inline{color:#ff6b6b;font-size:12px;margin-top:4px}

    /* === AGGIUNTA: biglietto da visita premium === */
    .profile-card {
      position:relative;
      background: radial-gradient(1000px 600px at 50% -120px, rgba(230,35,41,.25) 0%, rgba(0,0,0,0) 60%), #0f1114;
      border:1px solid rgba(255,255,255,.18);
      border-radius:14px;
      overflow:hidden;
      padding:48px 20px 40px;
      margin-bottom:20px;
      text-align:center;
      color:#fff;
      box-shadow:0 24px 60px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.04);
    }
    .profile-card .profile-overlay{
      position:absolute; inset:0;
      background:url('/assets/logo_arena.png') no-repeat center center;
      background-size:300px;          /* LOGO PIÙ GRANDE */
      opacity:.18;                    /* PIÙ VISIBILE */
      filter: saturate(120%);
    }
    .profile-card .profile-content{ position:relative; z-index:1; letter-spacing:.08em; }
    .profile-card .profile-content div{ margin:10px 0; font-weight:900; text-transform:uppercase; }

    .profile-code    { font-size:28px; color:#ffffff; text-shadow:0 1px 0 rgba(0,0,0,.35); }
    .profile-username{ font-size:24px; color:#f5f5f5; }
    .profile-name    { font-size:20px; color:#eaeaea; }
    .profile-credits { font-size:22px; color:#00c074; letter-spacing:.12em; }
    /* === FINE AGGIUNTA === */
  </style>
</head>
<body>

<?php require $ROOT . '/header_user.php'; ?>

<main class="auth" style="gap:10px;">
  <div class="auth__card">
    <h1 class="auth__title">Dati utente</h1>

    <?php if ($success): ?>
      <p class="success-msg"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>
    <?php if ($errors && !$success): ?>
      <ul class="error-list">
        <?php foreach ($errors as $msg): ?>
          <li><?php echo htmlspecialchars($msg); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <!-- === Biglietto da visita premium (logo + testi centrati) === -->
    <div class="profile-card">
      <div class="profile-overlay"></div>
      <div class="profile-content">
        <div class="profile-code"><?php echo strtoupper(htmlspecialchars($user['user_code'] ?? '—')); ?></div>
        <div class="profile-username"><?php echo strtoupper(htmlspecialchars($user['username'] ?? '—')); ?></div>
        <div class="profile-name"><?php echo strtoupper(htmlspecialchars(trim(($user['nome'] ?? '').' '.($user['cognome'] ?? '')))); ?></div>
        <div class="profile-credits"><?php echo number_format((float)($user['crediti'] ?? 0), 0, ',', '.'); ?> CREDITI</div>
      </div>
    </div>
    <!-- === FINE biglietto === -->

    <!-- Form aggiornamento -->
    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

      <div class="auth__group">
        <label class="auth__label">Email</label>
        <input class="auth__input" type="email" name="email"
               value="<?php echo htmlspecialchars($_POST['email'] ?? (string)($user['email'] ?? '')); ?>">
        <?php if (!empty($errors['email'])): ?><div class="error-inline"><?php echo htmlspecialchars($errors['email']); ?></div><?php endif; ?>
      </div>

      <div class="auth__group">
        <label class="auth__label">Numero di cellulare</label>
        <input class="auth__input" type="text" name="phone"
               value="<?php echo htmlspecialchars($_POST['phone'] ?? (string)($user['phone'] ?? '')); ?>">
        <?php if (!empty($errors['phone'])): ?><div class="error-inline"><?php echo htmlspecialchars($errors['phone']); ?></div><?php endif; ?>
      </div>

      <div class="auth__group">
        <label class="auth__label">Nuova password (opzionale)</label>
        <input class="auth__input" type="password" name="password" autocomplete="new-password" placeholder="Lascia vuoto per non cambiare">
        <small>Min 8, 1 maiuscola, 1 minuscola, 1 numero, 1 tra !$%&.,;</small>
        <?php if (!empty($errors['password'])): ?><div class="error-inline"><?php echo htmlspecialchars($errors['password']); ?></div><?php endif; ?>
      </div>

      <div class="auth__group">
        <label class="auth__label">Conferma nuova password</label>
        <input class="auth__input" type="password" name="password2" autocomplete="new-password" placeholder="Ripeti nuova password">
        <?php if (!empty($errors['password2'])): ?><div class="error-inline"><?php echo htmlspecialchars($errors['password2']); ?></div><?php endif; ?>
      </div>

      <button class="auth__submit" type="submit">Aggiorna</button>
    </form>
  </div>
</main>

<?php require $ROOT . '/footer.php'; ?>

</body>
</html>
