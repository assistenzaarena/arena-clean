(function () {
  // Non fare nulla in area admin
  if (location.pathname.startsWith('/admin')) return;

  const mm = window.matchMedia('(max-width: 900px)');
  if (!mm.matches) return; // attiva solo su mobile

  // Già montato?
  if (document.getElementById('mobileAppBar')) return;

  // Prova a capire se è utente loggato, usando l'header_user esistente nel DOM
  const userHeader = document.querySelector('.hdr.hdr--user');
  const isUser = !!userHeader;
  const userName = isUser ? (document.querySelector('.user-display__name')?.textContent || '').trim() : '';
  const userCredits = isUser ? (document.getElementById('headerCrediti')?.textContent || '').trim() : '';

  // Costruisci AppBar
  const appbar = document.createElement('div');
  appbar.id = 'mobileAppBar';
  appbar.innerHTML = `
    <a class="mBrand" href="/">
      <img src="/assets/logo_arena.png" alt="ARENA">
      <span>ARENA</span>
    </a>
    <div class="mRight">
      ${isUser
        ? `<span class="mCredit" id="mobileCredits">${userCredits || '0'} cr</span><span class="mUser">${userName || ''}</span>`
        : `<a class="mLogin" href="/login.php">Accedi</a>`
      }
      <button class="mBurger" id="mBurger" aria-label="Apri menu">☰</button>
    </div>
  `;
  document.body.prepend(appbar);

  // Backdrop + Drawer
  const backdrop = document.createElement('div');
  backdrop.id = 'mobileDrawerBackdrop';
  const drawer = document.createElement('aside');
  drawer.id = 'mobileDrawer';

  // Raccogli link subheader (se presenti nel DOM)
  const subLinks = Array.from(document.querySelectorAll('.subhdr__menu a'))
    .map(a => ({ href: a.getAttribute('href') || '#', label: (a.textContent || '').trim() }))
    // Evita duplicati banali
    .filter((v, i, arr) => arr.findIndex(x => x.href === v.href) === i);

  // Raccogli link footer (fallback se footer nascosto)
  const footerLinks = (function () {
    const f = document.querySelector('footer') || document.querySelector('.footer') || document.getElementById('footer');
    if (!f) {
      // fallback statico
      return [
        { href: '/contatti.php', label: 'Contatti' },
        { href: '/termini.php', label: 'Termini e condizioni' },
        { href: '/privacy.php', label: 'Privacy' }
      ];
    }
    const links = Array.from(f.querySelectorAll('a')).map(a => ({ href: a.getAttribute('href') || '#', label: (a.textContent || '').trim() }));
    // filtra voci vuote
    return links.filter(x => x.label);
  })();

  // Se utente → sezione account sopra; se guest → Accedi/Registrati
  const accountSection = isUser
    ? `
      <div class="mdr-section">
        <h4>Account</h4>
        <div class="mdr-list">
          <div class="mdr-muted">Saldo: <strong id="mobileCredits2">${userCredits || '0'}</strong> crediti</div>
          <div class="mdr-muted">Utente: <strong>${userName || ''}</strong></div>
          <form method="post" action="/logout.php" class="mdr-actions">
            <button type="submit" class="btn-ghost">Logout</button>
          </form>
        </div>
      </div>`
    : `
      <div class="mdr-section">
        <h4>Benvenuto</h4>
        <div class="mdr-actions">
          <a class="btn-primary" href="/registrazione.php">Registrati</a>
          <a class="btn-ghost" href="/login.php">Accedi</a>
        </div>
      </div>`;

  // Sezione navigazione (subheader)
  const navSection = `
    <div class="mdr-section">
      <h4>Navigazione</h4>
      <div class="mdr-list">
        ${
          (subLinks.length ? subLinks : [
            { href: '/', label: 'Home' },
            { href: '/lobby.php', label: 'Tornei' },
            { href: '/storico_tornei.php', label: 'Storico tornei' },
            { href: '/dati_utente.php', label: 'Dati utente' },
            { href: '/premi.php', label: 'Premi' }
          ]).map(l => `<a class="mdr-link" href="${l.href}">${l.label}</a>`).join('')
        }
      </div>
    </div>`;

  // Sezione footer (contatti/termini ecc.)
  const footerSection = `
    <div class="mdr-section">
      <h4>Info</h4>
      <div class="mdr-list">
        ${footerLinks.map(l => `<a class="mdr-muted" href="${l.href}">${l.label}</a>`).join('')}
      </div>
    </div>`;

  drawer.innerHTML = `
    <div class="mdr-head">
      <div class="mdr-title">
        <img src="/assets/logo_arena.png" alt="" style="height:20px; width:auto;">
        <span>Menu</span>
      </div>
      <button class="mdr-close" id="mClose" aria-label="Chiudi menu">✕</button>
    </div>
    ${accountSection}
    ${navSection}
    ${footerSection}
  `;
  backdrop.appendChild(drawer);
  document.body.appendChild(backdrop);

  // Toggle
  const open = () => { backdrop.classList.add('open'); drawer.classList.add('open'); };
  const close = () => { backdrop.classList.remove('open'); drawer.classList.remove('open'); };

  document.getElementById('mBurger')?.addEventListener('click', open);
  document.getElementById('mClose')?.addEventListener('click', close);
  backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

  // Se header_user aggiorna il saldo (#headerCrediti), prova a sincronizzare anche il mobile
  const observerTarget = document.getElementById('headerCrediti');
  if (observerTarget) {
    const sync = () => {
      const val = (observerTarget.textContent || '').trim();
      const a = document.getElementById('mobileCredits');
      const b = document.getElementById('mobileCredits2');
      if (a) a.textContent = val + ' cr';
      if (b) b.textContent = val;
    };
    const obs = new MutationObserver(sync);
    obs.observe(observerTarget, { childList: true, subtree: true, characterData: true });
    sync();
  }
})();
