<?php
// On-page site editor. Loaded by site.php only when the signed-in owner (or an
// admin who is impersonating this school) opens their site with ?edit=1.
// Expects $db, $school, $sections, $theme in scope.
require_once __DIR__ . '/includes/appearance.php';
require_once __DIR__ . '/includes/section_variants.php';
require_once __DIR__ . '/includes/edit-history.php';
require_once __DIR__ . '/dashboard/_live_editor_map.php';

$__registry = get_section_variant_registry();
$__editorFiles = get_live_editor_files();
$__schemas = [];
foreach ($db->query("SELECT id, key_name, label, is_premium, schema_json FROM section_types ORDER BY id")->fetchAll() as $t) $__schemas[$t['key_name']] = $t;

$__skipField = '/(_color$|^cta_|destination|_url$|_link$|^rating|_icon$)/';
$__data = [];
foreach ($sections as $s) {
    $key = $s['key_name'];
    $schema = json_decode($__schemas[$key]['schema_json'] ?? '[]', true) ?: [];
    $content = json_decode($s['content_json'], true) ?: [];
    $text = []; $imgs = [];
    foreach ($schema as $f => $type) {
        $v = $content[$f] ?? '';
        if (!is_string($v) || $v === '') continue;
        if ($type === 'image') $imgs[] = [$f, $v];
        elseif (!preg_match($__skipField, $f)) $text[] = [$f, $v];
    }
    $cfg = $__registry[$key] ?? [];
    $opts = [];
    foreach (($cfg['options'] ?? []) as $k => $o) $opts[] = ['k' => $k, 'l' => $o['label'], 'i' => $o['icon']];
    $__data[] = [
        'id' => (int)$s['id'], 'key' => $key, 'label' => $s['label'], 'visible' => (int)$s['is_visible'],
        'variant' => $s['layout_variant'] ?? ($cfg['default'] ?? ''), 'options' => $opts,
        'text' => $text, 'imgs' => $imgs, 'editFile' => $__editorFiles[$key] ?? 'section-edit.php',
    ];
}
$__types = [];
foreach ($__schemas as $t) $__types[] = ['id' => (int)$t['id'], 'l' => $t['label'], 'k' => $t['key_name'], 'p' => (int)$t['is_premium']];
$__pal = [];
foreach (get_active_palettes($db) as $p) {
    $v = json_decode($p['css_variables_json'], true) ?: [];
    $__pal[] = ['id' => (int)$p['id'], 'n' => $p['name'], 'p' => $v['primary'] ?? '#0F5257', 'a' => $v['accent'] ?? '#F2A65A', 'b' => $v['bg'] ?? '#FBF8F2'];
}
$__fonts = [];
foreach (get_font_pairs() as $k => $f) $__fonts[] = ['k' => $k, 'l' => $f['label']];
$__hist = history_status($db, (int)$school['id']);
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<style>
  .se-i{font-family:'Material Symbols Outlined';font-weight:400;font-style:normal;line-height:1;display:inline-block;vertical-align:middle;font-size:20px;}
  body.se-on{padding-bottom:84px;}
  body.se-on section[id], body.se-on .se-placeholder{position:relative;}
  body.se-on [data-se-edit]{outline:1.5px dashed transparent;outline-offset:3px;cursor:text;transition:outline-color .12s;}
  body.se-on [data-se-edit]:hover{outline-color:#F2A65A;} body.se-on [data-se-edit]:focus{outline:2px solid #F2A65A;}
  body.se-on [data-se-img]{cursor:pointer;outline:2px dashed transparent;outline-offset:-2px;} body.se-on [data-se-img]:hover{outline-color:#F2A65A;}
  body.se-on section[id]:hover{box-shadow:inset 0 0 0 2px rgba(242,166,90,.55);}
  section.se-hidden{opacity:.45;background-image:repeating-linear-gradient(45deg,transparent,transparent 12px,rgba(0,0,0,.04) 12px,rgba(0,0,0,.04) 24px);}
  .se-tools{position:absolute;top:10px;left:10px;right:10px;z-index:60;display:flex;justify-content:space-between;pointer-events:none;}
  .se-tools > *{pointer-events:auto;}
  .se-btn{background:#1C1C16;color:#fff;border:2px solid #fff;border-radius:999px;padding:7px 13px;font:700 .76rem Arial,sans-serif;cursor:pointer;display:inline-flex;gap:6px;align-items:center;box-shadow:0 3px 12px rgba(0,0,0,.3);opacity:.94;}
  .se-btn:hover{background:#0F5257;opacity:1;} .se-btn.round{padding:7px;width:36px;height:36px;justify-content:center;}
  .se-pop{position:absolute;z-index:70;background:#fff;color:#1C1C16;border-radius:14px;box-shadow:0 14px 38px rgba(0,0,0,.32);padding:6px;min-width:220px;display:none;font:600 .86rem Arial,sans-serif;text-align:left;}
  .se-pop.open{display:block;} .se-tools .se-pop{top:44px;} .se-tools .l .se-pop{left:0;} .se-tools .r .se-pop{right:0;}
  .se-pop button{display:flex;width:100%;gap:10px;align-items:center;background:none;border:none;border-radius:9px;padding:10px 12px;font:inherit;color:inherit;cursor:pointer;text-align:left;}
  .se-pop button:hover{background:#F4F1E6;} .se-pop button.on{background:#E6F0EE;color:#0F5257;} .se-pop button.danger{color:#B3261E;}
  .se-pop hr{border:none;border-top:1px solid #eee;margin:4px 0;} .se-tools .l,.se-tools .r{position:relative;}
  .se-placeholder{margin:0;padding:18px 20px;background:repeating-linear-gradient(45deg,#f7f4ea,#f7f4ea 12px,#efe9d6 12px,#efe9d6 24px);color:#5a5a52;font:600 .9rem Arial,sans-serif;min-height:70px;display:flex;align-items:center;gap:10px;}
  .se-placeholder .se-tools{position:absolute;}
  #seBar{position:fixed;left:50%;bottom:14px;transform:translateX(-50%);z-index:99990;background:#1C1C16;color:#fff;border-radius:18px;padding:8px 10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:center;box-shadow:0 12px 34px rgba(0,0,0,.4);font:700 .8rem Arial,sans-serif;max-width:calc(100vw - 16px);}
  #seBar button,#seBar a.se-done{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:11px;padding:9px 12px;font:inherit;cursor:pointer;display:inline-flex;gap:6px;align-items:center;text-decoration:none;}
  #seBar button:hover:not(:disabled){background:rgba(255,255,255,.24);} #seBar button:disabled{opacity:.35;cursor:default;}
  #seBar .sep{width:1px;height:26px;background:rgba(255,255,255,.2);margin:0 2px;} #seBar a.se-done{background:#F2A65A;border-color:#F2A65A;color:#0A3A3E;}
  #seBar .wrapb{position:relative;} #seBar .se-pop{bottom:calc(100% + 12px);left:50%;transform:translateX(-50%);top:auto;width:300px;padding:12px;}
  .se-pal{display:grid;grid-template-columns:1fr 1fr;gap:6px;max-height:230px;overflow:auto;} .se-pal button{border:1.5px solid #E2DCC6;padding:7px 8px;font-size:.74rem;}
  .se-pal button.on{border-color:#0F5257;background:#E6F0EE;} .se-dots span{display:inline-block;width:13px;height:13px;border-radius:50%;margin-right:-4px;border:1.5px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.14);}
  .se-pop h5{margin:2px 4px 8px;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#777;} .se-pop select{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;font:inherit;}
  #seToast{position:fixed;top:14px;right:14px;z-index:99999;background:#0F5257;color:#fff;border-radius:999px;padding:9px 16px;font:700 .8rem Arial,sans-serif;opacity:0;transform:translateY(-8px);transition:.2s;pointer-events:none;}
  #seToast.show{opacity:1;transform:none;} #seToast.err{background:#B3261E;}
  #seModal{position:fixed;inset:0;z-index:99998;background:rgba(10,30,32,.6);display:none;align-items:center;justify-content:center;padding:16px;} #seModal.open{display:flex;}
  #seModal .card{background:#fff;color:#1C1C16;border-radius:18px;max-width:420px;width:100%;padding:22px;font-family:Arial,sans-serif;max-height:80vh;overflow:auto;}
  #seModal h3{margin:0 0 6px;} #seModal p{margin:0 0 14px;color:#666;font-size:.88rem;} #seModal .acts{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;}
  #seModal .acts button{border-radius:999px;padding:10px 18px;font:800 .84rem Arial,sans-serif;cursor:pointer;border:2px solid #0F5257;background:#fff;color:#0F5257;} #seModal .acts .go{background:#0F5257;color:#fff;} #seModal .acts .bad{background:#B3261E;border-color:#B3261E;color:#fff;}
  #seModal .list button{display:flex;width:100%;gap:10px;align-items:center;background:none;border:1px solid #E2DCC6;border-radius:10px;padding:11px 12px;margin-bottom:6px;font:700 .86rem Arial,sans-serif;cursor:pointer;text-align:left;} #seModal .list button:hover{border-color:#0F5257;background:#F4F1E6;}
  @media(max-width:640px){#seBar .lbl{display:none;} .se-btn .lbl{display:none;} .se-btn{padding:7px 9px;}}
</style>
<div id="seToast"></div>
<div id="seModal"><div class="card"><h3 id="seMT"></h3><p id="seMP"></p><div id="seMB"></div><div class="acts" id="seMA"></div></div></div>
<div id="seBar">
  <button type="button" id="seUndo" title="Undo" <?= $__hist['canUndo'] ? '' : 'disabled' ?>><span class="se-i">undo</span><span class="lbl">Undo</span></button>
  <button type="button" id="seRedo" title="Redo" <?= $__hist['canRedo'] ? '' : 'disabled' ?>><span class="se-i">redo</span><span class="lbl">Redo</span></button>
  <span class="sep"></span>
  <div class="wrapb"><button type="button" data-pop="seCol"><span class="se-i">palette</span><span class="lbl">Colours</span></button>
    <div class="se-pop" id="seCol"><h5>Colour schemes</h5><div class="se-pal" id="sePal"></div>
      <h5 style="margin-top:12px;">Your own colours</h5>
      <div style="display:flex;gap:10px;align-items:center;font-size:.76rem;color:#555;flex-wrap:wrap;">Main <input type="color" id="seC1" value="#0F5257"> Accent <input type="color" id="seC2" value="#F2A65A"> Background <input type="color" id="seC3" value="#FBF8F2"></div>
      <button type="button" id="seCApply" style="margin-top:10px;background:#0F5257;color:#fff;justify-content:center;">Apply my colours</button></div></div>
  <div class="wrapb"><button type="button" data-pop="seFont"><span class="se-i">text_fields</span><span class="lbl">Fonts</span></button>
    <div class="se-pop" id="seFont"><h5>Font pairing</h5><select id="seFontSel"><option value="default">Somahub default</option></select></div></div>
  <button type="button" id="seAdd"><span class="se-i">add_circle</span><span class="lbl">Add section</span></button>
  <span class="sep"></span>
  <a class="se-done" href="https://somahub.top/dashboard/sections.php"><span class="se-i">check</span><span class="lbl">Done</span></a>
</div>
<input type="file" id="seFile" accept="image/*" style="display:none">
<script>
(function(){
  var DATA = <?= json_encode($__data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>, TYPES = <?= json_encode($__types, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
      PAL = <?= json_encode($__pal, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>, FONTS = <?= json_encode($__fonts, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
      CUR_PAL = <?= (int)($school['palette_id'] ?? 0) ?>;
  var OPS = '/dashboard/editor-ops-ajax.php', SAVE = '/dashboard/section-save-ajax.php';
  var body = document.body; body.classList.add('se-on');
  function $(id){ return document.getElementById(id); }
  function esc(t){ var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
  function norm(t){ return (t || '').replace(/ /g,' ').replace(/\s+/g,' ').trim(); }
  var toastT; function toast(msg, err){ var t = $('seToast'); t.textContent = msg; t.className = 'show' + (err ? ' err' : ''); clearTimeout(toastT); toastT = setTimeout(function(){ t.className = ''; }, 2200); }

  // ---------- saving ----------
  var pending = {}, timers = {};
  function post(url, fd){ return fetch(url, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); }); }
  function ops(action, extra){ var fd = new FormData(); fd.append('action', action); for (var k in (extra||{})) fd.append(k, extra[k]); return post(OPS, fd); }
  function saveField(sid, field, value){
    var fd = new FormData(); fd.append('section_id', sid); fd.append(field, value);
    return post(SAVE, fd).then(function(d){ if (d.ok) { toast('Saved'); refreshHist(); } else toast((d.errors && Object.values(d.errors)[0]) || 'Could not save', true); return d; }).catch(function(){ toast('Could not save', true); });
  }
  function queue(sid, field, el){
    var k = sid + ':' + field; pending[k] = function(){ var v = el.innerText.replace(/ /g,' ').replace(/\n{3,}/g,'\n\n').trim(); delete pending[k]; return saveField(sid, field, v); };
    clearTimeout(timers[k]); timers[k] = setTimeout(function(){ if (pending[k]) pending[k](); }, 900);
  }
  function flush(){ var ps = []; Object.keys(pending).forEach(function(k){ clearTimeout(timers[k]); ps.push(pending[k]()); }); return Promise.all(ps); }
  function reload(){ flush().then(function(){ try { sessionStorage.setItem('seScroll', String(window.scrollY)); } catch(e){} location.reload(); }); }
  try { var sc = sessionStorage.getItem('seScroll'); if (sc) { sessionStorage.removeItem('seScroll'); window.addEventListener('load', function(){ window.scrollTo(0, parseInt(sc, 10) || 0); }); } } catch(e){}
  window.addEventListener('beforeunload', function(){ Object.keys(pending).forEach(function(k){ pending[k](); }); });

  // ---------- history ----------
  function setHist(d){ $('seUndo').disabled = !d.canUndo; $('seRedo').disabled = !d.canRedo; }
  function refreshHist(){ ops('status').then(setHist).catch(function(){}); }
  $('seUndo').onclick = function(){ flush().then(function(){ return ops('undo'); }).then(function(d){ if (d.ok) { toast('Undone: ' + d.label); reloadSoon(); } else toast(d.error, true); }); };
  $('seRedo').onclick = function(){ flush().then(function(){ return ops('redo'); }).then(function(d){ if (d.ok) { toast('Redone: ' + d.label); reloadSoon(); } else toast(d.error, true); }); };
  function reloadSoon(){ setTimeout(reload, 350); }

  // ---------- modal ----------
  function modal(title, text, buttons, bodyHtml){
    $('seMT').textContent = title; $('seMP').textContent = text || ''; $('seMB').innerHTML = bodyHtml || '';
    var a = $('seMA'); a.innerHTML = '';
    buttons.forEach(function(b){ var x = document.createElement('button'); x.type = 'button'; x.textContent = b.t; if (b.c) x.className = b.c; x.onclick = function(){ $('seModal').classList.remove('open'); b.f && b.f(); }; a.appendChild(x); });
    $('seModal').classList.add('open');
  }
  $('seModal').addEventListener('click', function(e){ if (e.target === this) this.classList.remove('open'); });

  // ---------- popovers ----------
  function closePops(except){ document.querySelectorAll('.se-pop.open').forEach(function(p){ if (p !== except) p.classList.remove('open'); }); }
  document.addEventListener('click', function(e){ if (!e.target.closest('.se-pop') && !e.target.closest('[data-pop]') && !e.target.closest('.se-btn')) closePops(); });
  document.querySelectorAll('#seBar [data-pop]').forEach(function(b){ b.addEventListener('click', function(e){ e.stopPropagation(); var p = $(b.dataset.pop), was = p.classList.contains('open'); closePops(); if (!was) p.classList.add('open'); }); });

  // ---------- site-wide: colours, fonts, add section ----------
  var pal = $('sePal');
  PAL.forEach(function(x){
    var b = document.createElement('button'); b.type = 'button'; if (x.id === CUR_PAL) b.className = 'on';
    b.innerHTML = '<span class="se-dots"><span style="background:' + x.p + '"></span><span style="background:' + x.a + '"></span><span style="background:' + x.b + '"></span></span>' + esc(x.n);
    b.onclick = function(){ var fd = new FormData(); fd.append('color_mode','preset'); fd.append('palette_id', x.id); flush().then(function(){ return post('/dashboard/colors-save-ajax.php', fd); }).then(function(d){ d.ok ? reload() : toast(d.error || 'Could not save colours', true); }); };
    pal.appendChild(b);
  });
  $('seCApply').onclick = function(){ var fd = new FormData(); fd.append('color_mode','custom'); fd.append('primary',$('seC1').value); fd.append('secondary',$('seC1').value); fd.append('accent',$('seC2').value); fd.append('bg',$('seC3').value);
    flush().then(function(){ return post('/dashboard/colors-save-ajax.php', fd); }).then(function(d){ d.ok ? reload() : toast(d.error || 'Could not save colours', true); }); };
  var fs = $('seFontSel'); FONTS.forEach(function(f){ var o = document.createElement('option'); o.value = f.k; o.textContent = f.l; fs.appendChild(o); });
  fs.onchange = function(){ var fd = new FormData(); fd.append('font_pair', fs.value); flush().then(function(){ return post('/dashboard/font-save-ajax.php', fd); }).then(function(d){ d.ok ? reload() : toast('Could not save font', true); }); };
  $('seAdd').onclick = function(){
    var h = '<div class="list">' + TYPES.map(function(t){ return '<button type="button" data-t="' + t.id + '">' + esc(t.l) + (t.p ? ' <small style="color:#8a5a00">paid plan</small>' : '') + '</button>'; }).join('') + '</div>';
    modal('Add a section', 'It is added at the bottom. Move it wherever you like.', [{t:'Cancel'}], h);
    $('seMB').querySelectorAll('button').forEach(function(b){ b.onclick = function(){ $('seModal').classList.remove('open'); flush().then(function(){ return ops('add', {section_type_id: b.dataset.t}); }).then(function(d){
      if (!d.ok) return toast(d.error || 'Could not add', true);
      var sec = DATA.filter(function(x){ return x.key === d.key; })[0];
      var file = sec ? sec.editFile : 'section-edit.php';
      location.href = '/dashboard/' + file + '?id=' + d.new_id;
    }); }; });
  };

  // ---------- per-section chrome ----------
  function menuBtn(label, icon, fn, cls){ var b = document.createElement('button'); b.type = 'button'; if (cls) b.className = cls; b.innerHTML = '<span class="se-i">' + icon + '</span>' + esc(label); b.onclick = function(e){ e.stopPropagation(); closePops(); fn(); }; return b; }
  function doOp(action, sec, extra){ var p = {section_id: sec.id}; for (var k in (extra||{})) p[k] = extra[k]; return flush().then(function(){ return ops(action, p); }).then(function(d){ if (d.ok) reload(); else toast(d.error || 'Could not do that', true); }); }
  function tools(sec, host){
    var wrap = document.createElement('div'); wrap.className = 'se-tools';
    var l = document.createElement('div'); l.className = 'l';
    if (sec.options.length) {
      var lb = document.createElement('button'); lb.type = 'button'; lb.className = 'se-btn'; lb.innerHTML = '<span class="se-i" style="font-size:17px">dashboard_customize</span><span class="lbl">Layout</span>';
      var lp = document.createElement('div'); lp.className = 'se-pop';
      sec.options.forEach(function(o){ var b = menuBtn(o.l, o.i, function(){ if (o.k !== sec.variant) doOp('variant', sec, {variant:o.k}); }, o.k === sec.variant ? 'on' : ''); lp.appendChild(b); });
      lb.onclick = function(e){ e.stopPropagation(); var was = lp.classList.contains('open'); closePops(); if (!was) lp.classList.add('open'); };
      l.appendChild(lb); l.appendChild(lp);
    }
    var r = document.createElement('div'); r.className = 'r';
    var rb = document.createElement('button'); rb.type = 'button'; rb.className = 'se-btn round'; rb.title = 'Section options'; rb.innerHTML = '<span class="se-i">more_vert</span>';
    var rp = document.createElement('div'); rp.className = 'se-pop';
    rp.appendChild(menuBtn('Move up', 'arrow_upward', function(){ doOp('move', sec, {dir:'up'}); }));
    rp.appendChild(menuBtn('Move down', 'arrow_downward', function(){ doOp('move', sec, {dir:'down'}); }));
    rp.appendChild(menuBtn('Duplicate', 'content_copy', function(){ doOp('duplicate', sec); }));
    rp.appendChild(menuBtn(sec.visible ? 'Hide from site' : 'Show on site', sec.visible ? 'visibility_off' : 'visibility', function(){ doOp('toggle', sec); }));
    rp.appendChild(menuBtn('Edit all details', 'tune', function(){ flush().then(function(){ location.href = '/dashboard/' + sec.editFile + '?id=' + sec.id; }); }));
    rp.appendChild(document.createElement('hr'));
    rp.appendChild(menuBtn('Remove section', 'delete', function(){ modal('Remove "' + sec.label + '"?', 'It is taken off your site. You can bring it back with Undo.', [{t:'Keep it'}, {t:'Remove', c:'bad', f:function(){ doOp('remove', sec); }}]); }, 'danger'));
    rb.onclick = function(e){ e.stopPropagation(); var was = rp.classList.contains('open'); closePops(); if (!was) rp.classList.add('open'); };
    r.appendChild(rb); r.appendChild(rp);
    wrap.appendChild(l); wrap.appendChild(r); host.insertBefore(wrap, host.firstChild);
  }

  // ---------- bind sections to the page ----------
  var byId = {}; DATA.forEach(function(s){ byId[s.id] = s; });
  function targetFor(marker){
    var n = marker.nextElementSibling;
    while (n && !(n.classList && n.classList.contains('se-marker'))) {
      if (n.tagName === 'SECTION') return n;
      n = n.nextElementSibling;
    }
    return null;
  }
  function bindText(sec, root){
    var claimed = new Set();
    var all = Array.prototype.slice.call(root.querySelectorAll('*')).filter(function(el){ return !el.closest('.se-tools,form,script,style'); });
    sec.text.forEach(function(pair){
      var f = pair[0], want = norm(pair[1]); if (!want) return;
      var multi = /\n/.test(pair[1]);
      var hit = null;
      for (var i = 0; i < all.length; i++) {
        var el = all[i]; if (claimed.has(el)) continue;
        if (norm(el.innerText || el.textContent) !== want) continue;
        // deepest element carrying exactly this text
        var deeper = Array.prototype.some.call(el.children, function(ch){ return norm(ch.innerText || ch.textContent) === want; });
        if (deeper) continue;
        hit = el; break;
      }
      if (!hit) return;
      claimed.add(hit); hit.setAttribute('data-se-edit', f); hit.contentEditable = 'plaintext-only'; if (hit.contentEditable !== 'plaintext-only') hit.contentEditable = 'true'; hit.spellcheck = true;
      hit.addEventListener('input', function(){ queue(sec.id, f, hit); });
      hit.addEventListener('blur', function(){ var k = sec.id + ':' + f; if (pending[k]) { clearTimeout(timers[k]); pending[k](); } });
      hit.addEventListener('keydown', function(e){
        if (e.key === 'Enter' && !multi) { e.preventDefault(); hit.blur(); }
        if (e.key === ' ' && hit.tagName === 'SUMMARY') { e.preventDefault(); document.execCommand('insertText', false, ' '); }
      });
      hit.addEventListener('paste', function(e){ e.preventDefault(); var t = (e.clipboardData || window.clipboardData).getData('text'); document.execCommand('insertText', false, multi ? t : t.replace(/\s*\n\s*/g,' ')); });
    });
    sec.imgs.forEach(function(pair){
      var f = pair[0], path = pair[1];
      var els = root.querySelectorAll('img[src*="' + path.replace(/"/g,'') + '"], [style*="' + path.replace(/"/g,'') + '"]');
      els.forEach(function(im){ im.setAttribute('data-se-img', f); im.dataset.seSid = sec.id; });
    });
  }
  // accordions stay open while editing, so every answer is reachable
  document.addEventListener('click', function(e){ if (e.target.closest('summary')) e.preventDefault(); }, true);
  document.querySelectorAll('.se-marker').forEach(function(m){
    var sec = byId[parseInt(m.dataset.sid, 10)]; if (!sec) return;
    var el = targetFor(m);
    if (el) {
      if (!sec.visible) el.classList.add('se-hidden');
      tools(sec, el); bindText(sec, el); el.querySelectorAll('details').forEach(function(d){ d.open = true; });
    } else {
      var ph = document.createElement('div'); ph.className = 'se-placeholder';
      ph.innerHTML = '<span class="se-i">' + (sec.visible ? 'edit_note' : 'visibility_off') + '</span><span style="flex:1;padding-left:4px"><b>' + esc(sec.label) + '</b> &mdash; ' + (sec.visible ? 'nothing to show yet. Use the menu to fill it in.' : 'hidden from your site.') + '</span>';
      m.parentNode.insertBefore(ph, m.nextSibling);
      tools(sec, ph);
    }
  });

  // images: click to replace
  var imgTarget = null;
  document.addEventListener('click', function(e){
    var im = e.target.closest('[data-se-img]');
    if (im && !e.target.closest('.se-tools,#seBar')) { e.preventDefault(); e.stopPropagation(); imgTarget = im; $('seFile').click(); return; }
    // keep links and buttons inside sections from navigating away while editing
    var a = e.target.closest('body > section a, body > section button, body > header a');
    if (a && !a.closest('.se-tools') && !a.closest('#seBar')) e.preventDefault();
  }, true);
  $('seFile').addEventListener('change', function(){
    var f = this.files[0]; this.value = ''; if (!f || !imgTarget) return;
    var fd = new FormData(); fd.append('section_id', imgTarget.dataset.seSid); fd.append(imgTarget.getAttribute('data-se-img'), f);
    toast('Uploading photo...');
    post(SAVE, fd).then(function(d){ if (d.ok) reload(); else toast((d.errors && Object.values(d.errors)[0]) || 'Could not upload', true); }).catch(function(){ toast('Could not upload', true); });
  });
  window.addEventListener('scroll', function(){ closePops(); }, {passive:true});
})();
</script>
