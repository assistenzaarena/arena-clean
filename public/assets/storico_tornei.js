// public/assets/storico_tornei.js
(function(){
  var overlay = document.getElementById('histOverlay');
  var body    = document.getElementById('histBody');
  var close   = document.getElementById('histClose');

  function closeModal(){ if (overlay){ overlay.style.display='none'; body.innerHTML=''; } }
  if (close){ close.addEventListener('click', closeModal); }
  if (overlay){
    overlay.addEventListener('click', function(e){ if (e.target === overlay) closeModal(); });
  }

  function pill(outcome){
    var span = document.createElement('span');
    if (outcome === 'win'){ span.className='pill-win'; span.textContent='✅ VINTA'; }
    else { span.className='pill-lose'; span.textContent='❌ PERSA'; }
    return span;
  }

  function renderTable(items){
    var html = '<table class="round-table"><thead><tr>'+
               '<th>Round</th><th>Vita</th><th>Partita</th><th>Lato</th><th>Esito</th></tr></thead><tbody>';
    items.forEach(function(it){
      var sideLabel = (it.side === 'home') ? 'Casa' : 'Trasferta';
      var tag = it.is_fallback ? '<span class="tag-fb">FALLBACK</span>' : '';
      html += '<tr>'+
              '<td>'+ it.round +'</td>'+
              '<td>'+ (it.life_index+1) +'</td>'+
              '<td>'+ it.match +'</td>'+
              '<td>'+ sideLabel +'</td>'+
              '<td>'+ (it.outcome==='win' ? '✅ VINTA' : '❌ PERSA') + (it.is_fallback? ' '+tag : '') +'</td>'+
              '</tr>';
    });
    html += '</tbody></table>';
    return html;
  }

  function openModal(tid, title){
    fetch('/api/history_selections.php?tournament_id='+encodeURIComponent(tid), { credentials:'same-origin' })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(js){
        if (!js || !js.ok){ body.innerHTML = '<div class="muted">Dati non disponibili.</div>'; return; }
        // titolo dinamico
        var header = document.querySelector('.modal-title');
        if (header) header.textContent = 'Dettaglio scelte — '+ title + ' (#'+ (js.tour && js.tour.code ? js.tour.code : tid) +')';
        // tabella
        body.innerHTML = renderTable(js.items || []);
        overlay.style.display = 'flex';
      })
      .catch(function(){ body.innerHTML = '<div class="muted">Errore di rete.</div>'; overlay.style.display='flex'; });
  }

  // bind alle card
  document.querySelectorAll('.card-red[data-tid]').forEach(function(card){
    card.addEventListener('click', function(){
      var tid = card.getAttribute('data-tid');
      var title = card.getAttribute('data-tname') || 'Torneo';
      openModal(tid, title);
    });
  });
})();
