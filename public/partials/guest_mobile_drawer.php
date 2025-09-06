<style>
/* Il drawer esiste sempre nel DOM, ma lo mostriamo solo su mobile */
.mobile-only { display: none; }
@media (max-width: 900px){
  .mobile-only { display: block; }
}
</style>

<div class="mobile-only">
  <aside id="drawer" style="position:fixed;top:0;right:0;bottom:0;width:280px;background:#0f1114;color:#fff;transform:translateX(100%);transition:transform .3s ease;z-index:10000;box-shadow:-4px 0 12px rgba(0,0,0,.5);padding:20px;display:flex;flex-direction:column;">
    <button id="closeDrawer" style="align-self:flex-end;background:none;border:none;color:#fff;font-size:22px;cursor:pointer;margin-bottom:20px;">✕</button>

    <nav style="display:flex;flex-direction:column;gap:12px;flex:1;">
      <a href="/login.php" style="color:#fff;text-decoration:none;font-weight:700;">Accedi</a>
      <a href="/registrazione.php" style="color:#fff;text-decoration:none;font-weight:700;">Registrati</a>
      <hr style="border:0;border-top:1px solid rgba(255,255,255,.15);margin:12px 0;">
      <a href="/" style="color:#fff;text-decoration:none;">Home</a>
      <a href="/lobby.php" style="color:#fff;text-decoration:none;">Tornei</a>
      <a href="/storico_tornei.php" style="color:#fff;text-decoration:none;">Storico tornei</a>
    </nav>

    <footer style="font-size:13px;color:#aaa;display:flex;flex-direction:column;gap:6px;">
      <a href="/contatti.php" style="color:#aaa;text-decoration:none;">Contatti</a>
      <a href="/termini.php" style="color:#aaa;text-decoration:none;">Termini e condizioni</a>
    </footer>
  </aside>
</div>

<script>
  const drawer = document.getElementById('drawer');
  const openBtn = document.getElementById('openDrawer');
  const closeBtn = document.getElementById('closeDrawer');

  if (openBtn && drawer) {
    openBtn.addEventListener('click', () => { drawer.style.transform = 'translateX(0)'; });
  }
  if (closeBtn && drawer) {
    closeBtn.addEventListener('click', () => { drawer.style.transform = 'translateX(100%)'; });
  }
</script>
