<?php
/**
 * Image handling for uploads: resize to a sensible maximum and re-encode as
 * WebP (JPEG fallback). A 6 MB phone photo becomes a few hundred KB, which is
 * the single biggest speed win for school sites on mobile data.
 */
const IMG_MAX_DIM = 1600;      // longest side in px; plenty for full-width heroes
const IMG_QUALITY = 82;

/** Load an image file into a GD resource, honouring EXIF rotation. Null if unsupported. */
function img_load(string $path, string $mime) {
    switch ($mime) {
        case 'image/jpeg': $im = @imagecreatefromjpeg($path); break;
        case 'image/png':  $im = @imagecreatefrompng($path); break;
        case 'image/webp': $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false; break;
        default: return null;
    }
    if (!$im) return null;
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $o = (int)($exif['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) { $r = imagerotate($im, $rot, 0); if ($r) { imagedestroy($im); $im = $r; } }
    }
    return $im;
}

/** Shrink so the longest side is <= $max. Returns the (possibly new) resource. */
function img_fit($im, int $max = IMG_MAX_DIM) {
    $w = imagesx($im); $h = imagesy($im);
    if (max($w, $h) <= $max) return $im;
    $scale = $max / max($w, $h);
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false); imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($im);
    return $out;
}

/**
 * Move an uploaded image into $destDir, optimised. $safeName is the wanted
 * filename; the extension may change (to .webp / .jpg). Returns the final
 * filename. Falls back to a plain move if the image can't be processed, so an
 * upload never fails because of optimisation.
 */
function store_optimized_image(string $tmpPath, string $destDir, string $safeName, string $mime): string {
    $base = pathinfo($safeName, PATHINFO_FILENAME);
    $im = function_exists('imagecreatefromjpeg') ? img_load($tmpPath, $mime) : null;
    if ($im) {
        $im = img_fit($im);
        if (function_exists('imagewebp')) {
            $final = $base . '.webp';
            if (@imagewebp($im, $destDir . $final, IMG_QUALITY)) { imagedestroy($im); return $final; }
        }
        $final = $base . '.jpg';
        $flat = imagecreatetruecolor(imagesx($im), imagesy($im));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
        imagedestroy($im);
        if (@imagejpeg($flat, $destDir . $final, IMG_QUALITY)) { imagedestroy($flat); return $final; }
        imagedestroy($flat);
    }
    move_uploaded_file($tmpPath, $destDir . $safeName);
    return $safeName;
}
