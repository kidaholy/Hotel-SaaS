<?php
/**
 * Platform Super Admin — Hotel/Tenant CRUD API
 */
require_once '../../includes/auth.php';
require_once '../../includes/JsonDB.php';

requirePlatformSuperAdminApi();

function sendJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

try {
    if ($method === 'GET') {
        if ($id) {
            $tenant = TenantManager::getTenant($id);
            if (!$tenant || !empty($tenant['isDeleted'])) {
                sendJson(['status' => 'error', 'message' => 'Hotel not found'], 404);
            }
            sendJson(['status' => 'success', 'data' => array_merge($tenant, TenantManager::getTenantOwnerInfo($tenant))]);
        }

        sendJson(['status' => 'success', 'data' => TenantManager::listTenantsForPlatform()]);
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $hotelName = trim($data['name'] ?? $data['hotel_name'] ?? '');
        $slug = trim($data['slug'] ?? '');
        $ownerName = trim($data['owner_name'] ?? '');
        $username = trim($data['owner_username'] ?? $data['username'] ?? '');
        $password = $data['owner_password'] ?? $data['password'] ?? '';
        $plan = PlanFeatures::normalizePlan(trim($data['plan'] ?? 'starter'));

        $res = TenantManager::registerTenant($hotelName, $slug, $ownerName, $username, $password, '', $plan);
        if (!$res['success']) {
            sendJson(['status' => 'error', 'message' => $res['message']], 400);
        }

        $tenantId = $res['tenant']['id'];
        $tenant = TenantManager::getTenant($tenantId);
        sendJson([
            'status' => 'success',
            'message' => 'Hotel created',
            'data' => array_merge($tenant, TenantManager::getTenantOwnerInfo($tenant)),
        ], 201);
    }

    if ($method === 'PUT') {
        if (!$id) {
            sendJson(['status' => 'error', 'message' => 'Hotel ID required'], 400);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        if (($data['action'] ?? '') === 'verify_password') {
            $res = TenantManager::verifyAndStoreOwnerPassword($id, $data['password'] ?? '');
            if (!$res['success']) {
                sendJson(['status' => 'error', 'message' => $res['message']], 400);
            }
            $tenant = TenantManager::getTenant($id);
            sendJson([
                'status' => 'success',
                'message' => $res['message'],
                'data' => array_merge($tenant, TenantManager::getTenantOwnerInfo($tenant)),
            ]);
        }

        if (($data['action'] ?? '') === 'confirm_payment') {
            $months = (int)($data['months'] ?? 1);
            $res = TenantManager::confirmTenantPayment($id, $months);
            if (!$res['success']) {
                sendJson(['status' => 'error', 'message' => $res['message']], 400);
            }
            $tenant = TenantManager::getTenant($id);
            sendJson([
                'status' => 'success',
                'message' => 'Payment confirmed. Plan extended.',
                'data' => array_merge($tenant, TenantManager::getTenantOwnerInfo($tenant)),
            ]);
        }

        $res = TenantManager::updateTenantForPlatform($id, $data);
        if (!$res['success']) {
            sendJson(['status' => 'error', 'message' => $res['message']], 400);
        }

        sendJson(['status' => 'success', 'message' => 'Hotel updated', 'data' => $res['tenant']]);
    }

    if ($method === 'DELETE') {
        if (!$id) {
            sendJson(['status' => 'error', 'message' => 'Hotel ID required'], 400);
        }

        $res = TenantManager::deleteTenantForPlatform($id);
        if (!$res['success']) {
            sendJson(['status' => 'error', 'message' => $res['message']], 400);
        }

        sendJson(['status' => 'success', 'message' => $res['message']]);
    }

    sendJson(['status' => 'error', 'message' => 'Method not allowed'], 405);
} catch (Exception $e) {
    sendJson(['status' => 'error', 'message' => $e->getMessage()], 500);
}
