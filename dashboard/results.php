<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/plan.php';
$user = require_school_login();
$db = get_db();

$schoolStmt = $db->prepare("SELECT plan, promo_ends_at FROM schools WHERE id = ?");
$schoolStmt->execute([$user['school_id']]);
$schoolPlanRow = $schoolStmt->fetch();
if (is_premium_locked($schoolPlanRow)) {
    die('Results checking is a paid-plan feature, or your paid term has ended. <a href="checkout.php">Upgrade now</a> to reactivate.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['results_file']['tmp_name'])) {
    $termLabel = trim($_POST['term_label'] ?? '');
    $tmpPath = $_FILES['results_file']['tmp_name'];
    $origName = $_FILES['results_file']['name'];

    if (!$termLabel) {
        $error = 'Please enter a term label, e.g. "Term 2, 2026".';
    } elseif (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'Please upload a .csv file. Export your Excel sheet as CSV first (File > Save As > CSV).';
    } else {
        // Store the raw file for audit
        $storeDir = __DIR__ . '/../uploads/results/' . $user['school_id'] . '/';
        if (!is_dir($storeDir)) mkdir($storeDir, 0755, true);
        $storedName = 'results_' . time() . '.csv';
        move_uploaded_file($tmpPath, $storeDir . $storedName);
        $storedPath = '/uploads/results/' . $user['school_id'] . '/' . $storedName;

        $uploadStmt = $db->prepare("
            INSERT INTO result_uploads (school_id, uploaded_by_user_id, term_label, original_filename, stored_path, status)
            VALUES (?, ?, ?, ?, ?, 'processing')
        ");
        $uploadStmt->execute([$user['school_id'], $user['id'], $termLabel, $origName, $storedPath]);
        $uploadId = $db->lastInsertId();

        // Parse CSV — expected columns: admission_no,student_name,date_of_birth,grade,subject columns...,total,position
        $rows = array_map('str_getcsv', file($storeDir . $storedName));
        $header = array_map('trim', array_shift($rows));

        $required = ['admission_no', 'student_name'];
        $missing = array_diff($required, $header);

        if ($missing) {
            $error = 'Missing required column(s): ' . implode(', ', $missing) . '. Required columns: admission_no, student_name (plus date_of_birth, grade, subjects, total, position — optional).';
            $db->prepare("UPDATE result_uploads SET status='failed' WHERE id=?")->execute([$uploadId]);
        } else {
            $insertRow = $db->prepare("
                INSERT INTO result_rows (result_upload_id, school_id, admission_no, student_name, date_of_birth, grade, scores_json, total, position_in_class)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $knownCols = ['admission_no','student_name','date_of_birth','grade','total','position'];
            $count = 0;

            foreach ($rows as $row) {
                if (count($row) < 2) continue; // skip blank lines
                $data = array_combine($header, array_pad($row, count($header), ''));

                $scores = [];
                foreach ($data as $col => $val) {
                    if (!in_array($col, $knownCols)) {
                        $scores[$col] = $val;
                    }
                }

                $insertRow->execute([
                    $uploadId,
                    $user['school_id'],
                    trim($data['admission_no']),
                    trim($data['student_name']),
                    !empty($data['date_of_birth']) ? date('Y-m-d', strtotime($data['date_of_birth'])) : null,
                    $data['grade'] ?? null,
                    json_encode($scores),
                    $data['total'] ?? null,
                    $data['position'] ?? null,
                ]);
                $count++;
            }

            $db->prepare("UPDATE result_uploads SET status='ready', row_count=? WHERE id=?")
               ->execute([$count, $uploadId]);
            $success = "Uploaded successfully — $count student records added for $termLabel.";
        }
    }
}

$uploads = $db->prepare("SELECT * FROM result_uploads WHERE school_id=? ORDER BY uploaded_at DESC");
$uploads->execute([$user['school_id']]);
$uploads = $uploads->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Results</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .hint{background:#F4F1E6;padding:14px 16px;border-radius:6px;font-size:0.85rem;color:#555;margin-bottom:20px;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  .success{background:#E4F0E7;color:#1B4D3E;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  input[type=text],input[type=file]{padding:10px;border:1px solid #ccc;border-radius:4px;margin-bottom:14px;width:100%;box-sizing:border-box;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Results</h1>
  <p class="sub">Upload term results so parents can check them online with an admission number.</p>

  <div class="hint">
    <strong>File format:</strong> CSV file with columns <code>admission_no</code>, <code>student_name</code> (required),
    and optionally <code>date_of_birth</code>, <code>grade</code>, subject names, <code>total</code>, <code>position</code>.<br>
    In Excel: File → Save As → CSV (Comma delimited).
  </div>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <label>Term label (e.g. "Term 2, 2026")</label>
    <input type="text" name="term_label" required>
    <label>CSV file</label>
    <input type="file" name="results_file" accept=".csv" required>
    <button type="submit" class="btn">Upload Results</button>
  </form>

  <h3 style="margin-top:36px;">Upload History</h3>
  <table>
    <tr><th>Term</th><th>File</th><th>Students</th><th>Status</th><th>Uploaded</th></tr>
    <?php foreach ($uploads as $u): ?>
    <tr>
      <td data-label="Term"><?= htmlspecialchars($u['term_label']) ?></td>
      <td data-label="File"><?= htmlspecialchars($u['original_filename']) ?></td>
      <td data-label="Students"><?= $u['row_count'] ?? '—' ?></td>
      <td data-label="Status"><?= ucfirst($u['status']) ?></td>
      <td data-label="Uploaded"><?= date('d M Y', strtotime($u['uploaded_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</main>
</body>
</html>
