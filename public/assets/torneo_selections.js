// public/assets/torneo_selections.js
(function () {
  // Serve che torneo.php esponga questi dataset/variabili nella pagina
  var card = document.querySelector('.card.card--ps[data-tid]');
  if (!card) return;
  var TOURNAMENT_ID = card.getAttribute('data-tid');

  // Vita selezionata gestita in pagina dai cuori
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
  bindHearts(); // all’avvio

  // Helper per attaccare il logo al cuore
  function attachLogoToHeart(lifeIndex, logoUrl) {
    var heart = document.querySelector('.life-heart[data-life="' + lifeIndex + '"]');
    if (!heart) return;
    var old = heart.querySelector('.pick-logo'); if (old) old.remove();
    var img = document.createElement('img');
    img.className = 'pick-logo';
    img.src = logoUrl;
    img.alt = 'Pick';
    img.onerror = function(){ this.remove(); };
    heart.appendChild(img);
  }

  // Click su un lato (home/away) -> salvataggio server
  document.querySelectorAll('.event-card .team-side').forEach(function (sideEl) {
    sideEl.addEventListener('click', function () {
      if (selectedLife === null) {
        if (window.showMsg) window.showMsg('Seleziona una vita', 'Seleziona prima un cuore (vita) e poi la squadra.', 'error');
        return;
      }

      var evCard = sideEl.closest('.event-card');
      if (!evCard) return;

      var eventId = evCard.getAttribute('data-event-id');        // <— RICHIESTA: presente in torneo.php
      var side    = sideEl.getAttribute('data-side');            // 'home' | 'away'
      var logoUrl = (side === 'home') ? evCard.getAttribute('data-home-logo')
                                      : evCard.getAttribute('data-away-logo');

      // CSRF prelevato da window (torneo.php lo ha in sessione)
      var csrf = (window.CSRF || (document.querySelector('input[name="csrf"]') || {}).value || '');

      fetch('/api/save_selection.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf=' + encodeURIComponent(csrf) +
              '&tournament_id=' + encodeURIComponent(TOURNAMENT_ID) +
              '&event_id='      + encodeURIComponent(eventId) +
              '&life_index='    + encodeURIComponent(selectedLife) +
              '&side='          + encodeURIComponent(side)
      })
      .then(function (r) { return r.text(); })
      .then(function (txt) {
        var js = null; try { js = txt ? JSON.parse(txt) : null; } catch (e) {}
        if (!js) { if (window.showMsg) window.showMsg('Errore', 'Risposta non valida dal server:\n' + (txt || '(vuota)'), 'error'); return; }
        if (!js.ok) {
          var msg = js.error || 'errore';
          if (msg === 'bad_params')      msg = 'Parametri non validi.';
          if (msg === 'locked')          msg = 'Le scelte sono bloccate.';
          if (msg === 'not_enrolled')    msg = 'Non sei iscritto a questo torneo.';
          if (msg === 'bad_csrf')        msg = 'Sessione scaduta: ricarica e riprova.';
          if (msg === 'exception')       msg = 'Errore interno.';
          if (window.showMsg) window.showMsg('Salvataggio non riuscito', msg, 'error');
          return;
        }
        // OK -> aggiorna UI (logo affianco al cuore)
        attachLogoToHeart(selectedLife, js.team_logo || logoUrl);
      })
      .catch(function () {
        if (window.showMsg) window.showMsg('Errore di rete', 'Controlla la connessione e riprova.', 'error');
      });
    });
  });

  // Se dopo un’aggiunta vita rigeneri i cuori via JS esterno, ri-binda:
  window.rebindHeartsForSelections = bindHearts;
})();
