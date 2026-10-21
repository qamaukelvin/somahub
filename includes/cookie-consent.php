<?php /** Small cookie notice. Include once per page, before </body>. Choice is remembered for a year; window.somahubCookieConsent is 'all' or 'essential'. */ ?>
<style>
  #shCookie{position:fixed;left:12px;right:12px;bottom:12px;z-index:99990;max-width:560px;margin:0 auto;background:#0A3A3E;color:#F7F2E7;border-radius:16px;padding:16px 18px;font:500 .84rem/1.5 Arial,sans-serif;box-shadow:0 14px 40px rgba(0,0,0,.35);display:none;}
  #shCookie.show{display:block;} #shCookie a{color:#F2A65A;font-weight:700;}
  #shCookie .row{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;}
  #shCookie button{border:none;border-radius:99px;padding:9px 18px;font:800 .8rem Arial,sans-serif;cursor:pointer;} #shCookie .ok{background:#F2A65A;color:#0A3A3E;} #shCookie .no{background:rgba(255,255,255,.14);color:#F7F2E7;}
</style>
<div id="shCookie" role="dialog" aria-label="Cookie notice">
  We use essential cookies to keep you logged in and your forms secure. With your OK we also remember preferences. <a href="https://somahub.top/privacy.php#cookies">How we use cookies</a>
  <div class="row"><button class="ok" type="button" data-c="all">Accept all</button><button class="no" type="button" data-c="essential">Essential only</button></div>
</div>
<script>
(function(){
  var KEY = 'sh_cookie_consent', v = '';
  try { v = localStorage.getItem(KEY) || ''; } catch (e) {}
  if (!v) { var m = document.cookie.match(/(?:^|; )sh_cookie_consent=([^;]*)/); v = m ? decodeURIComponent(m[1]) : ''; }
  window.somahubCookieConsent = v || null;
  if (v) return;
  var box = document.getElementById('shCookie'); box.classList.add('show');
  box.addEventListener('click', function(e){
    var b = e.target.closest('button[data-c]'); if (!b) return;
    v = b.getAttribute('data-c'); window.somahubCookieConsent = v;
    try { localStorage.setItem(KEY, v); } catch (x) {}
    document.cookie = KEY + '=' + v + '; max-age=31536000; path=/; domain=.somahub.top; secure; samesite=lax';
    box.classList.remove('show');
  });
})();
</script>
