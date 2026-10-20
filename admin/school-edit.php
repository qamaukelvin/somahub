<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/content-presets.php';
require_platform_admin();
$db = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$id]);
$school = $stmt->fetch();

if (!$school) {
    die('School not found.');
}

$owner = $db->prepare("SELECT * FROM users WHERE school_id = ? AND role = 'school_owner' LIMIT 1");
$owner->execute([$id]);
$owner = $owner->fetch();

require_once __DIR__ . '/../includes/appearance.php';
$palettes = get_active_palettes($db);

$message = '';
$newTempPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action']) && $_POST['action'] === 'apply_content_preset') {
        $presetKey = $_POST['content_preset'] ?? '';
        $contentPresets = get_school_content_presets();
        $presetContent = $contentPresets[$presetKey]['content'] ?? [];

        if ($presetContent) {
            $typeStmt = $db->prepare("SELECT id, schema_json FROM section_types WHERE key_name = ?");
            $sectionStmt = $db->prepare("SELECT content_json FROM site_sections WHERE school_id = ? AND section_type_id = ?");
            $updateStmt = $db->prepare("UPDATE site_sections SET content_json = ? WHERE school_id = ? AND section_type_id = ?");
            $onlyIfEmpty = !empty($_POST['only_if_empty']);

            foreach ($presetContent as $key => $fields) {
                $typeStmt->execute([$key]);
                $type = $typeStmt->fetch();
                if (!$type) continue;

                $sectionStmt->execute([$id, $type['id']]);
                $existing = $sectionStmt->fetch();
                $current = $existing ? json_decode($existing['content_json'], true) : array_fill_keys(array_keys(json_decode($type['schema_json'], true)), '');

                foreach ($fields as $field => $text) {
                    if (!array_key_exists($field, $current)) continue;
                    if ($onlyIfEmpty && trim($current[$field]) !== '') continue; // don't clobber real content already written
                    $current[$field] = str_replace('{school}', $school['name'], $text);
                }
                $updateStmt->execute([json_encode($current), $id, $type['id']]);
            }
            $message = 'Content preset applied.';
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_details') {
        $colorMode = ($_POST['color_mode'] ?? 'preset') === 'custom' ? 'custom' : 'preset';
        $stmt = $db->prepare("
            UPDATE schools
            SET name=?, palette_id=?, color_mode=?, accent_override=?, primary_override=?, secondary_override=?, bg_override=?, plan=?, status=?, promo_ends_at=?, county=?, town=?, phone=?, email=?
            WHERE id=?
        ");
        $stmt->execute([
            trim($_POST['name']),
            $colorMode === 'preset' ? (int)$_POST['palette_id'] : null,
            $colorMode,
            trim($_POST['accent_override']) ?: null,
            trim($_POST['primary_override']) ?: null,
            trim($_POST['secondary_override']) ?: null,
            trim($_POST['bg_override']) ?: null,
            $_POST['plan'],
            $_POST['status'],
            $_POST['promo_ends_at'] ?: null,
            trim($_POST['county']),
            trim($_POST['town']),
            trim($_POST['phone']),
            trim($_POST['email']),
            $id,
        ]);
        $message = 'School details updated.';

        // refresh
        $stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$id]);
        $school = $stmt->fetch();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'review_verification') {
        $newStatus = $_POST['verification_status'];
        $notes = trim($_POST['verification_notes'] ?? '');
        $db->prepare("UPDATE schools SET verification_status = ?, verification_notes = ? WHERE id = ?")
           ->execute([$newStatus, $notes ?: null, $id]);
        $message = 'Verification status updated.';

        $stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$id]);
        $school = $stmt->fetch();

        if (in_array($newStatus, ['verified', 'rejected'], true)) {
            try {
                require_once __DIR__ . '/../includes/notifications.php';
                create_notification($db, (int)$id, 'verification_result',
                    $newStatus === 'verified' ? 'You are verified ✓' : 'Verification needs attention',
                    $newStatus === 'verified' ? 'Your school now shows the Verified badge.' : ($notes ?: 'Please review the feedback and resubmit your documents.'),
                    'verify.php');
            } catch (\Throwable $e) { error_log('verification notify failed: ' . $e->getMessage()); }
        }

        // Notify the school of the outcome — this is the kind of update they shouldn't have to discover by checking their dashboard
        if (!empty($school['email']) && in_array($newStatus, ['verified', 'rejected'])) {
            if ($newStatus === 'verified') {
                $statusBody = "
                    <h2 style='color:#0F5257;margin-top:0;'>You're Verified! ✓</h2>
                    <p><strong>" . htmlspecialchars($school['name']) . "</strong> is now a Verified school on Somahub.</p>
                    <p>Your ✓ Verified badge is now live on your website, giving parents extra confidence it's genuinely your school.</p>
                ";
            } else {
                $statusBody = "
                    <h2 style='color:#8C3B2E;margin-top:0;'>Verification Needs Attention</h2>
                    <p>We reviewed the documents for <strong>" . htmlspecialchars($school['name']) . "</strong> and need something fixed before we can verify your school.</p>
                    " . ($notes ? "<p style='background:#F7F2E7;padding:12px 16px;border-radius:6px;'>" . htmlspecialchars($notes) . "</p>" : "") . "
                    <p>Log into your dashboard's Verification page to re-upload.</p>
                ";
            }
            send_somahub_email($school['email'], "Verification update for {$school['name']}", $statusBody);
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'verify_owner_id' && $owner) {
        $db->prepare("UPDATE users SET id_verified_at = NOW() WHERE id = ?")->execute([$owner['id']]);
        $message = "Owner's ID marked as verified.";
        $owner['id_verified_at'] = date('Y-m-d H:i:s');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'start_trial_now') {
        require_once __DIR__ . '/../includes/payments.php';
        start_trial($db, $id, 60);
        $message = '60-day trial started manually.';
        $school['plan'] = 'promo_paid';
    }

    if (isset($_POST['action']) && $_POST['action'] === 'reset_password' && $owner) {
        $newTempPassword = bin2hex(random_bytes(4));
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([password_hash($newTempPassword, PASSWORD_DEFAULT), $owner['id']]);
        $message = 'Password reset. Send the new password below to the school directly.';

        // Notify them a reset happened (without the password itself — that goes via WhatsApp)
        if (!empty($owner['email'])) {
            $resetBody = "
                <h2 style='color:#0F5257;margin-top:0;'>Your Password Was Reset</h2>
                <p>Your Somahub dashboard password was just reset by our team.</p>
                <p>You'll receive your new temporary password on WhatsApp shortly. If you didn't request this, please message us immediately.</p>
            ";
            send_somahub_email($owner['email'], 'Your Somahub password was reset', $resetBody);
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_school') {
        $confirmName = trim($_POST['confirm_name'] ?? '');
        if ($confirmName !== $school['name']) {
            $message = 'School name did not match exactly — nothing was deleted.';
        } else {
            require_once __DIR__ . '/../includes/school-archive.php';
            require_once __DIR__ . '/../includes/mailer.php';

            $db->beginTransaction();
            try {
                // Archive everything worth keeping before any of it is deleted.
                $export = build_school_export($db, $id);
                $admin = current_user();
                $db->prepare("
                    INSERT INTO archived_schools (original_school_id, school_name, slug, county, phone, email, export_json, archived_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $id, $school['name'], $school['slug'], $school['county'],
                    $school['phone'], $school['email'], json_encode($export),
                    $admin['name'] ?? $admin['email'] ?? 'admin',
                ]);

                $userIds = $db->prepare("SELECT id FROM users WHERE school_id = ?");
                $userIds->execute([$id]);
                $userIds = array_column($userIds->fetchAll(), 'id');

                if ($userIds) {
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    $db->prepare("DELETE FROM magic_login_tokens WHERE user_id IN ($placeholders)")->execute($userIds);
                    $db->prepare("DELETE FROM password_resets WHERE user_id IN ($placeholders)")->execute($userIds);
                }

                $orderIds = $db->prepare("SELECT id FROM orders WHERE school_id = ?");
                $orderIds->execute([$id]);
                $orderIds = array_column($orderIds->fetchAll(), 'id');

                if ($orderIds) {
                    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                    $db->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)")->execute($orderIds);
                    $db->prepare("DELETE FROM refunds WHERE order_id IN ($placeholders)")->execute($orderIds);
                    $db->prepare("DELETE FROM service_requests WHERE order_id IN ($placeholders)")->execute($orderIds);
                }

                $attUploadIds = $db->prepare("SELECT id FROM attendance_uploads WHERE school_id = ?");
                $attUploadIds->execute([$id]);
                $attUploadIds = array_column($attUploadIds->fetchAll(), 'id');
                if ($attUploadIds) {
                    $placeholders = implode(',', array_fill(0, count($attUploadIds), '?'));
                    $db->prepare("DELETE FROM attendance_rows WHERE attendance_upload_id IN ($placeholders)")->execute($attUploadIds);
                }

                $resultUploadIds = $db->prepare("SELECT id FROM result_uploads WHERE school_id = ?");
                $resultUploadIds->execute([$id]);
                $resultUploadIds = array_column($resultUploadIds->fetchAll(), 'id');
                if ($resultUploadIds) {
                    $placeholders = implode(',', array_fill(0, count($resultUploadIds), '?'));
                    $db->prepare("DELETE FROM result_rows WHERE result_upload_id IN ($placeholders)")->execute($resultUploadIds);
                }

                $tablesByColumn = [
                    'orders' => 'school_id', 'attendance_uploads' => 'school_id',
                    'result_uploads' => 'school_id', 'fee_structures' => 'school_id',
                    'enrollment_applications' => 'school_id', 'content_audit_log' => 'school_id',
                    'site_sections' => 'school_id', 'notifications' => 'school_id',
                    'account_removal_requests' => 'school_id', 'reviews' => 'reviewable_id',
                    'school_contact_messages' => 'school_id', 'media' => 'school_id',
                ];
                foreach ($tablesByColumn as $table => $col) {
                    if ($table === 'reviews') {
                        $db->prepare("DELETE FROM reviews WHERE reviewable_type = 'school' AND reviewable_id = ?")->execute([$id]);
                    } else {
                        try {
                            $db->prepare("DELETE FROM {$table} WHERE {$col} = ?")->execute([$id]);
                        } catch (PDOException $e) {
                            // A table that was never created on this server is fine to skip.
                            if ($e->getCode() !== '42S02') throw $e;
                        }
                    }
                }

                // Uploads recorded against this school's users (e.g. made while an
                // admin was impersonating) also point at users.id, so they must go
                // before the users do or the delete fails on media_ibfk_2.
                if ($userIds) {
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    $db->prepare("DELETE FROM media WHERE uploaded_by_user_id IN ($placeholders)")->execute($userIds);
                }
                $db->prepare("UPDATE leads SET converted_school_id = NULL WHERE converted_school_id = ?")->execute([$id]);

                // Users last — everything above that referenced user_id is already gone
                $db->prepare("DELETE FROM users WHERE school_id = ?")->execute([$id]);

                $db->prepare("DELETE FROM schools WHERE id = ?")->execute([$id]);

                // Courtesy summary email — only if they have a real email on
                // file (phone-only logins from lead conversion have nothing
                // to send this to, which is fine, not an error).
                if (!empty($school['email']) && filter_var($school['email'], FILTER_VALIDATE_EMAIL)) {
                    $summaryHtml = build_school_export_summary_html($export);
                    send_somahub_email($school['email'], 'Your Somahub account has been removed', $summaryHtml);
                }

                // Physical uploaded files aren't DB-managed — clean up separately
                $uploadDir = __DIR__ . '/../uploads/schools/' . $id . '/';
                if (is_dir($uploadDir)) {
                    foreach (glob($uploadDir . '*') as $file) {
                        if (is_file($file)) unlink($file);
                    }
                    rmdir($uploadDir);
                }

                $db->commit();
                header("Location: index.php?deleted=1");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                error_log('School deletion failed: ' . $e->getMessage());
                $message = 'Deletion failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage <?= htmlspecialchars($school['name']) ?></title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:8px;padding:28px;margin-bottom:24px;}
  .success{background:#E4F0E7;color:#1B4D3E;padding:12px 16px;border-radius:6px;margin-bottom:20px;font-size:0.9rem;}
  .creds{background:#F4F1E6;padding:14px;border-radius:6px;font-family:monospace;margin:12px 0;}
  .shead{display:flex;gap:18px;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px 22px;box-shadow:0 2px 8px rgba(28,28,22,.05);margin-bottom:16px;}
  .sav{width:60px;height:60px;border-radius:18px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:800;}
  .sinfo{flex:1;min-width:240px;} .sinfo h1{font-size:1.4rem;margin:0 0 8px;} .chips{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
  .sacts{display:flex;gap:8px;flex-wrap:wrap;}
  .vchip{display:inline-block;padding:3px 10px;border-radius:99px;font-size:.72rem;font-weight:800;} .v-verified{background:#DCEFE1;color:#1B4D3E;} .v-pending{background:#FFF1DC;color:#8A5A12;} .v-rejected{background:#FBE8E4;color:#8C3B2E;}
  .stabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;border-bottom:1px solid var(--line);padding-bottom:12px;}
  .stabs button{border:none;background:none;padding:9px 16px;border-radius:99px;font:inherit;font-size:.86rem;font-weight:800;color:var(--muted);cursor:pointer;}
  .stabs button.on{background:var(--teal);color:#fff;} .stabs button.dng{color:#C0392B;} .stabs button.dng.on{background:#C0392B;color:#fff;}
  .tabpane{display:none;} .tabpane.show{display:block;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <p style="margin-bottom:14px;"><a href="index.php">&larr; All schools</a></p>
  <?php $__v = $school['verification_status'] ?? 'pending'; $__since = !empty($school['created_at']) ? date('d M Y', strtotime($school['created_at'])) : '—'; ?>
  <div class="shead">
    <div class="sav"><?= htmlspecialchars(strtoupper(mb_substr($school['name'], 0, 1))) ?></div>
    <div class="sinfo">
      <h1><?= htmlspecialchars($school['name']) ?></h1>
      <div class="chips">
        <span class="plan-badge plan-<?= htmlspecialchars($school['plan']) ?>"><?= ucfirst(str_replace('_', ' ', $school['plan'])) ?></span>
        <span class="vchip v-<?= htmlspecialchars($__v) ?>"><?= $__v === 'rejected' ? 'Needs attention' : ucfirst($__v) ?></span>
        <span class="vchip" style="background:#EEE;color:#555;"><?= ucfirst($school['status']) ?></span>
        <span style="color:var(--muted);font-size:.82rem;">Joined <?= $__since ?></span>
      </div>
      <div style="font-size:.86rem;color:var(--muted);margin-top:6px;">
        <a href="https://<?= urlencode($school['slug']) ?>.somahub.top/" target="_blank"><?= htmlspecialchars($school['slug']) ?>.somahub.top</a>
        <?php if ($owner): ?> · Owner: <?= htmlspecialchars($owner['name'] ?? '') ?> <a href="mailto:<?= htmlspecialchars($owner['email']) ?>"><?= htmlspecialchars($owner['email']) ?></a><?php endif; ?>
      </div>
    </div>
    <div class="sacts">
      <form method="POST" action="impersonate.php" style="display:inline"><input type="hidden" name="school_id" value="<?= (int)$school['id'] ?>"><button class="btn" type="submit">Edit their site</button></form>
      <a class="btn secondary" href="https://<?= urlencode($school['slug']) ?>.somahub.top/" target="_blank">View site</a>
      <?php if (!empty($school['email'])): ?><a class="btn secondary" href="outreach.php?<?= htmlspecialchars(http_build_query(['school_name' => $school['name'], 'name' => $owner['name'] ?? '', 'email' => $school['email'], 'phone' => $school['phone'] ?? '', 'slug' => $school['slug']])) ?>">Message</a><?php endif; ?>
    </div>
  </div>
  <div class="stabs" id="stabs">
    <button type="button" data-t="details" class="on">Details</button><button type="button" data-t="verify">Verification</button><button type="button" data-t="owner">Owner &amp; access</button><button type="button" data-t="content">Content</button><button type="button" data-t="site">Website</button><button type="button" data-t="danger" class="dng">Danger zone</button>
  </div>

  <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($newTempPassword): ?>
    <div class="creds">
      Login: <?= htmlspecialchars($owner['email']) ?><br>
      New temp password: <?= htmlspecialchars($newTempPassword) ?>
    </div>
  <?php endif; ?>

  <div class="box tabpane" data-tab="details">
    <h3 style="margin-bottom:16px;">School Details</h3>
    <form method="POST" class="stacked">
      <input type="hidden" name="action" value="update_details">

      <label>School Name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($school['name']) ?>" required>

      <label>Colors</label>
      <?php
        $selectedPaletteId = $school['palette_id'];
        $selectedColorMode = $school['color_mode'] ?? 'preset';
        $customColors = [
            'primary' => $school['primary_override'] ?: '#0F5257',
            'secondary' => $school['secondary_override'] ?: '#1C1C16',
            'accent' => $school['accent_override'] ?: '#F2A65A',
            'bg' => $school['bg_override'] ?: '#F7F2E7',
        ];
        include __DIR__ . '/_palette_picker.php';
      ?>

      <label>Plan</label>
      <select name="plan">
        <option value="free" <?= $school['plan'] === 'free' ? 'selected' : '' ?>>Free</option>
        <option value="promo_paid" <?= $school['plan'] === 'promo_paid' ? 'selected' : '' ?>>Paid (Promo / Free Term)</option>
        <option value="paid" <?= $school['plan'] === 'paid' ? 'selected' : '' ?>>Paid (Full)</option>
      </select>
      <?php if ($school['plan'] === 'free'): ?>
        <p style="font-size:0.78rem;color:#888;margin-top:-10px;margin-bottom:16px;">
          Converted before the auto-trial feature existed, or the automatic 60-day trial didn't fire on first login? Start it manually:
        </p>
        <form method="POST" style="margin-bottom:16px;">
          <input type="hidden" name="action" value="start_trial_now">
          <button type="submit" class="btn-secondary" style="background:#F2A65A;color:#0A3A3E;border:none;padding:8px 16px;border-radius:6px;font-size:0.82rem;font-weight:700;cursor:pointer;">Start 60-Day Trial Now</button>
        </form>
      <?php endif; ?>

      <label>Status</label>
      <select name="status">
        <option value="trial" <?= $school['status'] === 'trial' ? 'selected' : '' ?>>Trial</option>
        <option value="active" <?= $school['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="suspended" <?= $school['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
      </select>

      <label>Promo Ends At (if on promo plan)</label>
      <input type="date" name="promo_ends_at" value="<?= htmlspecialchars($school['promo_ends_at'] ?? '') ?>">

      <label>County</label>
      <input type="text" name="county" value="<?= htmlspecialchars($school['county'] ?? '') ?>">

      <label>Town</label>
      <input type="text" name="town" value="<?= htmlspecialchars($school['town'] ?? '') ?>">

      <label>Phone</label>
      <input type="text" name="phone" value="<?= htmlspecialchars($school['phone'] ?? '') ?>">

      <label>Email</label>
      <input type="text" name="email" value="<?= htmlspecialchars($school['email'] ?? '') ?>">

      <button type="submit" class="btn">Save Changes</button>
    </form>
  </div>

  <div class="box tabpane" data-tab="content">
    <h3 style="margin-bottom:10px;">Generic Content</h3>
    <p style="font-size:0.85rem;color:#666;margin-bottom:14px;">Fill in starter content matched to the school type — useful if the school hasn't written their own yet, or as a quick starting point for you to hand off.</p>
    <form method="POST" class="stacked">
      <input type="hidden" name="action" value="apply_content_preset">
      <label>Preset</label>
      <select name="content_preset">
        <?php foreach (get_school_content_presets() as $key => $p): ?>
          <?php if ($key === 'blank') continue; ?>
          <option value="<?= $key ?>"><?= htmlspecialchars($p['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-top:12px;">
        <input type="checkbox" name="only_if_empty" value="1" checked style="width:auto;margin:0;">
        Only fill in sections that are still empty (won't overwrite what the school already wrote)
      </label>
      <button type="submit" class="btn" style="margin-top:14px;">Apply Preset</button>
    </form>
  </div>

  <div class="box tabpane" data-tab="verify">
    <h3 style="margin-bottom:16px;">Verification</h3>
    <?php
      $statusLabels = ['pending' => 'Pending Review', 'verified' => 'Verified', 'rejected' => 'Needs Attention'];
      $currentStatus = $school['verification_status'] ?? 'pending';
    ?>
    <p style="margin-bottom:14px;">
      Current status: <strong><?= $statusLabels[$currentStatus] ?></strong>
    </p>

    <?php if (!empty($school['head_teacher_name']) || !empty($school['head_teacher_phone'])): ?>
      <p style="margin-bottom:14px;background:#F4F1E6;padding:10px 12px;border-radius:8px;font-size:.9rem;">
        <strong>Head teacher contact (given at signup):</strong><br>
        <?= htmlspecialchars($school['head_teacher_name'] ?? '—') ?> ·
        <?php if (!empty($school['head_teacher_phone'])): ?><a href="tel:<?= htmlspecialchars($school['head_teacher_phone']) ?>"><?= htmlspecialchars($school['head_teacher_phone']) ?></a><?php else: ?>no phone<?php endif; ?>
        <br><small style="color:#6E6A5C;">Call to confirm the owner is authorised to run this site.</small>
      </p>
    <?php endif; ?>
    <?php if (!empty($school['signed_agreement_path'])): ?>
      <p style="margin-bottom:14px;">
        <a href="private-file.php?f=<?= rawurlencode(ltrim($school['signed_agreement_path'],'/')) ?>" target="_blank" class="btn" style="display:inline-block;">View Uploaded Document</a>
      </p>
    <?php else: ?>
      <p style="color:#888;margin-bottom:14px;">No document uploaded yet by this school.</p>
    <?php endif; ?>

    <hr style="border:none;border-top:1px solid #eee;margin:18px 0;">

    <p style="font-weight:600;font-size:0.9rem;margin-bottom:10px;">Owner ID Verification</p>
    <?php if ($owner && !empty($owner['id_document_path'])): ?>
      <p style="margin-bottom:10px;">
        ID Number: <strong><?= htmlspecialchars($owner['id_number'] ?? 'Not provided') ?></strong><br>
        <a href="private-file.php?f=<?= rawurlencode(ltrim($owner['id_document_path'],'/')) ?>" target="_blank">View ID Document</a><br>
        <?php if ($owner['id_verified_at']): ?>
          <span style="color:#1B4D3E;">✓ Verified on <?= date('d M Y', strtotime($owner['id_verified_at'])) ?></span>
        <?php else: ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="verify_owner_id">
            <button type="submit" class="btn" style="margin-top:8px;">Mark ID as Verified</button>
          </form>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p style="color:#888;margin-bottom:14px;">Owner has not uploaded an ID yet.</p>
    <?php endif; ?>

    <hr style="border:none;border-top:1px solid #eee;margin:18px 0;">

    <form method="POST" class="stacked" style="max-width:400px;">
      <input type="hidden" name="action" value="review_verification">
      <label>Set Status</label>
      <select name="verification_status">
        <option value="pending" <?= $currentStatus === 'pending' ? 'selected' : '' ?>>Pending Review</option>
        <option value="verified" <?= $currentStatus === 'verified' ? 'selected' : '' ?>>Verified</option>
        <option value="rejected" <?= $currentStatus === 'rejected' ? 'selected' : '' ?>>Needs Attention</option>
      </select>
      <label>Note to school (shown if status is "Needs Attention")</label>
      <input type="text" name="verification_notes" value="<?= htmlspecialchars($school['verification_notes'] ?? '') ?>" placeholder="e.g. Document was blurry, please re-upload">
      <button type="submit" class="btn">Update Verification</button>
    </form>
  </div>

  <div class="box tabpane" data-tab="owner">
    <h3 style="margin-bottom:16px;">Owner Login</h3>
    <?php if ($owner): ?>
      <p style="margin-bottom:16px;">
        <strong><?= htmlspecialchars($owner['name']) ?></strong><?= !empty($owner['job_title']) ? ' — ' . htmlspecialchars($owner['job_title']) : '' ?><br>
        <?= htmlspecialchars($owner['email']) ?><br>
        <?= htmlspecialchars($owner['phone'] ?? '') ?>
      </p>
      <form method="POST" onsubmit="return confirm('Reset this owner\'s password? A new temporary password will be generated.')">
        <input type="hidden" name="action" value="reset_password">
        <button type="submit" class="btn">Reset Password</button>
      </form>
    <?php else: ?>
      <p style="color:#888;">No owner account found for this school.</p>
    <?php endif; ?>
  </div>

  <div class="box tabpane" data-tab="site">
    <h3 style="margin-bottom:16px;">Website Content</h3>
    <p style="color:var(--muted);font-size:0.88rem;margin-bottom:14px;">Edit this school's sections directly, using the same editor they use.</p>
    <form method="POST" action="impersonate.php">
      <input type="hidden" name="school_id" value="<?= $school['id'] ?>">
      <button type="submit" class="btn secondary">Edit This School's Website →</button>
    </form>
  </div>

  <div class="box tabpane" data-tab="danger" style="border:1.5px solid #F5C6C0;">
    <h3 style="margin-bottom:8px;color:#C0392B;">Danger Zone</h3>
    <p style="color:var(--muted);font-size:0.88rem;margin-bottom:16px;">
      This permanently deletes <strong><?= htmlspecialchars($school['name']) ?></strong>, including their website, all uploaded photos, enrollment applications, results, and fee records. A full data backup is automatically saved to <a href="archived-schools.php">Archived Schools</a> first, and a summary is emailed to them if they have a real email on file. The live account itself cannot be restored.
    </p>
    <form method="POST" onsubmit="return confirm('Are you absolutely sure? This cannot be undone.')">
      <input type="hidden" name="action" value="delete_school">
      <label style="display:block;font-size:0.85rem;font-weight:700;margin-bottom:6px;">Type the school's exact name to confirm:</label>
      <input type="text" name="confirm_name" placeholder="<?= htmlspecialchars($school['name']) ?>" required style="width:100%;max-width:400px;padding:10px 12px;border:1.5px solid var(--line);border-radius:8px;margin-bottom:14px;box-sizing:border-box;">
      <button type="submit" class="btn danger">Permanently Delete This School</button>
    </form>
  </div>
</main>
<script>
(function(){ var tabs=document.querySelectorAll('#stabs button'), panes=document.querySelectorAll('.tabpane');
  function go(t){ tabs.forEach(function(b){ b.classList.toggle('on', b.dataset.t===t); }); panes.forEach(function(p){ p.classList.toggle('show', p.dataset.tab===t); }); try{ history.replaceState(null,'','#'+t); }catch(e){} }
  tabs.forEach(function(b){ b.addEventListener('click', function(){ go(b.dataset.t); }); });
  var h=(location.hash||'').slice(1); go(['details','verify','owner','content','site','danger'].indexOf(h)>-1?h:'details'); })();
</script>
</body>
</html>