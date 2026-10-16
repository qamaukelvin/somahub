<?php
/**
 * Shared "your role + your school" fields for get-started.php (signup) and
 * dashboard/school-setup.php. Include inside a <form>.
 * Expects: $contentPresets, $subdomainCheckUrl, $sf (sticky values array, may be empty).
 */
require_once __DIR__ . '/school-provision.php';
require_once __DIR__ . '/consent.php';
$sf = $sf ?? [];
$v = fn($k, $d = '') => htmlspecialchars($sf[$k] ?? $d);
$curRole = $sf['job_title_raw'] ?? '';
?>
<style>
  .sf-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}
  .sf-full{grid-column:1/-1;}
  @media(max-width:560px){.sf-grid{grid-template-columns:1fr;}}
  .sf-h{grid-column:1/-1;font-size:.72rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:var(--teal);margin:22px 0 2px;padding-top:16px;border-top:1px solid var(--line);}
  .sf-h:first-child{margin-top:0;border-top:none;padding-top:0;}
  .sf-hint{font-size:.78rem;color:var(--muted);margin:6px 0 0;}
  .sf-box{background:#F4F8F6;border-radius:8px;padding:12px 14px;margin-top:14px;}
  .sf-box label:first-child{margin-top:0;}
  .sf-check{display:flex;gap:9px;align-items:flex-start;font-size:.85rem;font-weight:600;margin:0 !important;cursor:pointer;}
  .sf-check input{width:auto !important;margin-top:4px;}
  .sf-map summary{cursor:pointer;font-weight:700;font-size:.88rem;color:var(--teal);padding:6px 0;}
  .avail-msg{font-size:.82rem;margin-top:6px;min-height:18px;}
  .avail-ok{color:#1B4D3E;} .avail-bad{color:#8C3B2E;} .avail-checking{color:var(--muted);}
  .subdomain-row{display:flex;align-items:center;gap:8px;} .subdomain-row input{flex:1;} .subdomain-suffix{color:var(--muted);font-size:.85rem;white-space:nowrap;}
</style>
<?= consent_css() ?>

<div class="sf-grid">
  <div class="sf-h">Your role</div>
  <div>
    <label>Your Role at the School</label>
    <select name="job_title" id="sfRole" required>
      <option value="">Choose…</option>
      <?php foreach (get_signup_roles() as $r): ?><option value="<?= htmlspecialchars($r) ?>" <?= $curRole === $r ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div id="sfOtherWrap" style="display:none;">
    <label>Your role (type it)</label>
    <input type="text" name="job_other" maxlength="60" value="<?= $v('job_other') ?>" placeholder="e.g. Board member">
  </div>
  <div class="sf-full sf-box" id="sfAuthBox" style="display:none;">
    <label class="sf-check"><input type="checkbox" name="authorized" value="1" <?= !empty($sf['authorized']) ? 'checked' : '' ?>><span>I'm authorised by the school to create its website.</span></label>
    <p class="sf-hint">We verify every school. Please give your head teacher's details so we can confirm.</p>
    <div class="sf-grid" style="margin-top:10px;">
      <div><label>Head Teacher's Name</label><input type="text" name="head_name" value="<?= $v('head_name') ?>"></div>
      <div><label>Head Teacher's Phone</label><input type="text" name="head_phone" value="<?= $v('head_phone') ?>" placeholder="07XXXXXXXX"></div>
    </div>
  </div>

  <div class="sf-h">Your school</div>
  <div class="sf-full">
    <label>School Name</label>
    <input type="text" name="school_name" required value="<?= $v('school_name') ?>" placeholder="e.g. Kinangop Pride Primary School">
  </div>
  <div class="sf-full">
    <label>Choose Your Web Address</label>
    <div class="subdomain-row">
      <input type="text" name="slug" id="slugInput" required value="<?= $v('slug') ?>" placeholder="kinangoppride" pattern="[a-z0-9]+" autocomplete="off">
      <span class="subdomain-suffix">.somahub.top</span>
    </div>
    <div class="avail-msg" id="availMsg"></div>
  </div>
  <div>
    <label>County</label>
    <input type="text" name="county" value="<?= $v('county') ?>" placeholder="e.g. Nyandarua">
  </div>
  <div>
    <label>School Type</label>
    <select name="content_preset">
      <?php foreach ($contentPresets as $key => $p): ?>
        <option value="<?= $key ?>" <?= ($sf['content_preset'] ?? 'primary_day') === $key ? 'selected' : '' ?>><?= htmlspecialchars($p['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <p class="sf-hint sf-full">We'll start your site with sensible wording for your school type. You can change every word afterwards.</p>

  <div class="sf-full sf-map" style="margin-top:14px;">
    <details>
      <summary>Pin your school on the map (recommended)</summary>
      <script>window.somahubDeferGeo = true;</script>
      <?php $locLat = $sf['lat'] ?? ''; $locLng = $sf['lng'] ?? ''; $locFieldPrefix = ''; include __DIR__ . '/_location_picker.php'; ?>
    </details>
  </div>
</div>

<script>
(function(){
  var role = document.getElementById('sfRole');
  var authority = ['Head Teacher / Principal', 'Proprietor / Director'];
  function syncRole(){
    document.getElementById('sfOtherWrap').style.display = role.value === 'Other' ? '' : 'none';
    var need = role.value && authority.indexOf(role.value) === -1;
    var box = document.getElementById('sfAuthBox');
    box.style.display = need ? '' : 'none';
    box.querySelectorAll('input').forEach(function(i){ i.required = need && (i.name === 'head_name' || i.name === 'head_phone' || i.name === 'authorized'); });
  }
  role.addEventListener('change', syncRole); syncRole();

  var slugInput = document.getElementById('slugInput'), msg = document.getElementById('availMsg'), t;
  slugInput.addEventListener('input', function(){
    slugInput.value = slugInput.value.toLowerCase().replace(/[^a-z0-9]/g, '');
    clearTimeout(t);
    var val = slugInput.value.trim();
    if (val.length < 3) { msg.textContent = val.length ? 'At least 3 characters' : ''; msg.className = 'avail-msg avail-bad'; return; }
    msg.textContent = 'Checking availability...'; msg.className = 'avail-msg avail-checking';
    t = setTimeout(function(){
      fetch(<?= json_encode($subdomainCheckUrl) ?> + '?slug=' + encodeURIComponent(val)).then(function(r){ return r.json(); }).then(function(d){
        if (d.available) { msg.textContent = '✓ ' + val + '.somahub.top is available'; msg.className = 'avail-msg avail-ok'; }
        else { msg.textContent = '✗ That address is not available'; msg.className = 'avail-msg avail-bad'; }
      }).catch(function(){ msg.textContent = ''; });
    }, 350);
  });
  // The map sits in a closed <details>; tell Leaflet its size once opened.
  document.querySelectorAll('.sf-map details').forEach(function(d){
    d.addEventListener('toggle', function(){ if (d.open) window.dispatchEvent(new Event('somahub-map-open')); setTimeout(function(){ window.dispatchEvent(new Event('resize')); }, 60); });
  });
})();
</script>
