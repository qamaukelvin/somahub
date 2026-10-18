<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/private-storage.php';
require_platform_admin();
serve_private_file($_GET['f'] ?? '', null);
