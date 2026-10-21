<?php
require_once __DIR__ . '/includes/consent.php';
require_once __DIR__ . '/config/db.php';
$db = get_db();
$schoolCount = $db->query("SELECT COUNT(*) c FROM schools WHERE status IN ('active','trial') AND slug <> 'demo'")->fetch()['c'];
$pageTitle = 'Somahub: Free Websites for Kenyan Schools';
$pagePath = '/';
$pageJsonLd = <<<'LD'
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"Organization","name":"Somahub","url":"https://somahub.top/","logo":"https://somahub.top/assets/apple-touch-icon.png"},{"@type":"WebSite","name":"Somahub","url":"https://somahub.top/"}]}</script>
LD;
include __DIR__ . '/_public_head.php';
?>
<section class="hero">
  <div class="hero-inner">
    <span class="hero-tag"><span class="dot"></span> Built for Kenyan schools</span>
    <h1>Your school deserves a <span class="accent">real website</span>, at no cost to you</h1>
    <p>Sign up, answer a few questions, and your site is live in minutes. We set up the pages and host them. You edit text, photos and colours right on the page.</p>
    <div class="hero-ctas">
      <a href="get-started.php" class="btn-primary">Create my school site</a>
      <a href="#try" class="btn-ghost">Try the editor</a>
    </div>
    <p style="margin-top:14px;font-size:.84rem;color:var(--muted);">60 days of everything free, no card needed. Already have a site? <a href="dashboard/login.php" style="color:var(--teal);font-weight:700;text-decoration:underline;">Log in</a></p>

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
      document.getElementById('tryStart').onclick = function(){ set(true); };
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

<section class="explore" id="explore">
  <div class="wrap">
    <div class="section-head center">
      <span class="kicker">Keep exploring</span>
      <h2>Everything else, one tap away</h2>
    </div>
    <div class="explore-grid">
      <a href="features.php"><span class="ex-ic">checklist</span><h3>What is included</h3><p>Hosting, your own address, enrollment, results and fees. See what is free and what is paid.</p><b>See features &rarr;</b></a>
      <a href="pricing.php"><span class="ex-ic">sell</span><h3>Plans and pricing</h3><p>Free to start, a 60-day full trial, then Premium at KSh 3,000 a year.</p><b>View pricing &rarr;</b></a>
      <a href="schools.php"><span class="ex-ic">school</span><h3><?= $schoolCount ?> school<?= $schoolCount == 1 ? '' : 's' ?> online</h3><p>Browse the schools already using Somahub and visit their sites.</p><b>See schools &rarr;</b></a>
      <a href="reviews.php"><span class="ex-ic">reviews</span><h3>Reviews</h3><p>What head teachers and administrators say, and how to leave your own.</p><b>Read reviews &rarr;</b></a>
      <a href="contact.php"><span class="ex-ic">mail</span><h3>Talk to us</h3><p>Questions before you start? Message us and we will help you personally.</p><b>Contact us &rarr;</b></a>
    </div>
  </div>
</section>

<div class="cta-band">
  <div class="wrap">
    <h2>Ready to put your school online?</h2>
    <p>Create your site in minutes. It is free to start.</p>
    <a href="get-started.php" class="btn-primary" style="display:inline-block;">Get started free</a>
  </div>
</div>

<?php include __DIR__ . '/_public_foot.php'; ?>
