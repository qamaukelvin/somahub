<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/plan.php';
require_once __DIR__ . '/includes/reviews.php';
require_once __DIR__ . '/includes/appearance.php';
$db = get_db();

// Subdomain routing sets this via .htaccess; fallback for local testing.
$slug = $_GET['school'] ?? '';

$stmt = $db->prepare("SELECT * FROM schools WHERE slug = ?");
$stmt->execute([$slug]);
$school = $stmt->fetch();

if (!$school) {
    http_response_code(404);
    die('School not found. Check the address and try again.');
}

// Canonical redirect to the real subdomain (SEO)
$canonicalHost = $school['slug'] . '.somahub.top';
if (strtolower($_SERVER['HTTP_HOST']) !== $canonicalHost) {
    header("Location: https://{$canonicalHost}/", true, 301);
    exit;
}

// Grace period: new schools are public immediately, even before
// verification, and lose visibility if not verified in time.

require_once __DIR__ . '/includes/auth.php';
$viewer = current_user();
$isOwnerPreview = $viewer && (
    ($viewer['role'] === 'platform_admin') ||
    (in_array($viewer['role'] ?? '', ['school_owner', 'school_editor'], true) && $viewer['school_id'] == $school['id'])
);

$isVerified = ($school['verification_status'] ?? '') === 'verified';

// Grace period counts from first login, not account creation.
// countdown hasn't started, so the site stays visible.
if (empty($school['first_login_at'])) {
    $inGracePeriod = true;
    $daysLeftToVerify = VERIFICATION_GRACE_PERIOD_DAYS;
} else {
    $daysSinceFirstLogin = (time() - strtotime($school['first_login_at'])) / 86400;
    $inGracePeriod = $daysSinceFirstLogin <= VERIFICATION_GRACE_PERIOD_DAYS;
    $daysLeftToVerify = max(0, ceil(VERIFICATION_GRACE_PERIOD_DAYS - $daysSinceFirstLogin));
}

$sitePubliclyVisible = $isVerified || $inGracePeriod;

