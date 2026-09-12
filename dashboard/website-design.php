<?php
// Retired as a standalone page - template/color/section design now lives
// directly in sections.php (the "🎨 Design" button + panel), matching the
// same accordion/inline-edit patterns as the rest of content editing,
// instead of a separate page duplicating the same choices.
require_once __DIR__ . '/../includes/auth.php';
require_school_login();
$firstTime = isset($_GET['welcome']);
header('Location: sections.php?design=1' . ($firstTime ? '&welcome=1' : ''));
exit;
