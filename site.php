<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/plan.php';
$db = get_db();

// In production, subdomain routing (.htaccess) sets this from *.somahub.top automatically.
// Falling back to ?school=slug here so this file works standalone during setup/testing.
$slug = $_GET['school'] ?? '';

$stmt = $db->prepare("
    SELECT s.*, t.css_variables_json, t.custom_css, t.name AS theme_name
    FROM schools s
    JOIN themes t ON t.id = s.theme_id
    WHERE s.slug = ?
");
$stmt->execute([$slug]);
$school = $stmt->fetch();

if (!$school) {
    http_response_code(404);
    die('School not found. Check the address and try again.');
}

$theme = json_decode($school['css_variables_json'], true);
$theme_custom_css = $school['custom_css'] ?? '';
if (!empty($school['accent_override'])) $theme['accent'] = $school['accent_override'];
if (!empty($school['primary_override'])) $theme['primary'] = $school['primary_override'];
if (!empty($school['bg_override'])) $theme['bg'] = $school['bg_override'];

$sectionsStmt = $db->prepare("
    SELECT ss.*, st.key_name, st.label, st.is_premium
    FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.school_id = ? AND ss.is_visible = 1
    ORDER BY ss.position ASC
");
$sectionsStmt->execute([$school['id']]);
$sections = $sectionsStmt->fetchAll();

// Kill switch: if this school's paid plan has lapsed (past the grace period with no
// payment), quietly drop premium sections from what's rendered — same as if they'd
// never added them. This never shows publicly as "payment overdue"; it just reverts
// to looking like the free tier, keeping the school's public reputation intact.
if (is_premium_locked($school)) {
    $sections = array_filter($sections, fn($s) => !$s['is_premium']);
}

// Pick the best available image for social share previews: the school's own hero
// photo if they've uploaded one, otherwise fall back to Somahub's branded default.
$ogImage = 'https://somahub.top/assets/og-share-image.png';
foreach ($sections as $s) {
    if ($s['key_name'] === 'hero') {
        $heroContent = json_decode($s['content_json'], true);
        if (!empty($heroContent['hero_photo'])) {
            $ogImage = 'https://somahub.top/' . $heroContent['hero_photo'];
        }
        break;
    }
}

// Fee rows, only fetched if a fees section is actually present
$fees = [];
if (in_array('fees', array_column($sections, 'key_name'))) {
    $feeStmt = $db->prepare("SELECT * FROM fee_structures WHERE school_id = ? ORDER BY grade, term_label");
    $feeStmt->execute([$school['id']]);
    $fees = $feeStmt->fetchAll();
}

function img($path) {
    return $path ? htmlspecialchars($path) : '';
}
function esc($text) {
    return htmlspecialchars($text ?? '');
}
function nl2p($text) {
    // Turn plain textarea line breaks into paragraphs, since content is stored as plain text
    $parts = array_filter(array_map('trim', explode("\n", $text ?? '')));
    return implode('', array_map(fn($p) => '<p>' . nl2br(esc($p)) . '</p>', $parts));
}

// Build a nav from whichever sections this school actually has, in their chosen order.
// Related items (e.g. About + Staff + Stats, or Results + Enrollment + Fees) collapse into
// a single dropdown group ONLY when a school actually has 2+ items in that group — a school
// with just "About" still sees a plain link, not a one-item dropdown.
$navGroupMap = [
    'about' => 'About', 'staff' => 'About', 'testimonials' => 'About', 'stats' => 'About', 'faq' => 'About',
    'results_lookup' => 'Portals', 'enrollment_form' => 'Portals', 'fees' => 'Portals',
];

$navSequence = [];
$groupIndex = [];

foreach ($sections as $s) {
    if ($s['key_name'] === 'hero') continue; // hero is the top of page, not a nav target
    $group = $navGroupMap[$s['key_name']] ?? null;

    if ($group === null) {
        $navSequence[] = ['type' => 'link', 'key' => $s['key_name'], 'label' => $s['label']];
    } else {
        if (!isset($groupIndex[$group])) {
            $groupIndex[$group] = count($navSequence);
            $navSequence[] = ['type' => 'group', 'label' => $group, 'items' => []];
        }
        $navSequence[$groupIndex[$group]]['items'][] = ['key' => $s['key_name'], 'label' => $s['label']];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($school['name']) ?></title>
<meta name="description" content="<?= esc($school['name']) ?> — official website">

<!-- Favicons -->
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">

<!-- Open Graph (WhatsApp, Facebook, Instagram DM previews) -->
<meta property="og:type" content="website">
<meta property="og:title" content="<?= esc($school['name']) ?>">
<meta property="og:description" content="<?= esc($school['name']) ?> — official website, built with Somahub.">
<meta property="og:image" content="<?= esc($ogImage) ?>">
<meta property="og:url" content="https://<?= esc($school['slug']) ?>.somahub.top">
<meta property="og:site_name" content="<?= esc($school['name']) ?>">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= esc($school['name']) ?>">
<meta name="twitter:image" content="<?= esc($ogImage) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=<?= urlencode($theme['font_display'] ?? 'Sora') ?>:wght@500;600;700&family=<?= urlencode($theme['font_body'] ?? 'Nunito Sans') ?>:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{
    --primary: <?= esc($theme['primary'] ?? '#1B4D3E') ?>;
    --accent: <?= esc($theme['accent'] ?? '#F2B705') ?>;
    --bg: <?= esc($theme['bg'] ?? '#FBF8F2') ?>;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  html{scroll-behavior:smooth;}
  body{font-family:'<?= esc($theme['font_body'] ?? 'Nunito Sans') ?>',sans-serif;color:#1B1B18;background:var(--bg);line-height:1.65;}
  h1,h2,h3{font-family:'<?= esc($theme['font_display'] ?? 'Sora') ?>',sans-serif;letter-spacing:-0.01em;}
  .mono{font-family:'Space Mono',monospace;letter-spacing:0.02em;}
  a{color:inherit;text-decoration:none;}
  img{display:block;max-width:100%;}
  .wrap{max-width:1080px;margin:0 auto;padding:0 24px;}

  header{position:sticky;top:0;z-index:50;background:var(--bg);border-bottom:1px solid rgba(0,0,0,0.08);}
  .navbar{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;max-width:1080px;margin:0 auto;gap:16px;}
  .brand{font-family:'<?= esc($theme['font_display'] ?? 'Sora') ?>',sans-serif;font-weight:700;font-size:1.05rem;color:var(--primary);display:flex;align-items:center;gap:8px;}
  .verified-badge{background:#DCEFE1;color:#1B4D3E;font-size:0.65rem;font-weight:700;padding:3px 9px;border-radius:20px;font-family:'Space Mono',monospace;letter-spacing:0.02em;}
  nav ul{list-style:none;display:flex;gap:22px;align-items:center;}
  nav a{font-size:0.88rem;font-weight:600;}
  nav a:hover{opacity:0.7;}

  /* Nav dropdown groups (e.g. "About ▾", "Portals ▾") */
  .nav-group{position:relative;}
  .nav-group summary{list-style:none;font-size:0.88rem;font-weight:600;cursor:pointer;color:inherit;}
  .nav-group summary::-webkit-details-marker{display:none;}
  .nav-dropdown{position:absolute;top:28px;left:0;background:var(--bg);border:1px solid rgba(0,0,0,0.1);border-radius:8px;padding:8px;min-width:160px;box-shadow:0 8px 24px rgba(0,0,0,0.1);display:flex;flex-direction:column;z-index:60;}
  .nav-dropdown a{padding:8px 10px;border-radius:5px;}
  .nav-dropdown a:hover{background:rgba(0,0,0,0.05);opacity:1;}

  .menu-toggle{display:none;background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--primary);}
  @media(max-width:820px){
    nav ul{display:none;}
    .menu-toggle{display:block;}
    nav ul.open{display:flex;flex-direction:column;position:absolute;top:60px;left:0;right:0;background:var(--bg);padding:20px 24px;border-bottom:1px solid rgba(0,0,0,0.08);gap:16px;align-items:flex-start;}
    /* Inside the mobile menu, dropdowns become a simple indented nested list instead of a floating panel */
    .nav-dropdown{position:static;box-shadow:none;border:none;padding:6px 0 0 14px;margin-top:6px;background:none;}
  }

  section{padding:64px 24px;}
  section:nth-of-type(even){background:rgba(0,0,0,0.02);}
  .section-head{margin-bottom:32px;max-width:60ch;}
  .section-head h2{font-size:clamp(1.4rem,3.2vw,2rem);font-weight:700;color:var(--primary);}

  /* HERO */
  .hero{background:var(--primary);color:var(--bg);padding:80px 24px 60px;}
  .hero-inner{max-width:1080px;margin:0 auto;display:grid;grid-template-columns:1.1fr 0.9fr;gap:40px;align-items:center;}
  @media(max-width:820px){.hero-inner{grid-template-columns:1fr;}}
  .hero h1{font-size:clamp(2rem,4.8vw,3.2rem);font-weight:700;line-height:1.1;}
  .hero p{margin-top:18px;font-size:1.02rem;opacity:0.85;max-width:50ch;}
  .hero-photo{border-radius:10px;overflow:hidden;}
  .hero-photo img{width:100%;height:100%;object-fit:cover;aspect-ratio:4/3;}
  .hero-cta{background:var(--accent);color:var(--primary);margin-top:24px;padding:13px 28px;border-radius:6px;font-weight:700;font-size:0.9rem;display:inline-block;}

  /* MOSAIC — used when a school has more than one hero photo */
  .hero-mosaic{display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:10px;height:100%;min-height:280px;}
  .hero-mosaic img{width:100%;height:100%;object-fit:cover;}
  .hero-mosaic .m-main{grid-row:1/3;}
  .hero-mosaic.two-photos{grid-template-rows:1fr;}
  .hero-mosaic.two-photos .m-main{grid-row:1/2;}

  /* Theme-specific signature styling, injected per school's theme */
  <?= $theme_custom_css ?? '' ?>

  /* STAFF */
  .staff-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;}
  .staff-card{text-align:center;}
  .staff-card img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:50%;margin-bottom:12px;}
  .staff-card .name{font-weight:700;font-size:0.95rem;}
  .staff-card .role{font-size:0.82rem;color:#6b6b60;}

  /* TESTIMONIALS */
  .testimonial-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;}
  .testimonial-card{background:#fff;border:1px solid rgba(0,0,0,0.08);border-radius:10px;padding:24px;}
  .testimonial-card .quote{font-size:0.95rem;font-style:italic;color:#3a3a34;margin-bottom:14px;}
  .testimonial-card .author{font-size:0.82rem;font-weight:700;color:var(--primary);}

  /* FAQ */
  .faq-item{background:#fff;border-radius:10px;padding:20px 22px;margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
  .faq-item .q{font-weight:700;font-size:0.98rem;margin-bottom:6px;color:var(--primary);}
  .faq-item .a{font-size:0.9rem;color:#5a5a52;}

  /* STATS */
  .stats-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;}
  .stat-item{background:#fff;border-radius:12px;padding:24px 16px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
  .stat-item .number{font-family:'<?= esc($theme['font_display'] ?? 'Sora') ?>',sans-serif;font-weight:700;font-size:2rem;color:var(--accent);}
  .stat-item .label{font-size:0.8rem;color:#6b6b60;text-transform:uppercase;letter-spacing:0.04em;margin-top:4px;}

  /* CTA BANNER */
  .cta-banner{background:var(--primary);color:var(--bg);border-radius:10px;padding:44px 32px;text-align:center;}
  .cta-banner h2{font-size:clamp(1.4rem,3vw,1.9rem);margin-bottom:10px;}
  .cta-banner p{opacity:0.85;max-width:50ch;margin:0 auto 22px;}
  .cta-banner .btn-primary{background:var(--accent);color:var(--primary);}

  /* ABOUT */
  .about-grid{display:grid;grid-template-columns:1.1fr 0.9fr;gap:40px;align-items:start;}
  @media(max-width:820px){.about-grid{grid-template-columns:1fr;}}
  .about-grid p{margin-bottom:14px;color:#3a3a34;}
  .about-photo{border-radius:10px;overflow:hidden;}
  .about-photo img{width:100%;object-fit:cover;}

  /* GENERIC TEXT SECTIONS (academics/admissions) */
  .text-block p{margin-bottom:14px;max-width:70ch;color:#3a3a34;}

  /* GALLERY */
  .gallery-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;}
  .gallery-grid img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px;}

  /* CONTACT */
  .contact-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;}
  .contact-item{background:#fff;border-radius:10px;padding:20px 22px;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
  .contact-item .k{font-family:'Space Mono',monospace;font-size:0.7rem;text-transform:uppercase;color:var(--primary);display:block;margin-bottom:6px;}
  .contact-item .v{font-size:0.95rem;}

  /* BLOG */
  .blog-item{margin-bottom:36px;}
  .blog-item img{border-radius:8px;margin-bottom:14px;width:100%;aspect-ratio:16/9;object-fit:cover;}
  .blog-item h3{font-size:1.15rem;margin-bottom:8px;color:var(--primary);}

  /* FEES */
  table.fee-table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;}
  table.fee-table th{background:var(--primary);color:var(--bg);text-align:left;padding:12px 16px;font-size:0.78rem;text-transform:uppercase;}
  table.fee-table td{padding:12px 16px;border-bottom:1px solid rgba(0,0,0,0.08);font-size:0.9rem;}

  /* LOOKUP / FORM CALLOUT BLOCKS */
  .callout-block{background:#fff;border:1px solid rgba(0,0,0,0.08);border-radius:10px;padding:32px;text-align:center;max-width:520px;margin:0 auto;}
  .callout-block p{color:#5a5a52;margin-bottom:20px;font-size:0.95rem;}
  .btn-primary{background:var(--primary);color:var(--bg);padding:13px 28px;border-radius:6px;font-weight:600;font-size:0.9rem;display:inline-block;}

  footer{background:var(--primary);color:var(--bg);padding:32px 24px;text-align:center;font-size:0.82rem;opacity:0.9;}
  footer a{text-decoration:underline;}
</style>
</head>
<body>

<header>
  <div class="navbar">
    <div class="brand">
      <?= esc($school['name']) ?>
      <?php if (($school['verification_status'] ?? '') === 'verified'): ?>
        <span class="verified-badge" title="Verified by Somahub">✓ Verified</span>
      <?php endif; ?>
    </div>
    <nav><ul id="navlinks">
      <?php foreach ($navSequence as $item): ?>
        <?php if ($item['type'] === 'link'): ?>
          <li><a href="#<?= esc($item['key']) ?>"><?= esc($item['label']) ?></a></li>
        <?php elseif (count($item['items']) === 1): ?>
          <li><a href="#<?= esc($item['items'][0]['key']) ?>"><?= esc($item['items'][0]['label']) ?></a></li>
        <?php else: ?>
          <li>
            <details class="nav-group">
              <summary><?= esc($item['label']) ?> ▾</summary>
              <div class="nav-dropdown">
                <?php foreach ($item['items'] as $sub): ?>
                  <a href="#<?= esc($sub['key']) ?>"><?= esc($sub['label']) ?></a>
                <?php endforeach; ?>
              </div>
            </details>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul></nav>
    <button class="menu-toggle" onclick="document.getElementById('navlinks').classList.toggle('open')">☰</button>
  </div>
</header>

<?php foreach ($sections as $s):
    $c = json_decode($s['content_json'], true) ?: [];
    $key = $s['key_name'];
?>

<?php if ($key === 'hero'):
    $heroPhotos = array_filter([$c['hero_photo'] ?? '', $c['hero_photo_2'] ?? '', $c['hero_photo_3'] ?? '']);
    $heroPhotos = array_values($heroPhotos);
?>
  <section class="hero" id="hero">
    <div class="hero-inner">
      <div>
        <h1><?= esc($c['headline'] ?: $school['name']) ?></h1>
        <?php if (!empty($c['subheading'])): ?><p><?= esc($c['subheading']) ?></p><?php endif; ?>
      </div>
      <?php if (count($heroPhotos) >= 2): ?>
        <div class="hero-mosaic <?= count($heroPhotos) === 2 ? 'two-photos' : '' ?>">
          <img class="m-main" src="<?= img($heroPhotos[0]) ?>" alt="<?= esc($school['name']) ?>">
          <?php foreach (array_slice($heroPhotos, 1) as $extra): ?>
            <img src="<?= img($extra) ?>" alt="">
          <?php endforeach; ?>
        </div>
      <?php elseif (count($heroPhotos) === 1): ?>
        <div class="hero-photo"><img src="<?= img($heroPhotos[0]) ?>" alt="<?= esc($school['name']) ?>"></div>
      <?php endif; ?>
    </div>
  </section>

<?php elseif ($key === 'about'): ?>
  <section id="about">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="about-grid">
        <div><?= nl2p($c['body'] ?? '') ?></div>
        <?php if (!empty($c['photo'])): ?>
          <div class="about-photo"><img src="<?= img($c['photo']) ?>" alt=""></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php elseif (in_array($key, ['academics', 'admissions'])): ?>
  <section id="<?= esc($key) ?>">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="text-block"><?= nl2p($c['body'] ?? '') ?></div>
      <?php if ($key === 'admissions'): ?>
        <a href="enrollment-apply.php?school=<?= urlencode($school['slug']) ?>" class="btn-primary" style="margin-top:20px;display:inline-block;">Apply for Admission</a>
      <?php endif; ?>
    </div>
  </section>

<?php elseif ($key === 'gallery'): ?>
  <section id="gallery">
    <div class="wrap">
      <div class="section-head">
        <h2><?= esc($s['label']) ?></h2>
        <?php if (!empty($c['caption'])): ?><p style="color:#5a5a52;margin-top:6px;"><?= esc($c['caption']) ?></p><?php endif; ?>
      </div>
      <div class="gallery-grid">
        <?php foreach (['photo_1','photo_2','photo_3','photo_4'] as $ph): ?>
          <?php if (!empty($c[$ph])): ?><img src="<?= img($c[$ph]) ?>" alt=""><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

<?php elseif ($key === 'contact'): ?>
  <section id="contact">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="contact-grid">
        <?php if (!empty($c['address'])): ?><div class="contact-item"><span class="k">Location</span><span class="v"><?= esc($c['address']) ?></span></div><?php endif; ?>
        <?php if (!empty($c['phone'])): ?><div class="contact-item"><span class="k">Phone</span><span class="v"><a href="tel:<?= esc($c['phone']) ?>"><?= esc($c['phone']) ?></a></span></div><?php endif; ?>
        <?php if (!empty($c['email'])): ?><div class="contact-item"><span class="k">Email</span><span class="v"><a href="mailto:<?= esc($c['email']) ?>"><?= esc($c['email']) ?></a></span></div><?php endif; ?>
        <?php if (!empty($c['office_hours'])): ?><div class="contact-item"><span class="k">Office Hours</span><span class="v"><?= esc($c['office_hours']) ?></span></div><?php endif; ?>
      </div>
    </div>
  </section>

<?php elseif ($key === 'blog'): ?>
  <section id="blog">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="blog-item">
        <?php if (!empty($c['photo'])): ?><img src="<?= img($c['photo']) ?>" alt=""><?php endif; ?>
        <h3><?= esc($c['title'] ?? '') ?></h3>
        <?= nl2p($c['body'] ?? '') ?>
      </div>
    </div>
  </section>

<?php elseif ($key === 'fees'): ?>
  <section id="fees">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <?php if (!empty($c['intro_text'])): ?><div class="text-block"><?= nl2p($c['intro_text']) ?></div><?php endif; ?>
      <?php if ($fees): ?>
      <table class="fee-table">
        <tr><th>Grade</th><th>Term</th><th>Amount</th></tr>
        <?php foreach ($fees as $f): ?>
          <tr><td><?= esc($f['grade']) ?></td><td><?= esc($f['term_label']) ?></td><td>KSh <?= number_format($f['amount'], 2) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <p style="color:#8a8a80;">Fee details will be published here soon.</p>
      <?php endif; ?>
    </div>
  </section>

<?php elseif ($key === 'results_lookup'): ?>
  <section id="results_lookup">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="callout-block">
        <?php if (!empty($c['intro_text'])): ?><p><?= esc($c['intro_text']) ?></p><?php else: ?><p>Check your child's term results using their admission number.</p><?php endif; ?>
        <a href="results-check.php?school=<?= urlencode($school['slug']) ?>" class="btn-primary">Check Results</a>
      </div>
    </div>
  </section>

<?php elseif ($key === 'enrollment_form'): ?>
  <section id="enrollment_form">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="callout-block">
        <?php if (!empty($c['intro_text'])): ?><p><?= esc($c['intro_text']) ?></p><?php else: ?><p>Apply for your child's admission online.</p><?php endif; ?>
        <a href="enrollment-apply.php?school=<?= urlencode($school['slug']) ?>" class="btn-primary">Apply Now</a>
      </div>
    </div>
  </section>

<?php elseif ($key === 'staff'): ?>
  <section id="staff">
    <div class="wrap">
      <div class="section-head">
        <h2><?= esc($s['label']) ?></h2>
        <?php if (!empty($c['intro_text'])): ?><p style="color:#5a5a52;margin-top:6px;"><?= esc($c['intro_text']) ?></p><?php endif; ?>
      </div>
      <div class="staff-grid">
        <?php for ($i = 1; $i <= 4; $i++): if (empty($c["name_$i"])) continue; ?>
          <div class="staff-card">
            <?php if (!empty($c["photo_$i"])): ?><img src="<?= img($c["photo_$i"]) ?>" alt="<?= esc($c["name_$i"]) ?>"><?php endif; ?>
            <div class="name"><?= esc($c["name_$i"]) ?></div>
            <div class="role"><?= esc($c["role_$i"] ?? '') ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </section>

<?php elseif ($key === 'testimonials'): ?>
  <section id="testimonials">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="testimonial-grid">
        <?php for ($i = 1; $i <= 3; $i++): if (empty($c["quote_$i"])) continue; ?>
          <div class="testimonial-card">
            <div class="quote">"<?= esc($c["quote_$i"]) ?>"</div>
            <div class="author"><?= esc($c["author_$i"] ?? '') ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </section>

<?php elseif ($key === 'faq'): ?>
  <section id="faq">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div>
        <?php for ($i = 1; $i <= 4; $i++): if (empty($c["question_$i"])) continue; ?>
          <div class="faq-item">
            <div class="q"><?= esc($c["question_$i"]) ?></div>
            <div class="a"><?= esc($c["answer_$i"] ?? '') ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </section>

<?php elseif ($key === 'stats'): ?>
  <section id="stats">
    <div class="wrap">
      <div class="stats-strip">
        <?php for ($i = 1; $i <= 4; $i++): if (empty($c["stat_{$i}_number"])) continue; ?>
          <div class="stat-item">
            <div class="number"><?= esc($c["stat_{$i}_number"]) ?></div>
            <div class="label"><?= esc($c["stat_{$i}_label"] ?? '') ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </section>

<?php elseif ($key === 'cta_banner'): ?>
  <section id="cta_banner">
    <div class="wrap">
      <div class="cta-banner">
        <h2><?= esc($c['headline'] ?? '') ?></h2>
        <?php if (!empty($c['subtext'])): ?><p><?= esc($c['subtext']) ?></p><?php endif; ?>
        <?php if (!empty($c['button_text']) && !empty($c['button_link'])): ?>
          <a href="<?= esc($c['button_link']) ?>" class="btn-primary"><?= esc($c['button_text']) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php endif; ?>
<?php endforeach; ?>

<footer>
  <div><?= esc($school['name']) ?></div>
  <div style="margin-top:8px;opacity:0.75;">Website by <a href="https://somahub.top">Somahub</a></div>
</footer>

<?php
$SOMAHUB_CHAT_CONTEXT = 'school';
$SOMAHUB_CHAT_SCHOOL_NAME = $school['name'];
$SOMAHUB_CHAT_SCHOOL_SLUG = $school['slug'];
include __DIR__ . '/_chat_widget.php';
?>
</body>
</html>