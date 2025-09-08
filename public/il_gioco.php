<?php
// public/il_gioco.php — Regolamento & Come si gioca (Guest)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__; // <-- /var/www/html

// Header guest
require_once $ROOT . '/header_guest.php';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>ARENA — Il Gioco</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    :root{
      --c-bg:#0f0f10; --c-card:#141517; --c-text:#e8e8e8; --c-muted:#a9a9a9;
      --c-accent:#e21b2c; --c-accent-2:#ff394a; --maxw: 980px;
    }
    body{color:var(--c-text);}
    .wrap{max-width:var(--maxw); margin:40px auto 80px; padding:0 16px;}
    .page-title{ text-align:center; margin: 8px 0 24px;}
    .page-title h1{ font-size: clamp(28px, 4.6vw, 48px); margin:0; font-weight:900;}
    .page-title p{ color:var(--c-muted); margin: 8px 0 0; }

    .toc{
      background:var(--c-card); border:1px solid #222; border-radius:12px; padding:16px;
      margin: 16px 0 32px;
    }
    .toc strong{display:block; margin-bottom:8px;}
    .toc a{ color:#fff; text-decoration:none; }
    .toc a:hover{ color:var(--c-accent-2); }

    .section{ margin: 32px 0; background:var(--c-card); border:1px solid #222; border-radius:12px; }
    .section>.hd{ padding:18px 18px 0; }
    .section>.bd{ padding:14px 18px 18px; color:#d8d8d8; }
    .section h2{ font-size: clamp(20px, 3.2vw, 28px); margin:0 0 8px; }
    .pill{ display:inline-block; padding:4px 10px; border-radius:999px; background:#1b1c1f; border:1px solid #2a2b2e; color:#cfcfcf; font-size:12px; }

    .grid-2{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    @media (max-width: 820px){ .grid-2{ grid-template-columns:1fr; } }

    .rule-list li{ margin:6px 0; }
    .tip{ font-size:14px; color:var(--c-muted); }

    .callout{
      background:linear-gradient(180deg, rgba(226,27,44,.1), rgba(226,27,44,.05));
      border:1px solid rgba(226,27,44,.35); border-radius:12px; padding:14px 16px; color:#ffdede;
    }

    .table{ width:100%; border-collapse:separate; border-spacing:0; overflow:hidden; border-radius:10px; }
    .table th, .table td{ text-align:left; padding:10px 12px; border-bottom:1px solid #262626; }
    .table th{ background:#1a1b1e; }
    .table tr:last-child td{ border-bottom:none; }

    .cta{
      text-align:center; margin:36px 0 16px;
    }
/* CTA verde ovale stile "Registrati" */
.btn-enter{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  height:44px;
  padding:0 18px;
  border:1px solid #00c074;
  border-radius:9999px;
  background:#00c074;
  color:#fff;
  font-weight:800;
  font-size:16px;
  text-decoration:none;
  box-shadow:0 2px 12px rgba(0,192,116,.22);
  transition:background .2s, border-color .2s, transform .15s ease;
}
.btn-enter:hover{
  background:#00a862;
  border-color:#00a862;
  transform: translateY(-1px);
}

/* Mobile */
@media (max-width: 900px){
  .btn-enter{
    height:40px;
    padding:0 16px;
    font-size:14px;
  }
}
    .muted{ color:var(--c-muted); font-size:13px; }

    .badge{ font-size:12px; padding:2px 8px; border-radius: 6px; background:#1e1f22; border:1px solid #2a2b2e; }
    .updated{ color:#9b9b9b; font-size:12px; margin-top:6px; }
  </style>
</head>
<body>

  <div class="wrap">
    <div class="page-title">
      <h1>Come funziona l’Arena</h1>
      <p>Modalità Survivor calcistica: scegli bene, sopravvivi, conquista la gloria.</p>
      <div class="updated">Ultimo aggiornamento: <?php echo date('d/m/Y'); ?></div>
    </div>

    <!-- Indice -->
    <div class="toc">
      <strong>Indice rapido</strong>
      <div class="grid-2">
        <div>
          <a href="#panoramica">• Panoramica</a><br>
          <a href="#iscrizione">• Iscrizione & requisiti</a><br>
          <a href="#meccanica">• Meccanica di gioco</a><br>
          <a href="#regole">• Regole principali</a><br>
          <a href="#eccezioni">• Eccezioni & casi particolari</a>
        </div>
        <div>
          <a href="#esempi">• Esempi veloci</a><br>
          <a href="#premi">• Premi & classifiche</a><br>
          <a href="#fairplay">• Fair play & sanzioni</a><br>
          <a href="#faq">• FAQ</a><br>
          <a href="#note">• Note legali</a>
        </div>
      </div>
    </div>

    <!-- Panoramica -->
    <section id="panoramica" class="section">
      <div class="hd"><h2>Panoramica</h2></div>
      <div class="bd">
        <p>L’Arena è un torneo a eliminazione in stile <em>Survivor</em> basato sulle giornate di calcio. In ogni turno scegli una squadra; se soddisfa il requisito del turno, <strong>resti in gioco</strong>. In caso contrario, <strong>perdi una vita</strong> (o vieni eliminato se non hai più vite).</p>
        <p class="tip">La formula è flessibile: alcuni parametri possono variare a seconda del singolo torneo (vite, numero partecipanti, leghe supportate…). Ogni torneo indica sempre i parametri validi prima dell’iscrizione.</p>
      </div>
    </section>

    <!-- Iscrizione -->
    <section id="iscrizione" class="section">
      <div class="hd"><h2>Iscrizione & requisiti</h2></div>
      <div class="bd">
        <ul class="rule-list">
          <li><strong>Quota</strong>: paghi l’iscrizione in <b>crediti</b>. Il valore è indicato nella pagina del torneo (<span class="badge">es. 5 crediti</span>).</li>
          <li><strong>Requisiti</strong>: account verificato, accettazione T&C, accettazione regolamento.</li>
          <li><strong>Chiusura iscrizioni</strong>: prima dell’inizio della Round 1 del torneo.</li>
          <li><strong>Rientri</strong>: <span class="pill">opzionale</span> Se abilitati, puoi rientrare entro un limite di turni pagando una fee. (Vedi dettagli nel torneo specifico.)</li>
        </ul>
      </div>
    </section>

    <!-- Meccanica -->
    <section id="meccanica" class="section">
      <div class="hd"><h2>Meccanica di gioco</h2></div>
      <div class="bd">
        <div class="grid-2">
          <div>
            <h3>Obiettivo</h3>
            <p>Restare in vita fino all’ultimo turno. <strong>Ultimo giocatore vivo = Campione dell’Arena.</strong></p>
            <h3>Scelta della squadra</h3>
            <ul class="rule-list">
              <li>Ogni turno scegli <strong>una squadra</strong> che gioca in quella giornata.</li>
              <li><strong>Non puoi riutilizzare</strong> la stessa squadra in turni successivi.</li>
              <li>La scelta si blocca alla chiusura indicata (tipicamente <strong>5 minuti prima</strong> dell'inizio del primo evento in programma).</li>
            </ul>
          </div>
          <div>
            <h3>Esito del turno</h3>
            <ul class="rule-list">
              <li><strong>Vittoria</strong> della tua squadra → <b>Resti in vita</b>.</li>
              <li><strong>Sconfitta o Pareggio</strong> → <b>Perdi la vita</b>.</li>
              <li><strong></strong> → modalità configurabile:
                <ul>
                </ul>
              </li>
            </ul>
          </div>
        </div>
        <div class="callout"><strong>Pro tip:</strong> pianifica le scelte in anticipo: bruciare subito le big può lasciarti scoperto nei turni finali.</div>
      </div>
    </section>

    <!-- Regole -->
    <section id="regole" class="section">
      <div class="hd"><h2>Regole principali</h2></div>
      <div class="bd">
        <ul class="rule-list">
          <li><strong>Una squadra per turno.</strong> Nessun cambio dopo la chiusura.</li>
          <li><strong>Squadre disponibili</strong>: solo quelle indicate nel calendario del torneo.</li>
          <li><strong>Unicità</strong>: la stessa squadra non può essere associata più di una volta alla stessa squadra.</li>
          <li><strong>Trasparenza</strong>: orari/turni e risultati provengono da feed ufficiali (es. API di provider sportivi).</li>
          <li><strong>Tiebreak</strong> quando restano più vincitori al termine:
            <ul>
              <li><span class="pill">Jackpot split</span>: il montepremi si divide in parti uguali.</li>
              <li><span class="pill">Vite</span>: Quando una vita finisce il ciclo delle scelte, il ciclo ricomincia. In caso di impossibilità di scelta per eventi non disponibili, la vita può essere riassociata eccezionalmente a una squadra già scelta.</li>
            </ul>
          </li>
        </ul>
      </div>
    </section>

    <!-- Eccezioni -->
    <section id="eccezioni" class="section">
      <div class="hd"><h2>Eccezioni & casi particolari</h2></div>
      <div class="bd">
        <ul class="rule-list">
          <li><strong>Partita rinviata</strong>: Se rinviata oltre la finestra del turno, <b>la vita passa il turno</b>.</li>
          <li><strong>Partita sospesa/abbandonata</strong>: <b>la vita passa il turno</b>.</li>
          <li><strong>Calendario compresso</strong>: Se più partite vengono rinviate, l'organizzazione ha la facoltà di annullare il round in corso.</li>
          <li><strong>Errori tecnici</strong>: In caso di malfunzionamenti che impediscono la scelta a più utenti, l’organizzazione può estendere la finestra o azzerare il turno.</li>
        </ul>
      </div>
    </section>

    <!-- Esempi -->
    <section id="esempi" class="section">
      <div class="hd"><h2>Esempi veloci</h2></div>
      <div class="bd">
        <table class="table">
          <thead>
            <tr><th>Turno</th><th>Tua scelta</th><th>Risultato</th><th>Scelta effettuata</th><th>Vite residue</th></tr>
          </thead>
          <tbody>
            <tr><td>1</td><td>Juventus</td><td>Vittoria</td><td>Vittoria</td><td>1 → 1</td></tr>
            <tr><td>2</td><td>Roma</td><td>Pareggio</td><td>Vittoria</td><td>1 → 0 (eliminato)</td></tr>
            <tr><td>2</td><td>Inter VS Milan</td><td>Rinviata</td><td>Vittoria Milan</td><td>1 → 1</td></tr>
            <tr><td>3</td><td>Fiorentina</td><td>Sconfitta</td><td>Vittoria</td><td>1 → 0 (eliminato)</td></tr>
          </tbody>
        </table>
        <p class="tip">I nomi squadra sono d’esempio. Fa fede l’elenco ufficiale squadre/partite del torneo.</p>
      </div>
    </section>

    <!-- Premi -->
    <section id="premi" class="section">
      <div class="hd"><h2>Premi, classifiche & montepremi</h2></div>
      <div class="bd">
        <ul class="rule-list">
          <li><strong>Montepremi</strong>: somma delle quote al netto della fee di servizio.</li>
          <li><strong>Assegnazione</strong>:
            <ul>
              <li><span class="pill">Winner-Takes-All</span>: tutto al campione.</li>
              <li><span class="pill">Top-N</span>: ripartizione percentuale sui primi N.</li>
              <li><span class="pill">Split</span>: se più sopravvissuti finali.</li>
            </ul>
          </li>
          <li><strong>Classifica live</strong>: mostra vite residue, storico scelte e stato “Vivo/Eliminato”.</li>
        </ul>
      </div>
    </section>

    <!-- Fair play -->
    <section id="fairplay" class="section">
      <div class="hd"><h2>Fair play, account & sanzioni</h2></div>
      <div class="bd">
        <ul class="rule-list">
          <li><strong>Un account per persona</strong>. Multi-account = squalifica e ban account.</li>
          <li><strong>Collusione</strong> e condivisione di scelte coordinate per alterare l’esito → squalifica e ban account.</li>
          <li><strong>Abusi tecnici</strong> (bot, scraping malevolo, exploit) → ban immediato.</li>
          <li><strong>Controversie</strong>: è previsto un canale reclami; decisione finale dell’organizzazione.</li>
        </ul>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="section">
      <div class="hd"><h2>FAQ</h2></div>
      <div class="bd">
        <div class="grid-2">
          <div>
            <p><strong>Posso cambiare squadra dopo aver confermato?</strong><br>
              Puoi cambiare squadra fino al lock del round, dopo la chiusura del turno la scelta è bloccata.</p>
            <p><strong>Se dimentico di scegliere?</strong><br>
              Perdi 1 vita (come sconfitta).
            <p><strong>Vale il risultato ai rigori?</strong><br>
              Se il turno riguarda la gara nei 90’, fa fede il 90’. Se è un turno “coppa” configurato sui passaggi del turno, fa fede il verdetto ufficiale.</p>
          </div>
          <div>
            <p><strong>Cosa succede con partite rinviate?</strong><br>
             (vedi sezione Vite).</p>
            <p><strong>Quante vite ho?</strong><br>
              Dipende dal torneo. Controlla sempre la scheda del torneo.</p>
            <p><strong>Quando ricevo i premi?</strong><br>
              Dopo la validazione dell’ultimo turno e dei controlli anti-abuso.</p>
          </div>
        </div>
      </div>
    </section>

<!-- CTA -->
<div class="cta">
  <a class="btn-enter" href="/registrazione.php">Entra nell’Arena</a>
  <div class="muted">Pronto a giocarti la gloria? Iscriviti al prossimo torneo ufficiale.</div>
</div>

    <!-- Note legali -->
    <section id="note" class="section">
      <div class="hd"><h2>Note legali & trasparenza</h2></div>
      <div class="bd">
        <p class="tip">Questo documento descrive il funzionamento generale dell’Arena. Ogni torneo può avere integrazioni/deroghe esplicitate nella relativa scheda. In caso di conflitto, vale la pagina del torneo. L’organizzazione può aggiornare il regolamento per motivi tecnici o regolatori: la data in alto indica l’ultima revisione.</p>
      </div>
    </section>
  </div>

<?php
// Footer
if (file_exists($ROOT . '/footer_public.php')) {
  require_once $ROOT . '/footer_public.php';
} elseif (file_exists($ROOT . '/footer.php')) {
  require_once $ROOT . '/footer.php';
}
?>
</body>
</html>
