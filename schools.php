<?php
require_once __DIR__ . '/config/db.php';
$db = get_db();
$schools = $db->query("
    SELECT name, slug, plan, county, verification_status
    FROM schools
    WHERE status IN ('active','trial') AND slug <> 'demo'
    ORDER BY created_at DESC
    LIMIT 200
")->fetchAll();
$schoolCount = $db->query("SELECT COUNT(*) c FROM schools WHERE status IN ('active','trial') AND slug <> 'demo'")->fetch()['c'];
$pageTitle = 'Schools on Somahub';
$pageDesc = 'Browse Kenyan schools that already have their website on Somahub.';
$pagePath = '/schools.php';
$pageScripts = <<<'JS'
<script>
const portfolioSearch = document.getElementById('portfolioSearch');
if (portfolioSearch) {
    portfolioSearch.addEventListener('input', () => {
        const query = portfolioSearch.value.trim().toLowerCase();
        const cards = document.querySelectorAll('#portfolioGrid .portfolio-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const matches = card.dataset.name.includes(query) || card.dataset.county.includes(query);
            card.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        document.getElementById('portfolioNoResults').style.display = visibleCount === 0 ? 'block' : 'none';
    });
}

</script>
JS;
include __DIR__ . '/_public_head.php';
?>
<div class="page-top">
<section id="portfolio">
  <div class="wrap">
    <div class="section-head">
      <span class="kicker">Schools using Somahub</span>
      <h1><?= $schoolCount ?> school<?= $schoolCount == 1 ? '' : 's' ?> already online with us</h1>
    </div>

    <?php if ($schools): ?>
    <?php if (count($schools) > 6): ?>
    <input type="text" id="portfolioSearch" placeholder="Search schools by name or county…" style="width:100%;max-width:400px;padding:12px 16px;border:1.5px solid var(--line);border-radius:24px;margin-bottom:24px;font-family:inherit;font-size:0.9rem;">
    <?php endif; ?>
    <div class="portfolio-grid" id="portfolioGrid">
      <?php foreach ($schools as $s): ?>
      <a href="https://<?= urlencode($s['slug']) ?>.somahub.top/" class="portfolio-card" data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>" data-county="<?= htmlspecialchars(strtolower($s['county'] ?? '')) ?>">
        <div class="school-icon"><?= strtoupper(substr($s['name'], 0, 2)) ?></div>
        <h3><?php if ($s['verification_status'] === 'verified'): ?><span class="verified-pill" title="Verified by Somahub"></span> <?php endif; ?><?= htmlspecialchars($s['name']) ?></h3>
        <div class="url"><?= htmlspecialchars($s['slug']) ?>.somahub.top</div>
        <span class="county-pill"><?= $s['county'] ? htmlspecialchars($s['county']) : ucfirst(str_replace('_',' ',$s['plan'])) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <p id="portfolioNoResults" style="display:none;color:var(--muted);text-align:center;padding:30px;">No schools match that search.</p>
    <?php else: ?>
    <div class="empty-portfolio">
      We are onboarding our first schools right now. Check back soon, or be one of the first.
    </div>
    <?php endif; ?>
  </div>
</section>

</div>
<?php include __DIR__ . '/_public_foot.php'; ?>
