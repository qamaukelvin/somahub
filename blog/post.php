<?php
require_once __DIR__ . '/../config/db.php';
$db = get_db();

$slug = $_GET['slug'] ?? '';
$stmt = $db->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo "Post not found.";
    exit;
}

$commentError = '';
$commentSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_comment') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    // Honeypot field — real users never fill this in; bots usually do
    $honeypot = trim($_POST['website'] ?? '');

    if ($honeypot !== '') {
        // Silently drop — don't tip off the bot, just pretend it worked
        $commentSuccess = true;
    } elseif (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$comment) {
        $commentError = 'Please fill in your name, a valid email, and a comment.';
    } else {
        $insert = $db->prepare("INSERT INTO blog_comments (post_id, name, email, comment, status) VALUES (?, ?, ?, ?, 'pending')");
        $insert->execute([$post['id'], $name, $email, $comment]);
        $commentSuccess = true;
    }
}

$comments = $db->prepare("SELECT * FROM blog_comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at ASC");
$comments->execute([$post['id']]);
$comments = $comments->fetchAll();

$pageUrl = 'https://somahub.top/blog/' . urlencode($post['slug']);
$shareText = rawurlencode($post['title']);
$coverImage = $post['cover_image'] ?: 'https://somahub.top/assets/og-share-image.png';
$metaDesc = $post['meta_description'] ?: $post['excerpt'];

// JSON-LD structured data for rich search results
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $post['title'],
    'description' => $metaDesc,
    'image' => $coverImage,
    'datePublished' => date('c', strtotime($post['published_at'])),
    'author' => ['@type' => 'Organization', 'name' => 'Somahub'],
    'publisher' => ['@type' => 'Organization', 'name' => 'Somahub', 'logo' => ['@type' => 'ImageObject', 'url' => 'https://somahub.top/assets/logo.svg']],
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $pageUrl],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($post['title']) ?> — Somahub Blog</title>
<meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">
<link rel="canonical" href="<?= htmlspecialchars($pageUrl) ?>">

<!-- Open Graph / social preview -->
<meta property="og:type" content="article">
<meta property="og:title" content="<?= htmlspecialchars($post['title']) ?>">
<meta property="og:description" content="<?= htmlspecialchars($metaDesc) ?>">
<meta property="og:image" content="<?= htmlspecialchars($coverImage) ?>">
<meta property="og:url" content="<?= htmlspecialchars($pageUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($post['title']) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($metaDesc) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($coverImage) ?>">

<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES) ?></script>

