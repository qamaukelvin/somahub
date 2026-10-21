<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/mailer.php';
$db = get_db();
try { $db->exec("CREATE TABLE IF NOT EXISTS outreach_log (id INT AUTO_INCREMENT PRIMARY KEY, channel VARCHAR(12) NOT NULL, recipient_name VARCHAR(150) NOT NULL DEFAULT '', recipient VARCHAR(190) NOT NULL DEFAULT '', school_name VARCHAR(190) NOT NULL DEFAULT '', template VARCHAR(60) NOT NULL DEFAULT '', subject VARCHAR(200) NOT NULL DEFAULT '', ok TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (recipient))"); } catch (Throwable $e) {}
function log_outreach(PDO $db, string $channel, string $name, string $rcpt, string $school, string $tpl, string $subject, bool $ok): void {
    try {
        $db->prepare("INSERT INTO outreach_log (channel, recipient_name, recipient, school_name, template, subject, ok) VALUES (?,?,?,?,?,?,?)")->execute([$channel, $name, $rcpt, $school, $tpl, mb_substr($subject, 0, 200), $ok ? 1 : 0]);
        if ($ok) { // anyone we have contacted stops showing as an untouched new lead
            $db->prepare("UPDATE leads SET status = 'contacted' WHERE status = 'new' AND ((? <> '' AND email = ?) OR (? <> '' AND phone = ?))")->execute([$rcpt, $rcpt, $rcpt, $rcpt]);
        }
    } catch (Throwable $e) { error_log('outreach log: ' . $e->getMessage()); }
}

// Premade templates — {name}, {school}, {login_username}, {temp_password}
// get substituted before sending. cta_text/cta_link are optional.
$templates = [
    'first_contact' => [
        'label' => '1. First contact: free website offer',
        'subject' => 'A free website for {school}',
        'body' => "Hi {name},\n\nI'm Kelvin from Somahub. We build and host websites for Kenyan schools, and the basic site is free.\n\nParents can find {school} online, see your programmes and photos, and contact you directly. You can edit everything yourself from your phone, no tech skills needed.\n\nIf you'd like, I will set up a preview of your site so you can see exactly how it would look. Just reply to this email or WhatsApp me.\n\nKind regards,\nKelvin, Somahub",
        'cta_text' => 'See how it works',
        'cta_link' => 'https://somahub.top/',
    ],
    'preview_ready' => [
        'label' => '2. Preview is ready',
        'subject' => "{school}'s website preview is ready",
        'body' => "Hi {name},\n\nI have built a preview of the website for {school}. Please take a look and tell me what you would like changed: the school details, photos, and anything missing.\n\nIf you like it, I will give you a login so you can edit it yourself and publish it.",
        'cta_text' => 'View the preview',
        'cta_link' => '',
    ],
    'login_details' => [
        'label' => '3. Login link (they liked the preview)',
        'subject' => "Log in to make {school}'s site yours",
        'body' => "Hi {name},\n\nGlad you liked the preview! Use the button below to log in. No password is needed for this first visit.\n\nOnce you're in, click any text or photo on the page to change it. Everything saves by itself.\n\nIf the button does not work, copy this link into your browser:\n{magic_link}",
        'cta_text' => 'Log in to my site',
        'cta_link' => '{magic_link}',
    ],
    'getting_started' => [
        'label' => '4. Getting started tips (after first login)',
        'subject' => "Getting the most out of {school}'s website",
        'body' => "Hi {name},\n\nThanks for logging in. Three things worth doing first:\n\n1. Add your school's photos and write a short welcome.\n2. Check your contact details and map location.\n3. Verify your school so the site stays online. It takes a few minutes.\n\nStuck anywhere? Reply here or message me on WhatsApp and I will help.",
        'cta_text' => 'Open my dashboard',
        'cta_link' => 'https://somahub.top/dashboard/login.php',
    ],
    'follow_up' => [
        'label' => '5. Gentle follow-up',
        'subject' => "Quick follow-up for {school}",
        'body' => "Hi {name},\n\nJust checking in on my earlier message about a free website for {school}. No pressure at all. If now is not a good time, tell me and I will check back later.\n\nIf you have questions, reply here and I will answer them.",
        'cta_text' => 'Reply to Kelvin',
        'cta_link' => 'mailto:hello@somahub.top',
    ],
    'verification_nudge' => [
        'label' => '6. Verification reminder',
        'subject' => "Please verify {school}",
        'body' => "Hi {name},\n\nOne step is left to keep {school}'s website online: verification.\n\nIt confirms to parents that your site is genuine and run by the school. You only need your school agreement and an ID, and it takes a few minutes.",
        'cta_text' => 'Verify my school',
        'cta_link' => 'https://somahub.top/dashboard/verify.php',
    ],
    'upgrade' => [
        'label' => '7. Upgrade to Premium',
        'subject' => "Get more from {school}'s website",
        'body' => "Hi {name},\n\nYour website has been live for a while. Premium (KSh 3,000 a year) adds the tools schools use every day:\n\n- Online enrollment applications\n- Results that parents can check online\n- Attendance and fee structure\n- Every theme\n\nYou can pay by M-Pesa and it is activated as soon as we confirm.",
        'cta_text' => 'See plans',
        'cta_link' => 'https://somahub.top/dashboard/checkout.php',
    ],
    'renewal' => [
        'label' => '8. Premium renewal reminder',
        'subject' => "{school}'s Premium plan is due for renewal",
        'body' => "Hi {name},\n\nA quick reminder that {school}'s Premium plan is coming up for renewal. Renewing keeps enrollment, results, attendance and fees running without a break, and any time you have left is added on top.",
        'cta_text' => 'Renew now',
        'cta_link' => 'https://somahub.top/dashboard/checkout.php',
    ],
    'holiday' => [
        'label' => '9. Holiday greeting',
        'subject' => 'Warm wishes from Somahub',
        'body' => "Hi {name},\n\nWishing you and everyone at {school} a happy and restful holiday. Thank you for being part of Somahub.\n\nKind regards,\nKelvin",
        'cta_text' => '',
        'cta_link' => '',
    ],
    'custom' => [
        'label' => 'Write my own',
        'subject' => '',
        'body' => '',
        'cta_text' => '',
        'cta_link' => '',
    ],
];

