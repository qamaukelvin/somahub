<?php
if (!function_exists('live_color_attr')) {
    /** data-color-field + inline colour for an editable element, e.g. live_color_attr($content,'body'). */
    function live_color_attr(array $content, string $field): string {
        $v = $content[$field . '_color'] ?? '';
        $style = preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? ' style="color:' . $v . ';"' : '';
        return ' data-color-field="' . htmlspecialchars($field) . '_color"' . $style;
    }
}
/**
 * Per-text colour control for live editors. Mark any editable element with
 * data-color-field="<key>" (and inline style="color:..." when set). A small
 * pencil appears at the element's top-right on hover/focus; clicking it opens
 * a compact popover: recent colours, common colours, custom picker + hex +
 * Auto (template default). Fonts stay site-wide.
 */
function render_live_edit_color_tools(int $sectionId, array $theme): void {
    $common = ['#FFFFFF', '#1C1C16', '#FFD54A', '#E8573C', '#4C6FFF', '#2FB67C', '#9B51E0', '#F7F2E7'];
    foreach (['accent', 'primary'] as $k) {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $theme[$k] ?? '') && !in_array(strtoupper($theme[$k]), $common, true)) array_unshift($common, strtoupper($theme[$k]));
    }
    $common = array_slice($common, 0, 8);
