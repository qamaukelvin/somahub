<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/settings.php';
$db = get_db();
$wa = htmlspecialchars(get_setting($db, 'whatsapp_outreach_number', '254707306888'));

// [id, icon, title]
$toc = [
  ['overview','info','Overview'],
  ['signup','person_add','1. Create your site'],
  ['first-look','waving_hand','2. Your first look'],
  ['editing','edit','3. Editing your site'],
  ['sections','view_agenda','4. Section guide'],
  ['school-data','folder_open','5. School data'],
  ['parents','family_restroom','6. What parents see'],
  ['verification','verified','7. Verification'],
  ['plans','workspace_premium','8. Trial, Free and Premium'],
  ['domain','language','9. Domain and add-ons'],
  ['account','manage_accounts','10. Your account'],
  ['speed','speed','11. Keeping your site fast'],
  ['help','help','12. Troubleshooting'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>How Somahub works: guide from signup to a live school site</title>
<meta name="description" content="Step-by-step guide to Somahub: create your school site, edit it, publish, verify, collect enrollments and results, and manage your account.">
<link rel="canonical" href="https://somahub.top/docs.php">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<style>
  :root{--teal:#0F5257;--teal-deep:#0A3A3E;--amber:#F2A65A;--sand:#F7F2E7;--ink:#1C1C16;--muted:#6E6A5C;--line:#E5DFCC;}
  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;scroll-padding-top:84px;}
  body{margin:0;font-family:'Manrope',sans-serif;background:var(--sand);color:var(--ink);line-height:1.7;}
  .material-symbols-outlined{font-family:'Material Symbols Outlined';font-weight:400;font-style:normal;line-height:1;display:inline-block;vertical-align:middle;}
  .layout{max-width:1120px;margin:0 auto;padding:36px 24px 80px;display:grid;grid-template-columns:230px 1fr;gap:44px;align-items:start;}
  .toc{position:sticky;top:84px;}
  .toc h4{margin:0 0 10px;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);}
  .toc a{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:9px;color:var(--ink);text-decoration:none;font-size:.85rem;font-weight:600;}
  .toc a:hover{background:rgba(15,82,87,.08);} .toc a .material-symbols-outlined{font-size:18px;color:var(--teal);}
  .toc-toggle{display:none;}
  article{min-width:0;max-width:760px;}
  h1{font-size:clamp(1.8rem,4vw,2.4rem);line-height:1.15;margin:0 0 10px;letter-spacing:-.02em;}
  .lead{color:var(--muted);font-size:1.05rem;margin:0 0 30px;}
  h2{font-size:1.35rem;margin:46px 0 10px;color:var(--teal);display:flex;align-items:center;gap:10px;letter-spacing:-.01em;}
  h2 .material-symbols-outlined{background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px;font-size:22px;}
  h3{font-size:1rem;margin:22px 0 6px;}
  p,li{font-size:.95rem;color:#2f2f28;} p{margin:0 0 12px;} ul,ol{padding-left:22px;margin:0 0 14px;} li{margin-bottom:6px;}
  a{color:var(--teal);font-weight:600;}
  .tip,.warn{border-radius:12px;padding:14px 16px;font-size:.88rem;margin:16px 0;display:flex;gap:10px;align-items:flex-start;}
  .tip{background:#E7F2EE;color:#17483f;} .warn{background:#FDF0DC;color:#7a4a0c;}
  .tip .material-symbols-outlined,.warn .material-symbols-outlined{font-size:20px;flex:0 0 auto;margin-top:2px;}
  table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden;margin:14px 0 20px;font-size:.88rem;}
  th,td{text-align:left;padding:10px 14px;border-bottom:1px solid var(--line);vertical-align:top;} th{background:#FAF7EE;font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);} tr:last-child td{border-bottom:none;}
  .tag{font-size:.64rem;background:var(--teal);color:var(--sand);padding:2px 8px;border-radius:20px;font-weight:700;text-transform:uppercase;white-space:nowrap;}
  .flow{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:14px 0 22px;}
  .flow span.s{background:#fff;border:1px solid var(--line);border-radius:999px;padding:7px 14px;font-size:.82rem;font-weight:700;} .flow .material-symbols-outlined{color:var(--amber);font-size:20px;}
  details{background:#fff;border:1px solid var(--line);border-radius:12px;padding:4px 16px;margin-bottom:10px;} summary{cursor:pointer;font-weight:700;padding:10px 0;font-size:.92rem;} details p{margin-bottom:12px;}
  .cta{background:var(--teal-deep);color:var(--sand);border-radius:18px;padding:30px;margin-top:50px;text-align:center;} .cta p{color:#BFD8D9;} .cta a.btn{display:inline-block;background:var(--amber);color:var(--teal-deep);text-decoration:none;padding:12px 24px;border-radius:24px;font-weight:800;margin:4px;}
  @media(max-width:900px){
    .layout{grid-template-columns:1fr;gap:16px;padding-top:20px;}
    .toc{position:static;background:#fff;border:1px solid var(--line);border-radius:12px;padding:6px 12px;}
    .toc-toggle{display:flex;width:100%;justify-content:space-between;align-items:center;background:none;border:none;font:inherit;font-weight:800;padding:10px 0;cursor:pointer;color:var(--ink);}
    .toc h4{display:none;} .toc nav{display:none;padding-bottom:8px;} .toc.open nav{display:block;}
  }
</style>
</head>
<body>
<?php $navRoot = '.'; include __DIR__ . '/_public_nav.php'; ?>
<div class="layout">
  <aside class="toc" id="toc">
    <button type="button" class="toc-toggle" onclick="document.getElementById('toc').classList.toggle('open')">Contents <span class="material-symbols-outlined">expand_more</span></button>
    <h4>On this page</h4>
    <nav>
      <?php foreach ($toc as $t): ?><a href="#<?= $t[0] ?>"><span class="material-symbols-outlined"><?= $t[1] ?></span><?= htmlspecialchars($t[2]) ?></a><?php endforeach; ?>
    </nav>
  </aside>

  <article>
    <h1>How Somahub works</h1>
    <p class="lead">Everything from creating your account to running a live school website, in the order you will meet it.</p>

    <h2 id="overview"><span class="material-symbols-outlined">info</span>Overview</h2>
    <p>Somahub gives every school a ready-made website at <strong>yourschool.somahub.top</strong>. You sign up, we set up the pages, and you change the text, photos, colours and layout by clicking right on the page. There is nothing to install and no coding.</p>
    <div class="flow">
      <span class="s">Sign up</span><span class="material-symbols-outlined">arrow_forward</span>
      <span class="s">Site is live</span><span class="material-symbols-outlined">arrow_forward</span>
      <span class="s">Make it yours</span><span class="material-symbols-outlined">arrow_forward</span>
      <span class="s">Verify within 7 days</span><span class="material-symbols-outlined">arrow_forward</span>
      <span class="s">Stay on Free or go Premium</span>
    </div>

    <h2 id="signup"><span class="material-symbols-outlined">person_add</span>1. Create your site</h2>
    <p>Go to <a href="get-started.php">Get Started</a> and fill in one short form. It has three parts.</p>
    <h3>About you</h3>
    <ul>
      <li><strong>Your name, phone and email.</strong> Your email is what you log in with.</li>
      <li><strong>A password</strong> of at least 8 characters. Use Show to check what you typed.</li>
    </ul>
    <h3>Your school</h3>
    <ul>
      <li><strong>School name</strong> and <strong>web address.</strong> The address becomes <em>yourschool</em>.somahub.top. It must be at least 3 letters or numbers, and not already taken. Names like admin or www are reserved. You can see if it is free as you type.</li>
      <li><strong>County</strong> and a <strong>map pin</strong> for your school. The pin is used on your Contact section map. You can use your current location or place it by hand.</li>
      <li><strong>Starter text.</strong> Pick the kind of school you run so the first pages come with sensible wording that you then edit.</li>
      <li><strong>Your role at the school.</strong> If you are the Head Teacher or the Proprietor / Director, you are done. Otherwise we ask you to confirm you are allowed to create the site, and for your head teacher's name and phone, so we can contact them when verifying.</li>
    </ul>
    <h3>Agreement</h3>
    <p>Tick the box to accept the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a>, then press <strong>Create my website</strong>.</p>
    <div class="tip"><span class="material-symbols-outlined">lightbulb</span><div>Every new school starts on a <strong>60-day trial of every feature</strong>. No card is needed, and nothing is charged unless you choose to upgrade.</div></div>
    <p>You get a welcome email, and your site is live straight away at your address.</p>

    <h2 id="first-look"><span class="material-symbols-outlined">waving_hand</span>2. Your first look</h2>
    <p>After signup you land on the <strong>Website</strong> page of your dashboard, with a short welcome message that shows you the four things to do first. This page lists every section of your site, in the order visitors see them.</p>
    <p>Your site starts with these sections: Hero, About, Academics, Admissions, Enrollment form, Gallery, Staff, Testimonials, FAQ, Results lookup and Contact. A Fees section is also added but kept <strong>hidden until you add a fee</strong>. Your head teacher's name and your contact details are filled in where we already know them.</p>
    <p>The top bar of every dashboard page has the same links: <strong>Home</strong>, <strong>Edit site</strong>, <strong>Sections</strong>, <strong>School data</strong>, <strong>Account</strong>, <strong>View site</strong> and <strong>Log out</strong>. The bell shows reminders such as verifying your school.</p>

    <h2 id="editing"><span class="material-symbols-outlined">edit</span>3. Editing your site</h2>
    <p>The quickest way is <strong>Edit site</strong> (the orange button in the top bar, and where you land right after signing up). It opens your <strong>real website</strong> with editing switched on, so you change things exactly where visitors see them. Everything saves by itself, and you can <strong>undo and redo</strong> from the bar at the bottom.</p>
    <ul>
      <li><strong>Click any text or photo</strong> to change it. A dashed box shows where a photo is still needed.</li>
      <li><strong>Layout</strong> (top left of a section) switches its design. The <strong>three dots</strong> (top right) move, duplicate, hide or remove the section, or open its full details.</li>
      <li>The small <strong>+</strong> between sections adds a new section right there, and you can drag one from the Add section list to the spot you want.</li>
      <li>Staff, testimonials, FAQs, gallery photos, blog posts and statistics each have an <strong>Add</strong> button and small tools on each item to move or remove it.</li>
      <li>The bottom bar holds <strong>Undo, Redo, Colours, Fonts, Add section</strong> and <strong>Done</strong>.</li>
    </ul>
    <p>Prefer one section at a time? Open it from the <strong>Sections</strong> page and you are in its <strong>live editor</strong>: the section looks exactly as visitors see it, and you edit it in place.</p>
    <ul>
      <li><strong>Text.</strong> Click it and type. Changes save automatically.</li>
      <li><strong>Photos.</strong> Click a photo box to upload or replace. Where there is no photo yet, a person icon is shown as a placeholder. Photos are shrunk automatically so your site stays fast.</li>
      <li><strong>Layout.</strong> Each section has layout chips (for example photo left or photo right). Click one to switch. Layouts that need a photo or a map pin fall back to a simpler look on the live site until you add one.</li>
      <li><strong>Text colour.</strong> Hover a heading or paragraph and use the <strong>Colour</strong> pill that appears beside it to colour just that text.</li>
      <li><strong>Site-wide colours.</strong> The <strong>Colours</strong> button in the top bar offers ready colour schemes, or choose your own main, accent, background and text colours. They apply everywhere.</li>
      <li><strong>Font.</strong> The <strong>Font</strong> button changes the font pairing for the whole site.</li>
      <li><strong>Menu.</strong> The <strong>Menu</strong> button in the top bar jumps you to other dashboard pages without going back first.</li>
    </ul>
    <h3>Managing sections</h3>
    <ul>
      <li><strong>Add section</strong> (top of the Website page) opens a picker grouped by type. The new section opens in its live editor straight away.</li>
      <li><strong>Reorder</strong> sections by dragging, <strong>hide</strong> one to remove it from the live site without losing it, <strong>duplicate</strong> it, or <strong>remove</strong> it.</li>
      <li>Your website menu is built from the sections you show, so hiding a section also removes it from the menu.</li>
    </ul>

    <h2 id="sections"><span class="material-symbols-outlined">view_agenda</span>4. Section guide</h2>
    <table>
      <tr><th>Section</th><th>What it does</th></tr>
      <tr><td><strong>Hero</strong></td><td>The big opening: headline, short line, photos and a button. Pick where the button goes: Contact, Request Admission, Check Results or School Fees. Only destinations your site actually has are offered.</td></tr>
      <tr><td><strong>About</strong></td><td>Your story, with an optional photo and the head teacher's name.</td></tr>
      <tr><td><strong>Academics</strong></td><td>Curriculum and grade levels.</td></tr>
      <tr><td><strong>Admissions</strong></td><td>How families join: requirements, dates, steps.</td></tr>
      <tr><td><strong>Stats</strong></td><td>A few key numbers, such as years serving the community.</td></tr>
      <tr><td><strong>Gallery</strong></td><td>Photos of school life. Skipped on the live site until it has photos.</td></tr>
      <tr><td><strong>Staff</strong></td><td>Teachers and leadership, as a grid or a sideways scroll. Skipped until you add someone.</td></tr>
      <tr><td><strong>Testimonials</strong></td><td>What parents say, with star ratings. Visitors can also leave a review for approval.</td></tr>
      <tr><td><strong>FAQ</strong></td><td>Answers to common questions.</td></tr>
      <tr><td><strong>Call-to-action banner</strong></td><td>A highlighted strip that sends visitors somewhere, such as admissions.</td></tr>
      <tr><td><strong>Blog</strong></td><td>News and updates.</td></tr>
      <tr><td><strong>Contact</strong></td><td>Address, phone, email and map. The contact form sends messages to your dashboard.</td></tr>
      <tr><td><strong>Fees</strong> <span class="tag">Trial / Premium</span></td><td>Your fee structure, grouped by grade. Hidden until you add a fee.</td></tr>
      <tr><td><strong>Enrollment form</strong> <span class="tag">Trial / Premium</span></td><td>Parents apply online, either inside the page or on its own page.</td></tr>
      <tr><td><strong>Results lookup</strong> <span class="tag">Trial / Premium</span></td><td>Parents check results with an admission number plus a second detail.</td></tr>
    </table>

    <h2 id="school-data"><span class="material-symbols-outlined">folder_open</span>5. School data</h2>
    <p>Under <strong>School data</strong> in the top bar:</p>
    <ul>
      <li><strong>Enrollment.</strong> Applications sent through your form, with the parent's contact details.</li>
      <li><strong>Contact messages.</strong> Messages sent from your Contact section.</li>
      <li><strong>Results.</strong> Upload a term's results as a <strong>CSV file</strong>. Required columns are <code>admission_no</code> and <code>student_name</code>. Optional columns are <code>date_of_birth</code>, <code>grade</code>, one column per subject, <code>total</code> and <code>position</code>. In Excel use File, Save As, CSV.</li>
      <li><strong>Attendance.</strong> Upload attendance so it appears in the parent's full report.</li>
      <li><strong>Fees.</strong> Add fees by grade and term. The Fees section appears on your site once you add the first one.</li>
    </ul>

    <h2 id="parents"><span class="material-symbols-outlined">family_restroom</span>6. What parents see</h2>
    <ul>
      <li><strong>Your site</strong> at yourschool.somahub.top, with a menu built from your visible sections.</li>
      <li><strong>Apply for admission</strong> on a form with a consent tick. You see the application in your dashboard.</li>
      <li><strong>Check results</strong> by entering the admission number plus the child's name or date of birth. Both are needed so no one can browse by guessing numbers.</li>
      <li><strong>Full report</strong> with results across terms, attendance, class position, trends and fees.</li>
      <li><strong>Contact form and reviews</strong>, each with a consent tick.</li>
    </ul>
    <p>The enrollment form, results check and report card pages share your school's name, colours and menu.</p>

    <h2 id="verification"><span class="material-symbols-outlined">verified</span>7. Verification</h2>
    <p>Your site is public immediately, but every school must be <strong>verified within 7 days of first logging in</strong>, whoever signed up. Verification is done on the <strong>Verification</strong> page under Account and has two steps:</p>
    <ol>
      <li><strong>School agreement.</strong> Upload a signed and stamped copy of your Somahub agreement.</li>
      <li><strong>Your ID.</strong> Your National ID number and a photo of the front. It is kept private and used only to check who is responsible for the account.</li>
    </ol>
    <p>You get reminders in the bell and by email as the deadline nears. A verified school shows a small badge by its name.</p>
    <div class="warn"><span class="material-symbols-outlined">warning</span><div>If 7 days pass without verification, visitors see a "temporarily unavailable" page until it is done. You, as the owner, can still see your site, with a red bar that says it is offline to the public. If it looks live on your laptop but locked on a phone, this is why.</div></div>

    <h2 id="plans"><span class="material-symbols-outlined">workspace_premium</span>8. Trial, Free and Premium</h2>
    <table>
      <tr><th>Plan</th><th>Cost</th><th>What you get</th></tr>
      <tr><td><strong>60-day trial</strong></td><td>KSh 0, starts automatically</td><td>Everything: the full site, online enrollment, results checking, the full parent report and fees.</td></tr>
      <tr><td><strong>Free</strong></td><td>KSh 0 forever</td><td>The full website with all core pages, your free address, and the editing dashboard.</td></tr>
      <tr><td><strong>Premium</strong></td><td>KSh 3,000 a year (about KSh 750 a term)</td><td>Everything the trial unlocks, permanently.</td></tr>
    </table>
    <p>When the trial ends without an upgrade, your site keeps running on Free. The Fees, Enrollment form and Results lookup sections quietly drop off the public site, and your data is kept. Upgrade from <strong>Account, Upgrade</strong> and they come straight back. <strong>Invoices</strong> lists your receipts.</p>

    <h2 id="domain"><span class="material-symbols-outlined">language</span>9. Domain and add-ons</h2>
    <ul>
      <li><strong>Custom domain</strong> (such as yourschool.top): KSh 900 a year. A .co.ke domain: KSh 1,800 a year. We handle registration, renewals and DNS.</li>
      <li><strong>Content writing:</strong> KSh 1,500 once. We write your About, Academics and Admissions text for you.</li>
      <li><strong>Google Business Profile setup:</strong> KSh 1,200 once, so your school shows on Google Maps and local search.</li>
      <li><strong>Fully custom design:</strong> from KSh 10,000.</li>
    </ul>
    <p>See <a href="pricing.php">Pricing</a> for the current list.</p>

    <h2 id="account"><span class="material-symbols-outlined">manage_accounts</span>10. Your account</h2>
    <p>Open <strong>Account, Account settings</strong>.</p>
    <ul>
      <li><strong>Profile.</strong> Change your name, phone, picture and your role at the school.</li>
      <li><strong>Login email.</strong> To change it, enter the new address and your password. We email a confirmation link to the new address (valid for 1 hour) and warn the old one. Nothing changes until you click the link. This keeps your account safe if someone else gets hold of your session.</li>
      <li><strong>Password.</strong> Change it any time. If you forget it, use <strong>Forgot password</strong> on the login page to get a reset code by email.</li>
      <li><strong>Notifications.</strong> Click the bell. Read ones stay quiet and come back only when something new needs your attention.</li>
      <li><strong>Remove my account.</strong> You can request removal and we will follow up.</li>
    </ul>

    <h2 id="speed"><span class="material-symbols-outlined">speed</span>11. Keeping your site fast</h2>
    <ul>
      <li>Photos you upload are resized and compressed for you, so even phone photos stay light.</li>
      <li>Pictures below the top of a page load only as a visitor scrolls to them.</li>
      <li>Aim for clear, well-lit photos rather than many photos. Twelve good gallery photos beat sixty similar ones.</li>
      <li>Use the layout chips to pick layouts that suit your content. A section with no photos looks better in a text layout.</li>
    </ul>

    <h2 id="help"><span class="material-symbols-outlined">help</span>12. Troubleshooting</h2>
    <details><summary>My site shows "temporarily unavailable" to visitors</summary><p>Your 7-day verification window has passed. Complete both steps on the Verification page and the site returns once we confirm.</p></details>
    <details><summary>I can't log in</summary><p>Use the email you signed up with. If you have forgotten the password, choose Forgot password on the <a href="dashboard/login.php">login page</a>. If you signed in by a one-time link, set a password under Account settings.</p></details>
    <details><summary>The web address I wanted is taken</summary><p>Addresses are first come, first served. Try adding your town or an abbreviation. You can have a custom domain later.</p></details>
    <details><summary>Fees, enrollment or results disappeared from my site</summary><p>Your 60-day trial ended and the school is on Free. Upgrade to Premium and they reappear with all your data.</p></details>
    <details><summary>Parents can't find their child's results</summary><p>Check that the admission number and the child's name or date of birth in your CSV match what the parent types. Both are required.</p></details>
    <details><summary>A section isn't showing on my live site</summary><p>Sections are hidden when switched off, when a premium section is locked, or when they have nothing to show yet (an empty gallery, staff list or FAQ). Add content or switch it on from the Website page.</p></details>
    <details><summary>I need to change my email</summary><p>Use Account settings, Login email. You will get a confirmation link at the new address.</p></details>

    <div class="cta">
      <h2 style="justify-content:center;color:var(--sand);margin-top:0;">Ready to start?</h2>
      <p>Your school can be online in minutes, with 60 days of everything free.</p>
      <a class="btn" href="get-started.php">Create my school site</a>
      <a class="btn" style="background:transparent;color:var(--sand);border:2px solid #BFD8D9;" href="https://wa.me/<?= $wa ?>?text=<?= urlencode('Hi Somahub, I have a question.') ?>">Ask us on WhatsApp</a>
    </div>
  </article>
</div>
<?php
$SOMAHUB_CHAT_CONTEXT = 'marketing';
include __DIR__ . '/_chat_widget.php';
?>
</body>
</html>
