<?php
require_once __DIR__ . '/config/db.php';
$db = get_db();

// Live portfolio — schools actually on the platform (for social proof + showcase)
$schools = $db->query("
    SELECT name, slug, plan, county, verification_status
    FROM schools
    WHERE status IN ('active','trial')
    ORDER BY created_at DESC
    LIMIT 12
")->fetchAll();

$schoolCount = $db->query("SELECT COUNT(*) c FROM schools WHERE status IN ('active','trial')")->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Somahub: Free Websites for Kenyan Schools</title>
<meta name="description" content="Get your school online free. Somahub builds and hosts your school's website, you just edit and go live.">

<!-- Favicons -->
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="assets/icon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/icon-16.png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">

<!-- Open Graph (WhatsApp, Facebook, Instagram DM previews) -->
<meta property="og:type" content="website">
<meta property="og:title" content="Somahub — Free Websites for Kenyan Schools">
<meta property="og:description" content="Get your school online free. We build and host your school's website, you just edit and go live.">
<meta property="og:image" content="https://somahub.top/assets/og-share-image.png">
<meta property="og:url" content="https://somahub.top">
<meta property="og:site_name" content="Somahub">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Somahub — Free Websites for Kenyan Schools">
<meta name="twitter:description" content="Get your school online free. We build and host your school's website, you just edit and go live.">
<meta name="twitter:image" content="https://somahub.top/assets/og-share-image.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{
    --teal:#0F5257;
    --teal-deep:#0A3A3E;
    --amber:#F2A65A;
    --sand:#F7F2E7;
    --ink:#1C1C16;
    --muted:#6E6A5C;
    --line:#E5DFCC;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  html{scroll-behavior:smooth;}
  body{font-family:'Manrope',sans-serif;color:var(--ink);background:var(--sand);line-height:1.65;}
  h1,h2,h3{font-family:'Manrope',sans-serif;font-weight:800;letter-spacing:-0.02em;}
  .mono{font-family:'Space Mono',monospace;letter-spacing:0.02em;}
  a{color:inherit;text-decoration:none;}
  .wrap{max-width:1120px;margin:0 auto;padding:0 24px;}

  header{position:sticky;top:0;z-index:50;background:rgba(247,242,231,0.94);backdrop-filter:blur(6px);border-bottom:1px solid var(--line);}
  .navbar{display:flex;align-items:center;justify-content:space-between;padding:18px 24px;max-width:1120px;margin:0 auto;}
  .brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:1.2rem;}
  .brand .dot{width:10px;height:10px;background:var(--amber);border-radius:50%;}
  nav ul{list-style:none;display:flex;gap:28px;}
  nav a{font-size:0.9rem;font-weight:600;}
  nav a:hover{color:var(--teal);}
  .navcta{background:var(--teal);color:var(--sand)!important;padding:10px 20px;border-radius:24px;font-size:0.85rem;font-weight:700;}
  .menu-toggle{display:none;background:none;border:none;font-size:1.5rem;cursor:pointer;}
  @media(max-width:820px){
    nav ul{display:none;}
    .menu-toggle{display:block;}
    nav ul.open{display:flex;flex-direction:column;position:absolute;top:60px;left:0;right:0;background:var(--sand);padding:20px 24px;border-bottom:1px solid var(--line);gap:16px;}
  }

  /* HERO */
  .hero{padding:80px 24px 50px;}
  .hero-inner{max-width:1120px;margin:0 auto;text-align:center;}
  .hero-tag{display:inline-flex;align-items:center;gap:8px;font-family:'Space Mono',monospace;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;background:#fff;border:1px solid var(--line);padding:7px 16px;border-radius:24px;margin-bottom:26px;}
  .hero-tag .dot{width:7px;height:7px;background:var(--amber);border-radius:50%;}
  .hero h1{font-size:clamp(2.3rem,6vw,4rem);line-height:1.08;max-width:18ch;margin:0 auto;}
  .hero h1 .accent{color:var(--teal);}
  .hero p{max-width:48ch;margin:22px auto 0;font-size:1.08rem;color:var(--muted);}
  .hero-ctas{display:flex;gap:14px;justify-content:center;margin-top:32px;flex-wrap:wrap;}
  .quick-links{display:flex;gap:20px;justify-content:center;margin-top:22px;flex-wrap:wrap;}
  .quick-links a{font-size:0.85rem;color:var(--muted);text-decoration:underline;text-underline-offset:3px;}
  .quick-links a:hover{color:var(--teal);}
  .btn-primary{background:var(--teal);color:var(--sand);padding:15px 28px;font-weight:700;border-radius:24px;font-size:0.92rem;border:none;cursor:pointer;}
  .btn-ghost{border:2px solid var(--teal);color:var(--teal);padding:13px 28px;border-radius:24px;font-weight:700;font-size:0.92rem;}

  /* BENTO PREVIEW under hero */
  .bento{display:grid;grid-template-columns:1.3fr 1fr 1fr;grid-template-rows:auto auto;gap:14px;max-width:1120px;margin:56px auto 0;}
  @media(max-width:800px){.bento{grid-template-columns:1fr 1fr;}}
  .bento-cell{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;}
  .bento-cell.dark{background:var(--teal);color:var(--sand);}
  .bento-cell.amber{background:var(--amber);color:var(--teal-deep);}
  .bento-cell h4{font-size:0.95rem;margin-bottom:6px;}
  .bento-cell p{font-size:0.82rem;color:var(--muted);}
  .bento-cell.dark p{color:#BFD8D9;}
  .bento-cell.tall{grid-row:span 2;display:flex;flex-direction:column;justify-content:space-between;}
  .bento-num{font-size:2rem;font-weight:800;}

  section{padding:70px 24px;}
  .section-head{margin-bottom:44px;max-width:60ch;}
  .section-head.center{margin-left:auto;margin-right:auto;text-align:center;}
  .kicker{font-family:'Space Mono',monospace;font-size:0.74rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--teal);display:block;margin-bottom:10px;}
  .section-head h2{font-size:clamp(1.6rem,3.6vw,2.3rem);}
  .bg-white{background:#fff;}

  /* HOW IT WORKS */
  .steps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;}
  .step-card{background:var(--sand);border:1px solid var(--line);border-radius:16px;padding:26px;}
  .step-card .num{font-weight:800;font-size:1.7rem;color:var(--amber);margin-bottom:14px;}
  .step-card h3{font-size:1.05rem;margin-bottom:8px;}
  .step-card p{font-size:0.88rem;color:var(--muted);}

  /* FEATURES */
  .feature-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;}
  .feature-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:24px;}
  .feature-card h3{font-size:0.98rem;margin-bottom:8px;display:flex;align-items:center;gap:8px;}
  .feature-card p{font-size:0.86rem;color:var(--muted);}
  .tag{font-size:0.62rem;background:var(--teal);color:var(--sand);padding:2px 9px;border-radius:20px;font-weight:700;text-transform:uppercase;}

  /* PLANS — two clean cards, not a table */
  .plans-wrap{display:flex;gap:24px;flex-wrap:wrap;justify-content:center;}
  .plan-card{background:#fff;border:2px solid var(--line);border-radius:20px;padding:36px;width:320px;}
  .plan-card.highlight{background:var(--teal);color:var(--sand);border-color:var(--teal);position:relative;}
  .plan-card.highlight .plan-note{position:absolute;top:-14px;left:36px;background:var(--amber);color:var(--teal-deep);font-size:0.7rem;font-weight:800;padding:6px 14px;border-radius:20px;text-transform:uppercase;}
  .plan-name{font-size:1.1rem;font-weight:700;margin-bottom:6px;}
  .plan-price{font-size:2.1rem;font-weight:800;margin:10px 0 4px;}
  .plan-price span{font-size:0.85rem;font-weight:500;opacity:0.7;}
  .plan-desc{font-size:0.85rem;opacity:0.8;margin-bottom:24px;}
  .plan-card ul{list-style:none;margin-bottom:28px;}
  .plan-card li{font-size:0.88rem;padding:10px 0;display:flex;gap:10px;align-items:flex-start;}
  .plan-card .check{color:var(--amber);font-weight:800;}
  .plan-card.highlight .check{color:var(--amber);}
  .plan-cta{display:block;text-align:center;padding:13px;border-radius:24px;font-weight:700;font-size:0.9rem;}
  .plan-card:not(.highlight) .plan-cta{background:var(--teal);color:var(--sand);}
  .plan-card.highlight .plan-cta{background:var(--amber);color:var(--teal-deep);}

  /* PORTFOLIO */
  .portfolio-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;}
  .portfolio-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;}
  .school-icon{width:42px;height:42px;background:var(--teal);color:var(--amber);border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;margin-bottom:14px;}
  .portfolio-card h3{font-size:1rem;margin-bottom:4px;}
  .portfolio-card .url{font-family:'Space Mono',monospace;font-size:0.78rem;color:var(--muted);margin-bottom:10px;}
  .portfolio-card .county-pill{font-size:0.72rem;background:var(--sand);padding:4px 12px;border-radius:20px;}
  .verified-pill{background:#DCEFE1;color:#1B4D3E;font-size:0.68rem;padding:2px 6px;border-radius:10px;vertical-align:middle;}
  .empty-portfolio{padding:40px;text-align:center;color:var(--muted);border:1px dashed var(--line);border-radius:16px;}

  /* CONTACT FORM */
  .contact-form{max-width:600px;margin:0 auto;background:var(--sand);border:1px solid var(--line);border-radius:20px;padding:32px;}
  .contact-form label{display:block;font-size:0.82rem;font-weight:700;margin-bottom:6px;margin-top:16px;}
  .contact-form label:first-of-type{margin-top:0;}
  .contact-form input, .contact-form textarea{width:100%;padding:12px 14px;border:1.5px solid var(--line);border-radius:10px;font-family:inherit;font-size:0.92rem;box-sizing:border-box;background:#fff;}
  .contact-form textarea{resize:vertical;}
  .consent-check{display:flex;align-items:flex-start;gap:8px;font-size:0.82rem;color:var(--muted);margin-top:18px;font-weight:400;cursor:pointer;}
  .consent-check input{width:auto;margin-top:2px;flex-shrink:0;}
  .consent-check a{color:var(--teal);}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  @media(max-width:560px){.form-row{grid-template-columns:1fr;}}

  .cta-band{background:var(--teal-deep);color:var(--sand);padding:80px 24px;text-align:center;}
  .cta-band h2{font-size:clamp(1.7rem,4.3vw,2.5rem);margin-bottom:16px;}
  .cta-band p{max-width:50ch;margin:0 auto 30px;color:#BFD8D9;}

  footer{background:var(--teal-deep);color:#7FA5A6;padding:36px 24px;text-align:center;font-size:0.85rem;border-top:1px solid #164447;}
</style>
</head>
<body>

<header>
  <div class="navbar">
    <div class="brand"><span class="dot"></span> somahub</div>
    <nav><ul id="navlinks">
      <li><a href="#how">How it works</a></li>
      <li><a href="#features">What is included</a></li>
      <li><a href="#plans">Plans</a></li>
      <li><a href="pricing.php">Full Pricing</a></li>
      <li><a href="#portfolio">Schools</a></li>
      <li><a href="results-portal.php">Check Results</a></li>
      <li><a href="dashboard/login.php">School Login</a></li>
      <li><a href="#contact" class="navcta">Get Started</a></li>
    </ul></nav>
    <button class="menu-toggle" onclick="document.getElementById('navlinks').classList.toggle('open')">☰</button>
  </div>
</header>

<section class="hero">
  <div class="hero-inner">
    <span class="hero-tag"><span class="dot"></span> Built for Kenyan schools</span>
    <h1>Your school deserves a <span class="accent">real website</span>, at no cost to you</h1>
    <p>Somahub builds and hosts your school's site. You review it, edit it from a simple dashboard, and it goes live in days, not months.</p>
    <div class="hero-ctas">
      <a href="#contact" class="btn-primary">Get Your Free Website</a>
      <a href="#portfolio" class="btn-ghost">See Schools Already Live</a>
    </div>
    <div class="quick-links">
      <a href="results-portal.php">🔎 Parent? Check your child's results</a>
      <a href="dashboard/login.php">🏫 School staff? Log in to your dashboard</a>
    </div>

    <div class="bento">
      <div class="bento-cell tall dark">
        <div>
          <h4>Zero setup cost</h4>
          <p>Your core pages, hosted and online, at no charge to your school.</p>
        </div>
        <div class="bento-num">KSh 0</div>
      </div>
      <div class="bento-cell amber">
        <div class="bento-num"><?= $schoolCount ?></div>
        <h4 style="margin-top:6px;">School<?= $schoolCount == 1 ? '' : 's' ?> live already</h4>
      </div>
      <div class="bento-cell">
        <h4>Free subdomain</h4>
        <p>yourschool.somahub.top, ready from day one.</p>
      </div>
      <div class="bento-cell" style="grid-column:span 2;">
        <h4>Edit it yourself, anytime</h4>
        <p>Update text and photos from your dashboard, no coding, no waiting on us for small changes.</p>
      </div>
    </div>
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
        <h3>We build your site</h3>
        <p>Send us your school's details and a few photos. We put together your pages, including Home, About, Academics, Admissions, Gallery, and Contact.</p>
      </div>
      <div class="step-card">
        <div class="num">02</div>
        <h3>You review and edit</h3>
        <p>Log into your dashboard to update text, swap photos, or reorder sections whenever you like.</p>
      </div>
      <div class="step-card">
        <div class="num">03</div>
        <h3>Your school goes live</h3>
        <p>Your site is published immediately on a free Somahub address. Add your own domain whenever you are ready.</p>
      </div>
    </div>
  </div>
</section>

<section id="features">
  <div class="wrap">
    <div class="section-head">
      <span class="kicker">What is included</span>
      <h2>Free to start, more when you need it</h2>
    </div>
    <div class="feature-grid">
      <div class="feature-card"><h3>Website and hosting</h3><p>Every core page your school needs, hosted and online, always free.</p></div>
      <div class="feature-card"><h3>Free subdomain</h3><p>yourschool.somahub.top, live from day one, no setup fee.</p></div>
      <div class="feature-card"><h3>Editable dashboard</h3><p>Update your own text and photos anytime, from a phone or a computer.</p></div>
      <div class="feature-card"><h3>Your own domain <span class="tag">Paid</span></h3><p>Bring a domain like yourschool.ac.ke, from KSh 900/year. <a href="pricing.php" style="color:var(--teal);font-weight:600;">See pricing</a></p></div>
      <div class="feature-card"><h3>Online enrollment <span class="tag">Paid</span></h3><p>Parents apply directly through your site instead of calling the office.</p></div>
      <div class="feature-card"><h3>Results checking <span class="tag">Paid</span></h3><p>Upload term results and let parents check them securely by admission number.</p></div>
      <div class="feature-card"><h3>Fee structure <span class="tag">Paid</span></h3><p>Publish clear fees and payment details for every grade and term.</p></div>
      <div class="feature-card"><h3>News and updates <span class="tag">Paid</span></h3><p>Post announcements, events, and photos as they happen.</p></div>
    </div>
  </div>
</section>

<section class="bg-white" id="plans">
  <div class="wrap">
    <div class="section-head center">
      <span class="kicker">Plans</span>
      <h2>Pick what your school needs today</h2>
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
          <li><span class="check">＋</span> Unlimited photo updates</li>
        </ul>
        <a href="#contact" class="plan-cta">Get Started Free</a>
      </div>
      <div class="plan-card highlight">
        <div class="plan-note">First term free</div>
        <div class="plan-name">Paid</div>
        <div class="plan-price">KSh 2,500 <span>per year</span></div>
        <div class="plan-desc">Everything in Free, plus the tools that save your office real time.</div>
        <ul>
          <li><span class="check">＋</span> Everything in Free</li>
          <li><span class="check">＋</span> Online enrollment applications</li>
          <li><span class="check">＋</span> Term results checking for parents</li>
          <li><span class="check">＋</span> Published fee structure</li>
        </ul>
        <a href="#contact" class="plan-cta">Start Your Free Term</a>
      </div>
    </div>
    <p style="text-align:center;margin-top:24px;font-size:0.9rem;color:var(--muted);">
      Want your own domain, or a custom designed site? <a href="pricing.php" style="color:var(--teal);font-weight:600;">See full pricing</a>
    </p>
  </div>
</section>

<section id="portfolio">
  <div class="wrap">
    <div class="section-head">
      <span class="kicker">Schools using Somahub</span>
      <h2><?= $schoolCount ?> school<?= $schoolCount == 1 ? '' : 's' ?> already online with us</h2>
    </div>

    <?php if ($schools): ?>
    <div class="portfolio-grid">
      <?php foreach ($schools as $s): ?>
      <a href="https://<?= urlencode($s['slug']) ?>.somahub.top/" class="portfolio-card">
        <div class="school-icon"><?= strtoupper(substr($s['name'], 0, 2)) ?></div>
        <h3><?= htmlspecialchars($s['name']) ?> <?php if ($s['verification_status'] === 'verified'): ?><span class="verified-pill">✓</span><?php endif; ?></h3>
        <div class="url"><?= htmlspecialchars($s['slug']) ?>.somahub.top</div>
        <span class="county-pill"><?= $s['county'] ? htmlspecialchars($s['county']) : ucfirst(str_replace('_',' ',$s['plan'])) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-portfolio">
      We are onboarding our first schools right now. Check back soon, or be one of the first.
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="bg-white" id="contact">
  <div class="wrap">
    <div class="section-head center">
      <span class="kicker">Get in touch</span>
      <h2>Tell us about your school</h2>
      <p style="margin-top:14px;font-size:0.95rem;color:var(--muted);">Send your details and we will reach out to set up your free website.</p>
    </div>
    <form method="POST" action="contact-submit.php" class="contact-form">
      <div class="form-row">
        <div>
          <label>School name</label>
          <input type="text" name="school_name" required>
        </div>
        <div>
          <label>Your name</label>
          <input type="text" name="contact_name" required>
        </div>
      </div>
      <div class="form-row">
        <div>
          <label>Phone number</label>
          <input type="tel" name="phone" required>
        </div>
        <div>
          <label>Email (optional)</label>
          <input type="email" name="email">
        </div>
      </div>
      <label>County</label>
      <input type="text" name="county" placeholder="e.g. Nyandarua">
      <label>Anything else we should know</label>
      <textarea name="message" rows="4" placeholder="Tell us a little about your school"></textarea>
      <label class="consent-check">
        <input type="checkbox" name="agreed_to_terms" required>
        I agree to Somahub's <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.
      </label>
      <button type="submit" class="btn-primary" style="width:100%;margin-top:10px;">Send and Get Started</button>
    </form>
  </div>
</section>

<div class="cta-band">
  <div class="wrap">
    <h2>Prefer to reach us directly</h2>
    <p>Call, message, or email and we will set up your school personally.</p>
    <a href="mailto:hello@somahub.top" class="btn-primary" style="display:inline-block;">Email hello@somahub.top</a>
  </div>
</div>

<footer>
  <div>Somahub. Websites for Kenyan schools.</div>
</footer>

<?php include __DIR__ . '/_chat_widget.php'; ?>
</body>
</html>