?>
<style>
  .color-pencil{position:absolute;z-index:9998;display:none;height:28px;border-radius:14px;background:#1C1C16;color:#fff;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.4);cursor:pointer;align-items:center;justify-content:center;padding:0 10px 0 8px;gap:6px;font:700 11px Arial,sans-serif;white-space:nowrap;}
  .color-pencil .dot{width:12px;height:12px;border-radius:50%;border:1.5px solid rgba(255,255,255,.8);flex-shrink:0;}
  [data-color-field]:hover{outline:1px dashed rgba(140,140,140,.8);outline-offset:3px;}
  .color-pencil.show{display:flex;}
  .color-pencil .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
  .color-pop{position:absolute;z-index:9999;display:none;flex-direction:column;gap:9px;width:224px;background:#1C1C16;border-radius:10px;padding:10px 12px;box-shadow:0 8px 24px rgba(0,0,0,.4);font-family:Arial,sans-serif;}
  .color-pop.show{display:flex;}
  .color-pop .row{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
  .color-pop .lbl{width:100%;color:rgba(255,255,255,.6);font-size:.66rem;text-transform:uppercase;letter-spacing:.05em;}
  .color-pop .sw{width:22px;height:22px;border-radius:50%;border:2px solid rgba(255,255,255,.55);cursor:pointer;padding:0;}
  .color-pop .sw:hover{border-color:#fff;}
  .color-pop .none{color:rgba(255,255,255,.4);font-size:.72rem;}
  .color-pop .ct-custom{display:flex;align-items:center;gap:5px;color:#fff;font-size:.72rem;cursor:pointer;}
  .color-pop input[type=color]{width:26px;height:26px;border:none;background:none;padding:0;cursor:pointer;}
  .color-pop #cpHex{width:74px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.35);color:#fff;border-radius:5px;padding:4px 6px;font-size:.72rem;}
  .color-pop .auto{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:5px;font-size:.7rem;font-weight:600;padding:4px 8px;cursor:pointer;margin-left:auto;}
</style>
<button type="button" class="color-pencil" id="colorPencil" title="Change text colour"><span class="dot" id="cpDot"></span><span class="material-symbols-outlined">edit</span><span>Colour</span></button>
<div class="color-pop" id="colorPop">
  <div class="row"><span class="lbl">Recent</span><span id="cpRecent"></span></div>
  <div class="row"><span class="lbl">Common</span>
    <?php foreach ($common as $c): ?><button type="button" class="sw" style="background:<?= htmlspecialchars($c) ?>" data-c="<?= htmlspecialchars($c) ?>"></button><?php endforeach; ?>
  </div>
  <div class="row">
    <label class="ct-custom" title="Pick any colour"><input type="color" id="cpPicker" value="#ffffff"><span>Custom</span></label>
    <input type="text" id="cpHex" maxlength="7" placeholder="#RRGGBB" spellcheck="false">
    <button type="button" class="auto" id="cpAuto">Auto</button>
  </div>
</div>
<script>
(function(){
  var pencil = document.getElementById('colorPencil'), pop = document.getElementById('colorPop');
  var picker = document.getElementById('cpPicker'), hex = document.getElementById('cpHex'), recentEl = document.getElementById('cpRecent');
  var target = null, hideT = null, KEY = 'somahubRecentColors';
  function getRecent(){ try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch(e){ return []; } }
  function pushRecent(c){ try { var r = getRecent().filter(function(x){return x!==c;}); r.unshift(c); localStorage.setItem(KEY, JSON.stringify(r.slice(0,6))); } catch(e){} }
  function renderRecent(){
    var r = getRecent(); recentEl.innerHTML = '';
    if (!r.length) { recentEl.innerHTML = '<span class="none">None yet</span>'; return; }
    r.forEach(function(c){ var b = document.createElement('button'); b.type='button'; b.className='sw'; b.style.background=c; b.setAttribute('data-c',c); recentEl.appendChild(b); });
  }
  function placePencil(el){
    // Sit at the end of the actual text, not the (often much wider) box.
    var box = el.getBoundingClientRect(), top = box.top, right = box.right;
    try {
      var rg = document.createRange(); rg.selectNodeContents(el);
      var rects = Array.prototype.filter.call(rg.getClientRects(), function(q){ return q.width > 0; });
      if (rects.length) {
        top = Math.min.apply(null, rects.map(function(q){ return q.top; }));
        right = Math.max.apply(null, rects.map(function(q){ return q.right; }));
      } else { right = box.left + 80; }
    } catch(e){}
    right = Math.min(right, box.right);
    pencil.style.top = (window.scrollY + top - 16) + 'px';
    pencil.style.left = (window.scrollX + right - 14) + 'px';
    var dot = document.getElementById('cpDot'); if (dot) dot.style.background = getComputedStyle(el).color;
    pencil.classList.add('show');
  }
  function save(value){
    if (!target) return;
    var cf = target.getAttribute('data-color-field');
    document.querySelectorAll('[data-color-field="' + cf + '"]').forEach(function(el){ el.style.color = value || ''; });
    var dot = document.getElementById('cpDot'); if (dot) dot.style.background = getComputedStyle(target).color;
    if (value) { pushRecent(value); hex.value = value; picker.value = value; }
    var fd = new FormData();
    fd.append('section_id', <?= $sectionId ?>); fd.append('field', target.getAttribute('data-color-field')); fd.append('value', value);
    fetch('section-style-save-ajax.php', { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){ window.somahubShowSaved && window.somahubShowSaved(!!d.ok, d.ok ? 'Colour saved' : 'Could not save colour'); renderRecent(); })
      .catch(function(){ window.somahubShowSaved && window.somahubShowSaved(false); });
  }
  document.querySelectorAll('[data-color-field]').forEach(function(el){
    function show(){ clearTimeout(hideT); target = el; if (!pop.classList.contains('show')) placePencil(el); }
    function later(){ hideT = setTimeout(function(){ if (!pop.classList.contains('show')) pencil.classList.remove('show'); }, 400); }
    el.addEventListener('mouseenter', show); el.addEventListener('focus', show);
    el.addEventListener('mouseleave', later);
  });
  pencil.addEventListener('mouseenter', function(){ clearTimeout(hideT); });
  pencil.addEventListener('mouseleave', function(){ hideT = setTimeout(function(){ if (!pop.classList.contains('show')) pencil.classList.remove('show'); }, 400); });
  pencil.addEventListener('mousedown', function(e){ e.preventDefault(); });
  pencil.addEventListener('click', function(){
    renderRecent();
    var r = pencil.getBoundingClientRect();
    pop.style.top = (window.scrollY + r.bottom + 6) + 'px';
    pop.style.left = (window.scrollX + Math.max(8, Math.min(r.left, window.innerWidth - 232))) + 'px';
    pop.classList.add('show');
  });
  pop.addEventListener('mousedown', function(e){ if (e.target !== picker && e.target !== hex) e.preventDefault(); });
  pop.addEventListener('click', function(e){ var b = e.target.closest && e.target.closest('.sw'); if (b) save(b.getAttribute('data-c')); });
  document.getElementById('cpAuto').addEventListener('click', function(){ hex.value=''; save(''); });
  picker.addEventListener('change', function(){ save(picker.value); });
  hex.addEventListener('keydown', function(e){ if (e.key==='Enter'){ e.preventDefault(); hex.blur(); } });
  hex.addEventListener('change', function(){ var v = hex.value.trim(); if (v && v[0] !== '#') v = '#'+v; if (/^#[0-9a-fA-F]{6}$/.test(v)) save(v.toUpperCase()); });
  document.addEventListener('mousedown', function(e){
    if (pop.contains(e.target) || pencil.contains(e.target)) return;
    pop.classList.remove('show'); if (!(e.target.closest && e.target.closest('[data-color-field]'))) pencil.classList.remove('show');
  });
})();
</script>
<?php }
