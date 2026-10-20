<?php /** Adds a "Show" button to every password box on the page. Include just before </body>. */ ?>
<style>
  .pw-wrap{position:relative;display:block;} .pw-wrap input{padding-right:64px !important;}
  .pw-show{position:absolute;right:10px;top:calc(50% - 8px);transform:translateY(-50%);background:none !important;border:none;color:#0F5257 !important;font:800 .74rem/1 inherit;cursor:pointer;padding:6px 8px !important;width:auto !important;border-radius:6px !important;letter-spacing:.02em;}
  .pw-show:hover{background:rgba(15,82,87,.08) !important;}
</style>
<script>
(function(){
  document.querySelectorAll('input[type="password"]').forEach(function(inp){
    if (inp.dataset.pw || inp.closest('.pw-row')) return; inp.dataset.pw = '1';
    var wrap = document.createElement('span'); wrap.className = 'pw-wrap';
    inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(inp);
    var b = document.createElement('button'); b.type = 'button'; b.className = 'pw-show'; b.textContent = 'Show'; b.setAttribute('aria-label', 'Show password');
    b.addEventListener('click', function(){ var show = inp.type === 'password'; inp.type = show ? 'text' : 'password'; b.textContent = show ? 'Hide' : 'Show'; });
    wrap.appendChild(b);
  });
})();
</script>
