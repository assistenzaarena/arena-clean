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

  // === NUOVO: mappa scelte per vita (persistenza forte)
  // struttura: { [lifeIndex]: { eventId:String, side:'home'|'away' } }
  var selectedByLife = {};

  // === NUOVO: storage locale per persistenza post-refresh
  var LS_KEY = 'arena_sel_t' + TOURNAMENT_ID;
  function loadFromStorage() {
    try {
      var txt = localStorage.getItem(LS_KEY);
      return txt ? (JSON.parse(txt) || {}) : {};
    } catch (_) { return {}; }
  }
  function saveToStorage(map) {
    try { localStorage.setItem(LS_KEY, JSON.stringify(map || {})); } catch (_) {}
  }

  // === NUOVO: applica il flag ✓ sulla squadra della vita corrente
  function updateSelectedMarker() {
    // rimuove eventuali flag precedenti
    document.querySelectorAll('.team-side--selected').forEach(function(el){
      el.classList.remove('team-side--selected');
    });
    if (selectedLife === null) return;
    var sel = selectedByLife[selectedLife];
    if (!sel || !sel.eventId || !sel.side) return;
    var selector = '.event-card[data-event-id="'+ sel.eventId +'"] .team-side[data-side="'+ sel.side +'"]';
    var el = document.querySelector(selector);
    if (el) el.classList.add('team-side--selected');
  }

  function bindHearts() {
    document.querySelectorAll('.life-heart').forEach(function (h) {
      h.addEventListener('click', function () {
        document.querySelectorAll('.life-heart').forEach(function (x) { x.classList.remove('life-heart--active'); });
        h.classList.add('life-heart--active');
        selectedLife = parseInt(h.getAttribute('data-life') || '0', 10);
        // ogni volta che seleziono una vita, aggiorno squadre disabilitate
        refreshDisabledTeams(selectedLife);
        // e riposiziono il flag ✓ (persistenza forte)
        updateSelectedMarker();
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

  // Carica scelte correnti e attacca i loghi accanto ai cuori
  function loadSelections() {
    fetch('/api/get_selections.php?tournament_id=' + encodeURIComponent(TOURNAMENT_ID), {
      method: 'GET',
      credentials: 'same-origin'
    })
    .then(function (r) { return r.text(); })
    .then(function (txt) {
      var js = null; try { js = txt ? JSON.parse(txt) : null; } catch (e) {}
      // reset locale
      selectedByLife = {};

      if (js && js.ok && Array.isArray(js.items)) {
        js.items.forEach(function (it) {
          if (typeof it.life_index === 'undefined') return;

          // logo sui cuori (se presente)
          if (it.logo_url) {
            attachLogoToHeart(parseInt(it.life_index,10), it.logo_url);
          }

          // ricostruzione flag persistente se l'API fornisce event_id/side
          if (it.event_id && it.side) {
            selectedByLife[parseInt(it.life_index,10)] = {
              eventId: String(it.event_id),
              side: String(it.side)  // 'home' | 'away'
            };
          }
        });
      }

      // === NUOVO: merge con storage locale (fallback se server non fornisce id/side)
      var fromLS = loadFromStorage();
      Object.keys(fromLS).forEach(function(k){
        if (!selectedByLife[k]) selectedByLife[k] = fromLS[k];
      });

      // mostra il flag in base alla vita attuale
      updateSelectedMarker();
    })
    .catch(function(){
      // anche in caso di errore server, tenta almeno dalla cache locale
      selectedByLife = loadFromStorage();
      updateSelectedMarker();
    });
  }

  // rende richiamabile dall'esterno il ricaricamento dei loghi
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
      var teamId  = sideEl.getAttribute('data-team-id-raw') || sideEl.getAttribute('data-team-id'); // << preferisci RAW

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
              '&team_id=' + encodeURIComponent(teamId) +          // << AGGIUNTO
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

        // OK -> UI
        attachLogoToHeart(selectedLife, js.team_logo || logoUrl);

        // FLASH elegante momentaneo
        try {
          sideEl.classList.add('team-side--flash');
          setTimeout(function(){ sideEl.classList.remove('team-side--flash'); }, 900);
        } catch(_) {}

        // === NUOVO: aggiorna la mappa persistente e il flag fisso
        selectedByLife[selectedLife] = { eventId: String(eventId), side: String(side) };
        // salva anche su storage locale (persistenza post-refresh)
        saveToStorage(selectedByLife);

        updateSelectedMarker();

        if (window.showMsg) window.showMsg('Scelta salvata', 'Selezione registrata.', 'success');
      })
      .catch(function () {
        if (window.showMsg) window.showMsg('Errore di rete', 'Controlla la connessione e riprova.', 'error');
      })
      .finally(function () { inFlight = false; });
    });
  });

  // ====== NUOVO: funzione che colora grigio le squadre in base a used/blocked + fallback ======
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
          // Disabilito solo blocked e (eventuale) last_fallback_team
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

  // Se dopo “aggiungi vita” rigeneri i cuori, chiama:
  window.rebindHeartsForSelections = bindHearts;
})();
