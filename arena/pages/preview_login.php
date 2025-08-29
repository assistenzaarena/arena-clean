<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login — ARENA</title>

  <!-- CSS principali -->
  <link rel="stylesheet" href="/css/header_guest.css">
  <link rel="stylesheet" href="/css/footer.css">
  <link rel="stylesheet" href="/css/auth.css">
</head>
<body>

  <?php include $_SERVER['DOCUMENT_ROOT'].'/components/header_guest.html'; ?>

  <main class="auth auth--login" role="main">
    <section class="auth-card" aria-labelledby="login-title">
      <h1 id="login-title" class="auth-title">Accedi al tuo conto gioco</h1>

      <div class="auth-alert" role="alert" aria-live="polite"></div>

      <!-- STEP 1: credenziali -->
      <form class="auth-form auth-step auth-step--credentials" action="/api/login" method="post" novalidate>
        <div class="auth-field">
          <label class="auth-label" for="login-id">Email / Username</label>
          <input id="login-id" name="id" type="text" class="auth-input"
                 autocomplete="username" placeholder="Email o username" required>
        </div>

        <div class="auth-field">
          <label class="auth-label" for="login-pw">Password</label>
          <div class="auth-input-wrap">
            <input id="login-pw" name="password" type="password" class="auth-input"
                   autocomplete="current-password" placeholder="Password" required>
            <button type="button" class="auth-pw-toggle" aria-label="Mostra/Nascondi password">👁️</button>
          </div>
        </div>

        <div class="auth-helper">
          <a class="auth-link" href="/recupero-password">Hai dimenticato la password?</a>
        </div>

        <button type="submit" class="btn btn--muted auth-submit">Accedi</button>
      </form>

      <!-- STEP 2: 2FA (mostrato solo per admin) -->
      <form class="auth-form auth-step auth-2fa" action="/api/login/2fa" method="post" novalidate>
        <div class="auth-field">
          <label class="auth-label" for="otp">Codice 2FA</label>
          <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]*"
                 maxlength="6" class="auth-input" placeholder="000000" required>
          <small class="auth-hint">Inserisci il codice dell’app di autenticazione.</small>
        </div>

        <button type="submit" class="btn btn--muted auth-submit">Verifica</button>
        <div class="auth-row">
          <a class="auth-link" href="#" id="back-to-credentials">Torna indietro</a>
        </div>
      </form>

      <!-- Riga finale -->
      <div class="auth-row auth-row--center">
        <span class="auth-muted">Non sei registrato?</span>
        <a class="btn btn--primary" href="/registrazione">Registrati</a>
      </div>
    </section>
  </main>

  <?php include $_SERVER['DOCUMENT_ROOT'].'/components/footer.html'; ?>

  <!-- JS: toggle password + flusso demo -->
  <script>
  (function(){
    // 1) Mostra/Nascondi password
    document.querySelectorAll('.auth-pw-toggle').forEach(btn=>{
      const input = btn.closest('.auth-input-wrap')?.querySelector('input');
      if(!input) return;
      btn.addEventListener('click', ()=>{ input.type = input.type==='password' ? 'text' : 'password'; });
    });

    // 2) Flusso di login (DEMO front‑end) — sostituisci con fetch POST al tuo backend
    const alertBox = document.querySelector('.auth-alert');
    const fCred    = document.querySelector('.auth-step--credentials');
    const f2fa     = document.querySelector('.auth-2fa');
    const back     = document.getElementById('back-to-credentials');

    // Simulazione: se l'ID contiene "admin" → richiedi 2FA, altrimenti vai a home utente
    const DEMO_MODE = true;

    fCred.addEventListener('submit', function(e){
      if(!DEMO_MODE) return;  // togli questa riga quando colleghi il backend
      e.preventDefault();

      const id = document.getElementById('login-id').value.trim();
      const pw = document.getElementById('login-pw').value;

      if(!id || !pw){
        alertBox.textContent = 'Inserisci email/username e password.';
        alertBox.setAttribute('aria-live','polite');
        return;
      }

      alertBox.textContent = '';
      alertBox.removeAttribute('aria-live');

      if(id.toLowerCase().includes('admin')){
        // mostra step 2FA
        fCred.style.display = 'none';
        f2fa.classList.add('is-visible');
        document.getElementById('otp').focus();
      }else{
        // redirect utente
        window.location.href = '/home-utente';
      }
    });

    f2fa.addEventListener('submit', function(e){
      if(!DEMO_MODE) return;  // togli quando colleghi il backend
      e.preventDefault();

      const otp = document.getElementById('otp').value.trim();
      if(otp.length !== 6){
        alertBox.textContent = 'Inserisci un codice 2FA di 6 cifre.';
        alertBox.setAttribute('aria-live','polite');
        return;
      }
      // redirect admin
      window.location.href = '/dashboard-admin';
    });

    back?.addEventListener('click', function(e){
      e.preventDefault();
      alertBox.textContent = '';
      alertBox.removeAttribute('aria-live');
      f2fa.classList.remove('is-visible');
      fCred.style.display = '';
      document.getElementById('login-id').focus();
    });
  })();
  </script>
</body>
</html>