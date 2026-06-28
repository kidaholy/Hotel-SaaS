<?php
/**
 * Import menu, category, and store lists from legacy cloud database.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/CloudImport.php';
require_once __DIR__ . '/../../includes/TenantManager.php';

requireApiAuth(['admin'], ['settings:update', 'services:update', 'store:view', 'stock:view']);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$platform = TenantManager::getPlatformBrandingVars();

try {
    if ($method === 'GET') {
        echo json_encode([
            'success' => true,
            'platform_name' => $platform['appName'],
            'available' => CloudImport::canImport(),
            'is_legacy_tenant' => CloudImport::isActiveTenantLegacy(),
            'legacy_path' => basename(CloudImport::getLegacyPath()),
            'counts' => CloudImport::getStats(),
        ]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        exit;
    }

    if (!CloudImport::canImport()) {
        http_response_code(400);
        echo json_encode([
            'message' => CloudImport::isActiveTenantLegacy()
                ? 'This hotel already uses the cloud database.'
                : 'Cloud database is not available.',
        ]);
        exit;
    }

    $scope = $input['scope'] ?? $_GET['scope'] ?? '';

    switch ($scope) {
        case 'categories':
            $type = $input['type'] ?? $_GET['type'] ?? 'menu';
            $result = CloudImport::importCategories($type);
            break;

        case 'menus':
            $collection = $input['collection'] ?? $_GET['collection'] ?? 'menuItems';
            $result = CloudImport::importMenus($collection);
            break;

        case 'stocks':
            $result = CloudImport::importStocks();
            break;

        default:
            http_response_code(400);
            echo json_encode(['message' => 'Invalid import scope']);
            exit;
    }

    echo json_encode([
        'success' => true,
        'platform_name' => $platform['appName'],
        'scope' => $scope,
        'result' => $result,
        'message' => sprintf(
            'Imported %d item(s), skipped %d duplicate(s).',
            $result['imported'] ?? 0,
            $result['skipped'] ?? 0
        ),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => $e->getMessage()]);
}
