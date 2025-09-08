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
        // ogni volta che seleziono una vita, aggiorno squadre disabilitate
        refreshDisabledTeams(selectedLife);
        // evidenzia il flag della vita selezionata (se già presente)
        highlightByLife(selectedLife);
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

  // Rimuove i flag persistenti legati a una specifica vita (senza toccare gli altri)
  function clearSelectedForLife(lifeIndex) {
    document.querySelectorAll('.team-side.team-side--selected').forEach(function(el){
      if (el.getAttribute('data-selected-life') === String(lifeIndex)) {
        el.classList.remove('team-side--selected');
        el.removeAttribute('data-selected-life');
      }
    });
  }

  // Evidenzia (scroll/visivo) il flag relativo alla vita selezionata (se esiste)
  function highlightByLife(lifeIndex){
    var target = document.querySelector('.team-side.team-side--selected[data-selected-life="'+ String(lifeIndex) +'"]');
    if (!target) return;
    // piccolo flash senza rimuovere lo stato
    target.classList.add('team-side--flash');
    setTimeout(function(){ target.classList.remove('team-side--flash'); }, 900);
  }

  // Carica scelte correnti e:
  //  - attacca i loghi ai cuori
  //  - applica i flag persistenti per OGNI vita (non solo la prima)
  function loadSelections() {
    fetch('/api/get_selections.php?tournament_id=' + encodeURIComponent(TOURNAMENT_ID), {
      method: 'GET',
      credentials: 'same-origin'
    })
    .then(function (r) { return r.text(); })
    .then(function (txt) {
      var js = null; try { js = txt ? JSON.parse(txt) : null; } catch (e) {}
      if (!js || !js.ok || !Array.isArray(js.items)) return;

      // pulizia completa dei flag (ricostruisco da server)
      document.querySelectorAll('.team-side.team-side--selected').forEach(function(el){
        el.classList.remove('team-side--selected','team-side--flash');
        el.removeAttribute('data-selected-life');
      });

      js.items.forEach(function (it) {
        var lifeIdx = (typeof it.life_index !== 'undefined') ? parseInt(it.life_index,10) : null;

        // 1) Logo accanto al cuore
        if (lifeIdx !== null && it.logo_url) {
          attachLogoToHeart(lifeIdx, it.logo_url);
        }

        // 2) Flag persistente sulla squadra scelta
        //    Priorità di matching: event_id + side (sicuro) – non richiede id squadra
        if (it.event_id && it.side) {
          var card = document.querySelector('.event-card[data-event-id="'+ String(it.event_id) +'"]');
          if (card) {
            var target = card.querySelector('.team-side[data-side="'+ String(it.side) +'"]');
            if (target) {
              target.classList.add('team-side--selected');
              if (lifeIdx !== null) target.setAttribute('data-selected-life', String(lifeIdx));
            }
          }
        }
      });

      // se ho già una vita selezionata, richiama un piccolo highlight su quella
      if (selectedLife !== null) {
        highlightByLife(selectedLife);
      }
    })
    .catch(function(){ /* silenzioso */ });
  }

  // rende richiamabile dall'esterno il ricaricamento dei loghi/flag
  window.reloadSelectionsForHearts = loadSelections;

  loadSelections();

  // Salvataggio selezione al click lato (home/away)
  var inFlight = false;
  document.querySelectorAll('.event-card .team-side').forEach(function (sideEl) {
    sideEl.addEventListener('click', function () {
      if (inFlight) return;
      if (selectedLife === null) {
        if (window.showMsg) window.showMsg('Seleziona una vita', 'Seleziona prima un cuore (vita) e poi la squadra.', 'error');
        return;
      }

      var evCard = sideEl.closest('.event-card');
      if (!evCard) return;

      var eventId = evCard.getAttribute('data-event-id');
      var side    = sideEl.getAttribute('data-side');
      var teamId  = sideEl.getAttribute('data-team-id-raw') || sideEl.getAttribute('data-team-id'); // preferisci RAW

      if (!eventId || !side || !teamId) {
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
              '&team_id=' + encodeURIComponent(teamId) +
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
          else if (msg === 'team_already_used')  msg = 'Con questa vita hai già usato questa squadra in questo giro.';
          else if (msg === 'fallback_same_twice') msg = 'Non puoi ripetere la stessa fallback della scorsa volta.';
          else if (msg === 'exception')          msg = 'Errore interno.';
          if (window.showMsg) window.showMsg('Salvataggio non riuscito', msg, 'error');
          return;
        }

        // === OK -> UI ===
        attachLogoToHeart(selectedLife, js.team_logo || logoUrl);

        // rimuovo SOLO i flag della vita corrente, poi applico quello nuovo
        clearSelectedForLife(selectedLife);
        sideEl.classList.add('team-side--selected', 'team-side--flash');
        sideEl.setAttribute('data-selected-life', String(selectedLife));
        setTimeout(function(){ sideEl.classList.remove('team-side--flash'); }, 1200);

        if (window.showMsg) window.showMsg('Scelta salvata', 'Selezione registrata.', 'success');
      })
      .catch(function () {
        if (window.showMsg) window.showMsg('Errore di rete', 'Controlla la connessione e riprova.', 'error');
      })
      .finally(function () { inFlight = false; });
    });
  });

  // ====== Colora grigio le squadre (used/blocked + fallback) ======
  function refreshDisabledTeams(lifeIndex){
    if (lifeIndex === null) return;
    fetch('/api/used_teams.php?tournament_id=' + encodeURIComponent(TOURNAMENT_ID) + '&life_index=' + encodeURIComponent(lifeIndex),
          {credentials:'same-origin'})
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(js){
        if (!js || !js.ok) return;

        // reset: tolgo tutti i disabled
        document.querySelectorAll('.team-side').forEach(function(el){
          el.classList.remove('disabled');
          el.style.pointerEvents = '';
          el.style.opacity = '';
          el.title = '';
        });

        var toDisable = new Set();

        if (js.fallback === true) {
          // Fallback mode: NON disabilito le "used"
          (js.blocked || []).forEach(function(teamId){ toDisable.add(String(teamId)); });
          if (js.last_fallback_team) toDisable.add(String(js.last_fallback_team));
        } else {
          // Modalità normale: disabilito used + blocked
          (js.used || []).forEach(function(teamId){ toDisable.add(String(teamId)); });
          (js.blocked || []).forEach(function(teamId){ toDisable.add(String(teamId)); });
        }

        // applica disabilitazione
        toDisable.forEach(function(teamId){
          document.querySelectorAll('.team-side[data-team-id="'+teamId+'"]').forEach(function(el){
            el.classList.add('disabled');
            el.style.pointerEvents = 'none';
            el.style.opacity = '0.3';
            el.title = (js.fallback === true ? 'Fallback: squadra non consentita' : 'Già usata o bloccata');
          });
        });
      })
      .catch(function(){});
  }

  // Espone il rebinding quando rigeneri i cuori
  window.rebindHeartsForSelections = bindHearts;
})();
