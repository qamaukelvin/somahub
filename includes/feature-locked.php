<?php
/**
 * Renders a proper branded page (not a bare die()) when a Free-plan school's
 * public-facing premium feature (enrollment, results checking, full report)
 * is accessed. Call render_feature_locked_page() and it exits — nothing
 * runs after it.
 *
 * @param array  $school     The school row (needs at least name, slug)
 * @param string $featureName Human label, e.g. "Online Enrollment"
 */
function render_feature_locked_page(array $school, string $featureName): void {
    http_response_code(403);
    $siteUrl = "https://" . htmlspecialchars($school['slug']) . ".somahub.top/";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($featureName) ?> — Not Available</title>
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
      body{font-family:'Manrope',sans-serif;background:#F7F2E7;color:#1C1C16;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;text-align:center;}
      .box{background:#fff;padding:36px 32px;border-radius:16px;max-width:400px;box-shadow:0 8px 30px rgba(0,0,0,0.06);}
      .badge{display:inline-block;background:#0F5257;color:#F7F2E7;font-weight:800;padding:6px 16px;border-radius:20px;font-size:0.8rem;margin-bottom:20px;}
      h1{color:#0F5257;font-size:1.25rem;margin-bottom:10px;}
      p{color:#6E6A5C;line-height:1.6;font-size:0.92rem;margin-bottom:24px;}
      a.back{display:inline-block;background:#0F5257;color:#fff;padding:11px 24px;border-radius:24px;text-decoration:none;font-weight:700;font-size:0.88rem;}
    </style>
    </head>
    <body>
      <div class="box">
        <div class="badge">● somahub</div>
        <h1><?= htmlspecialchars($featureName) ?> isn't available here</h1>
        <p><?= htmlspecialchars($school['name']) ?> hasn't enabled this feature yet. Please contact the school directly, or visit their website for other ways to reach them.</p>
        <a href="<?= $siteUrl ?>" class="back">Visit <?= htmlspecialchars($school['name']) ?>'s Site</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}
