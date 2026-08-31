<?php
// Expects $themes (array of theme rows) and $selectedThemeId to be set by the includer.
?>
<style>
  .theme-picker{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;max-height:160px;overflow-y:auto;padding:2px;}
  .theme-option{position:relative;}
  .theme-option input{position:absolute;opacity:0;}
  .theme-option label{
    display:flex;align-items:center;gap:8px;border:1.5px solid var(--line);border-radius:20px;
    padding:6px 12px 6px 8px;cursor:pointer;background:#fff;transition:border-color .15s;white-space:nowrap;
  }
  .theme-option input:checked + label{border-color:var(--teal);box-shadow:0 0 0 2px rgba(15,82,87,0.15);background:#F4F8F6;}
  .theme-mini-swatch{display:flex;gap:2px;flex-shrink:0;}
  .theme-mini-swatch span{width:10px;height:10px;border-radius:50%;border:1px solid rgba(0,0,0,0.08);display:block;}
  .theme-option-name{font-size:0.8rem;font-weight:600;color:var(--ink);}
  .theme-premium-tag{background:#F2A65A;color:#0A3A3E;font-size:0.6rem;font-weight:800;padding:1px 6px;border-radius:8px;margin-left:2px;}
</style>
<div class="theme-picker">
  <?php foreach ($themes as $t):
    $vars = json_decode($t['css_variables_json'], true);
    $isPremium = !empty($t['is_premium']);
  ?>
    <div class="theme-option">
      <input type="radio" name="theme_id" id="theme_<?= $t['id'] ?>" value="<?= $t['id'] ?>" <?= $selectedThemeId == $t['id'] ? 'checked' : '' ?>>
      <label for="theme_<?= $t['id'] ?>">
        <span class="theme-mini-swatch">
          <span style="background:<?= htmlspecialchars($vars['primary'] ?? '#ccc') ?>;"></span>
          <span style="background:<?= htmlspecialchars($vars['accent'] ?? '#ccc') ?>;"></span>
          <span style="background:<?= htmlspecialchars($vars['bg'] ?? '#ccc') ?>;"></span>
        </span>
        <span class="theme-option-name"><?= htmlspecialchars($t['name']) ?></span>
        <?php if ($isPremium): ?><span class="theme-premium-tag">Premium</span><?php endif; ?>
      </label>
    </div>
  <?php endforeach; ?>
</div>
