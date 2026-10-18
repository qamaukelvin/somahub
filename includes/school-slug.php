<?php
/** The school a request is for: ?school=slug, else the subdomain it was served on. */
function request_school_slug(): string {
    $slug = strtolower(trim($_GET['school'] ?? ''));
    if ($slug === '' && preg_match('/^([a-z0-9-]+)\.somahub\.top$/i', $_SERVER['HTTP_HOST'] ?? '', $m) && strtolower($m[1]) !== 'www') {
        $slug = strtolower($m[1]);
    }
    return $slug;
}
