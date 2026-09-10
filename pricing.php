<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/settings.php';
$db = get_db();
require_once __DIR__ . '/includes/appearance.php';
$templates = get_active_templates($db);
$palettes = get_active_palettes($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pricing - Somahub</title>
<link rel="canonical" href="https://somahub.top/pricing.php">
<meta name="description" content="Simple, transparent pricing for Somahub. Free websites for schools, a 60-day full-access trial, plus clear pricing for domains, templates, and content writing.">
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; --ink:#1C1C16; --muted:#6E6A5C; --line:#E5DFCC; }
  *{box-sizing:border-box;margin:0;padding:0;}
  html{scroll-behavior:smooth;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);color:var(--ink);line-height:1.65;}
  h1,h2,h3{font-weight:800;letter-spacing:-0.02em;}
  .mono{font-family:'Space Mono',monospace;letter-spacing:0.02em;}
  a{color:inherit;text-decoration:none;}
  .wrap{max-width:1080px;margin:0 auto;padding:0 24px;}

  .navcta{background:var(--teal);color:var(--sand);padding:10px 20px;border-radius:24px;font-size:0.85rem;font-weight:700;}

  .hero{padding:70px 24px 40px;text-align:center;}
  .hero span.kicker{font-family:'Space Mono',monospace;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--teal);}
  .hero h1{font-size:clamp(2rem,5vw,3rem);margin:12px 0;}
  .hero p{color:var(--muted);max-width:50ch;margin:0 auto;font-size:1.05rem;}

  section{padding:50px 24px;}
  .section-head{max-width:60ch;margin-bottom:32px;}
  .section-head h2{font-size:clamp(1.4rem,3vw,1.9rem);}
  .section-head p{color:var(--muted);margin-top:8px;font-size:0.95rem;}

  /* PLANS */
  .plans-wrap{display:flex;gap:24px;flex-wrap:wrap;justify-content:center;}
  .plan-card{background:#fff;border:2px solid var(--line);border-radius:20px;padding:32px;width:100%;max-width:280px;}
  .plan-card.highlight{background:var(--teal);color:var(--sand);border-color:var(--teal);}
  .plan-card.trial{border-color:var(--amber);}
  .plan-name{font-size:1.05rem;font-weight:700;margin-bottom:4px;}
  .plan-price{font-size:1.9rem;font-weight:800;margin:10px 0 2px;}
  .plan-price span{font-size:0.8rem;font-weight:500;opacity:0.7;}
  .plan-desc{font-size:0.83rem;opacity:0.8;margin-bottom:20px;}
  .plan-card ul{list-style:none;margin-bottom:24px;}
  .plan-card li{font-size:0.86rem;padding:8px 0;display:flex;gap:8px;align-items:flex-start;}
  .plan-card .check{color:var(--amber);font-weight:800;}
  .plan-cta{display:block;text-align:center;padding:12px;border-radius:24px;font-weight:700;font-size:0.88rem;}
  .plan-card:not(.highlight) .plan-cta{background:var(--teal);color:var(--sand);}
  .plan-card.highlight .plan-cta{background:var(--amber);color:var(--teal-deep);}
  .plan-card.trial .plan-cta{background:var(--amber);color:var(--teal-deep);}

  /* ADD-ONS TABLE */
  .addon-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;}
  .addon-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;}
  .addon-name{font-weight:700;font-size:0.95rem;margin-bottom:2px;}
  .addon-example{font-size:0.78rem;color:var(--muted);margin-bottom:8px;}
  .addon-desc{font-size:0.85rem;color:var(--muted);margin-bottom:16px;line-height:1.5;}
  .addon-price{font-size:1.1rem;font-weight:800;color:var(--teal);}
  .addon-price span{font-size:0.75rem;font-weight:500;color:var(--muted);}

  /* CUSTOM BUILD CARDS */
  .build-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;}
  .build-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px;}
  .build-card.featured{border:2px solid var(--amber);}
  .build-name{font-size:1rem;font-weight:700;margin-bottom:6px;}
  .build-price{font-size:1.5rem;font-weight:800;color:var(--teal);margin-bottom:4px;}
  .build-time{font-family:'Space Mono',monospace;font-size:0.76rem;color:var(--muted);margin-bottom:14px;display:inline-block;background:var(--sand);padding:3px 10px;border-radius:20px;}
  .build-card p{font-size:0.86rem;color:var(--muted);}

  /* THEME GALLERY */
  .theme-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:18px;}
  .theme-card{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;}
  .theme-preview{height:110px;position:relative;}
  .theme-preview .tp-bar{height:22px;display:flex;align-items:center;padding:0 10px;gap:5px;}
  .theme-preview .tp-dot{width:8px;height:8px;border-radius:50%;}
  .theme-preview .tp-body{padding:12px;}
  .theme-preview .tp-line{height:8px;border-radius:3px;margin-bottom:7px;}
  .theme-card-meta{padding:12px 14px;display:flex;justify-content:space-between;align-items:center;}
  .theme-card-name{font-size:0.88rem;font-weight:700;}
  .premium-tag{display:inline-block;background:#F2A65A;color:#0A3A3E;font-size:0.66rem;font-weight:800;padding:2px 8px;border-radius:10px;}

  .note-box{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px 24px;font-size:0.87rem;color:var(--muted);margin-top:20px;}
  .note-box strong{color:var(--ink);}

  .cta-band{background:var(--teal-deep);color:var(--sand);padding:70px 24px;text-align:center;}
  .cta-band h2{font-size:clamp(1.5rem,3.5vw,2.1rem);margin-bottom:14px;}
  .cta-band p{color:#BFD8D9;max-width:50ch;margin:0 auto 26px;}
  .btn-primary{background:var(--amber);color:var(--teal-deep);padding:14px 26px;border-radius:24px;font-weight:700;font-size:0.9rem;display:inline-block;}

  footer{background:var(--teal-deep);color:#7FA5A6;padding:32px 24px;text-align:center;font-size:0.85rem;}
</style>
</head>
<body>

<?php $navRoot = '.'; include __DIR__ . '/_public_nav.php'; ?>

<section id="plans" style="padding-top:60px;">
  <div class="section-head" style="text-align:center;margin-left:auto;margin-right:auto;">
    <h2>Website plans</h2>
    <p>Start on Free anytime, or try everything free for 60 days on Trial - no card required either way.</p>
  </div>
  <div class="plans-wrap">
    <div class="plan-card">
      <div class="plan-name">Free</div>
      <div class="plan-price">KSh 0 <span>forever</span></div>
      <div class="plan-desc">Everything a school needs for a real presence online.</div>
      <ul>
        <li><span class="check">＋</span> Full website with all core pages</li>
        <li><span class="check">＋</span> Free yourschool.somahub.top address</li>
        <li><span class="check">＋</span> Self service editing dashboard</li>
        <li><span class="check">＋</span> Published fee structure</li>
        <li><span class="check">＋</span> Term-by-term results checking</li>
      </ul>
      <a href="get-started.php" class="plan-cta">Get Started Free</a>
    </div>
    <div class="plan-card trial">
      <div class="plan-name">60-Day Trial</div>
      <div class="plan-price">KSh 0 <span>for 60 days</span></div>
      <div class="plan-desc">Everything unlocked, free, so you can see the real value before deciding.</div>
      <ul>
        <li><span class="check">＋</span> Everything in Free</li>
        <li><span class="check">＋</span> Every premium template</li>
        <li><span class="check">＋</span> Online enrollment applications</li>
        <li><span class="check">＋</span> Full report: results, attendance, position, trends & fees</li>
      </ul>
      <a href="get-started.php" class="plan-cta">Start Free Trial</a>
    </div>
    <div class="plan-card highlight">
      <div class="plan-name">Premium</div>
      <div class="plan-price">KSh 3,000 <span>/ year</span></div>
      <div class="plan-desc">About KSh 750 a term. Everything the Trial unlocks, permanently.</div>
      <ul>
        <li><span class="check">＋</span> Everything in Free</li>
        <li><span class="check">＋</span> Every premium template</li>
        <li><span class="check">＋</span> Online enrollment applications</li>
        <li><span class="check">＋</span> Full report: results, attendance, position, trends & fees</li>
      </ul>
      <a href="get-started.php" class="plan-cta">Get Started</a>
    </div>
  </div>
</section>

<section style="background:#fff;">
  <div class="wrap">
    <div class="section-head">
      <h2>See our templates</h2>
      <p>Every school picks a template at signup - change it anytime from your dashboard. Premium templates are included with the Premium plan and the 60-Day Trial. Any color palette below can be paired with any template, on any plan.</p>
    </div>
    <div class="theme-gallery">
      <?php foreach ($templates as $t): $isPremium = !empty($t['is_premium']); ?>
        <div class="theme-card">
          <div class="theme-card-meta">
            <span class="theme-card-name"><?= htmlspecialchars($t['name']) ?></span>
            <?php if ($isPremium): ?><span class="premium-tag">Premium</span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="section-head" style="margin-top:40px;">
      <h2>Color palettes</h2>
      <p>Free to choose and change anytime - no plan or premium restriction on color.</p>
    </div>
    <div class="theme-gallery">
      <?php foreach ($palettes as $p): $vars = json_decode($p['css_variables_json'], true); ?>
        <div class="theme-card">
          <div class="theme-preview" style="background:<?= htmlspecialchars($vars['bg'] ?? '#f4f4f4') ?>;">
            <div class="tp-bar" style="background:<?= htmlspecialchars($vars['primary'] ?? '#333') ?>;">
              <span class="tp-dot" style="background:<?= htmlspecialchars($vars['accent'] ?? '#fff') ?>;"></span>
            </div>
            <div class="tp-body">
              <div class="tp-line" style="width:75%;background:<?= htmlspecialchars($vars['primary'] ?? '#333') ?>;opacity:0.85;"></div>
              <div class="tp-line" style="width:95%;background:<?= htmlspecialchars($vars['accent'] ?? '#333') ?>;opacity:0.5;"></div>
              <div class="tp-line" style="width:55%;background:<?= htmlspecialchars($vars['accent'] ?? '#333') ?>;opacity:0.5;"></div>
            </div>
          </div>
          <div class="theme-card-meta">
            <span class="theme-card-name"><?= htmlspecialchars($p['name']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="addons">
  <div class="wrap">
    <div class="section-head">
      <h2>Add-ons</h2>
      <p>Optional extras, purchased anytime from your dashboard once your site is set up.</p>
    </div>
    <div class="addon-grid">
      <div class="addon-card">
        <div class="addon-name">Custom Domain — Budget</div>
        <div class="addon-example">e.g. yourschool.top</div>
        <p class="addon-desc">Registration, renewal tracking, DNS setup and support</p>
        <div class="addon-price">KSh 900 <span>/ year</span></div>
      </div>
      <div class="addon-card">
        <div class="addon-name">Custom Domain — .co.ke</div>
        <div class="addon-example">e.g. yourschool.co.ke</div>
        <p class="addon-desc">Registration, renewal tracking, DNS setup and support</p>
        <div class="addon-price">KSh 1,800 <span>/ year</span></div>
      </div>
      <div class="addon-card">
        <div class="addon-name">Content Writing</div>
        <p class="addon-desc">We write your About, Academics, and Admissions text for you, tailored to your actual school — not the generic starter content</p>
        <div class="addon-price">KSh 1,500 <span>one-time</span></div>
      </div>
      <div class="addon-card">
        <div class="addon-name">Google Business Profile Setup</div>
        <p class="addon-desc">We create and verify your school on Google Business Profile using your exact location — shows up on Google Maps and local search</p>
        <div class="addon-price">KSh 1,200 <span>one-time</span></div>
      </div>
    </div>
  </div>
</section>

<section id="custom-builds" style="background:#fff;">
  <div class="wrap">
    <div class="section-head">
      <h2>Fully custom design</h2>
      <p>Most schools are happy with a theme plus the add-ons above. If you want something genuinely bespoke beyond our templates, here's what that costs.</p>
    </div>
    <div class="build-grid">
      <div class="build-card">
        <div class="build-name">Standard Build</div>
        <div class="build-price">Free</div>
        <span class="build-time">2–3 days</span>
        <p>Uses our existing templates and color palettes. You provide your content and photos, we set it up and you review it before it goes live.</p>
      </div>
      <div class="build-card featured">
        <div class="build-name">Fully Custom Design</div>
        <div class="build-price">From KSh 10,000</div>
        <span class="build-time">2–3 weeks</span>
        <p>A genuinely bespoke layout and sections beyond our standard templates, matched to your school's real branding. Final price depends on scope - we'll quote after understanding what you need.</p>
      </div>
    </div>
  </div>
</section>

<div class="cta-band">
  <h2>Questions about pricing?</h2>
  <p>Ask Rafiki using the chat button in the corner, or message us directly.</p>
  <a href="https://wa.me/<?= htmlspecialchars(get_setting($db, 'whatsapp_outreach_number', '254707306888')) ?>?text=<?= urlencode('Hi Somahub, I have a question about pricing.') ?>" class="btn-primary">Chat on WhatsApp</a>
</div>

<footer>
  <div>Somahub. Websites for Kenyan schools.</div>
  <div style="margin-top:6px;"><a href="index.php" style="text-decoration:underline;">Back to somahub.top</a></div>
</footer>

<?php
$SOMAHUB_CHAT_CONTEXT = 'marketing';
include __DIR__ . '/_chat_widget.php';
?>
</body>
</html>
