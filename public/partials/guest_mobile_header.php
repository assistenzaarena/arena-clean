<style>
/* Mostra questo header SOLO su mobile */
.mobile-only { display: none; }
@media (max-width: 900px){
  .mobile-only { display: block; }
}
</style>

<div class="mobile-only">
  <header class="mobile-header" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#0f1114;border-bottom:1px solid rgba(255,255,255,.12);color:#fff;">
    <div class="left" style="display:flex;align-items:center;gap:10px;">
      <img src="/assets/logo_arena.png" alt="ARENA" style="height:32px;width:auto;">
      <span style="font-weight:900;font-size:18px;">ARENA</span>
    </div>
    <div class="right" style="display:flex;align-items:center;gap:12px;">
      <a href="/login.php" class="btn-login" style="color:#fff;text-decoration:none;font-weight:700;">Accedi</a>
      <button id="openDrawer" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">☰</button>
    </div>
  </header>
</div>
