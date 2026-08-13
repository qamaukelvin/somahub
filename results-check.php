<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/plan.php';
$db = get_db();

$slug = $_GET['school'] ?? '';

if (!$slug) {
    header('Location: results-portal.php');
    exit;
}

$schoolStmt = $db->prepare("
    SELECT s.id, s.name, s.slug, s.plan, s.promo_ends_at, s.accent_override, s.primary_override, s.bg_override, t.css_variables_json, t.custom_css
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
    die('This feature is not currently available for this school. Please contact the school directly, or use the results method they currently provide.');
}

$theme = json_decode($school['css_variables_json'], true);
if (!empty($school['accent_override'])) $theme['accent'] = $school['accent_override'];
if (!empty($school['primary_override'])) $theme['primary'] = $school['primary_override'];
if (!empty($school['bg_override'])) $theme['bg'] = $school['bg_override'];

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admissionNo = trim($_POST['admission_no'] ?? '');
    $secondFactor = trim($_POST['second_factor'] ?? '');

    if (!$admissionNo || !$secondFactor) {
        $error = 'Please enter both fields.';
    } else {
        $stmt = $db->prepare("
            SELECT r.*, u.term_label
            FROM result_rows r
            JOIN result_uploads u ON u.id = r.result_upload_id
            WHERE r.school_id = ?
              AND r.admission_no = ?
              AND (LOWER(r.student_name) = LOWER(?) OR r.date_of_birth = ?)
            ORDER BY u.uploaded_at DESC
            LIMIT 5
        ");
        $stmt->execute([$school['id'], $admissionNo, $secondFactor, $secondFactor]);
        $results = $stmt->fetchAll();

        if (!$results) {
            $error = 'No matching results found. Please check the admission number and name or date of birth entered.';
        } else {
            $result = $results;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check Results — <?= htmlspecialchars($school['name']) ?></title>
<link rel="canonical" href="https://<?= htmlspecialchars($school['slug']) ?>.somahub.top/results-check.php?school=<?= htmlspecialchars($school['slug']) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=<?= urlencode($theme['font_display'] ?? 'Sora') ?>:wght@600;700&family=<?= urlencode($theme['font_body'] ?? 'Nunito Sans') ?>:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{ --primary: <?= htmlspecialchars($theme['primary'] ?? '#1B4D3E') ?>; --accent: <?= htmlspecialchars($theme['accent'] ?? '#F2B705') ?>; --bg: <?= htmlspecialchars($theme['bg'] ?? '#FBF8F2') ?>; }
  *{box-sizing:border-box;}
  body{font-family:'<?= htmlspecialchars($theme['font_body'] ?? 'Nunito Sans') ?>',sans-serif;background:var(--bg);margin:0;padding:0;color:#1B1B18;}
  h1{font-family:'<?= htmlspecialchars($theme['font_display'] ?? 'Sora') ?>',sans-serif;font-size:1.2rem;color:var(--primary);margin-bottom:6px;}
  .school-header{background:var(--primary);padding:16px 24px;text-align:center;}
  .school-brand{color:var(--bg);font-weight:700;font-size:1rem;text-decoration:none;}
  .card{max-width:480px;margin:32px auto 24px;background:#fff;border-radius:10px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,0.06);box-sizing:border-box;width:calc(100% - 32px);}
  p.sub{color:#666;font-size:0.85rem;margin-bottom:20px;}
  label{display:block;font-size:0.85rem;margin-bottom:6px;font-weight:600;}
  input{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:16px;box-sizing:border-box;}
  button{width:100%;padding:12px;background:var(--primary);color:var(--bg);border:none;border-radius:6px;font-weight:700;cursor:pointer;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.85rem;}
  .result-card{margin-top:24px;border-top:2px solid var(--accent);padding-top:20px;}
  .result-card h3{color:var(--primary);margin-bottom:4px;}
  table{width:100%;border-collapse:collapse;margin-top:10px;}
  td{padding:6px 0;font-size:0.9rem;border-bottom:1px solid #eee;}
  .back{display:block;text-align:center;margin-top:16px;font-size:0.82rem;color:#888;}
</style>
</head>
<body>
<header class="school-header">
  <a href="site.php?school=<?= urlencode($school['slug']) ?>" class="school-brand"><?= htmlspecialchars($school['name']) ?></a>
</header>
<div class="card">
  <h1><?= htmlspecialchars($school['name']) ?></h1>
  <p class="sub">Enter your child's admission number and full name (or date of birth) to view results.</p>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST">
    <label>Admission Number</label>
    <input type="text" name="admission_no" required value="<?= htmlspecialchars($_POST['admission_no'] ?? '') ?>">
    <label>Student's Full Name or Date of Birth (YYYY-MM-DD)</label>
    <input type="text" name="second_factor" required value="<?= htmlspecialchars($_POST['second_factor'] ?? '') ?>">
    <button type="submit">Check Results</button>
  </form>

  <?php if ($result): foreach ($result as $r): $scores = json_decode($r['scores_json'], true); ?>
    <div class="result-card">
      <h3><?= htmlspecialchars($r['student_name']) ?></h3>
      <p class="sub"><?= htmlspecialchars($r['term_label']) ?> · Grade <?= htmlspecialchars($r['grade']) ?></p>
      <table>
        <?php foreach ($scores as $subject => $score): ?>
          <tr><td><?= htmlspecialchars($subject) ?></td><td style="text-align:right;"><?= htmlspecialchars($score) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($r['total']): ?><tr><td><strong>Total</strong></td><td style="text-align:right;"><strong><?= htmlspecialchars($r['total']) ?></strong></td></tr><?php endif; ?>
        <?php if ($r['position_in_class']): ?><tr><td>Position in Class</td><td style="text-align:right;"><?= htmlspecialchars($r['position_in_class']) ?></td></tr><?php endif; ?>
      </table>
    </div>
  <?php endforeach; endif; ?>

  <a href="site.php?school=<?= urlencode($school['slug']) ?>" class="back">&larr; Back to <?= htmlspecialchars($school['name']) ?>'s website</a>
  <a href="results-portal.php" class="back">Checking a different school? Find it here</a>
</div>
<?php
$SOMAHUB_CHAT_CONTEXT = 'school';
$SOMAHUB_CHAT_SCHOOL_NAME = $school['name'];
$SOMAHUB_CHAT_SCHOOL_SLUG = $school['slug'];
include __DIR__ . '/_chat_widget.php';
?>
</body>
</html>
