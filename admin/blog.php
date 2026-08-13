<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int)($_POST['post_id'] ?? 0);

    if ($action === 'publish') {
        $stmt = $db->prepare("UPDATE blog_posts SET status='published', published_at=NOW() WHERE id=?");
        $stmt->execute([$postId]);
    } elseif ($action === 'unpublish') {
        $stmt = $db->prepare("UPDATE blog_posts SET status='draft', published_at=NULL WHERE id=?");
        $stmt->execute([$postId]);
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM blog_posts WHERE id=?");
        $stmt->execute([$postId]);
    } elseif ($action === 'save_edit') {
        $stmt = $db->prepare("UPDATE blog_posts SET title=?, excerpt=?, meta_description=?, body_html=?, cover_image=?, cta_text=?, cta_link=? WHERE id=?");
        $stmt->execute([$_POST['title'], $_POST['excerpt'], $_POST['meta_description'], $_POST['body_html'], $_POST['cover_image'], $_POST['cta_text'], $_POST['cta_link'], $postId]);
    } elseif ($action === 'create_manual') {
        require_once __DIR__ . '/../includes/blog.php';
        $title = trim($_POST['title']);
        if ($title) {
            create_blog_draft(
                $db, 'manual', $title, $_POST['body_html'], $_POST['excerpt'], null,
                $_POST['cover_image'] ?: null, $_POST['cta_text'] ?: null, $_POST['cta_link'] ?: null,
                $_POST['meta_description'] ?: null
            );
        } else {
            $error = 'Title is required.';
        }
    } elseif ($action === 'approve_comment') {
        $stmt = $db->prepare("UPDATE blog_comments SET status='approved' WHERE id=?");
        $stmt->execute([(int)$_POST['comment_id']]);
    } elseif ($action === 'delete_comment') {
        $stmt = $db->prepare("DELETE FROM blog_comments WHERE id=?");
        $stmt->execute([(int)$_POST['comment_id']]);
    }
}