<style>
  body{font-family:Arial,sans-serif;background:#F7F2E7;margin:0;color:#1C1C16;}
  header{background:#0F5257;padding:20px 24px;}
  header a{color:#F7F2E7;text-decoration:none;font-weight:800;font-size:18px;}
  .wrap{max-width:680px;margin:0 auto;padding:32px 20px;}
  .back-link{display:inline-block;margin-bottom:18px;color:#0F5257;text-decoration:none;font-size:0.85rem;font-weight:700;}
  .post-date{font-size:0.8rem;color:#999;margin-bottom:8px;}
  h1{color:#0F5257;margin-top:0;}
  .cover-image{width:100%;border-radius:12px;margin:16px 0 24px;display:block;}
  .content{background:#fff;border-radius:10px;padding:28px;line-height:1.7;box-shadow:0 1px 3px rgba(0,0,0,0.06);}
  .cta-box{text-align:center;margin-top:28px;}
  .cta-box a{display:inline-block;background:#F2A65A;color:#0A3A3E;font-weight:800;padding:13px 28px;border-radius:24px;text-decoration:none;font-size:0.95rem;}

  .share-row{display:flex;gap:10px;flex-wrap:wrap;margin:24px 0;}
  .share-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:20px;text-decoration:none;font-size:0.82rem;font-weight:700;color:#fff;}
  .share-wa{background:#25D366;}
  .share-fb{background:#1877F2;}
  .share-x{background:#000;}
  .share-li{background:#0A66C2;}
  .share-copy{background:#6E6A5C;border:none;cursor:pointer;font-family:inherit;}

  .comments-section{margin-top:40px;}
  .comment-card{background:#fff;border-radius:8px;padding:16px 18px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
  .comment-card .c-name{font-weight:700;font-size:0.9rem;}
  .comment-card .c-date{font-size:0.75rem;color:#999;margin-left:8px;}
  .comment-form{background:#fff;border-radius:10px;padding:22px;margin-top:18px;}
  .comment-form input, .comment-form textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;font-family:inherit;}
  .comment-form button{background:#0F5257;color:#fff;border:none;padding:10px 22px;border-radius:6px;font-weight:700;cursor:pointer;}
  .notice-success{background:#E4F5EA;color:#1B4D3E;padding:10px 14px;border-radius:6px;font-size:0.85rem;margin-bottom:14px;}
  .notice-error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;font-size:0.85rem;margin-bottom:14px;}
  .honeypot{position:absolute;left:-9999px;opacity:0;height:0;}
</style>
</head>
<body>
<header><a href="/">● somahub</a></header>
<main class="wrap">
  <a href="/blog/" class="back-link">← Back to Blog</a>
  <div class="post-date"><?= date('d M Y', strtotime($post['published_at'])) ?></div>
  <h1><?= htmlspecialchars($post['title']) ?></h1>

  <?php if ($post['cover_image']): ?>
    <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="cover-image">
  <?php endif; ?>

  <div class="content">
    <?= $post['body_html'] ?>
    <?php if ($post['cta_text'] && $post['cta_link']): ?>
      <div class="cta-box">
        <a href="<?= htmlspecialchars($post['cta_link']) ?>"><?= htmlspecialchars($post['cta_text']) ?></a>
      </div>
    <?php endif; ?>
  </div>

  <div class="share-row">
    <a class="share-btn share-wa" target="_blank" href="https://wa.me/?text=<?= $shareText ?>%20<?= urlencode($pageUrl) ?>">WhatsApp</a>
    <a class="share-btn share-fb" target="_blank" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($pageUrl) ?>">Facebook</a>
    <a class="share-btn share-x" target="_blank" href="https://twitter.com/intent/tweet?text=<?= $shareText ?>&url=<?= urlencode($pageUrl) ?>">X</a>
    <a class="share-btn share-li" target="_blank" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($pageUrl) ?>">LinkedIn</a>
    <button class="share-btn share-copy" onclick="copyLink(this)">Copy Link</button>
  </div>

  <div class="comments-section">
    <h3>Comments (<?= count($comments) ?>)</h3>

    <?php foreach ($comments as $c): ?>
      <div class="comment-card">
        <span class="c-name"><?= htmlspecialchars($c['name']) ?></span>
        <span class="c-date"><?= date('d M Y', strtotime($c['created_at'])) ?></span>
        <p style="margin:8px 0 0;"><?= nl2br(htmlspecialchars($c['comment'])) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if (!$comments): ?>
      <p style="color:#888;font-size:0.9rem;">No comments yet — be the first to share your thoughts.</p>
    <?php endif; ?>

    <div class="comment-form">
      <?php if ($commentSuccess): ?>
        <div class="notice-success">Thanks! Your comment has been submitted and will appear once reviewed.</div>
      <?php endif; ?>
      <?php if ($commentError): ?>
        <div class="notice-error"><?= htmlspecialchars($commentError) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="submit_comment">
        <input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off">
        <input type="text" name="name" placeholder="Your name" required>
        <input type="email" name="email" placeholder="Your email (not published)" required>
        <textarea name="comment" rows="4" placeholder="Your comment" required></textarea>
        <button type="submit">Post Comment</button>
      </form>
    </div>
  </div>
</main>

<script>
function copyLink(btn) {
    navigator.clipboard.writeText(window.location.href).then(() => {
        const original = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = original, 1500);
    });
}
</script>
</body>
</html>
