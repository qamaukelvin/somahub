<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/mailer.php';
$db = get_db();

// Premade templates — {name} and {school} get substituted before sending.
// cta_text/cta_link are optional; leave both blank for no button.
$templates = [
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
        'label' => 'Upgrade prompt (Free to Paid)',
        'subject' => 'Unlock more for {school}\'s website',
        'body' => "Hi {name},\n\nHope the website has been useful so far. Wanted to check in about our Paid plan (KSh 2,500/year, first term free), which adds:\n\n- Online enrollment applications\n- Term results checking for parents\n- Published fee structure\n\nThese tend to save admin offices real time, especially around enrollment and results season.\n\nBest,\nSomahub",
        'cta_text' => 'See Full Pricing',
        'cta_link' => 'https://somahub.top/pricing.php',
    ],
    'verification_nudge' => [
        'label' => 'Verification nudge',
        'subject' => 'Get {school} verified on Somahub',
        'body' => "Hi {name},\n\nOne last step to complete your school's setup — verification.\n\nA verified badge on your page confirms to parents and the public that {school}'s site is genuine and managed by an authorized representative.\n\nYou'll need to upload a signed agreement and your ID as the representative managing the account.\n\nBest,\nSomahub",
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

$schools = $db->query("SELECT name, phone, email FROM schools ORDER BY name")->fetchAll();

$sentResult = null;
$waLink = null;

$recipientName = $_POST['recipient_name'] ?? '';
$schoolName = $_POST['school_name'] ?? '';
$recipientEmail = $_POST['recipient_email'] ?? '';
$recipientPhone = $_POST['recipient_phone'] ?? '';
$templateKey = $_POST['template'] ?? 'custom';
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';
$ctaText = $_POST['cta_text'] ?? '';
$ctaLink = $_POST['cta_link'] ?? '';

function fill_placeholders(string $text, string $name, string $school): string {
    return str_replace(['{name}', '{school}'], [$name ?: 'there', $school ?: 'your school'], $text);
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
    $finalSubject = fill_placeholders($subject, $recipientName, $schoolName);
    $finalMessage = fill_placeholders($message, $recipientName, $schoolName);
    $finalCtaLink = fill_placeholders($ctaLink, $recipientName, $schoolName);

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

function applyTemplate() {
    const key = document.getElementById('template').value;
    const t = templates[key];
    if (!t) return;
    document.getElementById('subject').value = t.subject;
    document.getElementById('message').value = t.body;
    document.getElementById('cta_text').value = t.cta_text || '';
    document.getElementById('cta_link').value = t.cta_link || '';
}

function fillFromSchool(select) {
    if (!select.value) return;
    const s = JSON.parse(select.value);
    document.getElementById('school_name').value = s.name || '';
    document.getElementById('recipient_email').value = s.email || '';
    document.getElementById('recipient_phone').value = s.phone || '';
}
</script>

<?php include __DIR__ . '/_chat_widget.php'; ?>
</body>
</html>