$leadPick = []; try { $leadPick = $db->query("SELECT school_name AS name, contact_name, phone, email FROM leads WHERE status <> 'declined' ORDER BY submitted_at DESC LIMIT 100")->fetchAll(); } catch (Throwable $e) {}
$lastContact = []; $recentLog = [];
try {
    foreach ($db->query("SELECT recipient, MAX(created_at) AS t, COUNT(*) AS n FROM outreach_log WHERE ok = 1 GROUP BY recipient")->fetchAll() as $r) $lastContact[strtolower($r['recipient'])] = ['t' => $r['t'], 'n' => (int)$r['n']];
    $recentLog = $db->query("SELECT * FROM outreach_log ORDER BY id DESC LIMIT 12")->fetchAll();
} catch (Throwable $e) {}
$schools = $db->query("SELECT name, phone, email, slug, verification_status FROM schools ORDER BY name")->fetchAll();

$sentResult = null;
$waLink = null;

// Prefill from leads-convert.php's "Go to Outreach" link, or from a POST resubmit
$recipientName = $_POST['recipient_name'] ?? $_GET['name'] ?? '';
$schoolName = $_POST['school_name'] ?? $_GET['school_name'] ?? '';
$recipientEmail = $_POST['recipient_email'] ?? $_GET['email'] ?? '';
$recipientPhone = $_POST['recipient_phone'] ?? $_GET['phone'] ?? '';
$magicLink = $_POST['magic_link'] ?? $_GET['magic_link'] ?? '';
$prefillSlug = $_GET['slug'] ?? '';
$templateKey = $_POST['template'] ?? ($prefillSlug ? 'preview_ready' : (isset($_GET['school_name']) ? 'first_contact' : 'custom'));
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
            log_outreach($db, 'email', $recipientName, $recipientEmail, $schoolName, $templateKey, $finalSubject, $ok);
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
            log_outreach($db, 'whatsapp', $recipientName, $digits, $schoolName, $templateKey, mb_substr($finalMessage, 0, 80), true);
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
  .pvbox{background:#fff;border:1.5px dashed var(--line);border-radius:12px;padding:14px 16px;margin-bottom:16px;}
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
      <label>Quick-fill from a school or lead (optional)</label>
      <select onchange="fillFromSchool(this)">
        <option value="">— Select a school or lead —</option>
        <optgroup label="Schools on Somahub">
        <?php foreach ($schools as $s): ?>
          <option value="<?= htmlspecialchars(json_encode($s)) ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
        </optgroup>
        <?php if ($leadPick): ?><optgroup label="Leads">
        <?php foreach ($leadPick as $l): ?>
          <option value="<?= htmlspecialchars(json_encode(['name' => $l['name'], 'email' => $l['email'], 'phone' => $l['phone'], 'contact' => $l['contact_name']])) ?>"><?= htmlspecialchars($l['name']) ?></option>
        <?php endforeach; ?></optgroup><?php endif; ?>
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

    <div id="lastc" class="hint" style="display:none;color:#8A5A12;background:#FFF1DC;padding:8px 12px;border-radius:8px;margin-top:-4px;"></div>
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

    <div class="pvbox"><strong style="font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);">Preview</strong><div id="pvsub" style="font-weight:800;margin:6px 0;"></div><div id="pvmsg" style="white-space:pre-wrap;font-size:.9rem;line-height:1.55;"></div></div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      <button type="submit" name="action" value="send_email" class="btn">Send Email</button>
      <button type="submit" name="action" value="prep_whatsapp" class="btn btn-wa">Prepare WhatsApp Message</button>
    </div>
  </form>

  <h2 style="margin-top:34px;">Recent outreach</h2>
  <table><thead><tr><th>When</th><th>To</th><th>School</th><th>Channel</th><th>Template</th></tr></thead>
  <?php foreach ($recentLog as $r): ?><tr>
    <td data-label="When"><?= date('d M H:i', strtotime($r['created_at'])) ?></td>
    <td data-label="To"><?= htmlspecialchars($r['recipient_name'] ?: $r['recipient']) ?><br><small><?= htmlspecialchars($r['recipient']) ?></small></td>
    <td data-label="School"><?= htmlspecialchars($r['school_name'] ?: '—') ?></td>
    <td data-label="Channel"><?= $r['channel'] === 'email' ? ($r['ok'] ? 'Email' : 'Email (failed)') : 'WhatsApp' ?></td>
    <td data-label="Template"><?= htmlspecialchars($templates[$r['template']]['label'] ?? $r['template']) ?></td>
  </tr><?php endforeach; if (!$recentLog): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);">Nothing sent yet. Sends from this page are logged here so you never message someone twice by accident.</td></tr><?php endif; ?></table>
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
<?php if ($prefillSlug && $templateKey === 'preview_ready'): ?>
document.addEventListener('DOMContentLoaded', () => {
    applyTemplate();
    document.getElementById('cta_link').value = <?= json_encode($ctaLink) ?>;
});
<?php endif; ?>

