
<meta charset="UTF-8">

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
  <meta name="theme-color" content="#1c3626">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<?php
$siteUrlBase = rtrim($settings['site_url'] ?? '', '/');
$canonicalUrl = $siteUrlBase ? $siteUrlBase . '/' . ltrim($canonicalPath ?? '', '/') : '';
$ogImageUrl = $siteUrlBase ? $siteUrlBase . '/images/floor2/IMG_5856.jpg' : 'images/floor2/IMG_5856.jpg';
?>
<?php if ($canonicalUrl): ?>
<link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
<?php endif; ?>
<meta property="og:site_name" content="Quattro Homes">
<meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<meta property="og:type" content="website">
<?php if ($canonicalUrl): ?><meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>"><?php endif; ?>
<meta property="og:image" content="<?php echo htmlspecialchars($ogImageUrl); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($ogImageUrl); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?php
$cssVer = file_exists(__DIR__.'/../css/style.css') ? substr(md5(filemtime(__DIR__.'/../css/style.css')),0,8) : '1';
?>
<link rel="stylesheet" href="css/style.css?v=<?php echo $cssVer; ?>">
<?php if (($activePage ?? '') === 'home'): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LodgingBusiness",
  "name": "Quattro Homes",
  "description": "Exclusive, fully-furnished 2-bedroom apartments for short and long stays in Bungoma, Kenya.",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Bungoma",
    "addressCountry": "KE"
  },
  "telephone": "+<?php echo htmlspecialchars($settings['whatsapp_number'] ?? ''); ?>",
  "priceRange": "<?php echo htmlspecialchars($settings['currency'] ?? 'KES'); ?> <?php echo htmlspecialchars($settings['price_per_night'] ?? ''); ?>",
  "url": "<?php echo htmlspecialchars($siteUrlBase); ?>"
}
</script>
<?php endif; ?>
