<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/blog.php';
$db = get_db();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$id]);
$school = $stmt->fetch();
$tempPassword = $_GET['pw'] ?? '';

$owner = $db->prepare("SELECT * FROM users WHERE school_id = ? AND role = 'school_owner' LIMIT 1");
$owner->execute([$id]);
$owner = $owner->fetch();

$blogResult = null; // 'created' | 'exists' | null

// Fires when the admin clicks "Generate blog post" once the site actually has
// real content in it (hero photo, About text) — not at raw creation time, since
// a freshly created school only has empty/generic placeholder sections.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_blog_post') {
    $newPostId = generate_school_joined_post($db, [
        'id' => $school['id'],
        'name' => $school['name'],
        'slug' => $school['slug'],
        'county' => $school['county'] ?? null,
    ]);
    $blogResult = $newPostId ? 'created' : 'exists';

    // Also check milestones now, since this is the more meaningful "school is really live" moment
    $totalSchools = (int)$db->query("SELECT COUNT(*) FROM schools")->fetchColumn();
    maybe_generate_milestone_post($db, $totalSchools);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>School Created</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:8px;padding:28px;max-width:480px;}
  .creds{background:#F4F1E6;padding:16px;border-radius:6px;font-family:monospace;margin:16px 0;}
  .btn-secondary{background:#0F5257;color:#fff;border:none;padding:10px 20px;border-radius:6px;font-weight:700;cursor:pointer;font-size:0.9rem;margin-top:10px;}
  .notice-success{background:#E4F5EA;color:#1B4D3E;padding:10px 14px;border-radius:6px;font-size:0.85rem;margin-top:14px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <div class="box">
    <h1>✓ <?= htmlspecialchars($school['name']) ?> created</h1>
    <p>Site is live at: <strong><?= htmlspecialchars($school['slug']) ?>.somahub.top</strong></p>

    <div class="creds">
      Login: <?= htmlspecialchars($owner['email']) ?><br>
      Temp password: <?= htmlspecialchars($tempPassword) ?>
    </div>

    <p style="font-size:0.85rem;color:#8C3B2E;">
      ⚠️ This password is shown once. Send it to the school directly (WhatsApp/SMS/email) now — it won't be shown again.
      Tell them to change it after first login.
    </p>

    <form method="POST">
      <input type="hidden" name="action" value="generate_blog_post">
      <button type="submit" class="btn-secondary">Generate "Welcome" Blog Post</button>
    </form>
    <p style="font-size:0.78rem;color:#888;margin-top:6px;">
      Best used once the school has added their hero photo and About text — the post pulls those in automatically. Creates a draft only; review and publish in the Blog admin.
    </p>

    <?php if ($blogResult === 'created'): ?>
      <div class="notice-success">✓ Draft blog post created — <a href="blog.php">review it here</a>.</div>
    <?php elseif ($blogResult === 'exists'): ?>
      <div class="notice-success">A blog post for this school already exists — check <a href="blog.php">Blog admin</a>.</div>
    <?php endif; ?>

    <p style="margin-top:20px;"><a href="index.php" class="btn">Back to Schools</a></p>
  </div>
</main>
</body>
</html>
