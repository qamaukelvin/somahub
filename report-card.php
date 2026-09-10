<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/plan.php';
require_once __DIR__ . '/includes/appearance.php';
$db = get_db();

$slug = $_GET['school'] ?? '';
if (!$slug) { header('Location: results-portal.php'); exit; }

$schoolStmt = $db->prepare("SELECT * FROM schools WHERE slug = ?");
$schoolStmt->execute([$slug]);
$school = $schoolStmt->fetch();

if (!$school) { http_response_code(404); die('School not found.'); }
if (is_premium_locked($school)) {
    http_response_code(403);
    die('This feature is not currently available for this school. Please contact the school directly.');
}

$theme = resolve_school_appearance($db, $school)['theme'];

$report = null;
$studentName = '';
$studentGrade = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admissionNo = trim($_POST['admission_no'] ?? '');
    $secondFactor = trim($_POST['second_factor'] ?? '');

    if (!$admissionNo || !$secondFactor) {
        $error = 'Please enter both fields.';
    } else {
        // All terms of results, oldest to newest, so trends read left-to-right naturally
        $resultsStmt = $db->prepare("
            SELECT r.*, u.term_label, u.uploaded_at
            FROM result_rows r JOIN result_uploads u ON u.id = r.result_upload_id
            WHERE r.school_id = ? AND r.admission_no = ?
              AND (LOWER(r.student_name) = LOWER(?) OR r.date_of_birth = ?)
            ORDER BY u.uploaded_at ASC
        ");
        $resultsStmt->execute([$school['id'], $admissionNo, $secondFactor, $secondFactor]);
        $results = $resultsStmt->fetchAll();

        if (!$results) {
            $error = 'No matching records found. Please check the admission number and name or date of birth entered.';
        } else {
            $studentName = $results[0]['student_name'];
            $studentGrade = $results[count($results) - 1]['grade']; // most recent grade

            // Attendance for the same student, all terms
            $attStmt = $db->prepare("
                SELECT a.*, u.term_label, u.uploaded_at
                FROM attendance_rows a JOIN attendance_uploads u ON u.id = a.attendance_upload_id
                WHERE a.school_id = ? AND a.admission_no = ?
                ORDER BY u.uploaded_at ASC
            ");
            $attStmt->execute([$school['id'], $admissionNo]);
            $attendance = $attStmt->fetchAll();
            $attendanceByTerm = [];
            foreach ($attendance as $a) { $attendanceByTerm[$a['term_label']] = $a; }

            // Build subject trend table: subject => [term_label => score]
            $allTerms = [];
            $subjectTrend = [];
            foreach ($results as $r) {
                $allTerms[$r['term_label']] = true;
                $scores = json_decode($r['scores_json'], true) ?: [];
                foreach ($scores as $subject => $score) {
                    $subjectTrend[$subject][$r['term_label']] = $score;
                }
            }
            $allTerms = array_keys($allTerms);

            // Fee structure for this student's current grade, if published
            $feesStmt = $db->prepare("SELECT * FROM fee_structures WHERE school_id = ? AND grade = ? ORDER BY term_label");
            $feesStmt->execute([$school['id'], $studentGrade]);
            $fees = $feesStmt->fetchAll();

            $report = [
                'results' => $results,
                'attendance_by_term' => $attendanceByTerm,
                'all_terms' => $allTerms,
                'subject_trend' => $subjectTrend,
                'fees' => $fees,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Full Report - <?= htmlspecialchars($school['name']) ?></title>
<link rel="canonical" href="https://<?= htmlspecialchars($school['slug']) ?>.somahub.top/report-card.php?school=<?= htmlspecialchars($school['slug']) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=<?= urlencode($theme['font_display'] ?? 'Sora') ?>:wght@600;700&family=<?= urlencode($theme['font_body'] ?? 'Nunito Sans') ?>:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{ --primary: <?= htmlspecialchars($theme['primary'] ?? '#1B4D3E') ?>; --accent: <?= htmlspecialchars($theme['accent'] ?? '#F2B705') ?>; --bg: <?= htmlspecialchars($theme['bg'] ?? '#FBF8F2') ?>; }
  *{box-sizing:border-box;}
  body{font-family:'<?= htmlspecialchars($theme['font_body'] ?? 'Nunito Sans') ?>',sans-serif;background:var(--bg);margin:0;padding:0;color:#1B1B18;}
  h1{font-family:'<?= htmlspecialchars($theme['font_display'] ?? 'Sora') ?>',sans-serif;font-size:1.2rem;color:var(--primary);margin-bottom:6px;}
  .school-header{background:var(--primary);padding:16px 24px;text-align:center;}
  .school-brand{color:var(--bg);font-weight:700;font-size:1rem;text-decoration:none;}
  .card{max-width:640px;margin:32px auto 24px;background:#fff;border-radius:10px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,0.06);box-sizing:border-box;width:calc(100% - 32px);}
  p.sub{color:#666;font-size:0.85rem;margin-bottom:20px;}
  label{display:block;font-size:0.85rem;margin-bottom:6px;font-weight:600;}
  input{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:16px;box-sizing:border-box;}
  button{width:100%;padding:12px;background:var(--primary);color:var(--bg);border:none;border-radius:6px;font-weight:700;cursor:pointer;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.85rem;}
  .back{display:block;text-align:center;margin-top:16px;font-size:0.82rem;color:#888;}

  .report-section{margin-top:28px;border-top:2px solid var(--accent);padding-top:20px;}
  .report-section h3{color:var(--primary);margin-bottom:10px;font-size:1rem;}
  table.report-table{width:100%;border-collapse:collapse;font-size:0.85rem;}
  table.report-table th{text-align:left;padding:6px 8px;background:#F4F1E6;font-size:0.75rem;text-transform:uppercase;color:#666;}
  table.report-table td{padding:6px 8px;border-bottom:1px solid #eee;}
  table.report-table td.num{text-align:right;}
  .term-summary{background:#F9F7F1;border-radius:8px;padding:14px 16px;margin-bottom:10px;}
  .term-summary .term-title{font-weight:700;color:var(--primary);margin-bottom:6px;}
  .print-btn{background:var(--accent);color:#1B1B18;margin-top:20px;}
  @media print { .no-print, header, .print-btn { display:none; } body{background:#fff;} .card{box-shadow:none;} }
</style>
</head>
<body>
<?php $navRoot = '.'; include __DIR__ . '/_public_nav.php'; ?>
<header class="school-header no-print">
  <a href="site.php?school=<?= urlencode($school['slug']) ?>" class="school-brand"><?= htmlspecialchars($school['name']) ?></a>
</header>

<div class="card">
  <h1><?= htmlspecialchars($school['name']) ?> — Full Report</h1>

  <?php if (!$report): ?>
    <p class="sub">Enter your child's admission number and full name (or date of birth) to view their full report — results across all terms, attendance, class position, trends, and current fee structure.</p>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <label>Admission Number</label>
      <input type="text" name="admission_no" required value="<?= htmlspecialchars($_POST['admission_no'] ?? '') ?>">
      <label>Student's Full Name or Date of Birth (YYYY-MM-DD)</label>
      <input type="text" name="second_factor" required value="<?= htmlspecialchars($_POST['second_factor'] ?? '') ?>">
      <button type="submit">View Full Report</button>
    </form>

  <?php else: ?>
    <p class="sub"><?= htmlspecialchars($studentName) ?> · Grade <?= htmlspecialchars($studentGrade) ?></p>

    <div class="report-section">
      <h3>Results by Term</h3>
      <?php foreach ($report['results'] as $r): $scores = json_decode($r['scores_json'], true) ?: []; ?>
        <div class="term-summary">
          <div class="term-title"><?= htmlspecialchars($r['term_label']) ?></div>
          <table class="report-table">
            <?php foreach ($scores as $subject => $score): ?>
              <tr><td><?= htmlspecialchars($subject) ?></td><td class="num"><?= htmlspecialchars($score) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($r['total']): ?><tr><td><strong>Total</strong></td><td class="num"><strong><?= htmlspecialchars($r['total']) ?></strong></td></tr><?php endif; ?>
            <?php if ($r['position_in_class']): ?><tr><td>Position in Class</td><td class="num"><?= htmlspecialchars($r['position_in_class']) ?></td></tr><?php endif; ?>
          </table>
          <?php if (isset($report['attendance_by_term'][$r['term_label']])): $a = $report['attendance_by_term'][$r['term_label']]; ?>
            <table class="report-table" style="margin-top:8px;">
              <?php if ($a['days_present'] !== null): ?><tr><td>Days Present</td><td class="num"><?= (int)$a['days_present'] ?></td></tr><?php endif; ?>
              <?php if ($a['days_absent'] !== null): ?><tr><td>Days Absent</td><td class="num"><?= (int)$a['days_absent'] ?></td></tr><?php endif; ?>
              <?php if ($a['days_late'] !== null): ?><tr><td>Days Late</td><td class="num"><?= (int)$a['days_late'] ?></td></tr><?php endif; ?>
            </table>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (count($report['all_terms']) > 1 && $report['subject_trend']): ?>
    <div class="report-section">
      <h3>Subject Trends</h3>
      <table class="report-table">
        <tr><th>Subject</th><?php foreach ($report['all_terms'] as $t): ?><th style="text-align:right;"><?= htmlspecialchars($t) ?></th><?php endforeach; ?></tr>
        <?php foreach ($report['subject_trend'] as $subject => $byTerm): ?>
          <tr>
            <td><?= htmlspecialchars($subject) ?></td>
            <?php foreach ($report['all_terms'] as $t): ?>
              <td class="num"><?= htmlspecialchars($byTerm[$t] ?? '—') ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($report['fees']): ?>
    <div class="report-section">
      <h3>Fee Structure — Grade <?= htmlspecialchars($studentGrade) ?></h3>
      <table class="report-table">
        <tr><th>Term</th><th style="text-align:right;">Amount</th></tr>
        <?php foreach ($report['fees'] as $f): ?>
          <tr><td><?= htmlspecialchars($f['term_label']) ?></td><td class="num">KSh <?= number_format($f['amount'], 2) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>
  <?php endif; ?>

  <a href="site.php?school=<?= urlencode($school['slug']) ?>" class="back no-print">&larr; Back to <?= htmlspecialchars($school['name']) ?>'s website</a>
</div>
<?php
$SOMAHUB_CHAT_CONTEXT = 'school';
$SOMAHUB_CHAT_SCHOOL_NAME = $school['name'];
$SOMAHUB_CHAT_SCHOOL_SLUG = $school['slug'];
include __DIR__ . '/_chat_widget.php';
?>
</body>
</html>
