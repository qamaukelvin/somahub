<?php
require_once __DIR__ . '/includes/consent.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/reviews.php';
$db = get_db();
$schoolCount = $db->query("SELECT COUNT(*) c FROM schools WHERE status IN ('active','trial') AND slug <> 'demo'")->fetch()['c'];
$platformReviews = get_approved_reviews($db, 'platform', null, 6);
$platformAvgRating = get_average_rating($platformReviews);
$pageTitle = 'Somahub: Free Websites for Kenyan Schools';
$pagePath = '/';
$pageJsonLd = <<<'LD'
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"Organization","name":"Somahub","url":"https://somahub.top/","logo":"https://somahub.top/assets/apple-touch-icon.png","sameAs":["https://www.instagram.com/somahub.top/"]},{"@type":"WebSite","name":"Somahub","url":"https://somahub.top/"}]}</script>
LD;
include __DIR__ . '/_public_head.php';
?>
<section class="hero">
  <div class="hero-inner">
    <span class="hero-tag"><span class="dot"></span> Free websites for Kenyan schools</span>
    <h1>Your school deserves a <span class="accent">real website</span>, at no cost to you</h1>
    <p>Sign up, answer a few questions, and your site is live in minutes. We build and host it, and it stays online for free. You edit text, photos and colours right on the page.</p>
    <div class="hero-ctas">
      <a href="get-started.php" class="btn-primary">Create my school site</a>
      <a href="#try" class="btn-ghost">Try the editor</a>
    </div>
    <p style="margin-top:14px;font-size:.84rem;color:var(--muted);">Core site free, always. Premium features free for 60 days, no card needed. Already have a site? <a href="dashboard/login.php" style="color:var(--teal);font-weight:700;text-decoration:underline;">Log in</a></p>

  </div>
</section>

<?php $demoExists = (bool)$db->query("SELECT 1 FROM schools WHERE slug = 'demo' AND status IN ('active','trial') LIMIT 1")->fetchColumn(); ?>
<section id="try" style="padding-top:20px;">
  <div class="wrap">
    <div class="section-head center">
      <span class="kicker">Try it yourself</span>
      <h2>Edit a real school site, right here</h2>
      <p style="margin-top:10px;font-size:0.95rem;color:var(--muted);">Click any text to change it, tap a photo to swap it, try colours and fonts, then press <strong>Review changes</strong>. Your edits stay on your screen only and vanish when you refresh, so you can't break anything and nobody else sees them.</p>
    </div>
<?php if ($demoExists): ?>
    <div class="try-frame" id="tryFrame">
      <div class="mock-bar"><i></i><i></i><i></i><span>demo.somahub.top</span></div>
      <div class="try-stage">
        <iframe id="tryIframe" src="https://demo.somahub.top/?embed=1" title="Live demo school site you can edit" loading="lazy"></iframe>
        <div class="try-gate" id="tryGate"><button type="button" id="tryStart"><span class="material-symbols-outlined">edit</span> Press to start editing</button><small>Scroll past freely. Editing only starts when you press.</small></div>
      </div>
      <div class="try-done" id="tryDone"><span>You are editing. Changes stay on your screen only.</span><button type="button" id="tryStop">Stop editing and keep scrolling</button></div>
    </div>
    <script>
    (function(){
      var f = document.getElementById('tryIframe'), g = document.getElementById('tryGate'), d = document.getElementById('tryDone'), frame = document.getElementById('tryFrame'), on = false;
      function send(c){ try { f.contentWindow.postMessage({dmCmd: c}, 'https://demo.somahub.top'); } catch (e) {} }
      function set(v){ on = v; frame.classList.toggle('editing', v); send(v ? 'start' : 'stop'); }
      document.getElementById('tryStart').onclick = function(){ set(true); if (window.matchMedia('(max-width:700px)').matches) frame.scrollIntoView({block: 'start', behavior: 'smooth'}); };
      document.getElementById('tryStop').onclick = function(){ set(false); frame.scrollIntoView({block: 'start', behavior: 'smooth'}); };
      f.addEventListener('load', function(){ if (on) send('start'); });
    })();
    </script>
    <p style="text-align:center;margin-top:14px;font-size:.88rem;"><a href="https://demo.somahub.top/?try=1" target="_blank" rel="noopener" style="color:var(--teal);font-weight:700;">Open it full screen</a> for the best experience on a phone.</p>
<?php else: ?>
    <div class="mock" aria-hidden="true">
      <div class="mock-bar"><i></i><i></i><i></i><span>mwangaza.somahub.top</span></div>
      <div class="mock-body">
        <div class="mock-site">
          <div class="mock-hero">
            <div class="mock-h"><span class="mock-edit">Welcome to Mwangaza Academy</span><em class="material-symbols-outlined">edit</em></div>
            <div class="mock-sub">Click any text or photo to change it, right on the page.</div>
            <div class="mock-btn">Apply for Admission</div>
          </div>
          <div class="mock-row"><div></div><div></div><div></div></div>
        </div>
        <div class="mock-side">
          <div class="mock-card"><b>Colours</b><div class="sw"><i style="background:#0F5257"></i><i style="background:#F2A65A"></i><i style="background:#7B3F8C"></i><i style="background:#1F6FB2"></i></div></div>
          <div class="mock-card"><b>Sections</b>
            <p><span class="material-symbols-outlined">visibility</span>Hero</p><p><span class="material-symbols-outlined">visibility</span>About</p><p><span class="material-symbols-outlined">visibility</span>Admissions</p><p><span class="material-symbols-outlined">add_circle</span>Add section</p></div>
        </div>
      </div>
    </div>
