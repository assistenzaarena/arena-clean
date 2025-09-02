// public/assets/lobby_fix.js
(function(){
  function layoutCols(parent){
    if (!parent) return;
    var w = window.innerWidth || document.documentElement.clientWidth;

    // 1 colonna su telefono, 3 colonne su schermi più grandi
    var cols = (w < 900) ? 1 : 3;

    parent.style.display = 'grid';
    parent.style.gridTemplateColumns = (cols === 1)
      ? '1fr'
      : 'repeat(3, minmax(300px, 1fr))';
    parent.style.gap = '16px';
    parent.style.alignItems = 'stretch';

    // titoli/separatori dentro lo stesso parent a tutta larghezza
    var titles = parent.querySelectorAll('h2, .lobby-section-title, .section-title');
    titles.forEach(function(t){ t.style.gridColumn = '1 / -1'; });

    // le card non devono forzare larghezze
    var cards = parent.querySelectorAll('.card--ps');
    cards.forEach(function(c){
      c.style.width = 'auto';
      c.style.maxWidth = 'none';
      c.style.margin = '0';
    });
  }

  function fixLobby() {
    // wrapper esplicito se esiste
    var lists = document.querySelectorAll('.lobby-list');
    lists.forEach(function(l){ if (l.querySelector('.card--ps')) layoutCols(l); });

    // fallback: qualsiasi parent che contiene >=2 card
    var cards = document.querySelectorAll('.card--ps');
    var seen = new Set();
    cards.forEach(function(card){
      var p = card && card.parentElement;
      if (!p || seen.has(p)) return;
      if (p.querySelectorAll('.card--ps').length >= 2) {
        layoutCols(p);
        seen.add(p);
      }
    });
  }

  // primo paint + resize + piccolo ritardo (font/immagini)
  window.addEventListener('load', fixLobby);
  window.addEventListener('resize', fixLobby);
  setTimeout(fixLobby, 60);
})();
