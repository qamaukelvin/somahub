<?php
require_once __DIR__ . '/config/db.php';
$db = get_db();

$query = trim($_GET['q'] ?? '');
$schoolMatches = [];
$blogMatches = [];

if ($query !== '') {
    $stmt = $db->prepare("
        SELECT name, slug, county, verification_status
        FROM schools
        WHERE status IN ('active','trial') AND (name LIKE ? OR county LIKE ?)
        ORDER BY name ASC
        LIMIT 12
    ");
    $stmt->execute(["%$query%", "%$query%"]);
    $schoolMatches = $stmt->fetchAll();

    $blogStmt = $db->prepare("
        SELECT title, slug, excerpt
        FROM blog_posts
        WHERE status = 'published' AND (title LIKE ? OR excerpt LIKE ?)
        ORDER BY published_at DESC
        LIMIT 8
    ");
    $blogStmt->execute(["%$query%", "%$query%"]);
    $blogMatches = $blogStmt->fetchAll();
}

$hasResults = $schoolMatches || $blogMatches;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search — Somahub</title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; --ink:#1C1C16; --muted:#6E6A5C; --line:#E5DFCC; }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);color:var(--ink);}
  .wrap{max-width:640px;margin:0 auto;padding:40px 24px 80px;}
  h1{font-size:1.5rem;margin-bottom:20px;}
  .search-box{display:flex;gap:10px;background:#fff;border:1.5px solid var(--line);border-radius:14px;padding:6px;margin-bottom:28px;}
  .search-box input{flex:1;border:none;padding:12px 14px;font-size:0.95rem;background:transparent;outline:none;font-family:inherit;}
  .search-box button{background:var(--teal);color:var(--sand);border:none;padding:12px 22px;border-radius:10px;font-weight:700;cursor:pointer;font-size:0.9rem;}
  .section-label{font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);font-weight:700;margin:24px 0 10px;}
  .result-row{display:flex;justify-content:space-between;align-items:center;background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:8px;text-decoration:none;color:inherit;}
  .result-row .name{font-weight:700;font-size:0.92rem;}
  .result-row .meta{font-size:0.78rem;color:var(--muted);margin-top:2px;}
  .result-row .cta{background:var(--amber);color:var(--teal-deep);padding:7px 14px;border-radius:16px;font-weight:700;font-size:0.78rem;white-space:nowrap;}
  .verified-mark{color:var(--teal);}
  .empty{text-align:center;color:var(--muted);padding:40px 20px;}
</style>
</head>
<body>
<?php $navRoot = '.'; include __DIR__ . '/_public_nav.php'; ?>
<div class="wrap">
  <h1>Search Somahub</h1>
  <form method="GET" class="search-box">
    <input type="text" name="q" placeholder="Search for a school, or an article…" value="<?= htmlspecialchars($query) ?>" autofocus>
    <button type="submit">Search</button>
  </form>

  <?php if ($query === ''): ?>
    <p class="empty">Search for a school by name or county, or find a blog article.</p>
  <?php elseif (!$hasResults): ?>
    <p class="empty">No results for "<?= htmlspecialchars($query) ?>". Try a different spelling or a shorter term.</p>
  <?php else: ?>

    <?php if ($schoolMatches): ?>
      <div class="section-label">Schools</div>
      <?php foreach ($schoolMatches as $s): ?>
        <a href="https://<?= urlencode($s['slug']) ?>.somahub.top/" class="result-row">
          <div>
            <div class="name"><?= htmlspecialchars($s['name']) ?> <?php if ($s['verification_status'] === 'verified'): ?><span class="verified-mark">●</span><?php endif; ?></div>
            <?php if ($s['county']): ?><div class="meta"><?= htmlspecialchars($s['county']) ?> County</div><?php endif; ?>
          </div>
          <span class="cta">Visit Site</span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($blogMatches): ?>
      <div class="section-label">Blog</div>
      <?php foreach ($blogMatches as $b): ?>
        <a href="blog/<?= urlencode($b['slug']) ?>" class="result-row">
          <div>
            <div class="name"><?= htmlspecialchars($b['title']) ?></div>
            <?php if ($b['excerpt']): ?><div class="meta"><?= htmlspecialchars(mb_strimwidth($b['excerpt'], 0, 70, '…')) ?></div><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php endif; ?>
</div>
<?php include __DIR__ . '/_chat_widget.php'; ?>
</body>
</html>
