<?php
/**
 * Authentication and Session management for PHP
 */

require_once 'config.php';
require_once 'JsonDB.php';
require_once 'TenantManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => SESSION_LIFETIME,
        'cookie_httponly' => true,
        'use_strict_mode' => true,
    ]);
}

TenantManager::bootstrap();
TenantManager::applySessionTenant();

/**
 * Super Admin Protection Constants
 */
define('SUPER_ADMIN_ID', '69c45a78ba1175fcacd0abd3');


/**
 * Check if a user is logged in
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

/**
 * Require authentication for a page
 */
function requireAuth($roles = [], $requiredPermission = null) {
    if (!isAuthenticated()) {
        header('Location: /login.php');
        exit;
    }

    if (!empty($_SESSION['is_platform_super_admin'])) {
        header('Location: /platform-admin.php');
        exit;
    }

    if (!empty($roles) || $requiredPermission !== null) {
        $userRole = $_SESSION['role'] ?? '';
        $userPermissions = $_SESSION['permissions'] ?? [];

        // Admin always has access to everything
        if ($userRole === 'admin') {
            return;
        }

        $roleMatch = !empty($roles) && in_array($userRole, $roles);
        
        $permissionMatch = false;
        if ($requiredPermission !== null) {
            $requiredPermissions = is_array($requiredPermission) ? $requiredPermission : [$requiredPermission];
            foreach ($requiredPermissions as $rp) {
                if (in_array($rp, $userPermissions)) {
                    $permissionMatch = true;
                    break;
                }
            }
        }

        if (!$roleMatch && !$permissionMatch) {
            header('Location: /unauthorized.php');
            exit;
        }
    }

    // Refresh user data to check if account is active
    try {
        $user = db('users')->findUnique([
            'where' => ['id' => $_SESSION['user_id']]
        ]);

        if (!$user || (isset($user['isActive']) && $user['isActive'] === false)) {
            logout();
            header('Location: /login.php?error=deactivated');
            exit;
        }
    } catch (Exception $e) {
        // If DB is unreachable, trust session but log error
        error_log("Auth DB check failed: " . $e->getMessage());
    }
}

/**
 * Require authentication for JSON API endpoints (returns JSON instead of redirecting)
 */
