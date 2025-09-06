document.addEventListener('DOMContentLoaded', function(){
  var drw = document.getElementById('g-drawer');
  var open = document.querySelector('[data-g-open]');
  var close = document.querySelector('[data-g-close]');
  var dim = document.querySelector('[data-g-dim]');
  function on(){ drw && drw.classList.add('on'); }
  function off(){ drw && drw.classList.remove('on'); }
  open && open.addEventListener('click', on);
  close && close.addEventListener('click', off);
  dim && dim.addEventListener('click', off);
});
