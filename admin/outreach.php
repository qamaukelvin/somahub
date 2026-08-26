<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/mailer.php';
$db = get_db();

// Premade templates — {name}, {school}, {login_username}, {temp_password}
// get substituted before sending. cta_text/cta_link are optional.
$templates = [
    'intro_who_we_are' => [
        'label' => '1. Intro — who we are + preview',
        'subject' => 'A free website for {school}',
        'body' => "Hi {name},\n\nMy name is Kelvin from Somahub — we build and host free websites for Kenyan schools. Parents can find your school online, see your programs, and reach you directly, and you don't pay anything to get started.\n\nWe've already put together a sample site for {school} so you can see exactly what it looks like before deciding anything.\n\nTake a look below — no commitment, just a preview.\n\nBest,\nKelvin, Somahub",
        'cta_text' => 'Preview Your Site',
        'cta_link' => '',
    ],
    'login_details' => [
        'label' => '2. Login link (after they like the preview)',
        'subject' => 'Log in to make {school}\'s site yours',
        'body' => "Hi {name},\n\nGlad you liked the preview! Tap the link below to log straight in — no password needed:\n\n{magic_link}\n\nOnce you're in, you can update photos, text, and contact details — everything is yours to edit.\n\nBest,\nKelvin, Somahub",
        'cta_text' => 'Log In Now',
        'cta_link' => '{magic_link}',
    ],
    'post_login_orientation' => [
        'label' => '3. How everything works (after first login)',
        'subject' => 'Getting the most out of {school}\'s Somahub account',
        'body' => "Hi {name},\n\nNow that you're logged in, here's a quick rundown:\n\n- Edit your site anytime from the dashboard — no tech skills needed\n- Your site is live now, but ask us about verification to keep it that way long-term\n- You're on a 60-day trial with every feature unlocked, including enrollment, results, and fees\n- Questions anytime — just message us here\n\nBest,\nKelvin, Somahub",
        'cta_text' => 'Go to Dashboard',
        'cta_link' => 'https://somahub.top/dashboard/login.php',
    ],
    'cold_intro' => [
        'label' => 'Cold outreach — first contact',
        'subject' => 'Getting {school} online with Somahub',
        'body' => "Hi {name},\n\nSomahub builds and hosts a free website for your school — parents can find you online, see your programs, and reach you directly.\n\nIt takes a few days to set up. You just review the content and we go live. No design or tech skills needed on your side.\n\nCould you share a few quick details (school name, location, and a contact number) so we can get started?\n\nBest,\nSomahub",
        'cta_text' => 'Reply to Get Started',
        'cta_link' => 'mailto:hello@somahub.top',
    ],
    'sample_ready' => [
        'label' => 'Sample site ready',
        'subject' => '{school}\'s website is ready to preview',
        'body' => "Hi {name},\n\nYour school's website is built and ready for your review.\n\nPlease take a look and check:\n- School details (name, location, contact info) are correct\n- Any photos, staff info, or programs you'd like added\n- General look and feel\n\nThis is a live preview — once you confirm it's good, it's ready to share with parents and the community. Let me know if you'd like any changes, or if you're happy to go live as-is.\n\nBest,\nSomahub",
        'cta_text' => 'View Your Website',
        'cta_link' => '',
    ],
    'follow_up' => [
        'label' => 'Follow-up on quiet lead',
        'subject' => 'Following up — {school}\'s website',
        'body' => "Hi {name},\n\nJust following up on my earlier message about setting up a free website for {school}. No rush at all — just wanted to check if you had any questions, or if you're ready to move forward.\n\nHappy to hop on a call if that's easier.\n\nBest,\nSomahub",
        'cta_text' => 'Reply Now',
        'cta_link' => 'mailto:hello@somahub.top',
    ],
    'upgrade_prompt' => [
        'label' => 'Upgrade prompt (Free to Premium)',
        'subject' => 'Unlock more for {school}\'s website',
        'body' => "Hi {name},\n\nHope the website has been useful so far. Wanted to check in about our Premium plan (KSh 3,000/year), which adds:\n\n- Online enrollment applications\n- Full report: results, attendance, position, trends & fees\n- Every premium theme\n\nThese tend to save admin offices real time, especially around enrollment and results season.\n\nBest,\nSomahub",
        'cta_text' => 'See Full Pricing',
        'cta_link' => 'https://somahub.top/pricing.php',
    ],
    'verification_nudge' => [
        'label' => 'Verification nudge',
        'subject' => 'Get {school} verified on Somahub',
        'body' => "Hi {name},\n\nOne last step to complete your school's setup — verification.\n\nA verified badge on your page confirms to parents and the public that {school}'s site is genuine and managed by an authorized representative.\n\nHere's what you'll need:\n1. Download and sign the agreement: https://somahub.top/agreement.php\n2. Upload the signed copy plus your ID as the representative managing the account\n\nBest,\nSomahub",
        'cta_text' => 'Verify Now',
        'cta_link' => 'https://somahub.top/dashboard/verify.php',
    ],
    'custom' => [
        'label' => 'Custom (write your own)',
        'subject' => '',
        'body' => '',
        'cta_text' => '',
        'cta_link' => '',
    ],
];

