<?php
// Expects $templates (array of template rows) and $selectedTemplateId to be set by the includer.
require_once __DIR__ . '/../includes/appearance.php';
?>
<style>
  .tpl-scroll{display:flex;gap:12px;overflow-x:auto;padding:6px 2px 14px;margin-bottom:6px;-webkit-overflow-scrolling:touch;}
  .tpl-card{position:relative;flex:0 0 180px;border:2px solid var(--line);border-radius:10px;overflow:hidden;cursor:pointer;background:#fff;transition:border-color .15s;}
  .tpl-card input{position:absolute;opacity:0;pointer-events:none;}
  .tpl-card.selected{border-color:var(--teal);box-shadow:0 0 0 2px rgba(15,82,87,0.15);}
  .tpl-card.locked{opacity:0.55;}
  .tpl-frame-wrap{width:100%;height:110px;overflow:hidden;position:relative;background:#F4F4F0;}
  .tpl-frame-wrap iframe{width:400%;height:400%;border:0;transform:scale(0.25);transform-origin:top left;pointer-events:none;}
  .tpl-card-footer{padding:8px 10px;display:flex;align-items:center;justify-content:space-between;gap:6px;}
  .tpl-card-name{font-size:0.8rem;font-weight:700;color:var(--ink);}
  .tpl-premium-tag{background:#F2A65A;color:#0A3A3E;font-size:0.58rem;font-weight:800;padding:2px 6px;border-radius:8px;white-space:nowrap;}
</style>
<div class="tpl-scroll" id="templatePickerScroll">
  <?php foreach ($templates as $t): $isPremium = !empty($t['is_premium']); $isSelected = $selectedTemplateId == $t['id']; ?>
    <label class="tpl-card<?= $isSelected ? ' selected' : '' ?>" data-premium="<?= $isPremium ? '1' : '0' ?>">
      <input type="radio" name="template_id" value="<?= $t['id'] ?>" <?= $isSelected ? 'checked' : '' ?> onchange="this.closest('.tpl-scroll').querySelectorAll('.tpl-card').forEach(c=>c.classList.remove('selected'));this.closest('.tpl-card').classList.add('selected');">
      <div class="tpl-frame-wrap">
        <iframe srcdoc="<?= htmlspecialchars(build_template_preview_html($t['custom_css'] ?? '')) ?>" tabindex="-1" loading="lazy"></iframe>
      </div>
      <div class="tpl-card-footer">
        <span class="tpl-card-name"><?= htmlspecialchars($t['name']) ?></span>
        <?php if ($isPremium): ?><span class="tpl-premium-tag">Premium</span><?php endif; ?>
      </div>
    </label>
  <?php endforeach; ?>
</div>
