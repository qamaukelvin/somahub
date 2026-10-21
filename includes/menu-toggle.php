<?php /** Hamburger behaviour for every nav: tap to open (icon becomes X), tap outside / a link / Esc to close. Buttons carry data-menu-toggle="<menu id>". */ ?>
<script>
(function(){
  if (window.__shMenus) return; window.__shMenus = true;
  document.querySelectorAll('[data-menu-toggle]').forEach(function(btn){
    var menu = document.getElementById(btn.getAttribute('data-menu-toggle')); if (!menu) return;
    var ic = btn.querySelector('.material-symbols-outlined');
    function set(o){
      menu.classList.toggle('open', o); btn.classList.toggle('is-open', o); btn.setAttribute('aria-expanded', o ? 'true' : 'false');
      if (ic) ic.textContent = o ? 'close' : 'menu'; else btn.textContent = o ? '✕' : '☰';
    }
    btn.setAttribute('aria-expanded', 'false');
    btn.addEventListener('click', function(e){ e.stopPropagation(); set(!menu.classList.contains('open')); });
    document.addEventListener('click', function(e){ if (menu.classList.contains('open') && !menu.contains(e.target) && !btn.contains(e.target)) set(false); });
    menu.addEventListener('click', function(e){ if (e.target.closest('a[href]')) set(false); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') set(false); });
  });
})();
</script>
