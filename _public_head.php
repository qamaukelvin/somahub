<?php
// Shared <head> + navbar for Somahub's own public pages.
// Set before including: $pageTitle, $pageDesc, $pagePath (e.g. '/features.php'), optional $pageJsonLd (raw <script> tag).
$pageTitle = $pageTitle ?? 'Somahub: Free Websites for Kenyan Schools';
$pageDesc  = $pageDesc  ?? "Get your school online free. Somahub builds and hosts your school's website, you just edit and go live.";
$pagePath  = $pagePath  ?? '/';
$pageUrl   = 'https://somahub.top' . $pagePath;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="canonical" href="<?= htmlspecialchars($pageUrl) ?>">
<?= $pageJsonLd ?? '' ?>
<meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">

<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="assets/icon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/icon-16.png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">

<meta property="og:type" content="website">
<meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
<meta property="og:image" content="https://somahub.top/assets/og-share-image.png">
<meta property="og:url" content="<?= htmlspecialchars($pageUrl) ?>">
<meta property="og:site_name" content="Somahub">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($pageDesc) ?>">
<meta name="twitter:image" content="https://somahub.top/assets/og-share-image.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/public.css?v=<?= (int)@filemtime(__DIR__ . '/assets/public.css') ?>">
</head>
<body>

<?php $navRoot = '.'; include __DIR__ . '/_public_nav.php'; ?>
