<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pricing — Somahub</title>
<meta name="description" content="Simple, transparent pricing for Somahub. Free websites for schools, plus clear pricing for custom domains and custom design work.">
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

  header{position:sticky;top:0;z-index:50;background:rgba(247,242,231,0.94);backdrop-filter:blur(6px);border-bottom:1px solid var(--line);}
  .navbar{display:flex;align-items:center;justify-content:space-between;padding:18px 24px;max-width:1080px;margin:0 auto;}
  .brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:1.2rem;}
  .brand .dot{width:10px;height:10px;background:var(--amber);border-radius:50%;}
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
  .plan-card{background:#fff;border:2px solid var(--line);border-radius:20px;padding:32px;width:300px;}
  .plan-card.highlight{background:var(--teal);color:var(--sand);border-color:var(--teal);}
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

  /* ADD-ONS TABLE */
  .addon-table{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;border:1px solid var(--line);}
  .addon-table th{background:var(--teal);color:var(--sand);text-align:left;padding:14px 18px;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.04em;}
  .addon-table td{padding:16px 18px;border-bottom:1px solid var(--line);font-size:0.9rem;}
  .addon-table tr:last-child td{border-bottom:none;}
  .addon-table .price{font-weight:700;color:var(--teal);}

  /* CUSTOM BUILD CARDS */
  .build-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;}
  .build-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px;}
  .build-card.featured{border:2px solid var(--amber);}
  .build-name{font-size:1rem;font-weight:700;margin-bottom:6px;}
  .build-price{font-size:1.5rem;font-weight:800;color:var(--teal);margin-bottom:4px;}
  .build-time{font-family:'Space Mono',monospace;font-size:0.76rem;color:var(--muted);margin-bottom:14px;display:inline-block;background:var(--sand);padding:3px 10px;border-radius:20px;}
  .build-card p{font-size:0.86rem;color:var(--muted);}

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

<header>
  <div class="navbar">
    <a href="index.php" class="brand"><span class="dot"></span> somahub</a>
    <a href="index.php#contact" class="navcta">Get Started</a>
  </div>
</header>

<section class="hero">
  <span class="kicker">Pricing</span>
  <h1>No hidden fees. Ever.</h1>
  <p>Every price we charge, published right here. If you ever see a number anywhere else that doesn't match this page, this page is the correct one.</p>
</section>

<section id="plans">
  <div class="section-head" style="text-align:center;margin-left:auto;margin-right:auto;">
    <h2>Website plans</h2>
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
      <a href="index.php#contact" class="plan-cta">Get Started Free</a>
    </div>
    <div class="plan-card highlight">
      <div class="plan-name">Paid</div>
      <div class="plan-price">KSh 2,500 <span>/ year</span></div>
      <div class="plan-desc">About KSh 625 a term. Everything in Free, plus the tools that save your office real time. First term free for new schools.</div>
      <ul>
        <li><span class="check">＋</span> Everything in Free</li>
        <li><span class="check">＋</span> Online enrollment applications</li>
        <li><span class="check">＋</span> Term results checking for parents</li>
        <li><span class="check">＋</span> Published fee structure</li>
      </ul>
      <a href="index.php#contact" class="plan-cta">Start Your Free Term</a>
    </div>
  </div>
</section>

<section style="background:#fff;">
  <div class="wrap">
    <div class="section-head">
      <h2>Custom domain</h2>
      <p>Want your own domain instead of the free somahub.top address? Add either of these to the Paid plan.</p>
    </div>
    <table class="addon-table">
      <tr><th>Domain type</th><th>What's included</th><th>Price</th></tr>
      <tr>
        <td>Budget domain<br><span style="color:var(--muted);font-size:0.8rem;">e.g. yourschool.top</span></td>
        <td>Registration, renewal tracking, DNS setup and support</td>
        <td class="price">KSh 900 / year</td>
      </tr>
      <tr>
        <td>.co.ke domain<br><span style="color:var(--muted);font-size:0.8rem;">e.g. yourschool.co.ke</span></td>
        <td>Registration, renewal tracking, DNS setup and support</td>
        <td class="price">KSh 1,800 / year</td>
      </tr>
    </table>
    <div class="note-box">
      <strong>Why we charge slightly above the raw domain cost:</strong> we track your renewal date so your domain never silently expires, handle the registrar relationship on your behalf, and set up the technical DNS pointing correctly. This is an ongoing service, not just a one-time purchase.
    </div>
  </div>
</section>

<section id="custom-builds">
  <div class="wrap">
    <div class="section-head">
      <h2>Custom site design</h2>
      <p>Most schools are happy with our standard themes. If you want something more tailored, here's exactly what that costs.</p>
    </div>
    <div class="build-grid">
      <div class="build-card">
        <div class="build-name">Standard Build</div>
        <div class="build-price">Free</div>
        <span class="build-time">2–3 days</span>
        <p>Uses our existing themes. You provide your content and photos, we set it up and you review it before it goes live.</p>
      </div>
      <div class="build-card featured">
        <div class="build-name">Branded Setup</div>
        <div class="build-price">KSh 3,500 <span style="font-size:0.9rem;font-weight:500;">one-time</span></div>
        <span class="build-time">4–6 days</span>
        <p>We match your school's real colors and logo, and help you write the copy if you don't have content ready yet.</p>
      </div>
      <div class="build-card">
        <div class="build-name">Fully Custom Design</div>
        <div class="build-price">From KSh 10,000</div>
        <span class="build-time">2–3 weeks</span>
        <p>A genuinely bespoke layout and sections beyond our standard templates. Final price depends on scope — we'll quote after understanding what you need.</p>
      </div>
    </div>
  </div>
</section>

<div class="cta-band">
  <h2>Questions about pricing?</h2>
  <p>Ask Rafiki using the chat button in the corner, or message us directly.</p>
  <a href="https://wa.me/254707306888?text=<?= urlencode('Hi Somahub, I have a question about pricing.') ?>" class="btn-primary">Chat on WhatsApp</a>
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
