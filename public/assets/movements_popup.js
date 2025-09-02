// public/assets/movements_popup.js
(function(){
  if (!document || !document.body) return;

  // Trova il link "Lista movimenti" nell'header
  function findMovementsLink(){
    var anchors = document.querySelectorAll('a, [role="link"]');
    for (var i=0; i<anchors.length; i++){
      var a = anchors[i];
      var href = (a.getAttribute && a.getAttribute('href')) || '';
      var txt  = (a.textContent || '').toLowerCase();
      if (href.indexOf('lista_movimenti') !== -1 || txt.indexOf('lista movimenti') !== -1) {
        return a;
      }
    }
    return null;
  }

  // Overlay + Modal (compatto, centrato)
  var overlay = document.createElement('div');
  overlay.style.position = 'fixed';
  overlay.style.inset = '0';
  overlay.style.background = 'rgba(0,0,0,.6)';
  overlay.style.display = 'none';
  overlay.style.alignItems = 'center';
  overlay.style.justifyContent = 'center';
  overlay.style.zIndex = '100000';
  overlay.style.padding = '16px';

  var modal = document.createElement('div');
  modal.style.background = '#0f1114';
  modal.style.color = '#fff';
  modal.style.border = '1px solid rgba(255,255,255,.15)';
  modal.style.borderRadius = '14px';
  modal.style.maxWidth = '720px';
  modal.style.width = '96vw';
  modal.style.maxHeight = '80vh';                // compatto
  modal.style.display = 'flex';
  modal.style.flexDirection = 'column';
  modal.style.boxShadow = '0 24px 60px rgba(0,0,0,.35)';
  overlay.appendChild(modal);

  var header = document.createElement('div');
  header.style.display = 'flex';
  header.style.alignItems = 'center';
  header.style.justifyContent = 'space-between';
  header.style.padding = '12px 14px';
  header.style.borderBottom = '1px solid rgba(255,255,255,.12)';
  modal.appendChild(header);

  var title = document.createElement('div');
  title.textContent = 'Lista movimenti';
  title.style.fontSize = '18px';
  title.style.fontWeight = '900';
  header.appendChild(title);

  var close = document.createElement('button');
  close.textContent = 'CHIUDI';
  close.style.background = '#333';
  close.style.color = '#fff';
  close.style.border = '0';
  close.style.borderRadius = '8px';
  close.style.padding = '6px 10px';
  close.style.fontWeight = '900';
  close.style.cursor = 'pointer';
  header.appendChild(close);

  var content = document.createElement('div');
  content.style.padding = '10px 12px';
  content.style.overflow = 'auto';              // scroll interno
  content.style.flex = '1 1 auto';
  modal.appendChild(content);

  var footer = document.createElement('div');
  footer.style.display = 'flex';
  footer.style.alignItems = 'center';
  footer.style.justifyContent = 'space-between';
  footer.style.padding = '10px 12px';
  footer.style.borderTop = '1px solid rgba(255,255,255,.12)';
  modal.appendChild(footer);

  var btnPrev = document.createElement('button');
  btnPrev.textContent = '← Precedente';
  btnPrev.style.background = '#2b2b2b';
  btnPrev.style.color = '#fff';
  btnPrev.style.border = '0';
  btnPrev.style.borderRadius = '8px';
  btnPrev.style.padding = '6px 10px';
  btnPrev.style.fontWeight = '900';
  btnPrev.style.cursor = 'pointer';

  var btnNext = document.createElement('button');
  btnNext.textContent = 'Successivo →';
  btnNext.style.background = '#2b2b2b';
  btnNext.style.color = '#fff';
  btnNext.style.border = '0';
  btnNext.style.borderRadius = '8px';
  btnNext.style.padding = '6px 10px';
  btnNext.style.fontWeight = '900';
  btnNext.style.cursor = 'pointer';

  var pageInfo = document.createElement('div');
  pageInfo.style.color = '#cfcfcf';
  pageInfo.style.fontSize = '12px';
  pageInfo.style.fontWeight = '800';

  footer.appendChild(btnPrev);
  footer.appendChild(pageInfo);
  footer.appendChild(btnNext);

  document.body.appendChild(overlay);

  function closeModal(){ overlay.style.display = 'none'; content.innerHTML=''; }
  overlay.addEventListener('click', function(e){ if (e.target === overlay) closeModal(); });
  close.addEventListener('click', closeModal);

  function formatAmount(n){
    var v = Number(n||0);
    var sign = v >= 0 ? '+' : '−';
    return sign + Math.abs(v).toLocaleString('it-IT') + ' crediti';
  }

  function typeBadge(type, label){
    var span = document.createElement('span');
    span.textContent = label || type;
    span.style.fontSize = '11px';
    span.style.fontWeight = '900';
    span.style.letterSpacing = '.4px';
    span.style.padding = '2px 8px';
    span.style.borderRadius = '999px';
    span.style.marginRight = '10px';
    span.style.textTransform = 'uppercase';
    var map = {
      'recharge' : '#1f8e46',
      'withdraw' : '#6846ff',
      'payout'   : '#0a7f5e',
      'enroll'   : '#8A0F0F',
      'buy_life' : '#b85a00',
      'unenroll' : '#6c6c6c'
    };
    var bg = map[type] || '#2b2b2b';
    span.style.background = bg;
    span.style.color = '#fff';
    return span;
  }

  function renderRows(items){
    var table = document.createElement('table');
    table.style.width = '100%';
    table.style.borderCollapse = 'collapse';
    table.style.fontSize = '14px';

    var thead = document.createElement('thead');
    var trh = document.createElement('tr');
    ['Data', 'Tipo', 'Dettagli', 'Importo'].forEach(function(h){
      var th = document.createElement('th');
      th.textContent = h.toUpperCase();
      th.style.textAlign = 'left';
      th.style.padding = '8px 6px';
      th.style.borderBottom = '1px solid rgba(255,255,255,.08)';
      th.style.fontSize = '11px';
      th.style.letterSpacing = '.5px';
      th.style.color = '#cfcfcf';
      trh.appendChild(th);
    });
    thead.appendChild(trh);
    table.appendChild(thead);

    var tbody = document.createElement('tbody');

    items.forEach(function(it){
      var tr = document.createElement('tr');

      var td0 = document.createElement('td');
      td0.textContent = it.ts_h || '';
      td0.style.padding = '8px 6px';
      td0.style.borderBottom = '1px solid rgba(255,255,255,.06)';
      td0.style.color = '#ddd';
      tr.appendChild(td0);

      var td1 = document.createElement('td');
      td1.style.padding = '8px 6px';
      td1.style.borderBottom = '1px solid rgba(255,255,255,.06)';
      var badge = typeBadge(it.type, it.type_label);
      td1.appendChild(badge);
      tr.appendChild(td1);

      var td2 = document.createElement('td');
      td2.style.padding = '8px 6px';
      td2.style.borderBottom = '1px solid rgba(255,255,255,.06)';
      td2.style.color = '#ccc';
      if (it.tournament_code || it.tournament_name) {
        var t = [];
        if (it.tournament_name) t.push(it.tournament_name);
        if (it.tournament_code) t.push('#'+it.tournament_code);
        td2.textContent = t.join(' — ');
      } else {
        td2.textContent = '—';
      }
      tr.appendChild(td2);

      var td3 = document.createElement('td');
      td3.style.padding = '8px 6px';
      td3.style.borderBottom = '1px solid rgba(255,255,255,.06)';
      td3.style.fontWeight = '900';
      td3.style.textAlign = 'right';
      td3.style.color = (it.sign === 'in') ? '#00c074' : '#ff6b6b';
      td3.textContent = formatAmount(it.amount);
      tr.appendChild(td3);

      tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    return table;
  }

  // stato di navigazione
  var state = { page: 1, pages: 1, limit: 12, busy: false };

  function updateButtons(){
    btnPrev.disabled = (state.page <= 1);
    btnNext.disabled = (state.page >= state.pages);
    btnPrev.style.opacity = btnPrev.disabled ? '.45' : '1';
    btnNext.style.opacity = btnNext.disabled ? '.45' : '1';
    pageInfo.textContent = 'Pagina ' + state.page + ' di ' + state.pages;
  }

  function loadPage(p){
    if (state.busy) return;
    state.busy = true;
    content.innerHTML = '<div style="color:#bbb;padding:6px 4px;">Carico movimenti...</div>';
    fetch('/api/movements_list.php?page='+encodeURIComponent(p)+'&limit='+encodeURIComponent(state.limit), { credentials:'same-origin' })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(js){
        state.busy = false;
        if (!js || !js.ok) { content.innerHTML = '<div style="color:#ff6b6b;">Errore nel caricamento.</div>'; return; }
        state.page  = js.page || 1;
        state.pages = js.pages || 1;
        state.limit = js.limit || state.limit;

        var items = js.items || [];
        if (!items.length){
          content.innerHTML = '<div style="color:#ddd;">Nessun movimento trovato.</div>';
        } else {
          content.innerHTML = '';
          content.appendChild(renderRows(items));
        }
        updateButtons();
      })
      .catch(function(){
        state.busy = false;
        content.innerHTML = '<div style="color:#ff6b6b;">Errore di rete.</div>';
      });
  }

  btnPrev.addEventListener('click', function(){ if (state.page > 1) loadPage(state.page - 1); });
  btnNext.addEventListener('click', function(){ if (state.page < state.pages) loadPage(state.page + 1); });

  // open/close
  function openPopup(){ overlay.style.display = 'flex'; loadPage(1); }
  function closePopup(){ overlay.style.display = 'none'; }

  overlay.addEventListener('click', function(e){ if (e.target === overlay) closePopup(); });
  close.addEventListener('click', closePopup);

  var link = findMovementsLink();
  if (link){
    link.addEventListener('click', function(e){
      // click normale → popup
      if (!(e.metaKey || e.ctrlKey || e.shiftKey)) {
        e.preventDefault();
        openPopup();
      }
      // con Ctrl/Cmd/Shift lasciamo la navigazione originale (se vuoi)
    });
  }
})();
