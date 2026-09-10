<?php
// Expects $palettes (array of color_palettes rows), $selectedPaletteId,
// $selectedColorMode ('preset'|'custom'), and $customColors (assoc array
// with primary/secondary/accent/bg, only used when mode is 'custom') to be
// set by the includer.
$selectedColorMode = $selectedColorMode ?? 'preset';
$customColors = $customColors ?? ['primary' => '#0F5257', 'secondary' => '#1C1C16', 'accent' => '#F2A65A', 'bg' => '#F7F2E7'];
?>
<style>
  .pal-scroll{display:flex;gap:10px;overflow-x:auto;padding:4px 2px 10px;-webkit-overflow-scrolling:touch;}
  .pal-chip{position:relative;flex:0 0 auto;}
  .pal-chip input{position:absolute;opacity:0;}
  .pal-chip label{display:flex;align-items:center;gap:7px;border:1.5px solid var(--line);border-radius:20px;padding:7px 13px;cursor:pointer;background:#fff;white-space:nowrap;transition:border-color .15s;}
  .pal-chip input:checked + label{border-color:var(--teal);box-shadow:0 0 0 2px rgba(15,82,87,0.15);background:#F4F8F6;}
  .pal-swatch-row{display:flex;gap:2px;}
  .pal-swatch-row span{width:11px;height:11px;border-radius:50%;border:1px solid rgba(0,0,0,0.08);display:block;}
  .pal-chip-name{font-size:0.8rem;font-weight:600;}

  .custom-accordion{border:1.5px solid var(--line);border-radius:10px;overflow:hidden;}
  .custom-accordion summary{list-style:none;padding:12px 14px;cursor:pointer;font-weight:700;font-size:0.85rem;display:flex;align-items:center;justify-content:space-between;background:#fafafa;}
  .custom-accordion summary::-webkit-details-marker{display:none;}
  .custom-accordion summary::after{content:'▾';font-size:0.75rem;color:#888;}
  .custom-accordion[open] summary::after{content:'▴';}
  .custom-fields{padding:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;}
  .custom-field label{display:block;font-size:0.75rem;font-weight:600;margin-bottom:5px;color:#555;}
  .custom-field .swatch-input{display:flex;align-items:center;gap:6px;}
  .custom-field input[type=color]{width:36px;height:36px;border:1px solid var(--line);border-radius:6px;padding:2px;cursor:pointer;}
  .custom-field input[type=text]{flex:1;padding:7px 8px;border:1px solid var(--line);border-radius:6px;font-family:monospace;font-size:0.82rem;}
</style>

<input type="hidden" name="color_mode" id="color_mode_field" value="<?= htmlspecialchars($selectedColorMode) ?>">

<div class="pal-scroll" id="palPresetRow">
  <?php foreach ($palettes as $p):
    $vars = json_decode($p['css_variables_json'], true);
    $isSelected = $selectedColorMode === 'preset' && $selectedPaletteId == $p['id'];
  ?>
    <div class="pal-chip">
      <input type="radio" name="palette_id" id="palette_<?= $p['id'] ?>" value="<?= $p['id'] ?>"
             data-primary="<?= htmlspecialchars($vars['primary'] ?? '#0F5257') ?>"
             data-secondary="<?= htmlspecialchars($vars['secondary'] ?? $vars['primary'] ?? '#1C1C16') ?>"
             data-accent="<?= htmlspecialchars($vars['accent'] ?? '#F2A65A') ?>"
             data-bg="<?= htmlspecialchars($vars['bg'] ?? '#F7F2E7') ?>"
             <?= $isSelected ? 'checked' : '' ?>
             onchange="somahubSelectPreset(this)">
      <label for="palette_<?= $p['id'] ?>">
        <span class="pal-swatch-row">
          <span style="background:<?= htmlspecialchars($vars['primary'] ?? '#ccc') ?>;"></span>
          <span style="background:<?= htmlspecialchars($vars['secondary'] ?? $vars['primary'] ?? '#ccc') ?>;"></span>
          <span style="background:<?= htmlspecialchars($vars['accent'] ?? '#ccc') ?>;"></span>
          <span style="background:<?= htmlspecialchars($vars['bg'] ?? '#ccc') ?>;"></span>
        </span>
        <span class="pal-chip-name"><?= htmlspecialchars($p['name']) ?></span>
      </label>
    </div>
  <?php endforeach; ?>
</div>

<details class="custom-accordion" id="customAccordion" <?= $selectedColorMode === 'custom' ? 'open' : '' ?>>
  <summary>🎨 Custom Palette — pick your own colors</summary>
  <div class="custom-fields">
    <div class="custom-field">
      <label>Primary</label>
      <div class="swatch-input">
        <input type="color" id="custom_primary_swatch" value="<?= htmlspecialchars($customColors['primary']) ?>" oninput="document.getElementById('primary_override').value=this.value;somahubMarkCustom();">
        <input type="text" name="primary_override" id="primary_override" value="<?= htmlspecialchars($customColors['primary']) ?>" oninput="document.getElementById('custom_primary_swatch').value=this.value;somahubMarkCustom();">
      </div>
    </div>
    <div class="custom-field">
      <label>Secondary</label>
      <div class="swatch-input">
        <input type="color" id="custom_secondary_swatch" value="<?= htmlspecialchars($customColors['secondary']) ?>" oninput="document.getElementById('secondary_override').value=this.value;somahubMarkCustom();">
        <input type="text" name="secondary_override" id="secondary_override" value="<?= htmlspecialchars($customColors['secondary']) ?>" oninput="document.getElementById('custom_secondary_swatch').value=this.value;somahubMarkCustom();">
      </div>
    </div>
    <div class="custom-field">
      <label>Accent</label>
      <div class="swatch-input">
        <input type="color" id="custom_accent_swatch" value="<?= htmlspecialchars($customColors['accent']) ?>" oninput="document.getElementById('accent_override').value=this.value;somahubMarkCustom();">
        <input type="text" name="accent_override" id="accent_override" value="<?= htmlspecialchars($customColors['accent']) ?>" oninput="document.getElementById('custom_accent_swatch').value=this.value;somahubMarkCustom();">
      </div>
    </div>
    <div class="custom-field">
      <label>Background</label>
      <div class="swatch-input">
        <input type="color" id="custom_bg_swatch" value="<?= htmlspecialchars($customColors['bg']) ?>" oninput="document.getElementById('bg_override').value=this.value;somahubMarkCustom();">
        <input type="text" name="bg_override" id="bg_override" value="<?= htmlspecialchars($customColors['bg']) ?>" oninput="document.getElementById('custom_bg_swatch').value=this.value;somahubMarkCustom();">
      </div>
    </div>
  </div>
</details>

<script>
  // Picking a preset fills the custom fields with its colors too, so if the
  // school later opens "Custom Palette" to fine-tune, they start from
  // something reasonable instead of blank/default values.
  function somahubSelectPreset(radio) {
    document.getElementById('color_mode_field').value = 'preset';
    document.getElementById('custom_primary_swatch').value = radio.dataset.primary;
    document.getElementById('primary_override').value = radio.dataset.primary;
    document.getElementById('custom_secondary_swatch').value = radio.dataset.secondary;
    document.getElementById('secondary_override').value = radio.dataset.secondary;
    document.getElementById('custom_accent_swatch').value = radio.dataset.accent;
    document.getElementById('accent_override').value = radio.dataset.accent;
    document.getElementById('custom_bg_swatch').value = radio.dataset.bg;
    document.getElementById('bg_override').value = radio.dataset.bg;
    document.getElementById('customAccordion').open = false;
  }
  // Editing any custom field switches the mode to 'custom' so the school's
  // hand-picked colors take priority over whichever preset was last picked.
  function somahubMarkCustom() {
    document.getElementById('color_mode_field').value = 'custom';
    document.querySelectorAll('#palPresetRow input[type=radio]').forEach(r => r.checked = false);
  }
</script>
