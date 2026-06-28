<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/TenantManager.php';

$slug = $_GET['slug'] ?? '';
$slug = TenantManager::slugify($slug);

if ($slug === '' || strlen($slug) < 3) {
    echo json_encode(['available' => false, 'message' => 'URL must be at least 3 characters']);
    exit;
}

if (!TenantManager::isValidSlug($slug)) {
    echo json_encode(['available' => false, 'message' => 'Invalid URL format']);
    exit;
}

$available = TenantManager::isSlugAvailable($slug);
echo json_encode([
    'available' => $available,
    'slug' => $slug,
    'message' => $available ? 'Available' : 'This URL is already taken',
]);
