<div class="section-row" data-id="<?= $s['id'] ?>">
  <div class="row-top">
    <span class="drag-handle" title="Drag to reorder">☰</span>

    <div class="section-info">
      <strong>
        <?= htmlspecialchars($s['label']) ?>
        <?php if (!$s['is_visible']): ?><span class="badge-hidden">Hidden</span><?php endif; ?>
        <?php if ($s['is_premium']): ?><span class="badge-premium">Paid</span><?php endif; ?>
      </strong>
      <span>somahub.top/<?= htmlspecialchars($school['slug']) ?>#<?= htmlspecialchars($s['key_name']) ?></span>
    </div>

    <!-- Desktop: original inline button layout, Edit now opens the same inline accordion as mobile -->
    <div class="desktop-actions">
      <button type="button" onclick="toggleAccordion(<?= $s['id'] ?>)">Edit</button>
      <button type="button" onclick="duplicateSection(<?= $s['id'] ?>)">Duplicate</button>
      <button type="button" onclick="toggleVisibility(<?= $s['id'] ?>)"><?= $s['is_visible'] ? 'Hide' : 'Show' ?></button>
      <button type="button" class="danger" onclick="removeSection(<?= $s['id'] ?>)">Remove</button>
    </div>

    <!-- Mobile: pencil opens inline accordion, ... hides the rest -->
    <div class="mobile-actions">
      <button type="button" class="icon-btn" onclick="toggleAccordion(<?= $s['id'] ?>)" title="Edit">✎</button>
      <details class="row-menu">
        <summary>⋯</summary>
        <div class="menu-items">
          <button type="button" onclick="duplicateSection(<?= $s['id'] ?>)">Duplicate</button>
          <button type="button" onclick="toggleVisibility(<?= $s['id'] ?>)"><?= $s['is_visible'] ? 'Hide' : 'Show' ?></button>
          <button type="button" class="danger" onclick="removeSection(<?= $s['id'] ?>)">Remove</button>
        </div>
      </details>
    </div>
  </div>

  <div class="accordion-panel" id="accordion-<?= $s['id'] ?>"></div>
</div>
