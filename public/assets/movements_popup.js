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

  // Crea overlay/modal una volta
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
  modal.style.maxWidth = '880px';
  modal.style.width = '100%';
  modal.style.boxShadow = '0 24px 60px rgba(0,0,0,.35)';
  modal.style.overflow = 'hidden';
  overlay.appendChild(modal);

  var header = document.createElement('div');
  header.style.display = 'flex';
  header.style.alignItems = 'center';
  header.style.justifyContent = 'space-between';
  header.style.padding = '14px 16px';
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
  content.style.padding = '12px 14px 16px';
  modal.appendChild(content);

  document.body.appendChild(overlay);

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

    // colori per tipo
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
    // wrapper
    var wrap = document.createElement('div');
    // tabella
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

      // data
      var td0 = document.createElement('td');
      td0.textContent = it.ts_h || '';
      td0.style.padding = '8px 6px';
      td0.style.borderBottom = '1px solid rgba(255,255,255,.06)';
      td0.style.color = '#ddd';
      tr.appendChild(td0);

      // tipo (badge)
      var td1 = document.createElement('td');
      td1.style.padding = '8px 6px';
      td1.style.borderBottom = '1px solid rgba(255,255,255,.06)';
      var badge = typeBadge(it.type, it.type_label);
      td1.appendChild(badge);
      tr.appendChild(td1);

      // dettagli (torneo)
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

      // importo
      var td3 = document.createElement('td');
      td3.style.padding = '8px 6px';
      td3.style.borderBottom = '1px solid rgba(255,255,255,.06)';
      td3.style.fontWeight = '900';
      td3.style.textAlign = 'right';
      if (it.sign === 'in') {
        td3.style.color = '#00c074';
      } else {
        td3.style.color = '#ff6b6b';
      }
      td3.textContent = formatAmount(it.amount);
      tr.appendChild(td3);

      tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    wrap.appendChild(table);
    return wrap;
  }

  function openPopup(){
    content.innerHTML = '<div style="color:#bbb;padding:6px 4px;">Carico movimenti...</div>';
    overlay.style.display = 'flex';
    fetch('/api/movements_list.php', { credentials:'same-origin' })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(js){
        if (!js || !js.ok) {
          content.innerHTML = '<div style="color:#ff6b6b;">Errore nel caricamento.</div>'; return;
        }
        var items = js.items || [];
        if (!items.length) {
          content.innerHTML = '<div style="color:#ddd;">Non ci sono movimenti.</div>'; return;
        }
        content.innerHTML = '';
        content.appendChild(renderRows(items));
      })
      .catch(function(){
        content.innerHTML = '<div style="color:#ff6b6b;">Errore di rete.</div>';
      });
  }

  // chiudi
  close.addEventListener('click', function(){ overlay.style.display = 'none'; });

  // intercetta click sul link in header
  var link = findMovementsLink();
  if (link){
    link.addEventListener('click', function(e){
      e.preventDefault();
      openPopup();
    });
  }
})();