$drafts = $db->query("SELECT * FROM blog_posts WHERE status='draft' ORDER BY created_at DESC")->fetchAll();
$published = $db->query("SELECT * FROM blog_posts WHERE status='published' ORDER BY published_at DESC LIMIT 30")->fetchAll();
$pendingComments = $db->query("
    SELECT c.*, p.title AS post_title, p.slug AS post_slug
    FROM blog_comments c JOIN blog_posts p ON p.id = c.post_id
    WHERE c.status='pending' ORDER BY c.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blog</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .post-card{background:#fff;border-radius:8px;padding:18px 20px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  .post-type-badge{display:inline-block;font-size:0.7rem;font-weight:700;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:#F4F1E6;color:#6E6A5C;margin-bottom:8px;}
  .post-actions{margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;}
  .post-actions button, .post-actions a{font-size:0.82rem;padding:6px 14px;border-radius:5px;border:none;cursor:pointer;text-decoration:none;}
  .btn-publish{background:#1B4D3E;color:#fff;}
  .btn-unpublish{background:#F4F1E6;color:#333;}
  .btn-delete{background:#FBE8E4;color:#8C3B2E;}
  .btn-edit{background:#EFE6D6;color:#0F5257;}
  details summary{cursor:pointer;font-size:0.82rem;color:#0F5257;margin-top:10px;}
  .edit-form textarea{width:100%;box-sizing:border-box;padding:8px;margin-top:6px;margin-bottom:10px;border:1px solid #ccc;border-radius:4px;font-family:inherit;}
  .edit-form input{width:100%;box-sizing:border-box;padding:8px;margin-top:6px;margin-bottom:10px;border:1px solid #ccc;border-radius:4px;}
  .section-tabs{display:flex;gap:8px;margin-bottom:20px;}
  .section-tabs a{padding:8px 16px;border-radius:6px;text-decoration:none;font-size:0.85rem;font-weight:600;color:#0F5257;background:#F4F1E6;}
  .section-tabs a.active{background:#0F5257;color:#fff;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <div class="header-row"><h1>Blog</h1></div>
  <?php if ($error): ?><div class="error" style="background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <h3>Drafts awaiting review (<?= count($drafts) ?>)</h3>
  <?php if (!$drafts): ?>
    <p style="color:#888;">No drafts right now. Auto-generated posts (new schools, milestones) will show up here for review before publishing.</p>
  <?php endif; ?>
  <?php foreach ($drafts as $p): ?>
    <div class="post-card">
      <span class="post-type-badge"><?= htmlspecialchars(str_replace('_',' ', $p['post_type'])) ?></span>
      <h3 style="margin:4px 0;"><?= htmlspecialchars($p['title']) ?></h3>
      <p style="color:#666;font-size:0.88rem;"><?= htmlspecialchars($p['excerpt']) ?></p>

      <details>
        <summary>Edit before publishing</summary>
        <form method="POST" class="edit-form">
          <input type="hidden" name="action" value="save_edit">
          <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
          <label>Title</label>
          <input type="text" name="title" value="<?= htmlspecialchars($p['title']) ?>">
          <label>Excerpt (shown on blog listing)</label>
          <input type="text" name="excerpt" value="<?= htmlspecialchars($p['excerpt']) ?>">
          <label>Meta description (SEO — 160 chars max, shown in Google results)</label>
          <input type="text" name="meta_description" maxlength="160" value="<?= htmlspecialchars($p['meta_description'] ?? '') ?>">
          <label>Cover image URL</label>
          <input type="text" name="cover_image" value="<?= htmlspecialchars($p['cover_image'] ?? '') ?>" placeholder="/uploads/blog/photo.jpg or full URL">
          <label>CTA button text</label>
          <input type="text" name="cta_text" value="<?= htmlspecialchars($p['cta_text'] ?? '') ?>" placeholder="e.g. Visit the School's Site">
          <label>CTA button link</label>
          <input type="text" name="cta_link" value="<?= htmlspecialchars($p['cta_link'] ?? '') ?>" placeholder="https://...">
          <label>Body (HTML)</label>
          <textarea name="body_html" rows="6"><?= htmlspecialchars($p['body_html']) ?></textarea>
          <button type="submit" class="btn-edit" style="padding:8px 16px;border-radius:5px;border:none;cursor:pointer;">Save Changes</button>
        </form>
      </details>

      <div class="post-actions">
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="publish"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button type="submit" class="btn-publish">Publish</button></form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this draft?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button type="submit" class="btn-delete">Delete</button></form>
      </div>
    </div>
  <?php endforeach; ?>

  <h3 style="margin-top:36px;">Write a new post</h3>
  <div class="post-card">
    <form method="POST" class="edit-form">
      <input type="hidden" name="action" value="create_manual">
      <label>Title</label>
      <input type="text" name="title" placeholder="e.g. 5 Tips for Schools Starting Online Enrollment">
      <label>Excerpt</label>
      <input type="text" name="excerpt" placeholder="One-line summary for the blog listing">
      <label>Meta description (SEO — 160 chars max)</label>
      <input type="text" name="meta_description" maxlength="160" placeholder="Shown in Google search results">
      <label>Cover image URL</label>
      <input type="text" name="cover_image" placeholder="/uploads/blog/photo.jpg or full URL">
      <label>CTA button text</label>
      <input type="text" name="cta_text" placeholder="e.g. Get Started Free">
      <label>CTA button link</label>
      <input type="text" name="cta_link" placeholder="https://...">
      <label>Body (HTML)</label>
      <textarea name="body_html" rows="8" placeholder="<p>Write your post here...</p>"></textarea>
      <button type="submit" class="btn-publish" style="padding:8px 16px;">Save as Draft</button>
    </form>
  </div>

  <?php if ($pendingComments): ?>
  <h3 style="margin-top:36px;">Comments awaiting approval (<?= count($pendingComments) ?>)</h3>
  <?php foreach ($pendingComments as $c): ?>
    <div class="post-card">
      <p style="font-size:0.8rem;color:#888;margin:0 0 4px;">On "<a href="/blog/<?= htmlspecialchars($c['post_slug']) ?>" target="_blank"><?= htmlspecialchars($c['post_title']) ?></a>" · <?= date('d M Y', strtotime($c['created_at'])) ?></p>
      <p style="margin:4px 0;"><strong><?= htmlspecialchars($c['name']) ?></strong> <span style="color:#999;font-size:0.8rem;">(<?= htmlspecialchars($c['email']) ?>)</span></p>
      <p style="margin:4px 0;"><?= htmlspecialchars($c['comment']) ?></p>
      <div class="post-actions">
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="approve_comment"><input type="hidden" name="comment_id" value="<?= $c['id'] ?>"><button type="submit" class="btn-publish">Approve</button></form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this comment?')"><input type="hidden" name="action" value="delete_comment"><input type="hidden" name="comment_id" value="<?= $c['id'] ?>"><button type="submit" class="btn-delete">Delete</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <h3 style="margin-top:36px;">Published (<?= count($published) ?>)</h3>
  <?php foreach ($published as $p): ?>
    <div class="post-card">
      <span class="post-type-badge"><?= htmlspecialchars(str_replace('_',' ', $p['post_type'])) ?></span>
      <h3 style="margin:4px 0;"><?= htmlspecialchars($p['title']) ?></h3>
      <p style="color:#666;font-size:0.85rem;">Published <?= date('d M Y', strtotime($p['published_at'])) ?> · <a href="/blog/<?= htmlspecialchars($p['slug']) ?>" target="_blank">View live →</a></p>
      <div class="post-actions">
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="unpublish"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button type="submit" class="btn-unpublish">Unpublish</button></form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this post?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button type="submit" class="btn-delete">Delete</button></form>
      </div>
    </div>
  <?php endforeach; ?>
</main>

<?php include __DIR__ . '/_chat_widget.php'; ?>
</body>
</html>
