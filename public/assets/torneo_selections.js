// public/assets/torneo_selections.js
(function () {
  // Richiede in pagina:
  // - .card.card--ps[data-tid] (ID torneo)
  // - window.CSRF valorizzato (da torneo.php)
  // - cuori .life-heart[data-life]
  // - card evento .event-card[data-event-id][data-home-logo][data-away-logo]

  var infoCard = document.querySelector('.card.card--ps[data-tid]');
  if (!infoCard) return;

  var TOURNAMENT_ID = infoCard.getAttribute('data-tid');
  var CSRF = (window.CSRF || (document.querySelector('input[name="csrf"]') || {}).value || '');

  var selectedLife = null;

  function bindHearts() {
    document.querySelectorAll('.life-heart').forEach(function (h) {
      h.addEventListener('click', function () {
        document.querySelectorAll('.life-heart').forEach(function (x) { x.classList.remove('life-heart--active'); });
        h.classList.add('life-heart--active');
        selectedLife = parseInt(h.getAttribute('data-life') || '0', 10);

        // NEW: aggiorna squadre disabilitate per la vita selezionata (usate/bloccate)
        refreshDisabledTeams(selectedLife);
      });
    });
  }
  bindHearts();

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

  // NEW: colora grigio le squadre già usate o non disponibili nel round corrente
  function refreshDisabledTeams(lifeIndex){
    if (lifeIndex === null || typeof lifeIndex === 'undefined') return;

    fetch('/api/used_teams.php?tournament_id=' + encodeURIComponent(TOURNAMENT_ID) + '&life_index=' + encodeURIComponent(String(lifeIndex)), {
      method: 'GET',
      credentials: 'same-origin'
    })
    .then(function(r){ return r.text(); })
    .then(function(txt){
      var js = null; try { js = txt ? JSON.parse(txt) : null; } catch(e){}
      if (!js || !js.ok) return;

      // reset
      document.querySelectorAll('.team-side').forEach(function(el){
        el.classList.remove('disabled');
      });

      // disabilita già usate
      (js.used || []).forEach(function(teamId){
        document.querySelectorAll('.team-side[data-team-id="'+ teamId +'"]').forEach(function(el){
          el.classList.add('disabled');
        });
      });

      // disabilita bloccate (evento non selezionabile nel round corrente)
      (js.blocked || []).forEach(function(teamId){
        document.querySelectorAll('.team-side[data-team-id="'+ teamId +'"]').forEach(function(el){
          el.classList.add('disabled');
        });
      });
    })
    .catch(function(){ /* silenzioso */ });
  }

  // Carica scelte correnti e attacca i loghi accanto ai cuori
  function loadSelections() {
    fetch('/api/get_selections.php?tournament_id=' + encodeURIComponent(TOURNAMENT_ID), {
      method: 'GET',
      credentials: 'same-origin'
    })
    .then(function (r) { return r.text(); })
    .then(function (txt) {
      var js = null; try { js = txt ? JSON.parse(txt) : null; } catch (e) {}
      if (!js || !js.ok || !Array.isArray(js.items)) return;

      // Per ogni item (life_index + logo_url) attacca il logo corretto
      js.items.forEach(function (it) {
        if (typeof it.life_index === 'undefined' || !it.logo_url) return;
        attachLogoToHeart(parseInt(it.life_index,10), it.logo_url);
      });
    })
    .catch(function(){ /* silenzioso */ });
  }
  loadSelections();

  // Salvataggio selezione al click lato (home/away)
  var inFlight = false;
  document.querySelectorAll('.event-card .team-side').forEach(function (sideEl) {
    sideEl.addEventListener('click', function () {
      if (inFlight) return;

      // NEW: blocca click su squadre disabilitate
      if (sideEl.classList.contains('disabled')) {
        if (window.showMsg) window.showMsg('Non selezionabile', 'Questa squadra non è disponibile con la vita selezionata.', 'error');
        return;
      }

      if (selectedLife === null) {
        if (window.showMsg) window.showMsg('Seleziona una vita', 'Seleziona prima un cuore (vita) e poi la squadra.', 'error');
        return;
      }

      var evCard = sideEl.closest('.event-card');
      if (!evCard) return;

      var eventId = evCard.getAttribute('data-event-id');
      var side    = sideEl.getAttribute('data-side');
      if (!eventId || !side) {
        if (window.showMsg) window.showMsg('Salvataggio non riuscito', 'Parametri non validi.', 'error');
        return;
      }

      var logoUrl = (side === 'home') ? evCard.getAttribute('data-home-logo')
                                      : evCard.getAttribute('data-away-logo');

      inFlight = true;
      fetch('/api/save_selection.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf=' + encodeURIComponent(CSRF) +
              '&tournament_id=' + encodeURIComponent(TOURNAMENT_ID) +
              '&event_id=' + encodeURIComponent(eventId) +
              '&life_index=' + encodeURIComponent(String(selectedLife)) +
              '&side=' + encodeURIComponent(side) +
              '&_ts=' + Date.now()
      })
      .then(function (r) { return r.text(); })
      .then(function (txt) {
        var js = null; try { js = txt ? JSON.parse(txt) : null; } catch (e) {}
        if (!js) {
          if (window.showMsg) window.showMsg('Errore', 'Risposta non valida:\n' + (txt || '(vuota)'), 'error');
          return;
        }
        if (!js.ok) {
          var msg = js.error || 'errore';
          if      (msg === 'bad_params')         msg = 'Parametri non validi.';
          else if (msg === 'locked')             msg = 'Le scelte sono bloccate.';
          else if (msg === 'not_enrolled')       msg = 'Non sei iscritto a questo torneo.';
          else if (msg === 'bad_csrf')           msg = 'Sessione scaduta: ricarica la pagina.';
          else if (msg === 'life_out_of_range')  msg = 'Indice vita non valido.';
          else if (msg === 'event_invalid')      msg = 'Evento non valido.';
          else if (msg === 'team_already_used')  msg = 'Con questa vita hai già usato questa squadra.';
          else if (msg === 'event_wrong_round')  msg = 'L’evento non appartiene al round corrente.';
          else if (msg === 'exception')  msg = (js.msg || 'Errore interno.');
          if (window.showMsg) window.showMsg('Salvataggio non riuscito', msg, 'error');
          return;
        }
        // OK -> UI
        attachLogoToHeart(selectedLife, js.team_logo || logoUrl);
        if (window.showMsg) window.showMsg('Scelta salvata', 'Selezione registrata.', 'success');
      })
      .catch(function () {
        if (window.showMsg) window.showMsg('Errore di rete', 'Controlla la connessione e riprova.', 'error');
      })
      .finally(function () { inFlight = false; });
    });
  });

  // Se dopo “aggiungi vita” rigeneri i cuori, chiama:
  window.rebindHeartsForSelections = bindHearts;
})();
