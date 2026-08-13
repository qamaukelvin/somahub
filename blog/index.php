<?php
require_once __DIR__ . '/../config/db.php';
$db = get_db();

$posts = $db->query("SELECT id, title, slug, excerpt, cover_image, published_at FROM blog_posts WHERE status='published' ORDER BY published_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blog — Somahub</title>
<meta name="description" content="News, updates, and stories from Somahub — free websites for Kenyan schools.">
<link rel="canonical" href="https://somahub.top/blog/">
<meta property="og:type" content="website">
<meta property="og:title" content="Somahub Blog">
<meta property="og:description" content="News, updates, and stories from Somahub — free websites for Kenyan schools.">
<meta property="og:image" content="https://somahub.top/assets/og-share-image.png">
<meta property="og:url" content="https://somahub.top/blog/">
<meta name="twitter:card" content="summary_large_image">
<style>
  body{font-family:Arial,sans-serif;background:#F7F2E7;margin:0;color:#1C1C16;}
  header{background:#0F5257;padding:20px 24px;}
  header a{color:#F7F2E7;text-decoration:none;font-weight:800;font-size:18px;}
  .wrap{max-width:720px;margin:0 auto;padding:32px 20px;}
  h1{color:#0F5257;}
  .post-card{background:#fff;border-radius:10px;overflow:hidden;margin-bottom:18px;box-shadow:0 1px 3px rgba(0,0,0,0.06);}
  .post-card img{width:100%;height:220px;object-fit:cover;display:block;}
  .post-card-body{padding:20px 22px;}
  .post-card h2{margin:0 0 8px;font-size:1.2rem;}
  .post-card h2 a{color:#0F5257;text-decoration:none;}
  .post-card p{color:#555;margin:0 0 10px;line-height:1.5;}
  .post-date{font-size:0.78rem;color:#999;}
</style>
</head>
<body>
<header><a href="/">● somahub</a></header>
<main class="wrap">
  <h1>Blog</h1>
  <?php if (!$posts): ?>
    <p>No posts yet — check back soon.</p>
  <?php endif; ?>
  <?php foreach ($posts as $p): ?>
    <div class="post-card">
      <?php if ($p['cover_image']): ?>
        <a href="/blog/<?= htmlspecialchars($p['slug']) ?>">
          <img src="<?= htmlspecialchars($p['cover_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
        </a>
      <?php endif; ?>
      <div class="post-card-body">
        <div class="post-date"><?= date('d M Y', strtotime($p['published_at'])) ?></div>
        <h2><a href="/blog/<?= htmlspecialchars($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a></h2>
        <p><?= htmlspecialchars($p['excerpt']) ?></p>
        <a href="/blog/<?= htmlspecialchars($p['slug']) ?>" style="color:#0F5257;font-weight:700;font-size:0.85rem;">Read more →</a>
      </div>
    </div>
  <?php endforeach; ?>
</main>
</body>
</html>
