<?php
require_once __DIR__ . '/config/db.php';
$db = get_db();

$query = trim($_GET['q'] ?? '');
$matches = [];

if ($query !== '') {
    $stmt = $db->prepare("
        SELECT s.name, s.slug, s.county
        FROM schools s
        JOIN site_sections ss ON ss.school_id = s.id
        JOIN section_types st ON st.id = ss.section_type_id
        WHERE st.key_name = 'results_lookup' AND ss.is_visible = 1
          AND s.name LIKE ?
        GROUP BY s.id
        LIMIT 10
    ");
    $stmt->execute(['%' . $query . '%']);
    $matches = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check Your Child's Results — Somahub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; --ink:#1C1C16; --muted:#6E6A5C; --line:#E5DFCC; }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);color:var(--ink);min-height:100vh;}
  .wrap{max-width:560px;margin:0 auto;padding:60px 24px;}
  .brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:1.1rem;margin-bottom:40px;justify-content:center;}
  .brand .dot{width:9px;height:9px;background:var(--amber);border-radius:50%;}
  h1{font-size:1.7rem;font-weight:800;text-align:center;margin-bottom:10px;}
  p.sub{text-align:center;color:var(--muted);margin-bottom:32px;font-size:0.95rem;}
  .search-box{display:flex;gap:10px;background:#fff;border:1.5px solid var(--line);border-radius:14px;padding:6px;}
  .search-box input{flex:1;border:none;padding:12px 14px;font-size:0.95rem;background:transparent;outline:none;font-family:inherit;}
  .search-box button{background:var(--teal);color:var(--sand);border:none;padding:12px 22px;border-radius:10px;font-weight:700;cursor:pointer;font-size:0.9rem;}
  .results-list{margin-top:24px;}
  .result-row{display:flex;justify-content:space-between;align-items:center;background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin-bottom:10px;}
  .result-row .name{font-weight:700;font-size:0.95rem;}
  .result-row .county{font-family:'Space Mono',monospace;font-size:0.78rem;color:var(--muted);}
  .result-row a{background:var(--amber);color:var(--teal-deep);padding:8px 16px;border-radius:20px;font-weight:700;font-size:0.82rem;text-decoration:none;}
  .empty{text-align:center;color:var(--muted);padding:30px;font-size:0.9rem;}
  .hint{text-align:center;margin-top:30px;font-size:0.82rem;color:var(--muted);}
  .hint a{color:var(--teal);font-weight:600;}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand"><span class="dot"></span> somahub</div>
  <h1>Check your child's results</h1>
  <p class="sub">Search for your child's school to get started.</p>

  <form method="GET" class="search-box">
    <input type="text" name="q" placeholder="Type your school's name" value="<?= htmlspecialchars($query) ?>" autofocus>
    <button type="submit">Search</button>
  </form>

  <div class="results-list">
    <?php if ($query !== '' && !$matches): ?>
      <div class="empty">No school found matching "<?= htmlspecialchars($query) ?>". Check the spelling, or ask your school for their direct results link.</div>
    <?php elseif ($matches): ?>
      <?php foreach ($matches as $m): ?>
        <div class="result-row">
          <div>
            <div class="name"><?= htmlspecialchars($m['name']) ?></div>
            <?php if ($m['county']): ?><div class="county"><?= htmlspecialchars($m['county']) ?> County</div><?php endif; ?>
          </div>
          <a href="results-check.php?school=<?= urlencode($m['slug']) ?>">Check Results</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="hint">Looking for something else? <a href="index.php">Back to Somahub</a></div>
</div>
<?php include __DIR__ . '/_chat_widget.php'; ?>
</body>
</html>
