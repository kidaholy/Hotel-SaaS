<?php
/**
 * API for User/Staff listing
 */
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../includes/JsonDB.php';

function sendJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    requireAuth(['admin', 'cashier', 'receptionist', 'reception'], ['services:view', 'users:view']);
} else {
    requireAuth(['admin'], ['users:create', 'users:update', 'users:delete']);
}

try {
    $db = db('users');

    if ($method === 'GET') {
        $fullMode = isset($_GET['full']) && $_GET['full'] === '1';
        $users = $db->findMany(['where' => ['isDeleted' => false]]);

        if ($fullMode) {
            // Full data for the staff admin grid — exclude hashed password
            $result = [];
            foreach ($users as $u) {
                // HIDE SUPER ADMIN from others (but allow them to see themselves)
                if ($u['id'] === SUPER_ADMIN_ID && !isSuperAdmin()) {
                    continue;
                }
                unset($u['password']);
                $result[] = $u;
            }
            sendJson($result);
        } else {
            // Minimal data for assignment dropdowns
            $minimal = [];
            foreach ($users as $u) {
                if ($u['id'] === SUPER_ADMIN_ID && !isSuperAdmin()) {
                    continue;
                }
                $minimal[] = ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role']];
            }
            sendJson(['status' => 'success', 'data' => $minimal]);
        }
    }

    if ($method === 'POST') {
        requireAuth(['admin'], 'users:create');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!TenantManager::canAddStaff()) {
            sendJson(['message' => TenantManager::getStaffLimitMessage()], 403);
        }

        $role = $data['role'] ?? 'cashier';
        if ($role === 'custom' && !tenantHasFeature('custom_permissions')) {
            sendJson(['message' => 'Custom roles require the Premium plan.'], 403);
        }

        $password = $data['password'] ?? '';
        $username = TenantManager::normalizeUsername($data['username'] ?? '');
        $hashed = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : '';

        if ($username === '' || !TenantManager::isValidUsername($username)) {
            sendJson(['message' => 'Valid username is required (3–32 characters)'], 400);
        }

        $tenantId = $_SESSION['tenant_id'] ?? TenantManager::DEFAULT_TENANT_ID;
        if (!TenantManager::isUsernameAvailableInTenant($tenantId, $username)) {
            sendJson(['message' => 'Username already taken in this hotel'], 400);
        }

        $created = $db->create(['data' => [
            'name'               => $data['name'],
            'username'           => $username,
            'email'              => $data['email'] ?? '',
            'password'           => $hashed,
            'plainPassword'      => $password,
            'role'               => $data['role'] ?? 'cashier',
            'isActive'           => true,
            'floorId'            => $data['floorId'] ?? null,
            'assignedCategories' => $data['assignedCategories'] ?? [],
            'permissions'        => $data['permissions'] ?? [],
        ]]);

        $existingMap = platformDb('tenant_users')->findFirst([
            'where' => [
                'tenant_id' => $tenantId,
                'user_id' => $created['id'],
            ],
        ]);
        if (!$existingMap) {
            platformDb('tenant_users')->create(['data' => [
                'tenant_id' => $tenantId,
                'user_id' => $created['id'],
                'username' => $username,
                'email' => strtolower($data['email'] ?? ''),
                'name' => $data['name'],
                'role' => $data['role'] ?? 'cashier',
                'created_at' => date('c'),
            ]]);
        }

        sendJson(['message' => 'User created', 'credentials' => ['username' => $username, 'password' => $password]], 201);
    }

    if ($method === 'PUT') {
        requireAuth(['admin'], 'users:update');
        $id = $_GET['id'] ?? null;
        if (!$id) sendJson(['message' => 'ID required'], 400);
        $data = json_decode(file_get_contents('php://input'), true);

        // If just toggling active status
        if (isset($data['isActive']) && count($data) === 1) {
            // PROTECT SUPER ADMIN from deactivation by OTHERS
            if ($id === SUPER_ADMIN_ID && !isSuperAdmin()) {
                sendJson(['message' => 'Super Admin cannot be deactivated'], 403);
            }
            $db->update(['where' => ['id' => $id], 'data' => ['isActive' => $data['isActive']]]);
            sendJson(['message' => 'Status updated']);
        }

        $update = [];
        if (!empty($data['name']))    $update['name']               = $data['name'];
        if (!empty($data['username'])) {
            $username = TenantManager::normalizeUsername($data['username']);
            if (!TenantManager::isValidUsername($username)) {
                sendJson(['message' => 'Invalid username'], 400);
            }
            $tenantId = $_SESSION['tenant_id'] ?? TenantManager::DEFAULT_TENANT_ID;
            $conflict = platformDb('tenant_users')->findFirst([
                'where' => [
                    'tenant_id' => $tenantId,
                    'username' => ['mode' => 'insensitive', 'equals' => $username],
                    'user_id' => ['not' => $id],
                ],
            ]);
            if ($conflict) {
                sendJson(['message' => 'Username already taken in this hotel'], 400);
            }
            $update['username'] = $username;
        }
        if (array_key_exists('email', $data))   $update['email']              = $data['email'];
        if (!empty($data['role']))    $update['role']               = $data['role'];
        if (isset($data['assignedCategories'])) $update['assignedCategories'] = $data['assignedCategories'];

        $nextRole = $update['role'] ?? null;
        if ($nextRole === 'custom' && !tenantHasFeature('custom_permissions')) {
            sendJson(['message' => 'Custom roles require the Premium plan.'], 403);
        }
        if (isset($data['permissions']) && !tenantHasFeature('custom_permissions')) {
            sendJson(['message' => 'Custom permissions require the Premium plan.'], 403);
        }
        if (array_key_exists('floorId', $data)) $update['floorId']  = $data['floorId'];
        if (isset($data['permissions']))        $update['permissions']        = $data['permissions'];

        if (!empty($data['password'])) {
            $update['password']      = password_hash($data['password'], PASSWORD_BCRYPT);
            $update['plainPassword'] = $data['password'];
        }

        $db->update(['where' => ['id' => $id], 'data' => $update]);

        if (!empty($update['username'])) {
            $tenantId = $_SESSION['tenant_id'] ?? TenantManager::DEFAULT_TENANT_ID;
            $mapped = platformDb('tenant_users')->findFirst([
                'where' => ['tenant_id' => $tenantId, 'user_id' => $id],
            ]);
            if ($mapped) {
                platformDb('tenant_users')->update([
                    'where' => ['id' => $mapped['id']],
                    'data' => ['username' => $update['username']],
                ]);
            }
        }

        sendJson(['message' => 'User updated']);
    }

    if ($method === 'DELETE') {
        requireAuth(['admin'], 'users:delete');
        $id = $_GET['id'] ?? null;
        if (!$id) sendJson(['message' => 'ID required'], 400);

        // PROTECT SUPER ADMIN from deletion
        if ($id === SUPER_ADMIN_ID) {
            sendJson(['message' => 'Super Admin cannot be deleted'], 403);
        }

        $db->update(['where' => ['id' => $id], 'data' => ['isDeleted' => true]]);
        sendJson(['message' => 'User deleted']);
    }

} catch (Exception $e) {
    sendJson(['status' => 'error', 'message' => $e->getMessage()], 500);
}
