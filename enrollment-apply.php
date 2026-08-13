<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/plan.php';
$db = get_db();

$slug = $_GET['school'] ?? '';
$schoolStmt = $db->prepare("
    SELECT s.id, s.name, s.slug, s.plan, s.promo_ends_at, s.email, s.accent_override, s.primary_override, s.bg_override, t.css_variables_json
    FROM schools s
    JOIN themes t ON t.id = s.theme_id
    WHERE s.slug = ?
");
$schoolStmt->execute([$slug]);
$school = $schoolStmt->fetch();

if (!$school) {
    http_response_code(404);
    die('School not found.');
}

if (is_premium_locked($school)) {
    http_response_code(403);
    die('Online applications are not currently available for this school. Please contact the school directly to apply.');
}

$theme = json_decode($school['css_variables_json'], true);
if (!empty($school['accent_override'])) $theme['accent'] = $school['accent_override'];
if (!empty($school['primary_override'])) $theme['primary'] = $school['primary_override'];
if (!empty($school['bg_override'])) $theme['bg'] = $school['bg_override'];

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("
        INSERT INTO enrollment_applications
        (school_id, child_name, date_of_birth, grade_applying_for, previous_school, parent_name, parent_phone, parent_email)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $school['id'],
        trim($_POST['child_name']),
        $_POST['date_of_birth'],
        trim($_POST['grade_applying_for']),
        trim($_POST['previous_school'] ?? ''),
        trim($_POST['parent_name']),
        trim($_POST['parent_phone']),
        trim($_POST['parent_email'] ?? ''),
    ]);

    // Notify the school directly so they don't need to log in just to know a new application arrived
    if (!empty($school['email'])) {
        $body = "
            <h2 style='color:#0F5257;margin-top:0;'>New Enrollment Application</h2>
            <p>A new application was submitted through your website for <strong>" . htmlspecialchars($school['name']) . "</strong>.</p>
            <table style='width:100%;font-size:14px;margin:16px 0;'>
                <tr><td style='color:#6E6A5C;padding:4px 0;'>Child's Name</td><td><strong>" . htmlspecialchars(trim($_POST['child_name'])) . "</strong></td></tr>
                <tr><td style='color:#6E6A5C;padding:4px 0;'>Grade</td><td>" . htmlspecialchars(trim($_POST['grade_applying_for'])) . "</td></tr>
                <tr><td style='color:#6E6A5C;padding:4px 0;'>Parent</td><td>" . htmlspecialchars(trim($_POST['parent_name'])) . "</td></tr>
                <tr><td style='color:#6E6A5C;padding:4px 0;'>Phone</td><td>" . htmlspecialchars(trim($_POST['parent_phone'])) . "</td></tr>
            </table>
            <p>Log into your dashboard to view the full application and respond.</p>
        ";
        send_somahub_email($school['email'], "New application for {$school['name']}", $body);
    }

    $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apply for Admission — <?= htmlspecialchars($school['name']) ?></title>
<link rel="canonical" href="https://<?= htmlspecialchars($school['slug']) ?>.somahub.top/enrollment-apply.php?school=<?= htmlspecialchars($school['slug']) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=<?= urlencode($theme['font_display'] ?? 'Sora') ?>:wght@600;700&family=<?= urlencode($theme['font_body'] ?? 'Nunito Sans') ?>:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{ --primary: <?= htmlspecialchars($theme['primary'] ?? '#1B4D3E') ?>; --accent: <?= htmlspecialchars($theme['accent'] ?? '#F2B705') ?>; --bg: <?= htmlspecialchars($theme['bg'] ?? '#FBF8F2') ?>; }
  *{box-sizing:border-box;}
  body{font-family:'<?= htmlspecialchars($theme['font_body'] ?? 'Nunito Sans') ?>',sans-serif;background:var(--bg);margin:0;padding:0;color:#1B1B18;}
  .school-header{background:var(--primary);padding:16px 24px;text-align:center;}
  .school-brand{color:var(--bg);font-weight:700;font-size:1rem;text-decoration:none;}
  h1{font-family:'<?= htmlspecialchars($theme['font_display'] ?? 'Sora') ?>',sans-serif;font-size:1.2rem;color:var(--primary);margin-bottom:20px;}
  .card{max-width:480px;margin:32px auto 24px;background:#fff;border-radius:10px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,0.06);box-sizing:border-box;width:calc(100% - 32px);}
  label{display:block;font-size:0.85rem;margin-bottom:6px;font-weight:600;}
  input{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:16px;box-sizing:border-box;}
  button{width:100%;padding:12px;background:var(--primary);color:var(--bg);border:none;border-radius:6px;font-weight:700;cursor:pointer;}
  .success{background:#E4F0E7;color:#1B4D3E;padding:16px;border-radius:6px;text-align:center;}
</style>
</head>
<body>
<header class="school-header">
  <a href="site.php?school=<?= urlencode($school['slug']) ?>" class="school-brand"><?= htmlspecialchars($school['name']) ?></a>
</header>
<div class="card">
  <?php if ($success): ?>
    <div class="success">
      <strong>Application submitted!</strong><br>
      <?= htmlspecialchars($school['name']) ?> will contact you shortly.
    </div>
  <?php else: ?>
    <h1>Apply for Admission — <?= htmlspecialchars($school['name']) ?></h1>
    <form method="POST">
      <label>Child's Full Name</label>
      <input type="text" name="child_name" required>
      <label>Date of Birth</label>
      <input type="date" name="date_of_birth" required>
      <label>Grade Applying For</label>
      <input type="text" name="grade_applying_for" placeholder="e.g. Grade 3" required>
      <label>Previous School (if transferring)</label>
      <input type="text" name="previous_school">
      <label>Parent / Guardian Name</label>
      <input type="text" name="parent_name" required>
      <label>Parent / Guardian Phone</label>
      <input type="tel" name="parent_phone" required>
      <label>Parent / Guardian Email (optional)</label>
      <input type="email" name="parent_email">
      <button type="submit">Submit Application</button>
    </form>
  <?php endif; ?>
</div>
<?php
$SOMAHUB_CHAT_CONTEXT = 'school';
$SOMAHUB_CHAT_SCHOOL_NAME = $school['name'];
$SOMAHUB_CHAT_SCHOOL_SLUG = $school['slug'];
include __DIR__ . '/_chat_widget.php';
?>
</body>
</html>