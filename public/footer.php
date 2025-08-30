<?php
// [SCOPO] Footer unico per tutte le pagine (guest, user, admin).
// [USO]   Includi alla fine di ogni pagina, prima di </body>:
//         <?php require __DIR__ . '/footer.php'; ?>
<link rel="stylesheet" href="/assets/footer.css">

<footer class="site-footer" role="contentinfo">
  <div class="site-footer__inner">

    <!-- NAV LINK LEGALI / INFO -->
    <nav class="site-footer__nav" aria-label="Link utili e legali">
      <ul class="footer-menu">
        <li><a href="/chi-siamo">Chi siamo</a></li>
        <li><a href="/contatti">Contatti</a></li>
        <li><a href="/regolamento">Regolamento</a></li>
        <li><a href="/termini-e-condizioni">Termini e condizioni</a></li>
        <li><a href="/condizioni-generali">Condizioni generali</a></li>
        <li><a href="/privacy-e-sicurezza">Privacy e sicurezza</a></li>
        <li><a href="/cookie-policy">Cookie policy</a></li>
        <li><a href="/faq">FAQ</a></li>
        <li><a href="/assistenza">Assistenza</a></li>
        <li><a href="mailto:abusi@arena.example">Segnalazione abusi</a></li>
      </ul>
    </nav>

    <!-- META: © anno + brand + “torna su” -->
    <div class="site-footer__meta">
      <span>© <span id="footer-year"></span> ARENA. Tutti i diritti riservati.</span>
      <a class="back-to-top" href="#" aria-label="Torna all’inizio">↑</a>
    </div>

  </div>
</footer>

<script>
// [RIGA] Inserisce automaticamente l’anno corrente
document.getElementById('footer-year').textContent = new Date().getFullYear();
</script>
