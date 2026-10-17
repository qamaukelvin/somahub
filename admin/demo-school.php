<?php
// Creates (or resets) the public demo school at demo.somahub.top.
// Platform admin only. Run once; to rebuild, delete the demo school first.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/school-provision.php';
require_once __DIR__ . '/../includes/consent.php';
require_platform_admin();
$db = get_db();

const DEMO_SLUG = 'demo';
$msg = '';
$err = '';
$existing = $db->prepare("SELECT id FROM schools WHERE slug = ?");
$existing->execute([DEMO_SLUG]);
$demoId = (int)$existing->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($demoId) throw new RuntimeException('The demo school already exists. Delete it from its admin edit page first to rebuild it.');
        $u = $db->prepare("SELECT * FROM users WHERE email = ?");
        $u->execute(['demo-owner@somahub.top']);
        $user = $u->fetch();
        if (!$user) {
            $db->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?, 'school_owner')")
               ->execute(['Demo Owner', 'demo-owner@somahub.top', '0700000000', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
            $u->execute(['demo-owner@somahub.top']);
            $user = $u->fetch();
        }
        $res = provision_school($db, $user, [
            'school_name' => 'Mwangaza Academy', 'slug' => DEMO_SLUG, 'county' => 'Laikipia',
            'content_preset' => 'primary_day', 'lat' => -0.0167, 'lng' => 37.0722,
            'job_title' => 'Head Teacher / Principal', 'job_other' => '', 'authorized' => true, 'head_name' => '', 'head_phone' => '',
        ]);
        // The demo must never lock: verified, permanent paid plan, all sections visible.
        $db->prepare("UPDATE schools SET verification_status = 'verified', plan = 'paid', status = 'active' WHERE id = ?")->execute([$res['school_id']]);
        $db->prepare("UPDATE site_sections SET is_visible = 1 WHERE school_id = ?")->execute([$res['school_id']]);
        $msg = 'Demo school is ready.';
    } catch (\Throwable $e) {
        $err = $e->getMessage();
    }
    $existing->execute([DEMO_SLUG]);
    $demoId = (int)$existing->fetchColumn();
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Demo school</title>
<?php include __DIR__ . '/_styles.php'; ?></head><body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="wrap" style="max-width:640px;">
  <h1>Demo school</h1>
  <p style="color:#666;margin:6px 0 20px;">A fully filled-in sample school shown on the homepage at <strong>demo.somahub.top</strong>. It is verified and on a permanent plan, so it never locks, and it is hidden from the homepage school list and count.</p>
  <?php if ($msg): ?><p style="background:#DCEFE1;color:#1B4D3E;padding:10px 14px;border-radius:8px;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
  <?php if ($err): ?><p style="background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:8px;"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <p>Status: <strong><?= $demoId ? 'exists' : 'not created yet' ?></strong>
    <?php if ($demoId): ?> · <a href="https://demo.somahub.top/" target="_blank" rel="noopener">Open demo site</a><?php endif; ?></p>
  <?php if (!$demoId): ?><form method="post" style="margin-top:16px;" onsubmit="return confirm('Create the demo school?');">
    <button class="btn" type="submit">Create demo school</button>
  </form><?php endif; ?>
</main></body></html>
