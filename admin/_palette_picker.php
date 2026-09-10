<?php
// Expects $palettes (array of color_palettes rows) and $selectedPaletteId to be set by the includer.
?>
<style>
  .palette-picker{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;max-height:160px;overflow-y:auto;padding:2px;}
  .palette-option{position:relative;}
  .palette-option input{position:absolute;opacity:0;}
  .palette-option label{
    display:flex;align-items:center;gap:8px;border:1.5px solid var(--line);border-radius:20px;
    padding:6px 12px 6px 8px;cursor:pointer;background:#fff;transition:border-color .15s;white-space:nowrap;
  }
  .palette-option input:checked + label{border-color:var(--teal);box-shadow:0 0 0 2px rgba(15,82,87,0.15);background:#F4F8F6;}
  .palette-mini-swatch{display:flex;gap:2px;flex-shrink:0;}
  .palette-mini-swatch span{width:10px;height:10px;border-radius:50%;border:1px solid rgba(0,0,0,0.08);display:block;}
  .palette-option-name{font-size:0.8rem;font-weight:600;color:var(--ink);}
</style>
<div class="palette-picker">
  <?php foreach ($palettes as $p):
    $vars = json_decode($p['css_variables_json'], true);
  ?>
    <div class="palette-option">
      <input type="radio" name="palette_id" id="palette_<?= $p['id'] ?>" value="<?= $p['id'] ?>" <?= $selectedPaletteId == $p['id'] ? 'checked' : '' ?>>
      <label for="palette_<?= $p['id'] ?>">
        <span class="palette-mini-swatch">
          <span style="background:<?= htmlspecialchars($vars['primary'] ?? '#ccc') ?>;"></span>
          <span style="background:<?= htmlspecialchars($vars['accent'] ?? '#ccc') ?>;"></span>
          <span style="background:<?= htmlspecialchars($vars['bg'] ?? '#ccc') ?>;"></span>
        </span>
        <span class="palette-option-name"><?= htmlspecialchars($p['name']) ?></span>
      </label>
    </div>
  <?php endforeach; ?>
</div>
