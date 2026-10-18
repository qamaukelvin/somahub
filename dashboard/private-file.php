<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/private-storage.php';
$user = require_school_login();
serve_private_file($_GET['f'] ?? '', (int)$user['school_id']);
