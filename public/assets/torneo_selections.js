// public/assets/torneo_selections.js
(function () {
  // Serve che torneo.php esponga la card info con data-tid e CSRF in window.CSRF
  var card = document.querySelector('.card.card--ps[data-tid]');
  if (!card) return;
  var TOURNAMENT_ID = card.getAttribute('data-tid');

  // Gestione vita selezionata (cuori)
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

  // Attacca il logo scelto a fianco del cuore (UI)
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

  // Click su un lato (home/away) → salvataggio sul server
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

      var eventId = evCard.getAttribute('data-event-id'); // deve esserci su torneo.php
      var side    = sideEl.getAttribute('data-side');     // 'home' | 'away'
      if (!eventId || !side) {
        if (window.showMsg) window.showMsg('Salvataggio non riuscito', 'Parametri non validi (event o side mancanti).', 'error');
        return;
      }

      var logoUrl = (side === 'home') ? evCard.getAttribute('data-home-logo')
                                      : evCard.getAttribute('data-away-logo');

      // CSRF preso da window (torneo.php lo setta prima)
      var csrf = (window.CSRF || (document.querySelector('input[name="csrf"]') || {}).value || '');

      inFlight = true;
      fetch('/api/save_selection.php', {
        method: 'POST',
        credentials: 'same-origin',            // include cookie/sessione
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:
          'csrf='           + encodeURIComponent(csrf) +
          '&tournament_id=' + encodeURIComponent(TOURNAMENT_ID) +
          '&event_id='      + encodeURIComponent(eventId) +
          '&life_index='    + encodeURIComponent(String(selectedLife)) +
          '&side='          + encodeURIComponent(side) +
          '&_ts='           + Date.now()          // cache-buster
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
          if (msg === 'bad_params')   msg = 'Parametri non validi.';
          if (msg === 'locked')       msg = 'Le scelte sono bloccate.';
          if (msg === 'not_enrolled') msg = 'Non sei iscritto a questo torneo.';
          if (msg === 'bad_csrf')     msg = 'Sessione scaduta: ricarica e riprova.';
          if (msg === 'exception')    msg = 'Errore interno.';
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

  // Se dopo un “aggiungi vita” rigeneri i cuori via JS esterno, chiama questo per ri-binderli:
  window.rebindHeartsForSelections = bindHearts;
})();
