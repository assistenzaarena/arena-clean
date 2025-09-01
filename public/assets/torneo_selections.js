// public/assets/torneo_selections.js
(function () {
  // --- Trova l'ID torneo dalla card; fallback: querystring ?id=... ---
  function getTidFromUrl() {
    var m = location.search.match(/[?&]id=(\d+)/);
    return m ? m[1] : null;
  }
  var card = document.querySelector('.card.card--ps[data-tid]');
  var TOURNAMENT_ID = card ? card.getAttribute('data-tid') : getTidFromUrl();
  if (!TOURNAMENT_ID) {
    console.warn('[selections] TOURNAMENT_ID mancante (data-tid o ?id=)');
    return;
  }

  // --- CSRF: deve essere presente; altrimenti avvisa l'utente ---
  var CSRF = (window.CSRF || (document.querySelector('input[name="csrf"]') || {}).value || '');
  if (!CSRF) {
    if (window.showMsg) window.showMsg('Sessione scaduta', 'Ricarica la pagina e riprova.', 'error');
    console.warn('[selections] CSRF mancante: esponi <script>window.CSRF = "..."</script> in torneo.php');
    return;
  }

  // --- Gestione vita selezionata (cuori) ---
  var selectedLife = null;
  function bindHearts() {
    document.querySelectorAll('.life-heart').forEach(function (h) {
      h.addEventListener('click', function () {
        document.querySelectorAll('.life-heart').forEach(function (x) { x.classList.remove('life-heart--active'); });
        h.classList.add('life-heart--active');
        selectedLife = parseInt(h.getAttribute('data-life') || '0', 10);
      });
    });
  }
  bindHearts(); // on load
  // Esporto rebind per quando rigeneri i cuori da altre azioni
  window.rebindHeartsForSelections = bindHearts;

  // --- Attacca il logo scelto a fianco del cuore (UI) ---
  function attachLogoToHeart(lifeIndex, logoUrl) {
    var heart = document.querySelector('.life-heart[data-life="' + lifeIndex + '"]');
    if (!heart) return;
    var old = heart.querySelector('.pick-logo'); if (old) old.remove();
    var img = document.createElement('img');
    img.className = 'pick-logo';
    img.src = logoUrl;
    img.alt = 'Pick';
    img.onerror = function(){ this.remove(); }; // evita “immagine rotta”
    heart.appendChild(img);
  }

  // --- Click su un lato (home/away) → salvataggio sul server ---
  var inFlight = false;
  document.querySelectorAll('.event-card .team-side').forEach(function (sideEl) {
    sideEl.addEventListener('click', function () {
      if (inFlight) return; // antirimbalzo
      if (selectedLife === null) {
        if (window.showMsg) window.showMsg('Seleziona una vita', 'Seleziona prima un cuore (vita) e poi la squadra.', 'error');
        return;
      }
      var evCard = sideEl.closest('.event-card');
      if (!evCard) return;

      var eventId = evCard.getAttribute('data-event-id');   // deve esserci in torneo.php
      var side    = sideEl.getAttribute('data-side');       // 'home' | 'away'
      if (!eventId || !side) {
        console.warn('[selections] attributi mancanti: eventId=', eventId, 'side=', side);
        if (window.showMsg) window.showMsg('Salvataggio non riuscito', 'Parametri non validi (event o side mancanti).', 'error');
        return;
      }

      var logoUrl = (side === 'home') ? evCard.getAttribute('data-home-logo')
                                      : evCard.getAttribute('data-away-logo');
      if (!logoUrl) {
        // non blocco il salvataggio: al limite non attacco logo al cuore
        console.warn('[selections] logo URL mancante per', side, 'event', eventId);
      }

      inFlight = true;
      fetch('/api/save_selection.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:
          'csrf='           + encodeURIComponent(CSRF) +
          '&tournament_id=' + encodeURIComponent(TOURNAMENT_ID) +
          '&event_id='      + encodeURIComponent(eventId) +
          '&life_index='    + encodeURIComponent(String(selectedLife)) +
          '&side='          + encodeURIComponent(side) +
          '&_ts='           + Date.now() // cache-buster
      })
      .then(async function (r) {
        var status = r.status;
        var txt = '';
        try { txt = await r.text(); } catch (_) {}
        var js = null; try { js = txt ? JSON.parse(txt) : null; } catch (_) {}
        if (!js) {
          if (window.showMsg) window.showMsg('Errore', 'HTTP ' + status + ' (non JSON):\n' + (txt ? txt.slice(0, 400) : '(vuota)'), 'error');
          return { ok: false };
        }
        return js;
      })
      .then(function (js) {
        if (!js) return;
        if (!js.ok) {
          var msg = js.error || 'errore';
          if (msg === 'bad_params')      msg = 'Parametri non validi.';
          if (msg === 'locked' || msg==='not_open') msg = 'Le scelte sono bloccate.';
          if (msg === 'not_enrolled')    msg = 'Non sei iscritto a questo torneo.';
          if (msg === 'bad_csrf')        msg = 'Sessione scaduta: ricarica e riprova.';
          if (msg === 'life_out_of_range') msg = 'Indice vita non valido.';
          if (msg === 'bad_event')       msg = 'Evento non valido.';
          if (msg === 'exception')       msg = 'Errore interno.';
          if (window.showMsg) window.showMsg('Salvataggio non riuscito', msg, 'error');
          return;
        }
        // OK → aggiorna UI (logo vicino al cuore)
        attachLogoToHeart(selectedLife, js.team_logo || logoUrl);
        if (window.showMsg) window.showMsg('Scelta salvata', 'Selezione registrata.', 'success');
      })
      .catch(function () {
        if (window.showMsg) window.showMsg('Errore di rete', 'Controlla la connessione e riprova.', 'error');
      })
      .finally(function () {
        inFlight = false;
      });
    });
  });
})();
