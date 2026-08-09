<?php
// Expects $themes (array of theme rows) and $selectedThemeId to be set by the includer.
?>
<style>
  .theme-picker{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:20px;}
  .theme-option{position:relative;}
  .theme-option input{position:absolute;opacity:0;}
  .theme-option label{
    display:block;border:2px solid var(--line);border-radius:12px;padding:12px;cursor:pointer;background:#fff;transition:border-color .15s;
  }
  .theme-option input:checked + label{border-color:var(--teal);box-shadow:0 0 0 2px rgba(15,82,87,0.15);}
  .theme-swatches{display:flex;gap:6px;margin-bottom:10px;}
  .theme-swatches span{width:26px;height:26px;border-radius:50%;border:1px solid rgba(0,0,0,0.08);display:block;}
  .theme-option-name{font-size:0.82rem;font-weight:700;color:var(--ink);}
</style>
<div class="theme-picker">
  <?php foreach ($themes as $t):
    $vars = json_decode($t['css_variables_json'], true);
  ?>
    <div class="theme-option">
      <input type="radio" name="theme_id" id="theme_<?= $t['id'] ?>" value="<?= $t['id'] ?>" <?= $selectedThemeId == $t['id'] ? 'checked' : '' ?>>
      <label for="theme_<?= $t['id'] ?>">
        <div class="theme-swatches">
          <span style="background:<?= htmlspecialchars($vars['primary'] ?? '#ccc') ?>;"></span>
          <span style="background:<?= htmlspecialchars($vars['accent'] ?? '#ccc') ?>;"></span>
          <span style="background:<?= htmlspecialchars($vars['bg'] ?? '#ccc') ?>;"></span>
        </div>
        <div class="theme-option-name"><?= htmlspecialchars($t['name']) ?></div>
      </label>
    </div>
  <?php endforeach; ?>
</div>