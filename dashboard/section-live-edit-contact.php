<?php
// Live, in-place editor for Contact: details edited where they sit, map
// location set by clicking on a map (no typed coordinates), all 6 layouts.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
require_once __DIR__ . '/../includes/appearance.php';
require_once __DIR__ . '/_live_edit_shared.php';
require_once __DIR__ . '/_live_edit_script.php';
require_once __DIR__ . '/_live_edit_color.php';
$user = require_school_login();
$db = get_db();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT ss.*, st.label, st.key_name, st.schema_json
    FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.id = ? AND ss.school_id = ?
");
$stmt->execute([$id, $user['school_id']]);
$section = $stmt->fetch();
if (!$section || $section['key_name'] !== 'contact') {
    http_response_code(404);
    exit('Live editor is not available for this section yet.');
}

$stmt2 = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt2->execute([$user['school_id']]);
$school = $stmt2->fetch();
$appearance = resolve_school_appearance($db, $school);
$theme = $appearance['theme'];
$fontPairs = get_font_pairs();
$currentFontPair = 'default';
foreach ($fontPairs as $pairKey => $pair) {
    if (($theme['font_display'] ?? '') === $pair['display'] && ($theme['font_body'] ?? '') === $pair['body']) { $currentFontPair = $pairKey; break; }
}

$content = json_decode($section['content_json'], true) ?: [];
$config = get_section_variant_registry()['contact'];
$variant = $section['layout_variant'] ?? 'stacked';
$coords = null;
if (!empty($content['map_location']) && strpos($content['map_location'], ',') !== false) {
    [$la, $ln] = array_map('trim', explode(',', $content['map_location'], 2));
    if (is_numeric($la) && is_numeric($ln)) $coords = [(float)$la, (float)$ln];
}
$needsMap = in_array($variant, ['side_by_side', 'map_only'], true);

function ct_ed(array $c, string $f, string $ph, string $cls, string $colorKey): string {
    return '<span class="' . $cls . '" data-field="' . $f . '"' . live_color_attr($c, $colorKey) . ' contenteditable="true" data-placeholder="' . $ph . '">' . esc($c[$f] ?? '') . '</span>';
}
function ct_details(array $c): void {
    $rows = [['address', 'Location', 'Add your address'], ['phone', 'Phone', 'Add a phone number'], ['email', 'Email', 'Add an email'], ['office_hours', 'Office Hours', 'e.g. Mon-Fri 8am-5pm']];
    echo '<div class="contact-grid">';
    foreach ($rows as [$f, $k, $ph]) {
        echo '<div class="contact-item' . (empty($c[$f]) ? ' is-empty' : '') . '"><span class="k"' . live_color_attr($c, 'labels') . '>' . $k . '</span>' . ct_ed($c, $f, $ph, 'v', 'details') . '</div>';
    }
    echo '</div>';
}
function ct_map(?array $coords, string $id): void {
    if ($coords) {
        echo '<div class="map-wrap"><div id="' . $id . '" class="school-map-embed" data-lat="' . $coords[0] . '" data-lng="' . $coords[1] . '"></div>';
        echo '<button type="button" class="map-btn" onclick="somahubOpenMapPicker()"><span class="material-symbols-outlined">edit_location_alt</span>Move pin</button>';
        echo '<button type="button" class="map-btn clear" onclick="somahubClearMap()" title="Remove map"><span class="material-symbols-outlined">location_off</span></button></div>';
    } else {
        echo '<div class="school-map-embed map-empty" onclick="somahubOpenMapPicker()"><span class="material-symbols-outlined">add_location_alt</span><b>Set your map location</b><small>Click to drop a pin on the map</small></div>';
    }
}

