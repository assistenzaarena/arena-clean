// public/assets/winner_popup.js
(function(){
  // Esegui solo in lobby
  if (!document.body) return;

  function shouldHide(key){
    try { return localStorage.getItem(key) === '1'; } catch(_){ return false; }
  }
  function markHidden(key){
    try { localStorage.setItem(key, '1'); } catch(_){}
  }

  fetch('/api/check_winner.php', { credentials:'same-origin' })
    .then(r => r.ok ? r.json() : null)
    .then(js => {
      if (!js || !js.ok || !js.show) return;
      if (!js.key || shouldHide(js.key)) return;

      var username = (js.username || 'UTENTE').toUpperCase();
      var amount = (typeof js.amount === 'number' ? js.amount : null);
      var tcode = (js.tournament_code ? String(js.tournament_code) : '');

      // crea overlay
      var overlay = document.createElement('div');
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.background = 'rgba(0,0,0,0.55)';
      overlay.style.zIndex = '99999';
      overlay.style.display = 'flex';
      overlay.style.alignItems = 'center';
      overlay.style.justifyContent = 'center';
      overlay.style.padding = '16px';

      var card = document.createElement('div');
      card.style.background = '#0f1114';
      card.style.color = '#fff';
      card.style.border = '1px solid rgba(255,255,255,.15)';
      card.style.borderRadius = '14px';
      card.style.width = 'min(520px, 92vw)';
      card.style.boxShadow = '0 20px 60px rgba(0,0,0,.35)';
      card.style.padding = '24px 22px';
      card.style.textAlign = 'center';
      card.style.fontFamily = 'system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';

      var top = document.createElement('div');
      top.style.fontSize = '20px';
      top.style.fontWeight = '900';
      top.style.letterSpacing = '.5px';
      top.style.marginBottom = '8px';
      top.style.textTransform = 'uppercase';
      top.innerHTML = '🎉 COMPLIMENTI ' + username + ' 🎉';

      var mid = document.createElement('div');
      mid.style.fontSize = '26px';
      mid.style.fontWeight = '900';
      mid.style.margin = '6px 0 12px';
      mid.style.textTransform = 'uppercase';
      mid.innerHTML = '🏆 SEI IL RE DELL’ARENA! 🏆';

      var small = document.createElement('div');
      small.style.fontSize = '13px';
      small.style.fontWeight = '800';
      small.style.letterSpacing = '.7px';
      small.style.color = '#d3ffd8';
      small.style.marginBottom = '18px';
      small.style.textTransform = 'uppercase';
      small.textContent = 'Premio accreditato!';

      var info = document.createElement('div');
      info.style.fontSize = '12px';
      info.style.color = '#bdbdbd';
      info.style.marginBottom = '14px';
      info.textContent = (amount !== null ? ('+' + amount.toLocaleString('it-IT') + ' crediti') : '') +
                         (tcode ? ('  •  Torneo #' + tcode) : '');

      var btn = document.createElement('button');
      btn.textContent = 'GRAZIE!';
      btn.style.background = '#00c074';
      btn.style.color = '#fff';
      btn.style.border = 'none';
      btn.style.fontWeight = '900';
      btn.style.padding = '10px 18px';
      btn.style.borderRadius = '10px';
      btn.style.cursor = 'pointer';
      btn.style.letterSpacing = '.6px';
      btn.style.textTransform = 'uppercase';
      btn.addEventListener('click', function(){
        markHidden(js.key);
        document.body.removeChild(overlay);
      });

      card.appendChild(top);
      card.appendChild(mid);
      card.appendChild(small);
      card.appendChild(info);
      card.appendChild(btn);
      overlay.appendChild(card);
      overlay.addEventListener('click', function(e){
        if (e.target === overlay) { markHidden(js.key); document.body.removeChild(overlay); }
      });

      document.body.appendChild(overlay);
    })
    .catch(function(){});
})();
