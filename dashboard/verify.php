<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

$stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$user['school_id']]);
$school = $stmt->fetch();

$userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$user['id']]);
$currentUser = $userStmt->fetch();

$error = '';
$success = '';

function validate_and_store_file($fileKey, $schoolId, $prefix, &$error) {
    if (empty($_FILES[$fileKey]['tmp_name'])) return null;

    $tmpPath = $_FILES[$fileKey]['tmp_name'];
    $originalName = $_FILES[$fileKey]['name'];
    $fileSize = $_FILES[$fileKey]['size'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($fileSize > 8 * 1024 * 1024) {
        $error = 'File is too large. Maximum size is 8MB.';
        return null;
    }
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
        $error = 'Please upload a PDF, JPG, or PNG file.';
        return null;
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png']) && @getimagesize($tmpPath) === false) {
        $error = 'That file does not appear to be a valid image.';
        return null;
    }

    $destDir = __DIR__ . '/../uploads/schools/' . $schoolId . '/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $safeName = $prefix . '_' . time() . '.' . $ext;
    move_uploaded_file($tmpPath, $destDir . $safeName);
    return 'uploads/schools/' . $schoolId . '/' . $safeName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Signed agreement upload
    if (!empty($_FILES['agreement_file']['tmp_name'])) {
        $relPath = validate_and_store_file('agreement_file', $user['school_id'], 'agreement', $error);
        if ($relPath) {
            $db->prepare("UPDATE schools SET signed_agreement_path = ?, verification_status = 'pending' WHERE id = ?")
               ->execute([$relPath, $user['school_id']]);
            $success = 'Agreement uploaded. ';
            $school['signed_agreement_path'] = $relPath;
            $school['verification_status'] = 'pending';
        }
    }

    // ID number + ID document upload — ties real accountability to the specific person
    if (!$error && (trim($_POST['id_number'] ?? '') || !empty($_FILES['id_document']['tmp_name']))) {
        $idNumber = trim($_POST['id_number'] ?? '');
        $idDocPath = validate_and_store_file('id_document', $user['school_id'], 'idcard', $error);

        if (!$error) {
            if ($idDocPath) {
                $db->prepare("UPDATE users SET id_number = ?, id_document_path = ?, id_verified_at = NULL WHERE id = ?")
                   ->execute([$idNumber ?: $currentUser['id_number'], $idDocPath, $user['id']]);
            } elseif ($idNumber) {
                $db->prepare("UPDATE users SET id_number = ?, id_verified_at = NULL WHERE id = ?")
                   ->execute([$idNumber, $user['id']]);
            }
            $success .= 'ID details saved for review.';
            $userStmt->execute([$user['id']]);
            $currentUser = $userStmt->fetch();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verification</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .status-badge{display:inline-block;padding:5px 14px;border-radius:20px;font-size:0.82rem;font-weight:700;margin-bottom:20px;}
  .status-pending{background:#FBF0D1;color:#8C6D1F;}
  .status-verified{background:#DCEFE1;color:#1B4D3E;}
  .status-rejected{background:#FBE8E4;color:#8C3B2E;}
  .upload-box{background:#fff;border:1px solid #E2DCC6;border-radius:8px;padding:24px;max-width:480px;margin-bottom:24px;}
  .upload-box h3{font-size:1rem;margin-bottom:6px;}
  .upload-box .desc{font-size:0.85rem;color:#666;margin-bottom:16px;}
  .error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  .success{background:#E4F0E7;color:#1B4D3E;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;}
  .current-doc{margin-bottom:18px;font-size:0.88rem;}
  input[type=file], input[type=text]{margin-bottom:14px;width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;}
  label{display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>School Verification</h1>
  <p class="sub">To confirm your school is genuine and that you're authorized to manage its website, please complete both steps below.</p>

  <?php
    $statusLabels = ['pending' => 'Pending Review', 'verified' => 'Verified', 'rejected' => 'Needs Attention'];
    $status = $school['verification_status'] ?? 'pending';
  ?>
  <span class="status-badge status-<?= $status ?>"><?= $statusLabels[$status] ?></span>

  <?php if ($status === 'rejected' && !empty($school['verification_notes'])): ?>
    <div class="error">Note from Somahub: <?= htmlspecialchars($school['verification_notes']) ?></div>
  <?php endif; ?>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="upload-box">
    <h3>1. School Agreement</h3>
    <p class="desc">A signed and stamped copy of your Somahub agreement.</p>
    <p style="margin-bottom:14px;">
      <a href="../agreement.php?school=<?= urlencode($school['slug']) ?>" target="_blank" style="background:#F4F1E6;color:#0F5257;padding:8px 16px;border-radius:6px;font-size:0.85rem;font-weight:700;text-decoration:none;display:inline-block;">📄 Download Agreement to Sign</a>
      <span style="font-size:0.78rem;color:#888;display:block;margin-top:4px;">Print it, sign and stamp it, then scan or photograph it and upload below.</span>
    </p>
    <?php if (!empty($school['signed_agreement_path'])): ?>
      <div class="current-doc">
        Current document: <a href="../<?= htmlspecialchars($school['signed_agreement_path']) ?>" target="_blank">View uploaded file</a>
      </div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="agreement_file" accept=".pdf,.jpg,.jpeg,.png" required>
      <button type="submit" class="btn">Upload Agreement</button>
    </form>
  </div>

  <div class="upload-box">
    <h3>2. Your ID Verification</h3>
    <p class="desc">This confirms who is personally responsible for this account. Your ID is kept private and used only for verification.</p>
    <?php if (!empty($currentUser['id_document_path'])): ?>
      <div class="current-doc">
        ID on file: <?= htmlspecialchars($currentUser['id_number'] ?? '') ?> —
        <a href="../<?= htmlspecialchars($currentUser['id_document_path']) ?>" target="_blank">View uploaded ID</a>
        <?php if ($currentUser['id_verified_at']): ?><br><span style="color:#1B4D3E;">✓ Verified</span><?php endif; ?>
      </div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <label>National ID Number</label>
      <input type="text" name="id_number" value="<?= htmlspecialchars($currentUser['id_number'] ?? '') ?>" placeholder="e.g. 12345678">
      <label>Photo of your National ID (front)</label>
      <input type="file" name="id_document" accept=".pdf,.jpg,.jpeg,.png">
      <button type="submit" class="btn">Save ID Details</button>
    </form>
  </div>
</main>
</body>
</html>