$schools = $db->query("SELECT name, phone, email, slug, verification_status FROM schools ORDER BY name")->fetchAll();

$sentResult = null;
$waLink = null;

// Prefill from leads-convert.php's "Go to Outreach" link, or from a POST resubmit
$recipientName = $_POST['recipient_name'] ?? '';
$schoolName = $_POST['school_name'] ?? $_GET['school_name'] ?? '';
$recipientEmail = $_POST['recipient_email'] ?? '';
$recipientPhone = $_POST['recipient_phone'] ?? $_GET['phone'] ?? '';
$magicLink = $_POST['magic_link'] ?? $_GET['magic_link'] ?? '';
$prefillSlug = $_GET['slug'] ?? '';
$templateKey = $_POST['template'] ?? ($prefillSlug ? 'intro_who_we_are' : 'custom');
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';
$ctaText = $_POST['cta_text'] ?? '';
$ctaLink = $_POST['cta_link'] ?? ($prefillSlug ? "https://{$prefillSlug}.somahub.top/" : '');

function fill_placeholders(string $text, string $name, string $school, string $magicLink = ''): string {
    return str_replace(
        ['{name}', '{school}', '{magic_link}'],
        [$name ?: 'there', $school ?: 'your school', $magicLink],
        $text
    );
}

// Renders message text + an optional styled CTA button, ready to pass into email_wrapper()
function build_email_body(string $message, string $ctaText, string $ctaLink): string {
    $html = '<div>' . nl2br(htmlspecialchars($message)) . '</div>';
    if ($ctaText && $ctaLink) {
        $html .= '
        <div style="text-align:center;margin-top:28px;">
          <a href="' . htmlspecialchars($ctaLink) . '" style="display:inline-block;background:#F2A65A;color:#0A3A3E;font-weight:800;padding:13px 28px;border-radius:24px;text-decoration:none;font-size:0.9rem;">' . htmlspecialchars($ctaText) . '</a>
        </div>';
    }
    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $finalSubject = fill_placeholders($subject, $recipientName, $schoolName, $magicLink);
    $finalMessage = fill_placeholders($message, $recipientName, $schoolName, $magicLink);
    $finalCtaLink = fill_placeholders($ctaLink, $recipientName, $schoolName, $magicLink);

    if ($action === 'send_email') {
        if (!$recipientEmail) {
            $sentResult = ['channel' => 'email', 'ok' => false, 'error' => 'No recipient email provided.'];
        } else {
            $bodyHtml = build_email_body($finalMessage, $ctaText, $finalCtaLink);
            // send_somahub_email() wraps this in the branded header/footer (logo mark + tagline) automatically
            $ok = send_somahub_email($recipientEmail, $finalSubject, $bodyHtml);
            $sentResult = ['channel' => 'email', 'ok' => $ok, 'error' => $ok ? '' : 'Send failed — check the error log.'];
        }
    } elseif ($action === 'prep_whatsapp') {
        if (!$recipientPhone) {
            $sentResult = ['channel' => 'whatsapp', 'ok' => false, 'error' => 'No recipient phone provided.'];
        } else {
            $digits = preg_replace('/[^0-9]/', '', $recipientPhone);
            if (str_starts_with($digits, '0')) {
                $digits = '254' . substr($digits, 1);
            }
            // WhatsApp is text-only — no logo/button support, so the CTA link (if any)
            // is appended as a plain line at the end of the message instead.
            $waText = $finalMessage;
            if ($ctaLink) {
                $waText .= "\n\n" . $finalCtaLink;
            }
            $waLink = 'https://wa.me/' . $digits . '?text=' . rawurlencode($waText);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Outreach</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;margin-bottom:16px;box-sizing:border-box;font-family:inherit;font-size:0.88rem;}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  @media(max-width:640px){.row2{grid-template-columns:1fr;}}
  .btn-wa{background:#25D366;}
  .notice-success{background:#E4F5EA;color:#1B4D3E;padding:10px 14px;border-radius:6px;font-size:0.85rem;margin-bottom:16px;}
  .notice-error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;font-size:0.85rem;margin-bottom:16px;}
  .school-pick{margin-bottom:16px;}
  .hint{font-size:0.78rem;color:#888;margin-top:-10px;margin-bottom:16px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <div class="header-row"><h1>Outreach</h1></div>

  <?php if ($sentResult): ?>
    <?php if ($sentResult['ok']): ?>
      <div class="notice-success">✓ <?= $sentResult['channel'] === 'email' ? 'Email sent' : 'Ready' ?> to <?= htmlspecialchars($recipientName ?: 'recipient') ?>.</div>
    <?php else: ?>
      <div class="notice-error"><?= htmlspecialchars($sentResult['error']) ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($waLink): ?>
    <div class="notice-success">
      WhatsApp message ready. <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" class="btn btn-wa">Open in WhatsApp →</a>
    </div>
  <?php endif; ?>

  <form method="POST" class="stacked" style="max-width:640px;">
    <input type="hidden" name="magic_link" id="magic_link" value="<?= htmlspecialchars($magicLink) ?>">
    <?php if ($magicLink): ?>
      <div class="notice-success">Login link ready: <strong><?= htmlspecialchars($magicLink) ?></strong> — use the "Login link" template, or reference <code>{magic_link}</code> in a custom message. One tap, no password to type.</div>
    <?php endif; ?>
    <?php if ($schools): ?>
    <div class="school-pick">
      <label>Quick-fill from existing school (optional)</label>
      <select onchange="fillFromSchool(this)">
        <option value="">— Select a school —</option>
        <?php foreach ($schools as $s): ?>
          <option value="<?= htmlspecialchars(json_encode($s)) ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="row2">
      <div>
        <label>Recipient name</label>
        <input type="text" name="recipient_name" id="recipient_name" value="<?= htmlspecialchars($recipientName) ?>" placeholder="e.g. Jane Wanjiru">
      </div>
      <div>
        <label>School name</label>
        <input type="text" name="school_name" id="school_name" value="<?= htmlspecialchars($schoolName) ?>" placeholder="e.g. Kinangop Pride Primary">
      </div>
    </div>

    <div class="row2">
      <div>
        <label>Email</label>
        <input type="email" name="recipient_email" id="recipient_email" value="<?= htmlspecialchars($recipientEmail) ?>" placeholder="jane@example.com">
      </div>
      <div>
        <label>Phone (for WhatsApp)</label>
        <input type="text" name="recipient_phone" id="recipient_phone" value="<?= htmlspecialchars($recipientPhone) ?>" placeholder="07xxxxxxxx">
      </div>
    </div>

    <label>Template</label>
    <select name="template" id="template" onchange="applyTemplate()">
      <?php foreach ($templates as $key => $t): ?>
        <option value="<?= $key ?>" <?= $templateKey === $key ? 'selected' : '' ?>><?= htmlspecialchars($t['label']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Subject (email only)</label>
    <input type="text" name="subject" id="subject" value="<?= htmlspecialchars($subject) ?>">

    <label>Message</label>
    <textarea name="message" id="message" rows="10"><?= htmlspecialchars($message) ?></textarea>

    <div class="row2">
      <div>
        <label>CTA button text (optional)</label>
        <input type="text" name="cta_text" id="cta_text" value="<?= htmlspecialchars($ctaText) ?>" placeholder="e.g. View Your Website">
      </div>
      <div>
        <label>CTA button link (optional)</label>
        <input type="text" name="cta_link" id="cta_link" value="<?= htmlspecialchars($ctaLink) ?>" placeholder="https://... or mailto:...">
      </div>
    </div>
    <p class="hint">Email shows this as a styled button below the message, wrapped in the Somahub branded header/footer automatically. WhatsApp appends the link as plain text (no buttons on WhatsApp). Use <code>{name}</code> / <code>{school}</code> anywhere, including in the link.</p>

    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      <button type="submit" name="action" value="send_email" class="btn">Send Email</button>
      <button type="submit" name="action" value="prep_whatsapp" class="btn btn-wa">Prepare WhatsApp Message</button>
    </div>
  </form>
</main>

<script>
const templates = <?= json_encode($templates) ?>;
const magicLink = <?= json_encode($magicLink) ?>;

function applyTemplate() {
    const key = document.getElementById('template').value;
    const t = templates[key];
    if (!t) return;
    document.getElementById('subject').value = t.subject;
    document.getElementById('message').value = t.body;
    document.getElementById('cta_text').value = t.cta_text || '';
    document.getElementById('cta_link').value = t.cta_link || '';
}

// Arriving from leads-convert.php with credentials ready — load the intro
// template automatically so the first message is one click away.
<?php if ($prefillSlug && $templateKey === 'intro_who_we_are'): ?>
document.addEventListener('DOMContentLoaded', () => {
    applyTemplate();
    document.getElementById('cta_link').value = <?= json_encode($ctaLink) ?>;
});
<?php endif; ?>

function fillFromSchool(select) {
    if (!select.value) return;
    const s = JSON.parse(select.value);
    document.getElementById('school_name').value = s.name || '';
    document.getElementById('recipient_email').value = s.email || '';
    document.getElementById('recipient_phone').value = s.phone || '';

    // Auto-fill the CTA with a live link to the school's own site — leading
    // with concrete evidence (a real, working preview) is far more convincing
    // in cold outreach than a text description alone.
    if (s.slug) {
        const ctaLinkField = document.getElementById('cta_link');
        const ctaTextField = document.getElementById('cta_text');
        if (!ctaLinkField.value || ctaLinkField.value.includes('somahub.top')) {
            ctaLinkField.value = 'https://' + s.slug + '.somahub.top/';
        }
        if (!ctaTextField.value) {
            ctaTextField.value = s.verification_status === 'verified' ? 'View ' + s.name + "'s Live Site" : 'Preview ' + s.name + "'s Site";
        }
    }
}
</script>

<?php include __DIR__ . '/_chat_widget.php'; ?>
</body>
</html>