if (!$sitePubliclyVisible && !$isOwnerPreview) {
    http_response_code(200);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($school['name']) ?> - Coming Soon</title>
    <meta name="robots" content="noindex">
    <style>
      body{font-family:Arial,sans-serif;background:#F7F2E7;color:#1C1C16;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px;}
      .box{max-width:420px;}
      h1{color:#0F5257;font-size:1.5rem;margin-bottom:10px;}
      p{color:#6E6A5C;line-height:1.6;}
      .badge{display:inline-block;background:#0F5257;color:#F7F2E7;font-weight:800;padding:6px 16px;border-radius:20px;font-size:0.8rem;margin-bottom:20px;}
    </style>
    </head>
    <body>
      <div class="box">
        <div class="badge">● somahub</div>
        <h1><?= htmlspecialchars($school['name']) ?>'s website is temporarily unavailable</h1>
        <p>This site's free preview period has ended. It will be back online once verification is complete.</p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$appearance = resolve_school_appearance($db, $school);
$theme = $appearance['theme'];
$theme_custom_css = $appearance['custom_css'];

$sectionsStmt = $db->prepare("
    SELECT ss.*, st.key_name, st.label, st.is_premium
    FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.school_id = ? AND ss.is_visible = 1
    ORDER BY ss.position ASC
");
$sectionsStmt->execute([$school['id']]);
$sections = $sectionsStmt->fetchAll();

// Payment lapsed: quietly drop premium sections, no public "overdue" notice.
if (is_premium_locked($school)) {
    $sections = array_filter($sections, fn($s) => !$s['is_premium']);
}

// Hero CTA destinations - a curated list, not free text, so a CTA never
// links to a section the school doesn't actually have (or has lost access
// to since choosing it, e.g. a plan downgrade).
$ctaDestinations = [
    'enroll' => ['label' => 'Request Admission', 'key' => 'enrollment_form'],
    'contact' => ['label' => 'Contact Us', 'key' => 'contact'],
    'results' => ['label' => 'Check Results', 'key' => 'results_lookup'],
    'fees' => ['label' => 'School Fees', 'key' => 'fees'],
];
$availableSectionKeys = array_column($sections, 'key_name');
$availableCtaKeys = array_filter(array_keys($ctaDestinations), fn($k) => in_array($ctaDestinations[$k]['key'], $availableSectionKeys, true));
// Sensible default when a school hasn't explicitly picked one: Enroll if
// they have it (and it's not locked out), otherwise Contact, since that's
// always free and always available.
$defaultCta = in_array('enroll', $availableCtaKeys, true) ? 'enroll' : (in_array('contact', $availableCtaKeys, true) ? 'contact' : null);

// Social share image: hero photo if set, else default
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
    // Line breaks to paragraphs
    $parts = array_filter(array_map('trim', explode("\n", $text ?? '')));
    return implode('', array_map(fn($p) => '<p>' . nl2br(esc($p)) . '</p>', $parts));
}

// Nav groups related sections into a dropdown when 2+ items share a group.
$navGroupMap = [
    'about' => 'About', 'staff' => 'About', 'testimonials' => 'About', 'reviews' => 'About', 'stats' => 'About', 'faq' => 'About',
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
<meta name="description" content="<?= esc($school['name']) ?> - official website">
<link rel="canonical" href="https://<?= esc($school['slug']) ?>.somahub.top/">

<!-- Favicons -->
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">

<!-- Open Graph (WhatsApp, Facebook, Instagram DM previews) -->
<meta property="og:type" content="website">
<meta property="og:title" content="<?= esc($school['name']) ?>">
<meta property="og:description" content="<?= esc($school['name']) ?> - official website, built with Somahub.">
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
  .verify-dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--accent,#F2A65A);flex-shrink:0;margin-right:2px;}
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
  .hero-inner{max-width:1080px;margin:0 auto;display:grid;grid-template-columns:1.1fr 0.9fr;gap:40px;align-items:stretch;min-height:420px;}
  .hero-inner > div:first-child{display:flex;flex-direction:column;justify-content:center;}
  .hero h1{font-size:clamp(2rem,4.8vw,3.2rem);font-weight:700;line-height:1.1;}
  .hero p{margin-top:18px;font-size:1.02rem;opacity:0.85;max-width:50ch;}
  .hero-photo{border-radius:10px;overflow:hidden;height:100%;}
  .hero-photo img{width:100%;height:100%;object-fit:cover;}
  .hero-cta{background:var(--accent);color:var(--primary);padding:13px 28px;border-radius:6px;font-weight:700;font-size:0.9rem;display:inline-block;}
  .hero-cta-row{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;}
  .hero-cta-row .hero-cta:nth-child(2){background:transparent;border:2px solid currentColor;}

  /* MOSAIC - used when a school has more than one hero photo. Now sizes
     against .hero-inner's own definite min-height (stretched, not
     content-sized), same fix applied consistently to both the single-photo
     and mosaic cases - no percentage-height-against-indefinite-parent
     anywhere in this chain anymore. */
  .hero-mosaic{display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:10px;height:100%;}
  .hero-mosaic img{width:100%;height:100%;object-fit:cover;}
  .hero-mosaic .m-main{grid-row:1/3;}
  .hero-mosaic.two-photos{grid-template-rows:1fr;}

  /* Mobile: the grid above collapses to one stacked column (text, then
     photo, in DOM order) - without these, a tall 4:3 photo at full mobile
     width plus the mosaic's 280px min-height made the hero taller than one
     screen. Placed after the base rules on purpose so it actually wins the
     cascade instead of being silently overridden by them. */
  @media(max-width:820px){
    .hero-inner{grid-template-columns:1fr;min-height:0;align-items:stretch;}
    .hero{padding:48px 20px 36px;}
    .hero-photo{max-height:220px;}
    .hero-photo img{aspect-ratio:16/9;}
    .hero-mosaic{min-height:0;max-height:220px;}
  }

  /* Hero variants: background_fixed (true CSS background-attachment:fixed
     photo + overlay - needs to be a real background-image, not an <img>,
     since background-attachment only applies to CSS backgrounds) and
     carousel (auto-rotating photos, still uses <img> tags since it needs
     to swap sources). */
  .hero-bgfixed-variant{position:relative;padding:0;overflow:hidden;min-height:420px;display:flex;align-items:flex-end;background-size:cover;background-position:center;background-attachment:fixed;}
  .hero-carousel-variant{position:relative;padding:0;overflow:hidden;min-height:420px;display:flex;align-items:flex-end;}
  .hero-carousel-track{position:absolute;inset:0;}
  .hero-carousel-slide{position:absolute;inset:0;opacity:0;transition:opacity 1s ease;}
  .hero-carousel-slide.active{opacity:1;}
  .hero-carousel-slide img{width:100%;height:100%;object-fit:cover;}
  .hero-bg-overlay{position:absolute;inset:0;background:linear-gradient(180deg, rgba(0,0,0,0.05), rgba(0,0,0,0.7));}
  .hero-bg-content{position:relative;z-index:2;padding:60px 6% 50px;color:#fff;max-width:720px;}
  .hero-bg-content h1{color:#fff;}
  .hero-bg-content p{color:rgba(255,255,255,0.88);}

  /* Text-only / text+CTA variant - no photo at all */
  .hero-text-variant{display:flex;align-items:center;justify-content:center;text-align:center;padding:70px 6%;}
  .hero-inner-text{max-width:680px;}
  .hero-inner-text .hero-cta-row{justify-content:center;}

  /* Independent height setting - layered on top of whichever variant is
     active, so any variant can be requested at full or half viewport
     height, not just one specific one. !important is deliberate here:
     this is an explicit, single-purpose user override that always needs
     to win regardless of which variant's own min-height it's layered on. */
  .hero-size-half{min-height:50vh !important;}
  .hero-size-full{min-height:100vh !important;}
  .hero-size-half.hero, .hero-size-full.hero{display:flex;align-items:center;}
  .hero-size-half .hero-inner, .hero-size-full .hero-inner{width:100%;}

  .hero-mosaic.two-photos .m-main{grid-row:1/2;}

  /* Theme-specific signature styling, injected per school's theme */
  <?= $theme_custom_css ?? '' ?>

  /* STAFF */
  .staff-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;}
  .staff-card{text-align:center;}
  .staff-card img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:50%;margin-bottom:12px;}
  .staff-card .name{font-weight:700;font-size:0.95rem;}
  .staff-card .role{font-size:0.82rem;color:#6b6b60;}

  /* Universal cap: show first 6 on desktop, first 3 on mobile, reveal the
     rest via "View More" (adds .expanded, which the more specific rule
     below overrides). Applies to #staffContainer's direct children,
     whichever layout that ends up being (cards, bio rows, minimal rows). */
  #staffContainer > *:nth-child(n+7){display:none;}
  @media(max-width:820px){#staffContainer > *:nth-child(n+4){display:none;}}
  #staffContainer.expanded > *{display:block;}
  .staff-list-bio.expanded > .staff-bio-row{display:flex;}
  .staff-minimal-list.expanded > .staff-minimal-item{display:flex;}
  .staff-view-more{display:block;margin:24px auto 0;background:none;border:2px solid var(--primary);color:var(--primary);padding:10px 26px;border-radius:6px;font-weight:700;cursor:pointer;}
  @media(min-width:821px){.staff-view-more.only-mobile{display:none;}}

  /* Horizontal scroll carousel - space problem solved by scrolling, not capping */
  .staff-carousel{display:flex;gap:20px;overflow-x:auto;padding-bottom:10px;-webkit-overflow-scrolling:touch;}
  .staff-carousel .staff-card{flex:0 0 160px;}

  /* List with bio */
  .staff-list-bio{display:flex;flex-direction:column;gap:22px;}
  .staff-bio-row{display:flex;gap:16px;align-items:flex-start;}
  .staff-bio-row img{width:64px;height:64px;border-radius:50%;object-fit:cover;flex-shrink:0;}
  .staff-bio-row .name{font-weight:700;}
  .staff-bio-row .role{font-size:0.82rem;color:#6b6b60;margin-bottom:6px;}
  .staff-bio-row .bio{color:#3a3a34;font-size:0.9rem;}

  /* Org chart - three tiers, sized down by seniority */
  .staff-org-chart{display:flex;flex-direction:column;align-items:center;gap:28px;}
  .org-tier{display:flex;gap:24px;justify-content:center;flex-wrap:wrap;}
  .org-tier-1 .staff-card img{width:120px;height:120px;}
  .org-tier-2 .staff-card img{width:90px;height:90px;}
  .org-tier-3 .staff-card img{width:70px;height:70px;}
  .org-tier .staff-card{width:140px;}

  /* Minimal - dense text-only list */
  .staff-minimal-list{max-width:640px;margin:0 auto;}
  .staff-minimal-item{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(0,0,0,0.08);}
  .staff-minimal-item .name{font-weight:700;}
  .staff-minimal-item .role{color:#6b6b60;font-size:0.88rem;}

  /* Grouped by department */
  .staff-dept-heading{font-size:1.1rem;margin:28px 0 14px;color:var(--primary);}
  .staff-dept-heading:first-child{margin-top:0;}

  /* TESTIMONIALS */
  .testimonial-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;}
  .testimonial-card{background:#fff;border:1px solid rgba(0,0,0,0.08);border-radius:10px;padding:24px;}

  /* Capping - same mechanism as Staff's, scoped to testimonialsContainer.
     All 4 capped variants' items default to display:block, so no
     per-variant override needed here (unlike Staff's bio-row/minimal-item,
     nothing in this section relies on flex for its own internal layout). */
  #testimonialsContainer > *:nth-child(n+7){display:none;}
  @media(max-width:820px){#testimonialsContainer > *:nth-child(n+4){display:none;}}
  #testimonialsContainer.expanded > *{display:block;}

  /* Auto-rotating slider */
  .testimonial-slider{position:relative;min-height:140px;max-width:720px;margin:0 auto;}
  .testimonial-slide{position:absolute;inset:0;opacity:0;transition:opacity 1s ease;text-align:center;}
  .testimonial-slide.active{opacity:1;position:relative;}
  .testimonial-slide .quote{font-size:1.2rem;font-style:italic;color:#3a3a34;}
  .testimonial-slide .author{margin-top:12px;font-weight:700;color:var(--primary);}

  /* Single large quote takeover */
  .testimonial-single{max-width:760px;margin:0 auto;text-align:center;}
  .testimonial-single .quote{font-family:Georgia,serif;font-size:1.7rem;font-style:italic;color:#2a2a24;line-height:1.4;}
  .testimonial-single .author{margin-top:18px;font-weight:700;color:var(--primary);}

  /* Quote wall - dense masonry-style via CSS columns */
  .testimonial-wall{columns:3 220px;column-gap:16px;}
  @media(max-width:820px){.testimonial-wall{columns:1;}}
  .testimonial-wall-item{break-inside:avoid;background:#fff;border:1px solid rgba(0,0,0,0.08);border-radius:8px;padding:16px;margin-bottom:16px;font-size:0.9rem;}
  .testimonial-wall-item .author{margin-top:8px;font-weight:700;font-size:0.82rem;color:var(--primary);}

  /* Review-style with rating */
  .testimonial-card .stars{color:var(--accent);letter-spacing:2px;margin-bottom:8px;}

  /* Photo-forward */
  .testimonial-photo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;}
  .testimonial-photo-card{text-align:center;}
  .testimonial-photo-card img{width:88px;height:88px;border-radius:50%;object-fit:cover;margin:0 auto 14px;}
  .testimonial-photo-card .quote{color:#3a3a34;}
  .testimonial-photo-card .author{margin-top:10px;font-weight:700;color:var(--primary);}
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
  .about-photo img{width:100%;max-height:420px;object-fit:cover;}
  .about-photo-left{grid-template-columns:0.9fr 1.1fr;}
  @media(max-width:820px){.about-photo-left{grid-template-columns:1fr;}}
  .about-photo-left .about-photo, .about-photo-left .about-carousel{order:-1;}
  @media(max-width:820px){.about-photo-left .about-photo, .about-photo-left .about-carousel{order:0;}}

  .about-text-only{max-width:760px;color:#3a3a34;}
  .about-text-only p{margin-bottom:14px;}

  /* About carousel - same definite-height pattern as the hero carousel */
  .about-carousel{position:relative;border-radius:10px;overflow:hidden;height:340px;}
  .about-carousel-slide{position:absolute;inset:0;opacity:0;transition:opacity 1s ease;}
  .about-carousel-slide.active{opacity:1;}
  .about-carousel-slide img{width:100%;height:100%;object-fit:cover;}

  .about-list-intro{max-width:760px;color:#3a3a34;margin-bottom:20px;}
  .about-list{max-width:760px;list-style:none;padding:0;}
  .about-list li{position:relative;padding:12px 0 12px 30px;border-bottom:1px solid rgba(0,0,0,0.08);color:#3a3a34;}
  .about-list li::before{content:"✓";position:absolute;left:0;top:12px;color:var(--accent);font-weight:800;}

  .about-timeline{max-width:760px;position:relative;padding-left:28px;}
  .about-timeline::before{content:"";position:absolute;left:6px;top:6px;bottom:6px;width:2px;background:var(--accent);}
  .about-timeline-item{position:relative;padding:0 0 22px 20px;color:#3a3a34;}
  .about-timeline-item::before{content:"";position:absolute;left:-28px;top:4px;width:11px;height:11px;border-radius:50%;background:var(--primary);border:2px solid var(--accent);}

  .about-quote-grid{display:grid;grid-template-columns:0.8fr 1.2fr;gap:40px;align-items:center;}
  @media(max-width:820px){.about-quote-grid{grid-template-columns:1fr;}}
  .about-quote-photo{border-radius:50%;overflow:hidden;width:220px;height:220px;margin:0 auto;}
  .about-quote-photo img{width:100%;height:100%;object-fit:cover;}
  .about-quote-mark{font-family:Georgia,serif;font-size:3.5rem;color:var(--accent);line-height:1;margin-bottom:-10px;}
  .about-quote-text{color:#3a3a34;font-size:1.1rem;font-style:italic;}
  .about-quote-author{margin-top:14px;font-weight:700;font-style:normal;color:var(--primary);}

  .about-inline-stats{display:flex;gap:28px;margin-top:20px;}
  .about-inline-stat{display:flex;flex-direction:column;}
  .about-inline-stat .num{font-size:1.6rem;font-weight:800;color:var(--primary);}
  .about-inline-stat .label{font-size:0.78rem;color:#5c5c52;}

  /* GENERIC TEXT SECTIONS (academics/admissions) */
  .text-block p{margin-bottom:14px;max-width:70ch;color:#3a3a34;}

  /* GALLERY */
  .gallery-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;}
  .gallery-grid img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px;}

  /* Capping - same mechanism as Staff/Testimonials */
  #galleryContainer > *:nth-child(n+7){display:none;}
  @media(max-width:820px){#galleryContainer > *:nth-child(n+4){display:none;}}
  #galleryContainer.expanded > *{display:block;}

  /* Masonry - variable height columns via CSS columns, not a strict grid */
  .gallery-masonry{columns:3 220px;column-gap:14px;}
  @media(max-width:820px){.gallery-masonry{columns:2;}}
  .gallery-masonry img{width:100%;border-radius:8px;margin-bottom:14px;break-inside:avoid;}

  /* Lightbox */
  .gallery-lightbox-trigger{cursor:pointer;transition:opacity .15s;}
  .gallery-lightbox-trigger:hover{opacity:0.85;}
  .gallery-lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:100;align-items:center;justify-content:center;padding:5%;}
  .gallery-lightbox.open{display:flex;}
  .gallery-lightbox img{max-width:100%;max-height:90vh;border-radius:6px;}
  .gallery-lightbox-close{position:absolute;top:20px;right:24px;background:none;border:none;color:#fff;font-size:1.6rem;cursor:pointer;}

  /* Before/After slider - clip-path reveal, not width, so neither photo
     ever squishes/distorts as the handle moves */
  .gallery-before-after{position:relative;max-width:800px;margin:0 auto;aspect-ratio:16/9;border-radius:10px;overflow:hidden;}
  .gallery-before-after img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
  .ba-after{position:absolute;inset:0;z-index:1;}
  .ba-before{position:absolute;inset:0;z-index:2;clip-path:inset(0 50% 0 0);}
  .ba-slider{position:absolute;inset:0;width:100%;height:100%;-webkit-appearance:none;appearance:none;background:transparent;z-index:3;margin:0;cursor:ew-resize;}
  .ba-slider::-webkit-slider-thumb{-webkit-appearance:none;width:4px;height:100%;background:#fff;box-shadow:0 0 8px rgba(0,0,0,0.4);}
  .ba-slider::-moz-range-thumb{width:4px;height:100%;background:#fff;border:none;box-shadow:0 0 8px rgba(0,0,0,0.4);}
  .ba-label{position:absolute;top:12px;background:rgba(0,0,0,0.6);color:#fff;padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;z-index:4;}
  .ba-label-before{left:12px;}
  .ba-label-after{right:12px;}

  /* Full-bleed slideshow */
  .gallery-slideshow{position:relative;aspect-ratio:16/9;border-radius:10px;overflow:hidden;}
  .gallery-slide{position:absolute;inset:0;opacity:0;transition:opacity 1s ease;}
  .gallery-slide.active{opacity:1;}
  .gallery-slide img{width:100%;height:100%;object-fit:cover;}

  /* Categorized tabs */
  .gallery-tab-buttons{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
  .gallery-tab-btn{border:1.5px solid rgba(0,0,0,0.15);background:#fff;padding:8px 18px;border-radius:20px;font-size:0.85rem;font-weight:600;cursor:pointer;}
  .gallery-tab-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);}
  .gallery-tab-panel{display:none;}
  .gallery-tab-panel.active{display:grid;}

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
      <?php if (($school['verification_status'] ?? '') === 'verified'): ?>
        <span class="verify-dot" title="Verified by Somahub"></span>
      <?php endif; ?>
      <?= esc($school['name']) ?>
    </div>
    <nav><ul id="navlinks">
      <?php foreach ($navSequence as $item): ?>
        <?php if ($item['type'] === 'link'): ?>
          <li><a href="#<?= esc($item['key']) ?>" data-key="<?= esc($item['key']) ?>" data-label="<?= esc($item['label']) ?>"><?= esc($item['label']) ?></a></li>
        <?php elseif (count($item['items']) === 1): ?>
          <li><a href="#<?= esc($item['items'][0]['key']) ?>" data-key="<?= esc($item['items'][0]['key']) ?>" data-label="<?= esc($item['items'][0]['label']) ?>"><?= esc($item['items'][0]['label']) ?></a></li>
        <?php else: ?>
          <li>
            <details class="nav-group">
              <summary data-label="<?= esc($item['label']) ?>"><?= esc($item['label']) ?> ▾</summary>
              <div class="nav-dropdown">
                <?php foreach ($item['items'] as $sub): ?>
                  <a href="#<?= esc($sub['key']) ?>" data-key="<?= esc($sub['key']) ?>" data-label="<?= esc($sub['label']) ?>"><?= esc($sub['label']) ?></a>
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
    $heroVariant = $s['layout_variant'] ?? 'split';
    $heroSize = $s['layout_size'] ?? 'auto';

    // Graceful fallbacks when the content a variant needs isn't there yet -
    // matches the same requirements enforced in section-design-save-ajax.php.
    if ($heroVariant === 'carousel' && count($heroPhotos) < 2) $heroVariant = 'split';
    if (in_array($heroVariant, ['background_fixed', 'background'], true) && empty($heroPhotos)) $heroVariant = 'split';
    if ($heroVariant === 'default') $heroVariant = 'split'; // legacy key from before this rewrite
    if ($heroVariant === 'background') $heroVariant = 'background_fixed'; // legacy key from before this rewrite

    $showCta = in_array($heroVariant, ['text_cta', 'split_cta', 'carousel', 'background_fixed'], true);
    $sizeClass = $heroSize === 'full' ? ' hero-size-full' : ($heroSize === 'half' ? ' hero-size-half' : '');

    // Resolve up to 2 real CTA buttons from the school's choice (cta_1/cta_2),
    // falling back to the smart default for slot 1 if never explicitly set.
    // A choice that no longer resolves to an available section (e.g. picked
    // "Check Results" then downgraded to free) is silently skipped rather
    // than linking to a dead anchor.
    $heroCtaButtons = [];
    if ($showCta) {
        $chosen1 = $c['cta_1'] ?? '';
        $chosen2 = $c['cta_2'] ?? '';
        if (empty($chosen1) && $defaultCta) $chosen1 = $defaultCta;
        foreach ([$chosen1, $chosen2] as $choice) {
            if ($choice && in_array($choice, $availableCtaKeys, true) && !isset($heroCtaButtons[$choice])) {
                $heroCtaButtons[$choice] = $ctaDestinations[$choice];
            }
        }
    }
    ob_start();
    foreach ($heroCtaButtons as $key => $dest): ?><a class="hero-cta" href="#<?= esc($dest['key']) ?>"><?= esc($dest['label']) ?></a><?php endforeach;
    $heroCtaHtml = ob_get_clean();
?>

<?php if ($heroVariant === 'background_fixed'): ?>
  <section class="hero hero-bgfixed-variant<?= $sizeClass ?>" id="hero" style="background-image:url('<?= img($heroPhotos[0]) ?>');">
    <div class="hero-bg-overlay"></div>
    <div class="hero-bg-content">
      <h1><?= esc($c['headline'] ?: $school['name']) ?></h1>
      <?php if (!empty($c['subheading'])): ?><p><?= esc($c['subheading']) ?></p><?php endif; ?>
      <?php if ($heroCtaButtons): ?><div class="hero-cta-row"><?= $heroCtaHtml ?></div><?php endif; ?>
    </div>
  </section>

<?php elseif ($heroVariant === 'carousel'): ?>
  <section class="hero hero-carousel-variant<?= $sizeClass ?>" id="hero">
    <div class="hero-carousel-track">
      <?php foreach ($heroPhotos as $i => $photo): ?>
        <div class="hero-carousel-slide<?= $i === 0 ? ' active' : '' ?>"><img src="<?= img($photo) ?>" alt=""></div>
      <?php endforeach; ?>
    </div>
    <div class="hero-bg-overlay"></div>
    <div class="hero-bg-content">
      <h1><?= esc($c['headline'] ?: $school['name']) ?></h1>
      <?php if (!empty($c['subheading'])): ?><p><?= esc($c['subheading']) ?></p><?php endif; ?>
      <?php if ($heroCtaButtons): ?><div class="hero-cta-row"><?= $heroCtaHtml ?></div><?php endif; ?>
    </div>
    <script>
      (function(){
        var slides = document.querySelectorAll('#hero .hero-carousel-slide');
        if (slides.length < 2) return;
        var i = 0;
        setInterval(function(){
          slides[i].classList.remove('active');
          i = (i + 1) % slides.length;
          slides[i].classList.add('active');
        }, 4500);
      })();
    </script>
  </section>

<?php elseif (in_array($heroVariant, ['text_only', 'text_cta'], true)): ?>
  <section class="hero hero-text-variant<?= $sizeClass ?>" id="hero">
    <div class="hero-inner-text">
      <h1><?= esc($c['headline'] ?: $school['name']) ?></h1>
      <?php if (!empty($c['subheading'])): ?><p><?= esc($c['subheading']) ?></p><?php endif; ?>
      <?php if ($heroCtaButtons): ?><div class="hero-cta-row"><?= $heroCtaHtml ?></div><?php endif; ?>
    </div>
  </section>

<?php else: /* split or split_cta - text left, image right */ ?>
  <section class="hero<?= $sizeClass ?>" id="hero">
    <div class="hero-inner">
      <div>
        <h1><?= esc($c['headline'] ?: $school['name']) ?></h1>
        <?php if (!empty($c['subheading'])): ?><p><?= esc($c['subheading']) ?></p><?php endif; ?>
        <?php if ($heroCtaButtons): ?><div class="hero-cta-row"><?= $heroCtaHtml ?></div><?php endif; ?>
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
<?php endif; ?>

<?php elseif ($key === 'about'):
    $aboutVariant = $s['layout_variant'] ?? 'photo_right';
    $aboutPhotos = array_values(array_filter([$c['photo'] ?? '', $c['photo_2'] ?? '', $c['photo_3'] ?? '']));
    $listItems = array_values(array_filter(array_map('trim', explode("\n", $c['list_items'] ?? ''))));

    // Graceful fallbacks when the content a variant needs isn't there yet.
    if ($aboutVariant === 'carousel_left' && count($aboutPhotos) < 2) $aboutVariant = 'photo_right';
    if (in_array($aboutVariant, ['photo_right', 'photo_left', 'quote', 'stats_inline'], true) && empty($aboutPhotos)) $aboutVariant = 'text_only';
    if ($aboutVariant === 'list' && empty($listItems)) $aboutVariant = 'text_only';
    if ($aboutVariant === 'timeline' && empty($listItems)) $aboutVariant = 'text_only';
?>

<?php if ($aboutVariant === 'text_only'): ?>
  <section id="about">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="about-text-only"><?= nl2p($c['body'] ?? '') ?></div>
    </div>
  </section>

<?php elseif ($aboutVariant === 'photo_left'): ?>
  <section id="about">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="about-grid about-photo-left">
        <div class="about-photo"><img src="<?= img($aboutPhotos[0]) ?>" alt=""></div>
        <div><?= nl2p($c['body'] ?? '') ?></div>
      </div>
    </div>
  </section>

<?php elseif ($aboutVariant === 'carousel_left'): ?>
  <section id="about">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="about-grid about-photo-left">
        <div class="about-carousel">
          <?php foreach ($aboutPhotos as $i => $photo): ?>
            <div class="about-carousel-slide<?= $i === 0 ? ' active' : '' ?>"><img src="<?= img($photo) ?>" alt=""></div>
          <?php endforeach; ?>
        </div>
        <div><?= nl2p($c['body'] ?? '') ?></div>
      </div>
    </div>
  </section>
  <script>
    (function(){
      var slides = document.querySelectorAll('#about .about-carousel-slide');
      if (slides.length < 2) return;
      var i = 0;
      setInterval(function(){
        slides[i].classList.remove('active');
        i = (i + 1) % slides.length;
        slides[i].classList.add('active');
      }, 4500);
    })();
  </script>

<?php elseif ($aboutVariant === 'list'): ?>
  <section id="about">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <?php if (!empty($c['body'])): ?><div class="about-list-intro"><?= nl2p($c['body']) ?></div><?php endif; ?>
      <ul class="about-list">
        <?php foreach ($listItems as $item): ?><li><?= esc($item) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </section>

<?php elseif ($aboutVariant === 'timeline'): ?>
  <section id="about">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <?php if (!empty($c['body'])): ?><div class="about-list-intro"><?= nl2p($c['body']) ?></div><?php endif; ?>
      <div class="about-timeline">
        <?php foreach ($listItems as $item): ?><div class="about-timeline-item"><?= esc($item) ?></div><?php endforeach; ?>
      </div>
    </div>
  </section>

<?php elseif ($aboutVariant === 'quote'): ?>
  <section id="about">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="about-quote-grid">
        <div class="about-quote-photo"><img src="<?= img($aboutPhotos[0]) ?>" alt=""></div>
        <div class="about-quote-text">
          <div class="about-quote-mark">&ldquo;</div>
          <?= nl2p($c['body'] ?? '') ?>
          <?php if (!empty($c['author_name'])): ?><div class="about-quote-author">— <?= esc($c['author_name']) ?></div><?php endif; ?>
        </div>
      </div>
    </div>
  </section>

<?php elseif ($aboutVariant === 'stats_inline'): ?>
  <section id="about">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="about-grid">
        <div>
          <?= nl2p($c['body'] ?? '') ?>
          <?php if (!empty($c['inline_stat_1']) || !empty($c['inline_stat_2'])): ?>
            <div class="about-inline-stats">
              <?php if (!empty($c['inline_stat_1'])): ?><div class="about-inline-stat"><span class="num"><?= esc($c['inline_stat_1']) ?></span><span class="label"><?= esc($c['inline_stat_1_label'] ?? '') ?></span></div><?php endif; ?>
              <?php if (!empty($c['inline_stat_2'])): ?><div class="about-inline-stat"><span class="num"><?= esc($c['inline_stat_2']) ?></span><span class="label"><?= esc($c['inline_stat_2_label'] ?? '') ?></span></div><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="about-photo"><img src="<?= img($aboutPhotos[0]) ?>" alt=""></div>
      </div>
    </div>
  </section>

<?php else: /* photo_right - default */ ?>
  <section id="about">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>
      <div class="about-grid">
        <div><?= nl2p($c['body'] ?? '') ?></div>
        <div class="about-photo"><img src="<?= img($aboutPhotos[0]) ?>" alt=""></div>
      </div>
    </div>
  </section>
<?php endif; ?>

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

<?php elseif ($key === 'gallery'):
    $galleryVariant = $s['layout_variant'] ?? 'grid';
    $galleryItems = [];
    for ($i = 1; $i <= 10; $i++) {
        if (empty($c["photo_$i"])) continue;
        $galleryItems[] = ['photo' => $c["photo_$i"], 'category' => $c["category_$i"] ?? ''];
    }
    if (empty($galleryItems)) continue; // nothing uploaded yet - skip the section rather than show an empty grid

    if ($galleryVariant === 'before_after' && count($galleryItems) < 2) $galleryVariant = 'grid';
    if ($galleryVariant === 'tabs' && !array_filter($galleryItems, fn($g) => !empty($g['category']))) $galleryVariant = 'grid'; // no categories set - tabs would just be one unlabeled tab

    $cappedGalleryVariants = ['grid', 'masonry'];
    $totalGalleryItems = count($galleryItems);
    $showGalleryViewMore = in_array($galleryVariant, $cappedGalleryVariants, true) && $totalGalleryItems > 3;
    $galleryViewMoreOnlyMobile = $totalGalleryItems <= 6;
?>
  <section id="gallery">
    <div class="wrap">
      <div class="section-head">
        <h2><?= esc($s['label']) ?></h2>
        <?php if (!empty($c['caption'])): ?><p style="color:#5a5a52;margin-top:6px;"><?= esc($c['caption']) ?></p><?php endif; ?>
      </div>

      <?php if ($galleryVariant === 'masonry'): ?>
        <div class="gallery-masonry" id="galleryContainer">
          <?php foreach ($galleryItems as $g): ?><img src="<?= img($g['photo']) ?>" alt=""><?php endforeach; ?>
        </div>

      <?php elseif ($galleryVariant === 'lightbox'): ?>
        <div class="gallery-grid">
          <?php foreach ($galleryItems as $i => $g): ?>
            <img src="<?= img($g['photo']) ?>" alt="" class="gallery-lightbox-trigger" onclick="document.getElementById('lightbox-<?= esc($key) ?>-<?= $i ?>').classList.add('open');">
          <?php endforeach; ?>
        </div>
        <?php foreach ($galleryItems as $i => $g): ?>
          <div class="gallery-lightbox" id="lightbox-<?= esc($key) ?>-<?= $i ?>" onclick="this.classList.remove('open');">
            <img src="<?= img($g['photo']) ?>" alt="">
            <button type="button" class="gallery-lightbox-close">✕</button>
          </div>
        <?php endforeach; ?>

      <?php elseif ($galleryVariant === 'before_after'): ?>
        <div class="gallery-before-after">
          <div class="ba-after"><img src="<?= img($galleryItems[1]['photo']) ?>" alt="After"></div>
          <div class="ba-before" id="baBefore"><img src="<?= img($galleryItems[0]['photo']) ?>" alt="Before"></div>
          <input type="range" min="0" max="100" value="50" class="ba-slider" oninput="document.getElementById('baBefore').style.clipPath='inset(0 '+(100-this.value)+'% 0 0)';">
          <span class="ba-label ba-label-before">Before</span>
          <span class="ba-label ba-label-after">After</span>
        </div>

      <?php elseif ($galleryVariant === 'slideshow'): ?>
        <div class="gallery-slideshow">
          <?php foreach ($galleryItems as $i => $g): ?>
            <div class="gallery-slide<?= $i === 0 ? ' active' : '' ?>"><img src="<?= img($g['photo']) ?>" alt=""></div>
          <?php endforeach; ?>
        </div>
        <?php if (count($galleryItems) > 1): ?>
        <script>
          (function(){
            var slides = document.querySelectorAll('#gallery .gallery-slide');
            if (slides.length < 2) return;
            var i = 0;
            setInterval(function(){
              slides[i].classList.remove('active');
              i = (i + 1) % slides.length;
              slides[i].classList.add('active');
            }, 4000);
          })();
        </script>
        <?php endif; ?>

      <?php elseif ($galleryVariant === 'tabs'):
          $tabGroups = [];
          foreach ($galleryItems as $g) {
              $cat = $g['category'] ?: 'Other';
              $tabGroups[$cat][] = $g;
          }
          $tabNames = array_keys($tabGroups);
      ?>
        <div class="gallery-tabs">
          <div class="gallery-tab-buttons">
            <?php foreach ($tabNames as $i => $tabName): ?>
              <button type="button" class="gallery-tab-btn<?= $i === 0 ? ' active' : '' ?>" onclick="document.querySelectorAll('#gallery .gallery-tab-btn').forEach(b=>b.classList.remove('active'));this.classList.add('active');document.querySelectorAll('#gallery .gallery-tab-panel').forEach(p=>p.classList.remove('active'));document.getElementById('galtab-<?= $i ?>').classList.add('active');"><?= esc($tabName) ?></button>
            <?php endforeach; ?>
          </div>
          <?php foreach ($tabNames as $i => $tabName): ?>
            <div class="gallery-tab-panel gallery-grid<?= $i === 0 ? ' active' : '' ?>" id="galtab-<?= $i ?>">
              <?php foreach ($tabGroups[$tabName] as $g): ?><img src="<?= img($g['photo']) ?>" alt=""><?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>

      <?php else: /* grid - default */ ?>
        <div class="gallery-grid" id="galleryContainer">
          <?php foreach ($galleryItems as $g): ?><img src="<?= img($g['photo']) ?>" alt=""><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($showGalleryViewMore): ?>
        <button type="button" class="staff-view-more<?= $galleryViewMoreOnlyMobile ? ' only-mobile' : '' ?>" onclick="document.getElementById('galleryContainer').classList.add('expanded');this.style.display='none';">View More</button>
      <?php endif; ?>
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
      <?php if (!empty($c['map_location']) && strpos($c['map_location'], ',') !== false):
        [$mapLat, $mapLng] = explode(',', $c['map_location'], 2);
        $mapLat = trim($mapLat); $mapLng = trim($mapLng);
      ?>
        <div id="school-map" style="height:300px;border-radius:12px;margin-top:20px;"></div>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
          (function() {
            const map = L.map('school-map').setView([<?= $mapLat ?>, <?= $mapLng ?>], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
            }).addTo(map);
            L.marker([<?= $mapLat ?>, <?= $mapLng ?>]).addTo(map)
              .bindPopup(<?= json_encode($school['name']) ?>);
          })();
        </script>
        <p style="margin-top:8px;"><a href="https://www.google.com/maps/dir/?api=1&destination=<?= $mapLat ?>,<?= $mapLng ?>" target="_blank" style="font-size:0.85rem;color:var(--primary,#0F5257);font-weight:700;">Get Directions →</a></p>
      <?php endif; ?>
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
        <a href="report-card.php?school=<?= urlencode($school['slug']) ?>" class="btn-primary" style="margin-left:10px;background:transparent;border:2px solid var(--primary);color:var(--primary);">View Full Report</a>
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

<?php elseif ($key === 'staff'):
    $staffVariant = $s['layout_variant'] ?? 'grid';
    $people = [];
    for ($i = 1; $i <= 10; $i++) {
        if (empty($c["name_$i"])) continue;
        $people[] = [
            'name' => $c["name_$i"],
            'role' => $c["role_$i"] ?? '',
            'photo' => $c["photo_$i"] ?? '',
            'bio' => $c["bio_$i"] ?? '',
            'department' => $c["department_$i"] ?? '',
        ];
    }
    if (empty($people)) continue; // nothing entered yet - skip rather than show an empty section

    if ($staffVariant === 'grouped' && !array_filter($people, fn($p) => !empty($p['department']))) $staffVariant = 'grid'; // no departments set - grouping would just be one unlabeled group

    // Universal "show 3 on mobile, 6 on desktop, View More" capping -
    // applies to the layouts that stack vertically/in a grid. Carousel
    // already solves the space problem via horizontal scroll, and org
    // chart is a small curated leadership view, not meant for browsing
    // through 10 people - neither gets capped.
    $cappedVariants = ['grid', 'list_bio', 'minimal'];
    $totalStaff = count($people);
    $showViewMore = in_array($staffVariant, $cappedVariants, true) && $totalStaff > 3;
    $viewMoreOnlyMobile = $totalStaff <= 6;
?>
  <section id="staff">
    <div class="wrap">
      <div class="section-head">
        <h2><?= esc($s['label']) ?></h2>
        <?php if (!empty($c['intro_text'])): ?><p style="color:#5a5a52;margin-top:6px;"><?= esc($c['intro_text']) ?></p><?php endif; ?>
      </div>

      <?php if ($staffVariant === 'carousel'): ?>
        <div class="staff-carousel">
          <?php foreach ($people as $p): ?>
            <div class="staff-card">
              <?php if (!empty($p['photo'])): ?><img src="<?= img($p['photo']) ?>" alt="<?= esc($p['name']) ?>"><?php endif; ?>
              <div class="name"><?= esc($p['name']) ?></div>
              <div class="role"><?= esc($p['role']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php elseif ($staffVariant === 'list_bio'): ?>
        <div class="staff-list-bio" id="staffContainer">
          <?php foreach ($people as $p): ?>
            <div class="staff-bio-row">
              <?php if (!empty($p['photo'])): ?><img src="<?= img($p['photo']) ?>" alt="<?= esc($p['name']) ?>"><?php endif; ?>
              <div>
                <div class="name"><?= esc($p['name']) ?></div>
                <div class="role"><?= esc($p['role']) ?></div>
                <?php if (!empty($p['bio'])): ?><p class="bio"><?= esc($p['bio']) ?></p><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php elseif ($staffVariant === 'org_chart'):
          $tier1 = array_slice($people, 0, 1);
          $tier2 = array_slice($people, 1, 2);
          $tier3 = array_slice($people, 3);
          $renderOrgCard = function($p) { ?>
            <div class="staff-card">
              <?php if (!empty($p['photo'])): ?><img src="<?= img($p['photo']) ?>" alt="<?= esc($p['name']) ?>"><?php endif; ?>
              <div class="name"><?= esc($p['name']) ?></div>
              <div class="role"><?= esc($p['role']) ?></div>
            </div>
          <?php };
      ?>
        <div class="staff-org-chart">
          <div class="org-tier org-tier-1"><?php foreach ($tier1 as $p) $renderOrgCard($p); ?></div>
          <?php if ($tier2): ?><div class="org-tier org-tier-2"><?php foreach ($tier2 as $p) $renderOrgCard($p); ?></div><?php endif; ?>
          <?php if ($tier3): ?><div class="org-tier org-tier-3"><?php foreach ($tier3 as $p) $renderOrgCard($p); ?></div><?php endif; ?>
        </div>

      <?php elseif ($staffVariant === 'minimal'): ?>
        <div class="staff-minimal-list" id="staffContainer">
          <?php foreach ($people as $p): ?>
            <div class="staff-minimal-item"><span class="name"><?= esc($p['name']) ?></span><span class="role"><?= esc($p['role']) ?></span></div>
          <?php endforeach; ?>
        </div>

      <?php elseif ($staffVariant === 'grouped'):
          $groups = [];
          foreach ($people as $p) {
              $dept = $p['department'] ?: 'Other';
              $groups[$dept][] = $p;
          }
      ?>
        <div id="staffContainer">
          <?php foreach ($groups as $deptName => $deptPeople): ?>
            <h3 class="staff-dept-heading"><?= esc($deptName) ?></h3>
            <div class="staff-grid">
              <?php foreach ($deptPeople as $p): ?>
                <div class="staff-card">
                  <?php if (!empty($p['photo'])): ?><img src="<?= img($p['photo']) ?>" alt="<?= esc($p['name']) ?>"><?php endif; ?>
                  <div class="name"><?= esc($p['name']) ?></div>
                  <div class="role"><?= esc($p['role']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>

      <?php else: /* grid - default */ ?>
        <div class="staff-grid" id="staffContainer">
          <?php foreach ($people as $p): ?>
            <div class="staff-card">
              <?php if (!empty($p['photo'])): ?><img src="<?= img($p['photo']) ?>" alt="<?= esc($p['name']) ?>"><?php endif; ?>
              <div class="name"><?= esc($p['name']) ?></div>
              <div class="role"><?= esc($p['role']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($showViewMore): ?>
        <button type="button" class="staff-view-more<?= $viewMoreOnlyMobile ? ' only-mobile' : '' ?>" onclick="document.getElementById('staffContainer').classList.add('expanded');this.style.display='none';">View More</button>
      <?php endif; ?>
    </div>
  </section>

<?php elseif ($key === 'testimonials'):
    $testimonialVariant = $s['layout_variant'] ?? 'cards';
    $quotes = [];
    for ($i = 1; $i <= 10; $i++) {
        if (empty($c["quote_$i"])) continue;
        $ratingRaw = (int)($c["rating_$i"] ?? 0);
        $quotes[] = [
            'quote' => $c["quote_$i"],
            'author' => $c["author_$i"] ?? '',
            'photo' => $c["photo_$i"] ?? '',
            'rating' => ($ratingRaw >= 1 && $ratingRaw <= 5) ? $ratingRaw : 0,
        ];
    }
    if (empty($quotes)) continue; // nothing entered yet - skip rather than show an empty section

    if ($testimonialVariant === 'photo_forward' && !array_filter($quotes, fn($q) => !empty($q['photo']))) $testimonialVariant = 'cards'; // no photos - photo-forward would just show blanks

    $cappedTestimonialVariants = ['cards', 'wall', 'rating', 'photo_forward'];
    $totalQuotes = count($quotes);
    $showQuoteViewMore = in_array($testimonialVariant, $cappedTestimonialVariants, true) && $totalQuotes > 3;
    $quoteViewMoreOnlyMobile = $totalQuotes <= 6;
?>
  <section id="testimonials">
    <div class="wrap">
      <div class="section-head"><h2><?= esc($s['label']) ?></h2></div>

      <?php if ($testimonialVariant === 'slider'): ?>
        <div class="testimonial-slider">
          <?php foreach ($quotes as $i => $q): ?>
            <div class="testimonial-slide<?= $i === 0 ? ' active' : '' ?>">
              <div class="quote">"<?= esc($q['quote']) ?>"</div>
              <div class="author"><?= esc($q['author']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($quotes) > 1): ?>
        <script>
          (function(){
            var slides = document.querySelectorAll('#testimonials .testimonial-slide');
            if (slides.length < 2) return;
            var i = 0;
            setInterval(function(){
              slides[i].classList.remove('active');
              i = (i + 1) % slides.length;
              slides[i].classList.add('active');
            }, 5500);
          })();
        </script>
        <?php endif; ?>

      <?php elseif ($testimonialVariant === 'single'): ?>
        <div class="testimonial-single">
          <div class="quote">"<?= esc($quotes[0]['quote']) ?>"</div>
          <div class="author"><?= esc($quotes[0]['author']) ?></div>
        </div>

      <?php elseif ($testimonialVariant === 'wall'): ?>
        <div class="testimonial-wall" id="testimonialsContainer">
          <?php foreach ($quotes as $q): ?>
            <div class="testimonial-wall-item">
              <div class="quote">"<?= esc($q['quote']) ?>"</div>
              <div class="author"><?= esc($q['author']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php elseif ($testimonialVariant === 'rating'): ?>
        <div class="testimonial-grid" id="testimonialsContainer">
          <?php foreach ($quotes as $q): ?>
            <div class="testimonial-card">
              <?php if ($q['rating']): ?><div class="stars"><?= str_repeat('★', $q['rating']) . str_repeat('☆', 5 - $q['rating']) ?></div><?php endif; ?>
              <div class="quote">"<?= esc($q['quote']) ?>"</div>
              <div class="author"><?= esc($q['author']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php elseif ($testimonialVariant === 'photo_forward'): ?>
        <div class="testimonial-photo-grid" id="testimonialsContainer">
          <?php foreach ($quotes as $q): ?>
            <div class="testimonial-photo-card">
              <?php if (!empty($q['photo'])): ?><img src="<?= img($q['photo']) ?>" alt="<?= esc($q['author']) ?>"><?php endif; ?>
              <div class="quote">"<?= esc($q['quote']) ?>"</div>
              <div class="author"><?= esc($q['author']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php else: /* cards - default */ ?>
        <div class="testimonial-grid" id="testimonialsContainer">
          <?php foreach ($quotes as $q): ?>
            <div class="testimonial-card">
              <div class="quote">"<?= esc($q['quote']) ?>"</div>
              <div class="author"><?= esc($q['author']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($showQuoteViewMore): ?>
        <button type="button" class="staff-view-more<?= $quoteViewMoreOnlyMobile ? ' only-mobile' : '' ?>" onclick="document.getElementById('testimonialsContainer').classList.add('expanded');this.style.display='none';">View More</button>
      <?php endif; ?>
    </div>
  </section>

<?php elseif ($key === 'reviews'):
    $schoolReviews = get_approved_reviews($db, 'school', $school['id']);
    $avgRating = get_average_rating($schoolReviews);
    $reviewFlagSubmitted = isset($_GET['review_submitted']);
    $reviewFlagError = $_GET['review_error'] ?? '';
?>
  <section id="reviews">
    <div class="wrap">
      <div class="section-head">
        <h2><?= esc($s['label']) ?></h2>
        <?php if ($avgRating !== null): ?>
          <p style="color:var(--muted);font-size:0.95rem;"><?= render_stars((int)round($avgRating)) ?> <?= $avgRating ?> out of 5 (<?= count($schoolReviews) ?> review<?= count($schoolReviews) == 1 ? '' : 's' ?>)</p>
        <?php endif; ?>
      </div>

      <?php if (!empty($c['intro_text'])): ?><p style="margin-bottom:24px;color:var(--muted);"><?= nl2p($c['intro_text']) ?></p><?php endif; ?>

      <?php if ($reviewFlagSubmitted): ?>
        <p style="background:#DCEFE1;color:#1B4D3E;padding:10px 16px;border-radius:8px;margin-bottom:20px;font-size:0.9rem;">✓ Thank you! Your review has been submitted and will appear once reviewed.</p>
      <?php elseif ($reviewFlagError): ?>
        <p style="background:#FBE8E4;color:#8C3B2E;padding:10px 16px;border-radius:8px;margin-bottom:20px;font-size:0.9rem;"><?= esc($reviewFlagError) ?></p>
      <?php endif; ?>

      <div class="testimonial-grid">
        <?php foreach ($schoolReviews as $r): ?>
          <div class="testimonial-card">
            <div style="color:#F2A65A;letter-spacing:2px;margin-bottom:6px;"><?= render_stars((int)$r['rating']) ?></div>
            <div class="quote">"<?= esc($r['comment']) ?>"</div>
            <div class="author"><?= esc($r['reviewer_name']) ?><?= $r['reviewer_role'] ? ' - ' . esc($r['reviewer_role']) : '' ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$schoolReviews): ?><p style="color:var(--muted);">No reviews yet - be the first to leave one.</p><?php endif; ?>
      </div>

      <details style="margin-top:28px;max-width:480px;">
        <summary style="cursor:pointer;color:var(--teal,#0F5257);font-weight:600;">Leave a review</summary>
        <form method="POST" action="/reviews-submit.php" style="margin-top:14px;">
          <input type="hidden" name="reviewable_type" value="school">
          <input type="hidden" name="reviewable_id" value="<?= (int)$school['id'] ?>">
          <input type="hidden" name="redirect_to" value="https://<?= esc($school['slug']) ?>.somahub.top/">
          <input type="text" name="website" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off">

          <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Your Name</label>
          <input type="text" name="reviewer_name" required style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;box-sizing:border-box;">

          <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">You are a... (optional)</label>
          <input type="text" name="reviewer_role" placeholder="e.g. Parent, Alumni" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;box-sizing:border-box;">

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

          <button type="submit" class="btn-primary">Submit Review</button>
        </form>
      </details>
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

<?php elseif ($key === 'stats'):
    $filledStats = array_filter([1,2,3,4], fn($i) => !empty($c["stat_{$i}_number"]));
    if (empty($filledStats)) continue; // nothing filled in yet - skip rather than show an empty strip
?>
  <section id="stats">
    <div class="wrap">
      <div class="stats-strip">
        <?php foreach ($filledStats as $i): ?>
          <div class="stat-item">
            <div class="number"><?= esc($c["stat_{$i}_number"]) ?></div>
            <div class="label"><?= esc($c["stat_{$i}_label"] ?? '') ?></div>
          </div>
        <?php endforeach; ?>
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