<?php
// /partials/guest_mobile_drawer.php
// Attesi (opzionali): $GUEST_SUBNAV = [['label'=>'Home','href'=>'/'], ...];
if (!isset($GUEST_SUBNAV) || !is_array($GUEST_SUBNAV)) {
  $GUEST_SUBNAV = [
    ['label'=>'Home',   'href'=>'/'],
    ['label'=>'Tornei', 'href'=>'/tornei.php'],
    ['label'=>'Promo',  'href'=>'/promo.php'],
  ];
}
$LOGIN_URL = $LOGIN_URL ?? '/login.php';
$REGISTER_URL = $REGISTER_URL ?? '/register.php';
?>
<div class="g-drawer" id="g-drawer">
  <div class="g-dim" data-g-dim></div>
  <div class="g-panel">
    <div class="g-ph">
      <strong>Menu</strong>
      <button class="g-burger" data-g-close>&times;</button>
    </div>
    <div class="g-pc">
      <!-- Accedi / Registrati -->
      <div class="g-card" style="margin-bottom:12px;">
        <div class="g-grid2">
          <a class="g-btn g-btn--ghost" href="<?php echo htmlspecialchars($LOGIN_URL); ?>">Accedi</a>
          <a class="g-btn g-btn--primary" href="<?php echo htmlspecialchars($REGISTER_URL); ?>">Registrati</a>
        </div>
      </div>

      <!-- Subheader (stessi link della header guest) -->
      <div class="g-card" style="margin-bottom:12px;">
        <div class="g-muted" style="margin-bottom:6px;font-size:12px;">Navigazione</div>
        <div class="g-tabs">
          <?php
            $active = $_GET['tab'] ?? '';
            foreach ($GUEST_SUBNAV as $item){
              $label = htmlspecialchars($item['label']);
              $href  = htmlspecialchars($item['href']);
              $on = ($active && strpos($href, $active)!==false) ? ' g-tab--on' : '';
              echo "<a class='g-tab$on' href='$href'>$label</a>";
            }
          ?>
        </div>
      </div>

      <!-- Footer dentro al menu -->
      <div class="g-footer">
        <div class="g-list">
          <a class="g-link" href="/contatti.php">Contatti</a>
          <a class="g-link" href="/termini.php">Termini e Condizioni</a>
          <a class="g-link" href="/privacy.php">Privacy</a>
          <a class="g-link" href="/responsabile.php">Gioco Responsabile</a>
          <a class="g-link" href="/faq.php">FAQ e Contatti</a>
        </div>
        <div class="g-sep"></div>
        <div class="g-muted">© <?php echo date('Y'); ?> ARENA</div>
      </div>
    </div>
  </div>
</div>
