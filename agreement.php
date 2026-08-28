<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$db = get_db();

$slug = $_GET['school'] ?? '';
$school = null;
if ($slug) {
    $stmt = $db->prepare("SELECT * FROM schools WHERE slug = ?");
    $stmt->execute([$slug]);
    $school = $stmt->fetch();
}

// Prefilled + gated mode only applies when the logged-in user actually
// owns this school. Anyone else (e.g. someone who followed a generic
// outreach link before logging in) sees the blank fillable version below.
$viewer = current_user();
$isOwnerViewing = $school && $viewer && in_array($viewer['role'] ?? '', ['school_owner', 'school_editor'], true) && $viewer['school_id'] == $school['id'];

$repName = '';
$idNumber = '';
$missing = [];

if ($isOwnerViewing) {
    $userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$viewer['id']]);
    $repUser = $userStmt->fetch();
    $repName = $repUser['name'] ?? '';
    $idNumber = $repUser['id_number'] ?? '';

    if (empty($school['name'])) $missing[] = 'school name';
    if (empty($school['slug'])) $missing[] = 'subdomain';
    if (empty($school['county'])) $missing[] = 'county';
    if (empty($repName)) $missing[] = 'representative name';
    if (empty($idNumber)) $missing[] = 'ID number';
    if (empty($repUser['id_document_path'])) $missing[] = 'uploaded ID document';
}

$canPrint = $isOwnerViewing && !$missing;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Somahub Website Agreement</title>
<style>
  body{font-family:Arial,sans-serif;background:#F7F2E7;margin:0;color:#1C1C16;}
  .wrap{max-width:720px;margin:0 auto;padding:32px 20px;}
  .doc{background:#fff;border-radius:10px;padding:40px;line-height:1.7;}
  .doc-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;border-bottom:2px solid #0F5257;padding-bottom:16px;}
  .brand{font-weight:800;color:#0F5257;font-size:20px;}
  h1{color:#0F5257;font-size:1.4rem;margin:0 0 4px;}
  h2{color:#0F5257;font-size:1rem;margin-top:28px;margin-bottom:8px;}
  .fill{display:inline-block;border-bottom:1px solid #999;min-width:220px;padding:0 4px;}
  .fill.filled{border-bottom:1px solid #0F5257;font-weight:700;color:#0F5257;}
  .sig-row{display:flex;gap:40px;margin-top:40px;flex-wrap:wrap;}
  .sig-block{flex:1;min-width:220px;}
  .sig-line{border-bottom:1px solid #333;height:40px;margin-bottom:6px;}
  .print-btn{background:#0F5257;color:#fff;border:none;padding:10px 20px;border-radius:6px;font-weight:700;cursor:pointer;margin-top:24px;}
  .print-btn:disabled{background:#ccc;cursor:not-allowed;}
  .missing-notice{background:#FBE8E4;color:#8C3B2E;padding:14px 18px;border-radius:8px;margin-bottom:24px;font-size:0.9rem;}
  .missing-notice a{color:#8C3B2E;font-weight:700;}
  @media print { .print-btn, .no-print, .somahub-public-header, .missing-notice { display:none; } body{background:#fff;} }
</style>
</head>
<body>
<?php $navRoot = '.'; include __DIR__ . '/_public_nav.php'; ?>
<div class="wrap">
  <div class="doc">
    <div class="doc-header">
      <div class="brand">● somahub</div>
      <div style="font-size:0.85rem;color:#666;">somahub.top</div>
    </div>

    <h1>Website Hosting Agreement</h1>
    <p style="color:#666;font-size:0.85rem;">Between Somahub and: <span class="fill <?= $school ? 'filled' : '' ?>"><?= htmlspecialchars($school['name'] ?? '') ?>&nbsp;</span></p>

    <?php if ($isOwnerViewing && $missing): ?>
      <div class="missing-notice no-print">
        This agreement can't be printed yet — missing: <strong><?= htmlspecialchars(implode(', ', $missing)) ?></strong>.
        Complete these in <a href="dashboard/verify.php">Verification</a> or <a href="dashboard/account.php">Account Settings</a> first.
      </div>
    <?php elseif (!$isOwnerViewing): ?>
      <div class="missing-notice no-print" style="background:#F4F1E6;color:#555;">
        This is a preview copy. Log in to your Somahub dashboard to generate your school's filled-in agreement.
      </div>
    <?php endif; ?>

    <h2>1. Services Provided</h2>
    <p>Somahub agrees to build, host, and maintain a website for the school ("the Site") at the subdomain <span class="fill <?= $school ? 'filled' : '' ?>"><?= $school ? htmlspecialchars($school['slug']) : '' ?>&nbsp;</span>.somahub.top, including the sections and features agreed upon at signup.</p>

    <h2>2. Plan &amp; Fees</h2>
    <p>The school is enrolled on the <span class="fill <?= $school ? 'filled' : '' ?>"><?= $school ? htmlspecialchars(ucfirst($school['plan'] === 'promo_paid' ? 'Trial' : $school['plan'])) : '' ?>&nbsp;</span> plan. Free plan sites incur no cost. Paid plan features (online enrollment, results checking, fee publishing) are billed at the rate published on somahub.top/pricing.php, payable as described at checkout.</p>

    <h2>3. Content Ownership</h2>
    <p>All text, photos, and information submitted by the school remain the property of the school. Somahub does not claim ownership of school-submitted content and will remove it upon request, subject to Section 5.</p>

    <h2>4. Verification</h2>
    <p>To confirm the site is managed by an authorized school representative, the school agrees to provide a signed copy of this agreement and a valid form of identification for the representative managing the account, <?php if ($isOwnerViewing && $idNumber): ?>namely <span class="fill filled"><?= htmlspecialchars($repName) ?>, ID No. <?= htmlspecialchars($idNumber) ?>&nbsp;</span>,<?php else: ?><span class="fill">&nbsp;</span>,<?php endif; ?> located in <span class="fill <?= $school && !empty($school['county']) ? 'filled' : '' ?>"><?= htmlspecialchars($school['county'] ?? '') ?>&nbsp;</span> County. New sites are publicly visible for a limited preview period from creation; if verification is not completed within that period, the Site will be taken offline until verification is finished.</p>

    <h2>5. Term &amp; Termination</h2>
    <p>This agreement remains in effect while the school's account is active. Either party may terminate with written notice. Upon termination, the Site may be taken offline; the school may request an export of their content within <span class="fill">&nbsp;</span> days of termination.</p>

    <h2>6. Accuracy of Information</h2>
    <p>The school representative confirms that all information provided (school name, contact details, admissions/fees information) is accurate and that they are authorized to represent the school named above.</p>

    <div class="sig-row">
      <div class="sig-block">
        <div class="sig-line"></div>
        <p style="font-size:0.85rem;color:#666;">School Representative Signature &amp; Date</p>
      </div>
      <div class="sig-block">
        <div class="sig-line"></div>
        <p style="font-size:0.85rem;color:#666;"><?= $isOwnerViewing && $repName ? htmlspecialchars($repName) : 'Full Name &amp; Title' ?></p>
      </div>
    </div>

    <?php if ($isOwnerViewing): ?>
      <button class="print-btn no-print" onclick="window.print()" <?= $canPrint ? '' : 'disabled title="Complete the missing details above first"' ?>>
        <?= $canPrint ? 'Print / Save as PDF' : 'Complete details above to print' ?>
      </button>
    <?php else: ?>
      <button class="print-btn no-print" onclick="window.print()">Print / Save as PDF (blank copy)</button>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
