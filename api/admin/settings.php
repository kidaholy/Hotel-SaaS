<?php
// api/admin/settings.php

ini_set('display_errors', '0');
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/SettingsManager.php';
require_once __DIR__ . '/../../includes/auth.php';

$manager = new SettingsManager();

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        requireApiAuth(['admin'], 'settings:view');
    } else {
        requireApiAuth(['admin'], 'settings:update');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // GET: Fetch all settings
    if ($method === 'GET') {
        $branding = $manager->getBranding();
        $config = $manager->getSetting('configuration') ?? [];
        
        $response = [
            'app_name' => $branding['app_name'] ?? 'ABE HOTEL',
            'app_tagline' => $branding['app_tagline'] ?? 'HOTEL MANAGEMENT SYSTEM',
            'vat_rate' => $config['vat_rate'] ?? 0.08,
            'enable_cashier_printing' => $config['enable_cashier_printing'] ?? true,
            'enable_cashier_today_revenue' => $config['enable_cashier_today_revenue'] ?? false
        ];
        
        echo json_encode($response);
    }
    
    // PUT: Update a setting
    else if ($method === 'PUT') {
        $key = $input['key'] ?? null;
        $value = $input['value'] ?? null;
        $type = $input['type'] ?? 'string';

        if (!$key || $value === null) {
            http_response_code(400);
            echo json_encode(['message' => 'Key and value are required']);
            exit;
        }

        $brandingKeys = ['app_name', 'app_tagline'];
        $protectedBrandingKeys = ['logo_url', 'favicon_url'];

        if (in_array($key, $protectedBrandingKeys, true)) {
            http_response_code(403);
            echo json_encode(['message' => 'Logo and favicon are managed by the platform administrator']);
            exit;
        }

        $section = in_array($key, $brandingKeys, true) ? 'branding' : 'configuration';

        // Type conversion
        if ($type === 'boolean') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } else if ($type === 'number') {
            $value = (float)$value;
        }

        $manager->updateSetting($section, $key, $value);

        $response = [
            'success' => true,
            'key' => $key,
            'type' => $type,
            'updated_at' => date('c')
        ];
        if (!in_array($key, ['logo_url', 'favicon_url'], true)) {
            $response['value'] = $value;
        }
        echo json_encode($response);
    }
    
    // POST: Upload image (disabled for tenants — platform admin only)
    else if ($method === 'POST' && isset($_FILES['file'])) {
        http_response_code(403);
        echo json_encode(['message' => 'Logo and favicon are managed by the platform administrator']);
    }
    
    else {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => $e->getMessage()]);
}
