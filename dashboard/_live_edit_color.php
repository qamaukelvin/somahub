<?php
/**
 * Per-text colour picker for live editors. Mark any editable element with
 * data-color-field="<key>" (and an inline style="color:..." when set). A
 * small toolbar appears above it on focus: curated theme swatches, a custom
 * colour, and Auto (back to the template default). Fonts stay site-wide.
 */
function render_live_edit_color_tools(int $sectionId, array $theme): void {
    $swatches = array_values(array_unique(array_filter([
        '#FFFFFF', '#1C1C16', $theme['accent'] ?? '', $theme['primary'] ?? '', '#F7F2E7', '#FFD54A',
    ], fn($c) => preg_match('/^#[0-9a-fA-F]{6}$/', $c))));
?>
<style>
  .color-tool{position:absolute;z-index:9999;display:none;align-items:center;gap:6px;background:#1C1C16;border-radius:8px;padding:6px 8px;box-shadow:0 6px 18px rgba(0,0,0,.35);font-family:Arial,sans-serif;}
  .color-tool.show{display:flex;}
  .color-tool .sw{width:20px;height:20px;border-radius:50%;border:2px solid rgba(255,255,255,.6);cursor:pointer;padding:0;}
  .color-tool input[type=color]{width:24px;height:24px;border:none;background:none;padding:0;cursor:pointer;}
  .color-tool .auto{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:5px;font-size:.7rem;font-weight:600;padding:3px 8px;cursor:pointer;}
  .color-tool .lbl{color:rgba(255,255,255,.65);font-size:.68rem;margin-right:2px;}
</style>
<div class="color-tool" id="colorTool">
  <span class="lbl">Text colour</span>
  <?php foreach ($swatches as $c): ?><button type="button" class="sw" style="background:<?= htmlspecialchars($c) ?>" data-c="<?= htmlspecialchars($c) ?>"></button><?php endforeach; ?>
  <input type="color" id="colorToolCustom" value="#ffffff" title="Custom colour">
  <button type="button" class="auto" id="colorToolAuto">Auto</button>
</div>
<script>
(function(){
  var tool = document.getElementById('colorTool');
  var custom = document.getElementById('colorToolCustom');
  var target = null;
  function place(el) {
    var r = el.getBoundingClientRect();
    tool.style.top = (window.scrollY + Math.max(r.top - 46, 60)) + 'px';
    tool.style.left = (window.scrollX + Math.max(r.left, 8)) + 'px';
  }
  function save(value) {
    if (!target) return;
    var field = target.getAttribute('data-color-field');
    target.style.color = value || '';
    var fd = new FormData();
    fd.append('section_id', <?= $sectionId ?>);
    fd.append('field', field);
    fd.append('value', value);
    fetch('section-style-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){ window.somahubShowSaved && window.somahubShowSaved(!!d.ok, d.ok ? 'Colour saved' : 'Could not save colour'); })
      .catch(function(){ window.somahubShowSaved && window.somahubShowSaved(false); });
  }
  document.querySelectorAll('[data-color-field]').forEach(function(el){
    el.addEventListener('focus', function(){ target = el; place(el); tool.classList.add('show'); });
  });
  // mousedown on the toolbar must not blur the text field being styled.
  tool.addEventListener('mousedown', function(e){ if (e.target !== custom) e.preventDefault(); });
  tool.querySelectorAll('.sw').forEach(function(b){ b.addEventListener('click', function(){ save(b.getAttribute('data-c')); }); });
  document.getElementById('colorToolAuto').addEventListener('click', function(){ save(''); });
  custom.addEventListener('change', function(){ save(custom.value); });
  document.addEventListener('mousedown', function(e){
    if (tool.contains(e.target) || (e.target.closest && e.target.closest('[data-color-field]'))) return;
    tool.classList.remove('show');
  });
})();
</script>
<?php }