$extraCss = <<<CSS
  section#contact{background:var(--bg);padding:60px 0;min-height:70vh;}
  .contact-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;}
  .contact-item{background:#fff;border-radius:10px;padding:20px 22px;box-shadow:0 1px 4px rgba(0,0,0,.05);min-width:0;}
  .contact-item.is-empty{background:rgba(255,255,255,.5);border:2px dashed rgba(0,0,0,.2);}
  .contact-item .k{font-family:'Space Mono',monospace;font-size:.7rem;text-transform:uppercase;color:var(--primary);display:block;margin-bottom:6px;}
  .contact-item .v{font-size:.95rem;display:block;overflow-wrap:anywhere;}
  .school-map-embed{height:300px;border-radius:12px;margin-top:20px;}
  .map-wrap{position:relative;height:100%;}
  .map-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;border:2px dashed rgba(0,0,0,.25);background:rgba(255,255,255,.5);cursor:pointer;color:#6b6b60;}
  .map-empty:hover{border-color:var(--primary);color:var(--primary);}
  .map-empty .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:36px;}
  .map-btn{position:absolute;top:28px;right:10px;z-index:500;display:flex;align-items:center;gap:4px;background:#1C1C16;color:#fff;border:none;border-radius:8px;padding:7px 11px;font-size:.78rem;font-weight:700;cursor:pointer;opacity:0;transition:opacity .15s;}
  .map-btn.clear{top:68px;padding:7px 8px;background:#B3261E;}
  .map-wrap:hover .map-btn{opacity:1;}
  .map-btn .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:18px;}
  .contact-side-by-side{display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start;}
  @media(max-width:820px){.contact-side-by-side{grid-template-columns:1fr;}}
  .contact-side-by-side .school-map-embed{margin-top:0;min-height:300px;height:100%;}
  .contact-map-only{position:relative;}
  .contact-map-only .school-map-embed{height:420px;margin-top:0;}
  .contact-overlay-card{position:relative;margin-top:-60px;margin-left:24px;max-width:340px;background:#fff;border-radius:12px;padding:20px;box-shadow:0 8px 24px rgba(0,0,0,.15);z-index:600;}
  .contact-overlay-card .contact-item{box-shadow:none;padding:10px 0;}
  .contact-form-box{background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
  .contact-form-box .fake{display:flex;flex-direction:column;gap:12px;}
  .contact-form-box .fake > div{padding:10px 12px;border:1px solid rgba(0,0,0,.15);border-radius:6px;font-size:.9rem;color:#9a9a8e;}
  .contact-form-box .fake .ta{min-height:100px;}
  .contact-form-box .fake .sendbtn{background:var(--primary);color:#fff;text-align:center;font-weight:700;border:none;}
  .wa-card{margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
  .wa-btn{display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;font-weight:700;padding:12px 22px;border-radius:30px;}
  .wa-btn .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:22px;}
  .wa-num{background:#fff;border-radius:8px;padding:10px 14px;font-size:.9rem;min-width:200px;border:2px dashed rgba(0,0,0,.2);}
  .i-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .i-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
  #mapModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center;}
  #mapModal.open{display:flex;}
  .mm-box{background:#fff;border-radius:14px;width:min(720px,94vw);padding:16px;}
  .mm-box h3{margin:0 0 4px;font-size:1rem;} .mm-box p{margin:0 0 10px;font-size:.82rem;color:#6b6b60;}
  #pickMap{height:380px;border-radius:10px;}
  .mm-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px;}
  .mm-actions button{border:none;border-radius:8px;padding:9px 16px;font-weight:700;cursor:pointer;}
  .mm-actions .save{background:#1C1C16;color:#fff;} .mm-actions .save:disabled{opacity:.4;cursor:not-allowed;} .mm-actions .cancel{background:#eee;}
CSS;
$extraCss .= "\n" . ($appearance['custom_css'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editing: <?= esc($section['label']) ?></title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php render_live_edit_head($theme, $extraCss); ?>
</head>
<body>

<?php render_live_edit_topbar($section, $fontPairs, $currentFontPair); ?>
<?php render_variant_switcher($config, $variant, 0); ?>

<div class="save-toast" id="saveToast"><span class="material-symbols-outlined">check_circle</span><span id="saveToastMsg">Saved</span></div>

<section id="contact">
  <div class="wrap">
    <?php if ($needsMap && !$coords): ?>
      <p class="i-note"><span class="material-symbols-outlined">info</span>This layout needs a map location. Until you set one, your live site shows the stacked layout instead.</p>
    <?php elseif ($variant === 'whatsapp_first' && empty($content['whatsapp_number'])): ?>
      <p class="i-note"><span class="material-symbols-outlined">info</span>Add your WhatsApp number below. Until then, your live site shows the stacked layout instead.</p>
    <?php endif; ?>
    <div class="section-head"><h2><?= esc($section['label']) ?></h2></div>

    <?php if ($variant === 'side_by_side'): ?>
      <div class="contact-side-by-side"><div><?php ct_details($content); ?></div><div><?php ct_map($coords, 'school-map'); ?></div></div>

    <?php elseif ($variant === 'map_only'): ?>
      <div class="contact-map-only"><?php ct_map($coords, 'school-map'); ?><div class="contact-overlay-card"><?php ct_details($content); ?></div></div>

    <?php elseif ($variant === 'details_only'): ?>
      <?php ct_details($content); ?>

    <?php elseif ($variant === 'with_form'): ?>
      <div class="contact-side-by-side">
        <div><?php ct_details($content); ?></div>
        <div class="contact-form-box">
          <p style="margin-bottom:14px;color:#5a5a52;"><span data-field="form_intro" contenteditable="true" data-placeholder="Add a short intro above the form"><?= esc($content['form_intro'] ?? '') ?></span></p>
          <div class="fake"><div>Your name</div><div>Your email</div><div class="ta">Your message</div><div class="sendbtn">Send Message</div></div>
          <p class="i-note" style="margin-top:10px;"><span class="material-symbols-outlined">mail</span>Preview only. Messages arrive in your inbox.</p>
        </div>
      </div>

    <?php elseif ($variant === 'whatsapp_first'): ?>
      <div class="wa-card">
        <span class="wa-btn"><span class="material-symbols-outlined">chat</span>Message us on WhatsApp</span>
        <span class="wa-num" data-field="whatsapp_number" contenteditable="true" data-placeholder="WhatsApp number, e.g. 254712345678"><?= esc($content['whatsapp_number'] ?? '') ?></span>
      </div>
      <?php ct_details($content); ?>

    <?php else: ?>
      <?php ct_details($content); ?>
      <?php ct_map($coords, 'school-map'); ?>
    <?php endif; ?>
  </div>
</section>

<div id="mapModal"><div class="mm-box">
  <h3>Set your map location</h3>
  <p>Click on the map to drop your school's pin. Drag it to fine-tune.</p>
  <div id="pickMap"></div>
  <div class="mm-actions"><button class="cancel" type="button" onclick="document.getElementById('mapModal').classList.remove('open')">Cancel</button><button class="save" id="mmSave" type="button" disabled>Save location</button></div>
</div></div>

<?php render_live_edit_script((int)$section['id'], $variant); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var sectionId = <?= (int)$section['id'] ?>;
  var coords = <?= $coords ? json_encode($coords) : 'null' ?>;
  var DEFAULT = [-0.0236, 37.9062];
  function post(val, msg){
    var fd = new FormData(); fd.append('section_id', sectionId); fd.append('map_location', val);
    fetch('section-save-ajax.php', {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
      if (d.ok) { sessionStorage.setItem('somahubLiveEditNotice', msg); location.reload(); }
      else if (window.somahubShowSaved) window.somahubShowSaved(false, 'Could not save location');
    });
  }
  var el = document.querySelector('.school-map-embed[data-lat]');
  if (el && window.L) {
    var m = L.map(el, {scrollWheelZoom:false}).setView(coords, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution:'&copy; OpenStreetMap contributors', maxZoom:19}).addTo(m);
    L.marker(coords).addTo(m);
  }
  var pick, pin, chosen = null;
  window.somahubOpenMapPicker = function(){
    document.getElementById('mapModal').classList.add('open');
    if (!window.L) return;
    if (!pick) {
      pick = L.map('pickMap').setView(coords || DEFAULT, coords ? 15 : 7);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution:'&copy; OpenStreetMap contributors', maxZoom:19}).addTo(pick);
      function place(ll){
        chosen = [ll.lat, ll.lng];
        if (!pin) { pin = L.marker(ll, {draggable:true}).addTo(pick); pin.on('dragend', function(){ var p = pin.getLatLng(); chosen = [p.lat, p.lng]; }); }
        else pin.setLatLng(ll);
        document.getElementById('mmSave').disabled = false;
      }
      pick.on('click', function(e){ place(e.latlng); });
      if (coords) place(L.latLng(coords[0], coords[1]));
    }
    setTimeout(function(){ pick.invalidateSize(); }, 80);
  };
  document.getElementById('mmSave').addEventListener('click', function(){
    if (chosen) post(chosen[0].toFixed(6) + ',' + chosen[1].toFixed(6), 'Location saved');
  });
  window.somahubClearMap = function(){ if (confirm('Remove the map from this section?')) post('', 'Map removed'); };
})();
</script>
</body>
</html>