<?php endif; ?>
  </div>
</section>

<section class="bg-white" id="how">
  <div class="wrap">
    <div class="section-head">
      <span class="kicker">How it works</span>
      <h2>Three steps to get your school online</h2>
    </div>
    <div class="steps-grid">
      <div class="step-card">
        <div class="num">01</div>
        <h3>Sign up in minutes</h3>
        <p>Tell us your school's name, county and your role. We instantly set up a ready-made site: Home, About, Academics, Admissions, Gallery, Staff, Reviews, FAQ and Contact.</p>
      </div>
      <div class="step-card">
        <div class="num">02</div>
        <h3>Make it yours</h3>
        <p>Click any text or photo to edit it on the page itself. Pick your colours and font, add or hide sections, and reorder them whenever you like.</p>
      </div>
      <div class="step-card">
        <div class="num">03</div>
        <h3>Verify and stay live</h3>
        <p>Your site is public straight away on a free Somahub address. Verify your school within 7 days to keep it online. Add your own domain whenever you are ready.</p>
      </div>
    </div>
  </div>
</section>

<section class="bg-white" id="plans">
  <div class="wrap">
    <div class="section-head center">
      <span class="kicker">Plans</span>
      <h2>A free website, with extras when you want them</h2>
      <p style="margin-top:10px;font-size:0.95rem;color:var(--muted);">Your core website is free and stays online. Premium adds tools like enrollment and results. Every new school gets them free for 60 days, starting automatically.</p>
    </div>
    <div class="plans-wrap two">
      <div class="plan-card">
        <div class="plan-name">Free</div>
        <div class="plan-price">KSh 0 <span>forever</span></div>
        <div class="plan-desc">Your school's website, online and yours to edit. No expiry.</div>
        <ul>
          <li><span class="check">＋</span> Full website with all core pages</li>
          <li><span class="check">＋</span> Free yourschool.somahub.top address</li>
          <li><span class="check">＋</span> Edit text, photos and colours yourself</li>
          <li><span class="check">＋</span> Optional: your own domain (add-on)</li>
        </ul>
        <a href="get-started.php" class="plan-cta">Create my free site</a>
      </div>
      <div class="plan-card highlight">
        <div class="plan-name">Premium <span style="font-size:.7rem;background:var(--amber);color:var(--teal-deep);padding:2px 9px;border-radius:20px;vertical-align:middle;">60 days free, automatic</span></div>
        <div class="plan-price">KSh 3,000 <span>per year</span></div>
        <div class="plan-desc">About KSh 750 a term. Try it free for 60 days, then keep it or not.</div>
        <ul>
          <li><span class="check">＋</span> Everything in Free</li>
          <li><span class="check">＋</span> Online enrollment applications</li>
          <li><span class="check">＋</span> Results checking, attendance, positions and trends</li>
          <li><span class="check">＋</span> Fee structure for every grade and term</li>
        </ul>
        <a href="get-started.php" class="plan-cta">Start with 60 days free</a>
      </div>
    </div>
    <p style="text-align:center;margin-top:22px;font-size:.92rem;color:var(--muted);max-width:620px;margin-left:auto;margin-right:auto;"><strong style="color:var(--teal);">Your site never goes offline after the trial.</strong> If you do not upgrade, it simply stays on Free and the Premium tools pause until you are ready. <a href="pricing.php" style="color:var(--teal);font-weight:700;">Full pricing</a></p>
  </div>
</section>

<section id="reviews">
  <div class="wrap">
    <div class="section-head center">
      <span class="kicker">What people are saying</span>
      <h2>Reviews</h2>
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
        <input type="hidden" name="redirect_to" value="/index.php">
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

<p style="text-align:center;margin:-30px 0 50px;font-size:.92rem;"><a href="reviews.php" style="color:var(--teal);font-weight:700;">Read all reviews</a> &nbsp;·&nbsp; <a href="schools.php" style="color:var(--teal);font-weight:700;"><?= (int)$schoolCount ?> school<?= $schoolCount == 1 ? '' : 's' ?> online</a> &nbsp;·&nbsp; <a href="features.php" style="color:var(--teal);font-weight:700;">Everything included</a></p>
<section class="bg-white" id="contact">
  <div class="wrap">
    <div class="section-head center">
      <span class="kicker">Get In Touch</span>
      <h2>Have a question?</h2>
      <p style="margin-top:14px;font-size:0.95rem;color:var(--muted);">We'll get back to you as soon as we can.</p>
    </div>
    <form method="POST" action="contact-submit.php" class="contact-form loader-on-submit">
      <div class="form-row">
        <div>
          <label>Email</label>
          <input type="email" name="email" required>
        </div>
        <div>
          <label>Phone (optional)</label>
          <input type="tel" name="phone">
        </div>
      </div>
      <label>Subject</label>
      <input type="text" name="subject" required>
      <label>Message</label>
      <textarea name="message" rows="5" required></textarea>
      <label class="consent-check">
        <input type="checkbox" name="agreed_to_terms" required>
        I agree to Somahub's <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.
      </label>
      <button type="submit" class="btn-primary" style="width:100%;margin-top:10px;">Send Message</button>
    </form>
  </div>
</section>

<div class="cta-band">
  <div class="wrap">
    <h2>Ready to put your school online?</h2>
    <p>Free to start. Your site is live in minutes.</p>
    <a href="get-started.php" class="btn-primary" style="display:inline-block;">Create my school site</a>
  </div>
</div>

<?php include __DIR__ . '/_public_foot.php'; ?>
