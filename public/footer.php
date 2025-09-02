<!-- Footer unico per tutte le pagine (guest, user, admin).
     Include questo file a fine pagina, prima di </body>.
     Richiede che in header_user.php, subito dopo la subheader, sia aperto:
     <div class="page-root">  (vedi sotto patch 2) -->

<link rel="stylesheet" href="/assets/footer.css">

<!-- Chiudo il wrapper centrale aperto in header_user.php -->
</div><!-- /.page-root -->

<footer class="site-footer" role="contentinfo">
  <div class="site-footer__inner">

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

    <div class="site-footer__meta">
      <span>© <span id="footer-year"></span> ARENA. Tutti i diritti riservati.</span>
      <a class="back-to-top" href="#" aria-label="Torna all’inizio">↑</a>
    </div>

  </div>
</footer>

<script>
  // Anno corrente
  var fy = document.getElementById('footer-year');
  if (fy) fy.textContent = new Date().getFullYear();

  // Torna su
  document.addEventListener('click', function(e){
    var a = e.target.closest('.back-to-top');
    if (!a) return;
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
</script>
