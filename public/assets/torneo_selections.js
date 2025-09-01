// /assets/torneo_selections.js
(function(){
  const cardInfo = document.querySelector('.card.card--ps[data-tid]');
  if(!cardInfo) return;

  const TID   = cardInfo.getAttribute('data-tid');
  const CSRF  = (window.CSRF || (document.querySelector('input[name="csrf"]')||{}).value || '');
  const livesWrap = document.getElementById('livesWrap');

  // vita selezionata
  let selectedLife = null;

  // util: aggancia logo scelta al cuore
  function attachLogoToHeart(lifeNo, logoUrl){
    const heart = livesWrap && livesWrap.querySelector('.life-heart[data-life="'+lifeNo+'"]');
    if(!heart) return;
    const old = heart.querySelector('.pick-logo'); if (old) old.remove();
    const img = document.createElement('img');
    img.className = 'pick-logo';
    img.src = logoUrl;
    img.alt = 'Pick';
    img.style.width  = '16px';
    img.style.height = '16px';
    img.style.marginLeft = '6px';
    img.onerror = function(){ this.remove(); };
    heart.appendChild(img);
  }

  // 1) Carica scelte già fatte e ricostruisci loghi accanto ai cuori
  function loadSelections(){
    fetch('/api/get_selections.php?tournament_id='+encodeURIComponent(TID), {credentials:'same-origin'})
      .then(r => r.ok ? r.json() : null)
      .then(js => {
        if(!js || !js.ok) return;
        (js.items || []).forEach(item => {
          attachLogoToHeart(item.life_no, item.logo_url);
        });
      }).catch(()=>{});
  }
  loadSelections();

  // 2) Selezione vita (cuore evidenziato)
  document.querySelectorAll('.life-heart').forEach(h=>{
    h.addEventListener('click',()=>{
      document.querySelectorAll('.life-heart').forEach(x=>x.classList.remove('life-heart--active'));
      h.classList.add('life-heart--active');
      selectedLife = parseInt(h.getAttribute('data-life')||'0',10);
    });
  });

  // 3) Click su una squadra -> salva scelta (per vita selezionata)
  document.querySelectorAll('.event-card .team-side').forEach(side=>{
    side.addEventListener('click', ()=>{
      if(selectedLife===null){
        if (window.showMsg) window.showMsg('Seleziona una vita','Seleziona prima un cuore (vita) e poi la squadra.','error');
        return;
      }
      const ec   = side.closest('.event-card');
      const sideName = side.getAttribute('data-side'); // 'home' | 'away'
      const eventId  = ec.getAttribute('data-event-id');
      const logoUrl  = (sideName==='home') ? ec.getAttribute('data-home-logo') : ec.getAttribute('data-away-logo');

      fetch('/api/select_team.php', {
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'csrf='+encodeURIComponent(CSRF)+
             '&tournament_id='+encodeURIComponent(TID)+
             '&life_no='+encodeURIComponent(selectedLife)+
             '&event_id='+encodeURIComponent(eventId)+
             '&team_side='+encodeURIComponent(sideName)
      })
      .then(r=>r.json().catch(()=>null))
      .then(js=>{
        if(!js || !js.ok){
          const msg = (js && (js.error||js.msg)) ? (js.error||js.msg) : 'errore';
          if (window.showMsg) window.showMsg('Salvataggio non riuscito', String(msg), 'error');
          return;
        }
        attachLogoToHeart(selectedLife, logoUrl);
      })
      .catch(()=>{ if(window.showMsg) window.showMsg('Errore di rete','Riprova più tardi.','error'); });
    });
  });

  // 4) Finalizzazione automatica alla scadenza del countdown (un trigger lato client)
  //    Se preferisci un CRON lato server, puoi omettere questo blocco.
  const cd = document.querySelector('.countdown[data-due]');
  if (cd){
    const due = new Date(cd.getAttribute('data-due').replace(' ','T')).getTime();
    function tryFinalize(){
      if (Date.now() >= due){
        fetch('/api/finalize_selections.php', {
          method:'POST',
          credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'tournament_id='+encodeURIComponent(TID)
        }).then(r=>r.json().catch(()=>null))
          .then(js=>{
            if(js && js.ok){
              if(window.showMsg) window.showMsg('Scelte ufficializzate','Le tue scelte sono state bloccate.','success');
            }
          }).catch(()=>{});
        return true;
      }
      return false;
    }
    // prova ogni 5s dopo la scadenza
    setInterval(tryFinalize, 5000);
  }
})();
