<?php
require_once __DIR__ . '/includes/consent.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/reviews.php';
$db = get_db();
$platformReviews = get_approved_reviews($db, 'platform', null, 60);
$platformAvgRating = get_average_rating($platformReviews);
$pageTitle = 'Reviews - Somahub';
$pageDesc = 'What schools say about Somahub, and how to leave your own review.';
$pagePath = '/reviews.php';
include __DIR__ . '/_public_head.php';
?>
<div class="page-top">
<section id="reviews">
  <div class="wrap">
    <div class="section-head center">
      <span class="kicker">What people are saying</span>
      <h1>Reviews</h1>
      <?php if ($platformAvgRating !== null): ?>
        <p style="color:var(--muted);"><?= render_stars((int)round($platformAvgRating)) ?> <?= $platformAvgRating ?> out of 5 (<?= count($platformReviews) ?> review<?= count($platformReviews) == 1 ? '' : 's' ?>)</p>
      <?php endif; ?>
    </div>

    <?php if (isset($_GET['review_submitted'])): ?>
      <p style="background:#DCEFE1;color:#1B4D3E;padding:10px 16px;border-radius:8px;margin-bottom:20px;font-size:0.9rem;max-width:480px;margin-left:auto;margin-right:auto;text-align:center;">Thank you! Your review has been submitted and will appear once reviewed.</p>
    <?php elseif (!empty($_GET['review_error'])): ?>
      <p style="background:#FBE8E4;color:#8C3B2E;padding:10px 16px;border-radius:8px;margin-bottom:20px;font-size:0.9rem;max-width:480px;margin-left:auto;margin-right:auto;text-align:center;"><?= htmlspecialchars($_GET['review_error']) ?></p>
    <?php endif; ?>

    <div class="testimonial-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-bottom:32px;">
      <?php foreach ($platformReviews as $r): ?>
        <div class="testimonial-card" style="background:#fff;border-radius:10px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
          <div style="color:#F2A65A;letter-spacing:2px;margin-bottom:8px;"><?= render_stars((int)$r['rating']) ?></div>
          <div style="font-style:italic;color:#333;margin-bottom:10px;">"<?= htmlspecialchars($r['comment']) ?>"</div>
          <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($r['reviewer_name']) ?><?= $r['reviewer_role'] ? ' - ' . htmlspecialchars($r['reviewer_role']) : '' ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$platformReviews): ?><p style="color:var(--muted);grid-column:1/-1;text-align:center;">No reviews yet - be the first to leave one.</p><?php endif; ?>
    </div>

    <details style="max-width:480px;margin:0 auto;">
      <summary style="cursor:pointer;color:var(--teal);font-weight:700;text-align:center;">Leave a review</summary>
      <form method="POST" action="/reviews-submit.php" style="margin-top:16px;">
        <input type="hidden" name="reviewable_type" value="platform">
        <input type="hidden" name="redirect_to" value="/reviews.php">
        <input type="text" name="website" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off">

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Your Name</label>
        <input type="text" name="reviewer_name" required style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;box-sizing:border-box;">

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">You are a... (optional)</label>
        <input type="text" name="reviewer_role" placeholder="e.g. Head Teacher, School Admin" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;box-sizing:border-box;">

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Rating</label>
        <select name="rating" required style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;">
          <option value="">Choose a rating</option>
          <option value="5">★★★★★ Excellent</option>
          <option value="4">★★★★☆ Good</option>
          <option value="3">★★★☆☆ Average</option>
          <option value="2">★★☆☆☆ Below Average</option>
          <option value="1">★☆☆☆☆ Poor</option>
        </select>

        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Your Review</label>
        <textarea name="comment" rows="4" required style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:14px;box-sizing:border-box;"></textarea>

        <?= consent_checkbox() ?>
        <button type="submit" class="btn-primary" style="width:100%;">Submit Review</button>
      </form>
    </details>
  </div>
</section>

</div>
<?php include __DIR__ . '/_public_foot.php'; ?>
