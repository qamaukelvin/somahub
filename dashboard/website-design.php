<?php
// Retired as a standalone page. Template/color selection is deferred for
// now (auto-assigned a sensible default at signup); per-section design
// options (currently just Hero's layout) live directly in sections.php,
// via the palette icon on each eligible section row.
require_once __DIR__ . '/../includes/auth.php';
require_school_login();
header('Location: sections.php');
exit;
