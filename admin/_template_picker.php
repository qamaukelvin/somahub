<?php
// Expects $templates (array of template rows) and $selectedTemplateId to be set by the includer.
?>
<style>
  .template-picker{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;max-height:160px;overflow-y:auto;padding:2px;}
  .template-option{position:relative;}
  .template-option input{position:absolute;opacity:0;}
  .template-option label{
    display:flex;align-items:center;gap:8px;border:1.5px solid var(--line);border-radius:20px;
    padding:6px 12px;cursor:pointer;background:#fff;transition:border-color .15s;white-space:nowrap;
  }
  .template-option input:checked + label{border-color:var(--teal);box-shadow:0 0 0 2px rgba(15,82,87,0.15);background:#F4F8F6;}
  .template-option-name{font-size:0.8rem;font-weight:600;color:var(--ink);}
  .template-premium-tag{background:#F2A65A;color:#0A3A3E;font-size:0.6rem;font-weight:800;padding:1px 6px;border-radius:8px;margin-left:2px;}
</style>
<div class="template-picker">
  <?php foreach ($templates as $t): $isPremium = !empty($t['is_premium']); ?>
    <div class="template-option">
      <input type="radio" name="template_id" id="template_<?= $t['id'] ?>" value="<?= $t['id'] ?>" <?= $selectedTemplateId == $t['id'] ? 'checked' : '' ?>>
      <label for="template_<?= $t['id'] ?>">
        <span class="template-option-name"><?= htmlspecialchars($t['name']) ?></span>
        <?php if ($isPremium): ?><span class="template-premium-tag">Premium</span><?php endif; ?>
      </label>
    </div>
  <?php endforeach; ?>
</div>
