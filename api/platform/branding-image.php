<?php
/**
 * Serve platform branding images (logo, favicon) for the main website.
 */
require_once __DIR__ . '/../../includes/TenantManager.php';

$type = $_GET['type'] ?? 'logo';
$branding = TenantManager::getPlatformBranding();

$image = '';
if ($type === 'favicon') {
    $image = $branding['favicon_url'] ?? $branding['logo_url'] ?? '';
} else {
    $image = $branding['logo_url'] ?? '';
}

if ($image === '') {
    http_response_code(404);
    echo 'Image not found';
    exit;
}

if (preg_match('#^data:(image/[^;]+);base64,(.+)$#s', $image, $m)) {
    $binary = base64_decode($m[2], true);
    if ($binary === false) {
        http_response_code(500);
        exit;
    }
    header('Content-Type: ' . $m[1]);
    header('Cache-Control: public, max-age=604800');
    header('Content-Length: ' . strlen($binary));
    echo $binary;
    exit;
}

if (preg_match('#^https?://#i', $image)) {
    header('Location: ' . $image, true, 302);
    exit;
}

$baseDir = dirname(__DIR__, 2);
$path = ltrim($image, '/');
$full = $baseDir . '/' . $path;

if (is_file($full)) {
    $mime = mime_content_type($full) ?: 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=604800');
    readfile($full);
    exit;
}

http_response_code(404);
echo 'Image data not found or invalid format';
