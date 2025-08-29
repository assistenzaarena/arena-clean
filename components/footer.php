<?php
/*
  components/footer.php
  ---------------------
  Footer unico per TUTTO il sito (guest / user / admin).
  - È un "partial" PHP: lo includi dove ti serve con include_once.
  - Mantiene lo stile del tuo footer.css.
  - I link portano alle rispettive pagine ("/chi-siamo", "/contatti", ecc.).
  - L'anno © è generato lato server (PHP) così non serve JS.
*/
?>

<!-- FOOTER (semantico) -->
<footer class="site-footer" role="contentinfo">
  <div class="site-footer__inner">

    <!-- NAV: elenco link legali / informativi -->
    <nav class="site-footer__nav" aria-label="Link utili e legali">
      <ul class="footer-menu">
        <!-- Minimo indispensabile (come da tua lista) -->
        <li><a href="/chi-siamo"             title="Scopri chi siamo">Chi siamo</a></li>
        <li><a href="/contatti"              title="Contattaci">Contatti</a></li>
        <li><a href="/regolamento"           title="Leggi il regolamento">Regolamento</a></li>
        <li><a href="/termini-e-condizioni"  title="Termini e condizioni">Termini e condizioni</a></li>
        <li><a href="/condizioni-generali"   title="Condizioni generali">Condizioni generali</a></li>
        <li><a href="/privacy-e-sicurezza"   title="Privacy e sicurezza">Privacy e sicurezza</a></li>

        <!-- Aggiunte utili -->
        <li><a href="/cookie-policy"         title="Cookie policy">Cookie policy</a></li>
        <li><a href="/faq"                   title="Domande frequenti">FAQ</a></li>
        <li><a href="/assistenza"            title="Assistenza">Assistenza</a></li>
        <!-- Segnalazione abusi via email dedicata -->
        <li><a href="mailto:abusi@arena.example" title="Segnala un abuso">Segnalazione abusi</a></li>
      </ul>
    </nav>

    <!-- META: © anno + brand + bottone 'Torna su' -->
    <div class="site-footer__meta">
      <!-- Anno lato server (niente JS necessario) -->
      <span>© <?= date('Y'); ?> ARENA. Tutti i diritti riservati.</span>

      <!-- Link "torna su": semplice anchor; se vuoi, possiamo aggiungere smooth scroll JS in futuro -->
      <a class="back-to-top" href="#" aria-label="Torna all’inizio">↑</a>
    </div>

  </div>
</footer>
