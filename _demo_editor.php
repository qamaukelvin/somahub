<?php
// Browser-only editing sandbox for the demo school (demo.somahub.top).
// Nothing is ever sent to the server: edits live in this visitor's page and
// vanish on refresh, so any number of people can "edit" at the same time
// without seeing each other's changes or touching the real demo data.
// Expects $db, $school and $demoEmbed in scope.
require_once __DIR__ . '/includes/appearance.php';
$demoPalettes = [];
foreach (get_active_palettes($db) as $p) {
    $v = json_decode($p['css_variables_json'], true) ?: [];
    if (!empty($v['primary'])) $demoPalettes[] = ['n' => $p['name'], 'p' => $v['primary'], 'a' => $v['accent'] ?? '#F2A65A', 'b' => $v['bg'] ?? '#FBF8F2'];
}
$demoFonts = [];
foreach (get_font_pairs() as $k => $f) $demoFonts[] = ['k' => $k, 'l' => $f['label'], 'd' => $f['display'], 'b' => $f['body']];
$demoAuto = isset($_GET['try']); // embedded copies wait for the visitor to press 'start editing' on somahub.top
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<style>
  .dm-i{font-family:'Material Symbols Outlined';font-weight:400;font-style:normal;line-height:1;display:inline-block;vertical-align:middle;font-size:19px;}
  #dmPill{position:fixed;right:16px;bottom:16px;z-index:99990;background:#0F5257;color:#fff;border:none;border-radius:999px;padding:12px 18px;font:800 .88rem Arial,sans-serif;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.28);display:flex;gap:8px;align-items:center;}
  #dmBar{position:fixed;left:50%;bottom:14px;transform:translateX(-50%);z-index:99990;background:#1C1C16;color:#fff;border-radius:16px;padding:8px 10px;display:none;gap:6px;align-items:center;flex-wrap:wrap;justify-content:center;box-shadow:0 12px 34px rgba(0,0,0,.38);font:700 .8rem Arial,sans-serif;max-width:calc(100vw - 20px);}
  body.dm-on #dmBar{display:flex;} body.dm-on #dmPill{display:none;}
  #dmBar button{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:10px;padding:8px 12px;font:inherit;cursor:pointer;display:inline-flex;gap:6px;align-items:center;}
  #dmBar button:hover{background:rgba(255,255,255,.22);} #dmBar .dm-main{background:#F2A65A;color:#0A3A3E;border-color:#F2A65A;}
  #dmBadge{background:#E4573D;border-radius:999px;padding:1px 7px;font-size:.7rem;}
  .dm-pop{position:absolute;bottom:calc(100% + 10px);left:50%;transform:translateX(-50%);background:#fff;color:#1C1C16;border-radius:14px;padding:12px;width:290px;box-shadow:0 14px 36px rgba(0,0,0,.3);display:none;text-align:left;}
  .dm-pop.open{display:block;} .dm-pop h5{margin:0 0 8px;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#777;}
  .dm-pal{display:grid;grid-template-columns:1fr 1fr;gap:6px;max-height:210px;overflow:auto;}
  .dm-pal button{background:#fff !important;color:#1C1C16 !important;border:1.5px solid #E2DCC6 !important;border-radius:9px !important;padding:6px 8px !important;font-size:.72rem !important;gap:6px;}
  .dm-pal button:hover{border-color:#0F5257 !important;} .dm-dots span{display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:-4px;border:1.5px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.12);}
  .dm-pop select{width:100%;padding:9px;border:1px solid #ccc;border-radius:8px;font:inherit;color:#1C1C16;}
  body.dm-on [data-dm-edit]{outline:1.5px dashed transparent;outline-offset:3px;cursor:text;transition:outline-color .12s;}
  body.dm-on [data-dm-edit]:hover{outline-color:#F2A65A;} body.dm-on [data-dm-edit]:focus{outline:2px solid #F2A65A;}
  body.dm-on [data-dm-img]{cursor:pointer;outline:2px dashed transparent;outline-offset:-2px;} body.dm-on [data-dm-img]:hover{outline-color:#F2A65A;}
  .dm-eye{display:none;position:absolute;top:10px;right:10px;z-index:50;background:#1C1C16;color:#fff;border:none;border-radius:999px;padding:7px 12px;font:700 .72rem Arial,sans-serif;cursor:pointer;gap:5px;align-items:center;opacity:.92;}
  body.dm-on .dm-eye{display:inline-flex;} body.dm-on section[id]{position:relative;}
  section.dm-hidden>*:not(.dm-eye){display:none !important;} section.dm-hidden{min-height:56px;background:repeating-linear-gradient(45deg,#f1ede0,#f1ede0 10px,#e9e4d2 10px,#e9e4d2 20px) !important;}
  #dmReview{position:fixed;inset:0;z-index:99995;background:rgba(10,30,32,.6);display:none;align-items:center;justify-content:center;padding:16px;}
  #dmReview.open{display:flex;} #dmReview .dm-card{background:#fff;color:#1C1C16;border-radius:18px;max-width:520px;width:100%;max-height:86vh;overflow:auto;padding:22px;font-family:Arial,sans-serif;}
  #dmReview h3{margin:0 0 4px;} #dmReview h4{margin:16px 0 6px;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#0F5257;}
  #dmReview ul{margin:0;padding-left:18px;font-size:.88rem;} #dmReview li{margin-bottom:6px;} #dmReview del{color:#a33;} #dmReview ins{color:#1B7A4E;text-decoration:none;font-weight:700;}
  #dmReview .dm-acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;} #dmReview .dm-acts a,#dmReview .dm-acts button{border-radius:999px;padding:11px 18px;font:800 .85rem Arial,sans-serif;cursor:pointer;text-decoration:none;border:2px solid #0F5257;background:#fff;color:#0F5257;}
  #dmReview .dm-acts .go{background:#0F5257;color:#fff;} #dmTip{position:fixed;top:0;left:0;right:0;z-index:99989;background:#0F5257;color:#fff;font:600 .8rem Arial,sans-serif;text-align:center;padding:8px 14px;display:none;}
  #dmBar button:disabled{opacity:.35;cursor:default;}
  .dm-sec{display:none;position:absolute;top:10px;right:10px;z-index:60;} body.dm-on .dm-sec{display:block;}
  .dm-dots3{background:#1C1C16;color:#fff;border:none;border-radius:999px;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.25);}
  .dm-menu{display:none;position:absolute;top:40px;right:0;background:#fff;color:#1C1C16;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.28);min-width:190px;padding:6px;font-family:Arial,sans-serif;}
  .dm-menu.open{display:block;} .dm-menu button{display:flex;gap:10px;align-items:center;width:100%;background:none;border:none;border-radius:8px;padding:10px 12px;font:700 .82rem Arial,sans-serif;color:#1C1C16;cursor:pointer;text-align:left;} .dm-menu button:hover{background:#F4F1E6;} .dm-menu button.del{color:#C0392B;}
  .dm-lay{display:none;position:absolute;top:10px;left:10px;z-index:60;} body.dm-on .dm-lay{display:block;}
  .dm-lay>button{background:#1C1C16;color:#fff;border:none;border-radius:999px;height:34px;padding:0 14px 0 10px;cursor:pointer;display:flex;gap:6px;align-items:center;font:700 .78rem Arial,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.25);} .dm-lay .dm-menu{left:0;right:auto;max-height:260px;overflow:auto;}
  .dm-menu button.on{background:#E7F0EE;color:#0F5257;}
  @media(max-width:640px){#dmBar .dm-lbl{display:none;} #dmBar button{padding:10px 12px;} #dmBar button#dmReviewBtn{gap:4px;}}
  .dm-menu hr{border:none;border-top:1px solid #eee;margin:4px 0;}
  body.dm-on #dmTip{display:block;} body.dm-on{padding-top:34px;} @media(max-width:640px){#dmBar{left:6px;right:6px;transform:none;justify-content:center;} #dmTip{font-size:.72rem;padding:6px 10px;}}
</style>
<div id="dmTip">Demo mode: change anything you like. Edits stay on your screen only and disappear when you refresh. Nobody else can see them.</div>
<button type="button" id="dmPill"><span class="dm-i">edit</span> Try the editor</button>
<div id="dmBar">
  <div style="position:relative;"><button type="button" data-pop="dmCol"><span class="dm-i">palette</span><span class="dm-lbl">Colours</span></button>
    <div class="dm-pop" id="dmCol"><h5>Colour schemes</h5><div class="dm-pal" id="dmPal"></div>
      <h5 style="margin-top:10px;">Your own</h5><div style="display:flex;gap:10px;align-items:center;font-size:.75rem;color:#555;">Main <input type="color" id="dmC1" value="#0F5257"> Accent <input type="color" id="dmC2" value="#F2A65A"></div></div></div>
  <div style="position:relative;"><button type="button" data-pop="dmFont"><span class="dm-i">text_fields</span><span class="dm-lbl">Font</span></button>
    <div class="dm-pop" id="dmFont"><h5>Font pairing</h5><select id="dmFontSel"><option value="">Keep current</option></select></div></div>
  <button type="button" id="dmUndo" title="Undo" disabled><span class="dm-i">undo</span></button><button type="button" id="dmRedo" title="Redo" disabled><span class="dm-i">redo</span></button>
  <button type="button" id="dmReviewBtn" class="dm-main"><span class="dm-i">fact_check</span><span class="dm-lbl">Review changes</span> <span id="dmBadge">0</span></button>
  <button type="button" id="dmReset"><span class="dm-i">restart_alt</span><span class="dm-lbl">Reset</span></button>
</div>
<input type="file" id="dmFile" accept="image/*" style="display:none">
<div id="dmReview"><div class="dm-card"><h3>Your changes</h3><p style="margin:0 0 4px;color:#666;font-size:.85rem;">This is what you changed in this demo. On your own site, every change here saves automatically.</p><div id="dmList"></div>
  <div class="dm-acts"><button type="button" id="dmClose">Keep editing</button><button type="button" id="dmResetAll">Reset all</button><a class="go" href="https://somahub.top/get-started.php" target="_top">Create my own site</a></div></div></div>
<script>
(function(){
  var PAL = <?= json_encode($demoPalettes, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>, FONTS = <?= json_encode($demoFonts, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
  var AUTO = <?= $demoAuto ? 'true' : 'false' ?>, LAY = <?= json_encode($demoLayouts ?? (object)[], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>, LAYSTATE = {};
  var body = document.body, root = document.documentElement, orig = new Map(), hidden = new Set(), photos = new Map(), colour = null, font = null, undo = [], redo = [], struct = 0, lastCol = 0;
  var TEXT = 'body > section h1, body > section h2, body > section h3, body > section p, body > section li, body > section .btn-primary, body > footer div';
  function $(id){ return document.getElementById(id); }
  function secName(el){ var s = el.closest('section'); if (!s) return 'Footer'; var h = s.querySelector('h2'); return (h && h.textContent.trim()) || (s.id ? s.id.replace(/_/g,' ') : 'Top of page'); }
  function esc(t){ var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
  function cut(t){ t = t.replace(/\s+/g,' ').trim(); return t.length > 70 ? t.slice(0,67) + '...' : t; }
  function changed(){ var n = 0; orig.forEach(function(o, el){ if (el.textContent.trim() !== o) n++; }); n += hidden.size + photos.size + Math.abs(struct) + (colour ? 1 : 0) + (font ? 1 : 0); return n; }
  function refresh(){ $('dmBadge').textContent = changed(); }

  function syncUR(){ $('dmUndo').disabled = !undo.length; $('dmRedo').disabled = !redo.length; }
  function push(a){ undo.push(a); redo.length = 0; struct += a.sd || 0; refresh(); syncUR(); }
  $('dmUndo').onclick = function(){ var a = undo.pop(); if (!a) return; a.u(); struct -= a.sd || 0; redo.push(a); refresh(); syncUR(); };
  $('dmRedo').onclick = function(){ var a = redo.pop(); if (!a) return; a.r(); struct += a.sd || 0; undo.push(a); refresh(); syncUR(); };
  function wireText(el){
    if (el.hasAttribute('data-dm-edit')) return;
    el.setAttribute('data-dm-edit', ''); el.contentEditable = 'true'; el.spellcheck = false;
    if (!orig.has(el)) orig.set(el, el.textContent.trim());
    el.addEventListener('input', refresh);
    el.addEventListener('focus', function(){ el._dmB = el.textContent; });
    el.addEventListener('blur', function(){ var b = el._dmB, a = el.textContent; if (b !== undefined && b !== a) push({u: function(){ el.textContent = b; }, r: function(){ el.textContent = a; }}); });
    el.addEventListener('keydown', function(e){ if (e.key === 'Enter') e.preventDefault(); });
  }
  function eligible(el){ return !(el.closest('form,nav,header,#dmBar,#dmReview,.dm-sec') || (el.children.length > 0 && !(el.children.length === 1 && el.children[0].tagName === 'BR')) || !el.textContent.trim()); }
  function sections(){ return Array.prototype.slice.call(document.querySelectorAll('body > section[id]')); }
  function setHidden(s, h){ s.classList.toggle('dm-hidden', h); h ? hidden.add(s) : hidden.delete(s); var b = s.querySelector('.dm-hide'); if (b) b.innerHTML = '<span class="dm-i">' + (h ? 'visibility' : 'visibility_off') + '</span>' + (h ? 'Show on site' : 'Hide from site'); }
  function wireSection(s){
    if (s.querySelector(':scope > .dm-sec')) return;
    var w = document.createElement('div'); w.className = 'dm-sec';
    w.innerHTML = '<button type="button" class="dm-dots3" aria-label="Section menu"><span class="dm-i">more_vert</span></button><div class="dm-menu">'
      + '<button type="button" class="dm-up"><span class="dm-i">arrow_upward</span>Move up</button><button type="button" class="dm-dn"><span class="dm-i">arrow_downward</span>Move down</button>'
      + '<button type="button" class="dm-dup"><span class="dm-i">content_copy</span>Duplicate</button><button type="button" class="dm-hide"><span class="dm-i">visibility_off</span>Hide from site</button><hr>'
      + '<button type="button" class="dm-rm del"><span class="dm-i">delete</span>Remove section</button></div>';
    var menu = w.querySelector('.dm-menu');
    w.querySelector('.dm-dots3').addEventListener('click', function(e){ e.stopPropagation(); var was = menu.classList.contains('open'); document.querySelectorAll('.dm-menu').forEach(function(m){ m.classList.remove('open'); }); if (!was) menu.classList.add('open'); });
    function doMove(dir){
      var all = sections(), i = all.indexOf(s), t = all[i + dir]; if (!t) return;
      var swap = function(d){ var a2 = sections(), j = a2.indexOf(s), t2 = a2[j + d]; if (!t2) return; s.parentNode.insertBefore(s, d < 0 ? t2 : t2.nextSibling); };
      swap(dir); push({u: function(){ swap(-dir); }, r: function(){ swap(dir); }, sd: 1}); s.scrollIntoView({block: 'center', behavior: 'smooth'});
    }
    w.querySelector('.dm-up').onclick = function(){ menu.classList.remove('open'); doMove(-1); };
    w.querySelector('.dm-dn').onclick = function(){ menu.classList.remove('open'); doMove(1); };
    w.querySelector('.dm-hide').onclick = function(){ menu.classList.remove('open'); var h = !s.classList.contains('dm-hidden'); setHidden(s, h); push({u: function(){ setHidden(s, !h); }, r: function(){ setHidden(s, h); }}); };
    w.querySelector('.dm-dup').onclick = function(){
      menu.classList.remove('open');
      var c = s.cloneNode(true); c.id = (s.id || 'sec') + '-copy' + Date.now().toString(36); c.querySelectorAll('.dm-sec').forEach(function(x){ x.remove(); }); c.querySelectorAll('[data-dm-edit]').forEach(function(x){ x.removeAttribute('data-dm-edit'); });
      s.parentNode.insertBefore(c, s.nextSibling); wireAll(c);
      push({u: function(){ c.remove(); }, r: function(){ s.parentNode.insertBefore(c, s.nextSibling); }, sd: 1}); c.scrollIntoView({block: 'center', behavior: 'smooth'});
    };
    w.querySelector('.dm-rm').onclick = function(){
      menu.classList.remove('open'); var nx = s.nextSibling, par = s.parentNode; s.remove();
      push({u: function(){ par.insertBefore(s, nx); }, r: function(){ s.remove(); }, sd: 1});
    };
    s.appendChild(w);
    var L = LAY[s.id];
    if (L && !s.querySelector(':scope > .dm-lay')) {
      var lw = document.createElement('div'); lw.className = 'dm-lay';
      lw.innerHTML = '<button type="button" class="dm-layb"><span class="dm-i">dashboard_customize</span>Layout</button><div class="dm-menu"></div>';
      var lm = lw.querySelector('.dm-menu'), cur = LAYSTATE[s.id] || L.cur;
      L.options.forEach(function(o){ var b = document.createElement('button'); b.type = 'button'; if (o.k === cur) b.className = 'on'; b.innerHTML = '<span class="dm-i">' + o.i + '</span>' + esc(o.l); b.onclick = function(){ lm.classList.remove('open'); swapLayout(s, o.k); }; lm.appendChild(b); });
      lw.querySelector('.dm-layb').addEventListener('click', function(e){ e.stopPropagation(); var was = lm.classList.contains('open'); document.querySelectorAll('.dm-menu').forEach(function(m){ m.classList.remove('open'); }); if (!was) lm.classList.add('open'); });
      s.appendChild(lw);
    }
  }
  function swapLayout(s, k){
    var key = s.id, cur = LAYSTATE[key] || LAY[key].cur; if (k === cur) return;
    var q = ['embed=1']; Object.keys(LAYSTATE).forEach(function(x){ if (x !== key) q.push('dv[' + encodeURIComponent(x) + ']=' + encodeURIComponent(LAYSTATE[x])); }); q.push('dv[' + encodeURIComponent(key) + ']=' + encodeURIComponent(k));
    body.classList.add('dm-busy');
    fetch(location.pathname + '?' + q.join('&'), {credentials: 'same-origin'}).then(function(r){ return r.text(); }).then(function(t){
      var doc = new DOMParser().parseFromString(t, 'text/html'), n = doc.querySelector('body > section[id="' + key + '"]');
      if (!n) throw new Error('missing');
      n = document.importNode(n, true);
      n.querySelectorAll('.dm-sec,.dm-lay').forEach(function(x){ x.remove(); });
      if (s.classList.contains('dm-hidden')) n.classList.add('dm-hidden');
      s.parentNode.replaceChild(n, s); LAYSTATE[key] = k; wireAll(n);
      push({u: function(){ n.parentNode.replaceChild(s, n); LAYSTATE[key] = cur; }, r: function(){ s.parentNode.replaceChild(n, s); LAYSTATE[key] = k; }, sd: 1});
      n.scrollIntoView({block: 'start', behavior: 'smooth'});
    }).catch(function(){ alert('Could not change the layout just now. Please try again.'); }).then(function(){ body.classList.remove('dm-busy'); });
  }
  function wireAll(scope){
    scope.querySelectorAll(TEXT).forEach(function(el){ if (eligible(el)) wireText(el); });
    scope.querySelectorAll('img').forEach(function(im){ im.setAttribute('data-dm-img', ''); });
    if (scope.matches && scope.matches('body > section[id]')) wireSection(scope);
    scope.querySelectorAll('body > section[id]').forEach(wireSection);
  }
  function enable(){
    body.classList.add('dm-on'); wireAll(document.body);
  }
  function disable(){ body.classList.remove('dm-on'); document.querySelectorAll('[data-dm-edit]').forEach(function(el){ el.contentEditable = 'false'; el.removeAttribute('data-dm-edit'); }); document.querySelectorAll('.dm-menu').forEach(function(m){ m.classList.remove('open'); }); }
  // links/buttons must not navigate while editing
  document.addEventListener('click', function(e){
    if (!body.classList.contains('dm-on')) return;
    var img = e.target.closest('[data-dm-img]');
    if (img) { e.preventDefault(); window.__dmImg = img; $('dmFile').click(); return; }
    if (e.target.closest('body > section a, body > section button:not(.dm-dots3):not(.dm-menu button)') && !e.target.closest('#dmBar')) e.preventDefault();
  }, true);
  $('dmFile').addEventListener('change', function(){
    var f = this.files[0], im = window.__dmImg; if (!f || !im) return;
    var prevSrc = im.src, prevSet = im.getAttribute('srcset'), u = URL.createObjectURL(f); im.removeAttribute('srcset'); im.src = u; photos.set(im, f.name);
    push({u: function(){ im.src = prevSrc; if (prevSet) im.setAttribute('srcset', prevSet); photos.delete(im); }, r: function(){ im.removeAttribute('srcset'); im.src = u; photos.set(im, f.name); }}); this.value = '';
  });

  function setCol(c){
    if (!c) { ['--primary','--accent','--bg'].forEach(function(k){ root.style.removeProperty(k); }); colour = null; refresh(); return; }
    root.style.setProperty('--primary', c.p); root.style.setProperty('--accent', c.a); if (c.b) root.style.setProperty('--bg', c.b);
    colour = c; $('dmC1').value = c.p; $('dmC2').value = c.a; refresh();
  }
  function applyColours(p, a, b){
    var prev = colour, next = {p: p, a: a, b: b}, now = Date.now(), top = undo[undo.length - 1];
    setCol(next);
    if (top && top.k === 'col' && now - lastCol < 900) { top.r = function(){ setCol(next); }; }
    else push({k: 'col', u: function(){ setCol(prev); }, r: function(){ setCol(next); }});
    lastCol = now;
  }
  var pal = $('dmPal');
  PAL.forEach(function(x){
    var b = document.createElement('button'); b.type = 'button';
    b.innerHTML = '<span class="dm-dots"><span style="background:' + x.p + '"></span><span style="background:' + x.a + '"></span><span style="background:' + x.b + '"></span></span>' + esc(x.n);
    b.onclick = function(){ applyColours(x.p, x.a, x.b); }; pal.appendChild(b);
  });
  $('dmC1').addEventListener('input', function(){ applyColours(this.value, $('dmC2').value, null); });
  $('dmC2').addEventListener('input', function(){ applyColours($('dmC1').value, this.value, null); });
  var fs = $('dmFontSel');
  FONTS.forEach(function(f){ var o = document.createElement('option'); o.value = f.k; o.textContent = f.l; fs.appendChild(o); });
  function setFont(f){
    var st = $('dmFontCss') || document.head.appendChild(Object.assign(document.createElement('style'), {id: 'dmFontCss'}));
    if (!f) { st.textContent = ''; font = null; fs.value = ''; refresh(); return; }
    var l = document.createElement('link'); l.rel = 'stylesheet';
    l.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(f.d).replace(/%20/g,'+') + ':wght@500;600;700&family=' + encodeURIComponent(f.b).replace(/%20/g,'+') + ':wght@400;500;600&display=swap';
    document.head.appendChild(l);
    st.textContent = "body{font-family:'" + f.b + "',sans-serif !important}h1,h2,h3,.brand,.stat-item .number{font-family:'" + f.d + "',sans-serif !important}";
    font = f.l; fs.value = f.k; refresh();
  }
  fs.addEventListener('change', function(){
    var f = FONTS.filter(function(x){ return x.k === fs.value; })[0]; if (!f) return;
    var prev = FONTS.filter(function(x){ return x.l === font; })[0] || null;
    setFont(f); push({u: function(){ setFont(prev); }, r: function(){ setFont(f); }});
  });
  document.querySelectorAll('#dmBar [data-pop]').forEach(function(b){
    b.addEventListener('click', function(e){ e.stopPropagation(); var p = $(b.dataset.pop), was = p.classList.contains('open'); document.querySelectorAll('.dm-pop').forEach(function(x){ x.classList.remove('open'); }); if (!was) p.classList.add('open'); });
  });
  document.addEventListener('click', function(e){ if (!e.target.closest('.dm-pop')) document.querySelectorAll('.dm-pop').forEach(function(x){ x.classList.remove('open'); }); if (!e.target.closest('.dm-sec')) document.querySelectorAll('.dm-menu').forEach(function(x){ x.classList.remove('open'); }); });

  function review(){
    var h = '', t = [], i;
    orig.forEach(function(o, el){ var n = el.textContent.trim(); if (n !== o) t.push('<li><b>' + esc(secName(el)) + ':</b> <del>' + esc(cut(o)) + '</del> &rarr; <ins>' + esc(cut(n)) + '</ins></li>'); });
    if (t.length) h += '<h4>Text (' + t.length + ')</h4><ul>' + t.join('') + '</ul>';
    if (colour) h += '<h4>Colours</h4><ul><li>Main ' + esc(colour.p) + ', accent ' + esc(colour.a) + (colour.b ? ', background ' + esc(colour.b) : '') + '</li></ul>';
    if (font) h += '<h4>Font</h4><ul><li>' + esc(font) + '</li></ul>';
    if (struct) h += '<h4>Sections</h4><ul><li>' + Math.abs(struct) + ' section change' + (Math.abs(struct) > 1 ? 's' : '') + ' (moved, copied or removed)</li></ul>';
    if (hidden.size) { var s = []; hidden.forEach(function(x){ s.push('<li>' + esc(secName(x.querySelector('h2') || x)) + '</li>'); }); h += '<h4>Hidden sections (' + hidden.size + ')</h4><ul>' + s.join('') + '</ul>'; }
    if (photos.size) { var p = []; photos.forEach(function(n){ p.push('<li>' + esc(n) + '</li>'); }); h += '<h4>Photos replaced (' + photos.size + ')</h4><ul>' + p.join('') + '</ul>'; }
    $('dmList').innerHTML = h || '<p style="color:#666;margin-top:14px;">No changes yet. Click any text to edit it, tap a photo to swap it, or try Colours and Font.</p>';
    $('dmReview').classList.add('open');
  }
  $('dmReviewBtn').onclick = review; $('dmClose').onclick = function(){ $('dmReview').classList.remove('open'); };
  $('dmReset').onclick = $('dmResetAll').onclick = function(){ location.reload(); };
  $('dmReview').addEventListener('click', function(e){ if (e.target === this) this.classList.remove('open'); });
  $('dmPill').onclick = enable;
  window.addEventListener('message', function(e){
    var m = e.data && e.data.dmCmd; if (!m) return;
    if (m === 'start') enable(); else if (m === 'stop') disable();
  });
  if (AUTO) enable();
})();
</script>
