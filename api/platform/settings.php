<?php
/**
 * Platform branding settings — super admin only
 */
ini_set('display_errors', '0');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/SettingsManager.php';
require_once __DIR__ . '/../../includes/TenantManager.php';

requirePlatformSuperAdminApi();

$manager = new SettingsManager();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    if ($method === 'GET') {
        $vars = TenantManager::getPlatformBrandingVars();
        $raw = TenantManager::getPlatformBranding();
        echo json_encode([
            'app_name' => $vars['appName'],
            'app_tagline' => $vars['appTagline'],
            'logo_url' => $raw['logo_url'] ?? '',
            'favicon_url' => $raw['favicon_url'] ?? '',
            'public_logo_url' => $vars['publicLogoUrl'],
            'favicon_public_url' => $vars['faviconUrl'],
            'updated_at' => $raw['updated_at'] ?? null,
        ]);
        exit;
    }

    if ($method === 'PUT') {
        $key = $input['key'] ?? null;
        $value = $input['value'] ?? null;

        if (!$key || $value === null) {
            http_response_code(400);
            echo json_encode(['message' => 'Key and value are required']);
            exit;
        }

        $allowed = ['app_name', 'app_tagline', 'logo_url', 'favicon_url'];
        if (!in_array($key, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid branding key']);
            exit;
        }

        TenantManager::updatePlatformBranding($key, $value);
        $vars = TenantManager::getPlatformBrandingVars();

        echo json_encode([
            'success' => true,
            'key' => $key,
            'app_name' => $vars['appName'],
            'app_tagline' => $vars['appTagline'],
            'public_logo_url' => $vars['publicLogoUrl'],
            'favicon_public_url' => $vars['faviconUrl'],
            'updated_at' => date('c'),
        ]);
        exit;
    }

    if ($method === 'POST' && isset($_FILES['file'])) {
        $type = $_GET['type'] ?? 'logo';

        if ($type === 'logo') {
            $logo = $manager->uploadImage($_FILES['file'], 'logo');
            $favicon = $manager->uploadImage($_FILES['file'], 'favicon');
            TenantManager::updatePlatformBranding('logo_url', $logo);
            TenantManager::updatePlatformBranding('favicon_url', $favicon);
            $vars = TenantManager::getPlatformBrandingVars();
            echo json_encode([
                'success' => true,
                'type' => $type,
                'public_logo_url' => $vars['publicLogoUrl'],
                'favicon_public_url' => $vars['faviconUrl'],
            ]);
            exit;
        }

        $base64 = $manager->uploadImage($_FILES['file'], 'favicon');
        TenantManager::updatePlatformBranding('favicon_url', $base64);
        $vars = TenantManager::getPlatformBrandingVars();
        echo json_encode([
            'success' => true,
            'type' => $type,
            'favicon_public_url' => $vars['faviconUrl'],
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => $e->getMessage()]);
}