function fillFromSchool(select) {
    if (!select.value) return;
    const s = JSON.parse(select.value);
    document.getElementById('school_name').value = s.name || '';
    if (s.contact) document.getElementById('recipient_name').value = s.contact;
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
const lastContact = <?= json_encode($lastContact) ?>;
function refresh() {
    const name = document.getElementById('recipient_name').value, school = document.getElementById('school_name').value;
    const first = name ? name.split(' ')[0] : 'there';
    const fill = t => (t || '').split('{name}').join(first).split('{school}').join(school || 'your school').split('{magic_link}').join(magicLink);
    document.getElementById('pvsub').textContent = fill(document.getElementById('subject').value);
    document.getElementById('pvmsg').textContent = fill(document.getElementById('message').value);
    const em = (document.getElementById('recipient_email').value || '').toLowerCase().trim(), ph = (document.getElementById('recipient_phone').value || '').replace(/[^0-9]/g, '');
    const lc = lastContact[em] || lastContact[ph.replace(/^0/, '254')];
    const box = document.getElementById('lastc');
    if (lc) { const d = Math.max(0, Math.floor((Date.now() - new Date(lc.t.replace(' ', 'T')).getTime()) / 86400000)); box.style.display = 'block'; box.textContent = 'Already contacted ' + lc.n + ' time' + (lc.n > 1 ? 's' : '') + ', last ' + (d === 0 ? 'today' : d + ' day' + (d > 1 ? 's' : '') + ' ago') + '.'; } else box.style.display = 'none';
}
['recipient_name','school_name','recipient_email','recipient_phone','subject','message'].forEach(id => document.getElementById(id).addEventListener('input', refresh));
document.getElementById('template').addEventListener('change', refresh);
const _ft = fillFromSchool; fillFromSchool = function(s) { _ft(s); refresh(); };
document.addEventListener('DOMContentLoaded', () => { <?php if (isset($_GET['school_name']) && !$_POST): ?>applyTemplate();<?php endif; ?> refresh(); });
</script>

<?php include __DIR__ . '/_chat_widget.php'; ?>
</body>
</html>