function requireApiAuth($roles = [], $requiredPermission = null) {
    header('Content-Type: application/json');

    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    if (!empty($_SESSION['is_platform_super_admin'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Platform admin cannot access tenant APIs']);
        exit;
    }

    if (!empty($roles) || $requiredPermission !== null) {
        $userRole = $_SESSION['role'] ?? '';
        $userPermissions = $_SESSION['permissions'] ?? [];

        // Admin always has access to everything
        if ($userRole === 'admin') {
            return;
        }

        $roleMatch = !empty($roles) && in_array($userRole, $roles);
        
        $permissionMatch = false;
        if ($requiredPermission !== null) {
            $requiredPermissions = is_array($requiredPermission) ? $requiredPermission : [$requiredPermission];
            foreach ($requiredPermissions as $rp) {
                if (in_array($rp, $userPermissions)) {
                    $permissionMatch = true;
                    break;
                }
            }
        }

        if (!$roleMatch && !$permissionMatch) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }
    }

    try {
        $user = db('users')->findUnique([
            'where' => ['id' => $_SESSION['user_id']]
        ]);

        if (!$user || (isset($user['isActive']) && $user['isActive'] === false)) {
            logout();
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Account deactivated']);
            exit;
        }
    } catch (Exception $e) {
        error_log("Auth DB check failed: " . $e->getMessage());
    }
}

/**
 * Attempt to log in a tenant user (hotel + username + password)
 */
function login($hotelName, $username, $password) {
    try {
        $result = TenantManager::loginWithCredentials($hotelName, $username, $password);
        if (!$result['success']) {
            return $result;
        }

        TenantManager::setSessionTenant($result['tenant'], $result['user']);
        return ['success' => true, 'user' => $result['user']];
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
    }

    return ['success' => false, 'message' => 'Invalid username or password'];
}

/**
 * Attempt to log in the platform super admin (username + password only)
 */
function loginSuperAdmin($username, $password) {
    try {
        $result = TenantManager::loginSuperAdmin($username, $password);
        if (!$result['success']) {
            return $result;
        }

        TenantManager::setSessionSuperAdmin($result['admin']);
        return ['success' => true, 'admin' => $result['admin']];
    } catch (Exception $e) {
        error_log("Super admin login error: " . $e->getMessage());
    }

    return ['success' => false, 'message' => 'Invalid username or password'];
}

/**
 * Require platform super admin session
 */
function requirePlatformSuperAdmin() {
    if (!isAuthenticated() || empty($_SESSION['is_platform_super_admin'])) {
        header('Location: /super-admin-login.php');
        exit;
    }
}

/**
 * Require platform super admin for JSON API endpoints
 */
function requirePlatformSuperAdminApi() {
    header('Content-Type: application/json');
    if (!isAuthenticated() || empty($_SESSION['is_platform_super_admin'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
}

/**
 * Log out the current user
 */
function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Get the current user data
 */
function getCurrentUser() {
    if (!isAuthenticated()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['name'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'],
        'permissions' => $_SESSION['permissions'] ?? [],
        'floorId' => $_SESSION['floorId'] ?? null,
        'tenant_id' => $_SESSION['tenant_id'] ?? null,
        'tenant_slug' => $_SESSION['tenant_slug'] ?? null,
        'tenant_name' => $_SESSION['tenant_name'] ?? null,
        'is_platform_super_admin' => !empty($_SESSION['is_platform_super_admin']),
    ];
}

/**
 * Check if the current user has a specific permission
 */
function hasPermission($permission) {
    if (!isAuthenticated()) return false;
    if (($_SESSION['role'] ?? '') === 'admin') return true;
    $permissions = $_SESSION['permissions'] ?? [];
    if (!is_array($permissions)) {
        $permissions = is_string($permissions) ? (json_decode($permissions, true) ?: []) : [];
    }
    return in_array($permission, $permissions);
}

/**
 * Check if the current user or a specific ID is the protected Super Admin
 */
function isSuperAdmin($userId = null) {
    if (!empty($_SESSION['is_platform_super_admin'])) {
        return true;
    }
    if ($userId === null) {
        if (!isAuthenticated()) return false;
        $userId = $_SESSION['user_id'];
    }
    return $userId === SUPER_ADMIN_ID;
}


/**
 * Check if the current user has any permission matching a pattern (e.g. 'reports:*')
 */
function hasPermissionPattern($pattern) {
    if (!isAuthenticated()) return false;
    if (($_SESSION['role'] ?? '') === 'admin') return true;
    $permissions = $_SESSION['permissions'] ?? [];
    if (!is_array($permissions)) {
        $permissions = is_string($permissions) ? (json_decode($permissions, true) ?: []) : [];
    }
    return !empty(preg_grep($pattern, $permissions));
}

/**
 * Redirect target after login based on user role
 */
function routeUserBasedOnRole($role) {
    if (!empty($_SESSION['is_platform_super_admin']) || $role === 'platform_super_admin') {
        return 'platform-admin.php';
    }

    switch ($role) {
        case 'admin':
            return 'admin.php';
        case 'cashier':
            return 'cashier.php';
        case 'chef':
            return 'chef.php';
        case 'bar':
            return 'bar.php';
        case 'store_keeper':
        case 'store':
            return 'store.php';
        case 'receptionist':
        case 'reception':
            return 'reception.php';
        case 'display':
            return 'display.php';
        case 'custom':
            $user = getCurrentUser();
            $perms = $user['permissions'] ?? [];

            if (in_array('cashier:access', $perms)) return 'cashier.php';
            if (in_array('chef:access', $perms)) return 'chef.php';
            if (in_array('bar:access', $perms)) return 'bar.php';
            if (in_array('reception:access', $perms)) return 'reception.php';
            if (in_array('display:access', $perms)) return 'display.php';
            if (in_array('overview:view', $perms)) return 'admin.php';
            if (hasPermissionPattern('/^orders:/')) return 'orders.php';
            if (hasPermissionPattern('/^reports:/')) return 'reports.php';
            if (hasPermissionPattern('/^stock:/')) return 'stock.php';
            if (hasPermissionPattern('/^store:/')) return 'store.php';
            if (hasPermissionPattern('/^users:/')) return 'staff.php';
            if (hasPermissionPattern('/^services:/')) return 'services.php';
            if (hasPermissionPattern('/^settings:/')) return 'settings.php';

            return 'index.php';
        default:
            return 'index.php';
    }
}
