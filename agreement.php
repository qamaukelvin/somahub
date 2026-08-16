<?php
require_once __DIR__ . '/config/db.php';
$db = get_db();

// Optional: pass ?school=slug to pre-fill the school name on the printed copy
$slug = $_GET['school'] ?? '';
$schoolName = '';
if ($slug) {
    $stmt = $db->prepare("SELECT name FROM schools WHERE slug = ?");
    $stmt->execute([$slug]);
    $schoolName = $stmt->fetchColumn() ?: '';
}
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
  .sig-row{display:flex;gap:40px;margin-top:40px;flex-wrap:wrap;}
  .sig-block{flex:1;min-width:220px;}
  .sig-line{border-bottom:1px solid #333;height:40px;margin-bottom:6px;}
  .print-btn{background:#0F5257;color:#fff;border:none;padding:10px 20px;border-radius:6px;font-weight:700;cursor:pointer;margin-top:24px;}
  @media print { .print-btn, .no-print { display:none; } body{background:#fff;} }
</style>
</head>
<body>
<div class="wrap">
  <div class="doc">
    <div class="doc-header">
      <div class="brand">● somahub</div>
      <div style="font-size:0.85rem;color:#666;">somahub.top</div>
    </div>

    <h1>Website Hosting Agreement</h1>
    <p style="color:#666;font-size:0.85rem;">Between Somahub and: <span class="fill"><?= htmlspecialchars($schoolName) ?>&nbsp;</span></p>

    <!--
      ⚠️ KELVIN — replace the placeholder clauses below with your actual
      agreed terms. This is a starting structure only, not legal advice.
      Consider having a lawyer review the final version once you're
      registered, especially the liability and data sections.
    -->

    <h2>1. Services Provided</h2>
    <p>Somahub agrees to build, host, and maintain a website for the school ("the Site") at the subdomain <span class="fill">&nbsp;</span>.somahub.top, including the sections and features agreed upon at signup.</p>

    <h2>2. Plan &amp; Fees</h2>
    <p>The school is enrolled on the <span class="fill">&nbsp;</span> plan. Free plan sites incur no cost. Paid plan features (online enrollment, results checking, fee publishing) are billed at the rate published on somahub.top/pricing.php, payable as described at checkout.</p>

    <h2>3. Content Ownership</h2>
    <p>All text, photos, and information submitted by the school remain the property of the school. Somahub does not claim ownership of school-submitted content and will remove it upon request, subject to Section 5.</p>

    <h2>4. Verification</h2>
    <p>To confirm the site is managed by an authorized school representative, the school agrees to provide a signed copy of this agreement and a valid form of identification for the representative managing the account. New sites are publicly visible for a limited preview period from creation; if verification is not completed within that period, the Site will be taken offline until verification is finished.</p>

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
        <p style="font-size:0.85rem;color:#666;">Full Name &amp; Title</p>
      </div>
    </div>

    <button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>
  </div>
</div>
</body>
</html>
