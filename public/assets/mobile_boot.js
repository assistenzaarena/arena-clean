(function () {
  // Non toccare l'area admin
  if (location.pathname.startsWith('/admin')) return;

  const isMobile = window.matchMedia('(max-width: 900px)').matches;
  if (!isMobile) return;

  // 1) Smonta qualsiasi appbar/drawer precedente (perché lo stato può essere cambiato tra guest/user)
  (function unmountPrev() {
    const oldBar = document.getElementById('mobileAppBar');
    const oldBackdrop = document.getElementById('mobileDrawerBackdrop');
    if (oldBar && oldBar.parentNode) oldBar.parentNode.removeChild(oldBar);
    if (oldBackdrop && oldBackdrop.parentNode) oldBackdrop.parentNode.removeChild(oldBackdrop);
  })();

  // 2) Capisco se sei USER o GUEST dalla pagina corrente (header_user ha questi elementi)
  const isUser =
    !!document.getElementById('headerCrediti') ||
    !!document.querySelector('.logout-form') ||
    !!document.querySelector('.user-display__name');

  const userName = isUser ? (document.querySelector('.user-display__name')?.textContent || '').trim() : '';
  const userCredits = isUser ? (document.getElementById('headerCrediti')?.textContent || '').trim() : '';

  // 3) Crea AppBar mobile coerente
  const bar = document.createElement('div');
  bar.id = 'mobileAppBar';
  bar.innerHTML = `
    <a class="mBrand" href="/">
      <img src="/assets/logo_arena.png" alt="ARENA"><span>ARENA</span>
    </a>
    <div class="mRight">
${isUser
  ? `<a href="/ricarica.php" class="mRecharge">Ricarica</a><span class="mUser">${userName || ''}</span>`
  : `<a class="mLogin" href="/login.php">Accedi</a>`
}
      <button class="mBurger" id="mBurger" aria-label="Apri menu">☰</button>
    </div>
  `;
  document.body.prepend(bar);

  // 4) Costruisci Drawer + Backdrop
  const backdrop = document.createElement('div');
  backdrop.id = 'mobileDrawerBackdrop';
  const drawer = document.createElement('aside');
  drawer.id = 'mobileDrawer';

  // Voci subheader (se presenti)
  const subLinks = Array.from(document.querySelectorAll('.subhdr__menu a'))
    .map(a => ({ href: a.getAttribute('href') || '#', label: (a.textContent || '').trim() }))
    .filter((v, i, arr) => v.label && arr.findIndex(x => x.href === v.href) === i);

  // Voci footer (se presenti), altrimenti fallback
  const footerLinks = (function () {
    const f = document.querySelector('footer, .footer, #footer');
    if (!f) {
      return [
        { href: '/contatti.php', label: 'Contatti' },
        { href: '/termini.php',  label: 'Termini e condizioni' },
        { href: '/privacy.php',  label: 'Privacy' }
      ];
    }
    return Array.from(f.querySelectorAll('a'))
      .map(a => ({ href: a.getAttribute('href') || '#', label: (a.textContent || '').trim() }))
      .filter(x => x.label);
  })();

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

  const navSection = `
    <div class="mdr-section">
      <h4>Navigazione</h4>
      <div class="mdr-list">
        ${
          (subLinks.length ? subLinks : [
            { href: '/',                label: 'Home' },
            { href: '/lobby.php',       label: 'Tornei' },
            { href: '/storico_tornei.php', label: 'Storico tornei' },
            { href: '/dati_utente.php', label: 'Dati utente' },
            { href: '/premi.php',       label: 'Premi' }
          ]).map(l => `<a class="mdr-link" href="${l.href}">${l.label}</a>`).join('')
        }
      </div>
    </div>`;

  const footerSection = `
    <div class="mdr-section">
      <h4>Info</h4>
      <div class="mdr-list">
        ${footerLinks.map(l => `<a class="mdr-muted" href="${l.href}">${l.label}</a>`).join('')}
      </div>
    </div>`;

  drawer.innerHTML = `
    <div class="mdr-head">
      <div class="mdr-title"><img src="/assets/logo_arena.png" alt="" style="height:20px;width:auto;"><span>Menu</span></div>
      <button class="mdr-close" id="mClose" aria-label="Chiudi menu">✕</button>
    </div>
    ${accountSection}
    ${navSection}
    ${footerSection}
  `;
  backdrop.appendChild(drawer);
  document.body.appendChild(backdrop);

  // Toggle drawer
  const open = () => { backdrop.classList.add('open'); drawer.classList.add('open'); };
  const close = () => { backdrop.classList.remove('open'); drawer.classList.remove('open'); };
  document.getElementById('mBurger')?.addEventListener('click', open);
  document.getElementById('mClose')?.addEventListener('click', close);
  backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

  // 5) Sincronizza saldo se l'header desktop lo aggiorna (#headerCrediti)
  const creditsEl = document.getElementById('headerCrediti');
  if (creditsEl) {
    const sync = () => {
      const val = (creditsEl.textContent || '').trim();
      const a = document.getElementById('mobileCredits');
      const b = document.getElementById('mobileCredits2');
      if (a) a.textContent = (val ? val + ' cr' : '0 cr');
      if (b) b.textContent = (val || '0');
    };
    const obs = new MutationObserver(sync);
    obs.observe(creditsEl, { childList:true, subtree:true, characterData:true });
    sync();
  }
})();
