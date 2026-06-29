<?php
/**
 * Multi-tenant SaaS management — platform registry + per-tenant databases.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/JsonDB.php';
require_once __DIR__ . '/SettingsManager.php';
require_once __DIR__ . '/PlanFeatures.php';

class TenantManager {
    const DEFAULT_TENANT_ID = 'default';
    const PLATFORM_APP_NAME = 'Hotel SaaS';
    const PLATFORM_TAGLINE = 'Hotel Management Platform';

    public static function bootstrap() {
        self::ensureSuperAdmin();
        self::ensurePlatformSettings();
        self::ensureDefaultTenant();
        self::syncAllTenantUsernames();
        self::backfillOwnerPlainPasswords();
    }

    public static function normalizeUsername($username) {
        return strtolower(trim($username));
    }

    public static function isValidUsername($username) {
        return (bool) preg_match('/^[a-z0-9][a-z0-9._-]{1,30}[a-z0-9]$/', self::normalizeUsername($username));
    }

    public static function findTenantByHotelInput($hotelInput) {
        $hotelInput = trim($hotelInput);
        if ($hotelInput === '') {
            return null;
        }

        $slug = self::slugify($hotelInput);
        $tenant = platformDb('tenants')->findUnique(['where' => ['slug' => $slug]]);
        if ($tenant) {
            return $tenant;
        }

        $tenants = platformDb('tenants')->findMany();
        $needle = strtolower($hotelInput);
        foreach ($tenants as $t) {
            if (strtolower($t['name'] ?? '') === $needle) {
                return $t;
            }
        }

        return null;
    }

    public static function isUsernameAvailableInTenant($tenantId, $username) {
        $username = self::normalizeUsername($username);
        if (!self::isValidUsername($username)) {
            return false;
        }
        $existing = platformDb('tenant_users')->findFirst([
            'where' => [
                'tenant_id' => $tenantId,
                'username' => ['mode' => 'insensitive', 'equals' => $username],
            ],
        ]);
        return $existing === null;
    }

    private static function ensureSuperAdmin() {
        $username = strtolower(trim(PLATFORM_SUPER_ADMIN_USERNAME));
        $passwordHash = password_hash(PLATFORM_SUPER_ADMIN_PASSWORD, PASSWORD_BCRYPT);

        $existing = platformDb('platform_admins')->findFirst([
            'where' => ['username' => ['mode' => 'insensitive', 'equals' => $username]],
        ]);

        if ($existing) {
            platformDb('platform_admins')->update([
                'where' => ['id' => $existing['id']],
                'data' => ['password' => $passwordHash],
            ]);
            return;
        }

        $legacy = platformDb('platform_admins')->findFirst([
            'where' => ['username' => ['mode' => 'insensitive', 'equals' => 'superadmin']],
        ]);
        if ($legacy) {
            platformDb('platform_admins')->update([
                'where' => ['id' => $legacy['id']],
                'data' => [
                    'username' => $username,
                    'password' => $passwordHash,
                    'name' => 'Platform Super Admin',
                ],
            ]);
            return;
        }

        platformDb('platform_admins')->create(['data' => [
            'id' => bin2hex(random_bytes(12)),
            'username' => $username,
            'password' => $passwordHash,
            'name' => 'Platform Super Admin',
            'isActive' => true,
            'created_at' => date('c'),
        ]]);
    }

    public static function loginSuperAdmin($username, $password) {
        $username = strtolower(trim($username));
        $admin = platformDb('platform_admins')->findFirst([
            'where' => ['username' => ['mode' => 'insensitive', 'equals' => $username]],
        ]);

        if (!$admin || !password_verify($password, $admin['password'] ?? '')) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        if (isset($admin['isActive']) && $admin['isActive'] === false) {
            return ['success' => false, 'message' => 'Account deactivated'];
        }

        return ['success' => true, 'admin' => $admin];
    }

    public static function setSessionSuperAdmin($admin) {
        SqliteDB::resetActivePath();
        $_SESSION['is_platform_super_admin'] = true;
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['name'] = $admin['name'] ?? 'Super Admin';
        $_SESSION['role'] = 'platform_super_admin';
        $_SESSION['permissions'] = ['*'];
        unset($_SESSION['tenant_id'], $_SESSION['tenant_slug'], $_SESSION['tenant_name'], $_SESSION['email'], $_SESSION['floorId']);
    }

    public static function applySessionTenant() {
        if (session_status() === PHP_SESSION_NONE) {
            return;
        }
        $tenantId = $_SESSION['tenant_id'] ?? null;
        if ($tenantId) {
            self::switchToTenant($tenantId);
        }
    }

    public static function switchToTenant($tenantId) {
        $tenant = platformDb('tenants')->findUnique(['where' => ['id' => $tenantId]]);
        if (!$tenant || empty($tenant['db_path']) || !file_exists($tenant['db_path'])) {
            throw new Exception('Tenant not found');
        }
        SqliteDB::setActivePath($tenant['db_path']);
        return $tenant;
    }

    public static function getTenantDbPath($tenantId) {
        return TENANTS_DIR . '/' . $tenantId . '/database.sqlite';
    }

    public static function slugify($text) {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return substr($slug, 0, 48);
    }

    public static function isValidSlug($slug) {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{1,46}[a-z0-9]$/', $slug);
    }

    public static function isSlugAvailable($slug) {
        if (!self::isValidSlug($slug)) {
            return false;
        }
        $existing = platformDb('tenants')->findUnique(['where' => ['slug' => $slug]]);
        return $existing === null;
    }

    public static function isEmailAvailable($email) {
        $email = strtolower(trim($email));
        $existing = platformDb('tenant_users')->findUnique([
            'where' => ['email' => ['mode' => 'insensitive', 'equals' => $email]]
        ]);
        return $existing === null;
    }

    public static function findAccountsByEmail($email) {
        $email = strtolower(trim($email));
        return platformDb('tenant_users')->findMany([
            'where' => ['email' => ['mode' => 'insensitive', 'equals' => $email]]
        ]);
    }

    public static function getTenant($tenantId) {
        return platformDb('tenants')->findUnique(['where' => ['id' => $tenantId]]);
    }

    public static function isTenantActive($tenantOrId) {
        $tenant = is_array($tenantOrId) ? $tenantOrId : self::getTenant($tenantOrId);
        if (!$tenant || !empty($tenant['isDeleted'])) {
            return false;
        }
        return ($tenant['status'] ?? 'active') === 'active';
    }

    public static function getCurrentPlan() {
        $tenantId = $_SESSION['tenant_id'] ?? null;
        if (!$tenantId) {
            return 'starter';
        }

        $tenant = self::getTenant($tenantId);
        if (!$tenant) {
            return 'starter';
        }

        $plan = PlanFeatures::normalizePlan($tenant['plan'] ?? 'starter');
        $_SESSION['tenant_plan'] = $plan;
        return $plan;
    }

    public static function tenantHasFeature($feature) {
        return PlanFeatures::hasFeature(self::getCurrentPlan(), $feature);
    }

    public static function getStaffCount() {
        try {
            $users = db('users')->findMany(['where' => ['isDeleted' => false]]);
            return count($users);
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function canAddStaff() {
        $plan = self::getCurrentPlan();
        $limit = PlanFeatures::getStaffLimit($plan);
        if ($limit === null) {
            return true;
        }
        return self::getStaffCount() < $limit;
    }

    public static function getStaffLimitMessage() {
        $plan = self::getCurrentPlan();
        $limit = PlanFeatures::getStaffLimit($plan);
        if ($limit === null) {
            return '';
        }
        $label = PlanFeatures::getLabel($plan);
        return "Your {$label} plan allows up to {$limit} staff accounts. Upgrade to add more.";
    }

    public static function getVipTierCount() {
        try {
            $tiers = db('menuTiers')->findMany(['where' => ['isDeleted' => false]]);
            return count($tiers);
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function canAddVipTier() {
        $plan = self::getCurrentPlan();
        $limit = PlanFeatures::getVipTierLimit($plan);
        if ($limit === null) {
            return true;
        }
        if ($limit === 0) {
            return false;
        }
        return self::getVipTierCount() < $limit;
    }

    public static function getVipTierLimitMessage() {
        $plan = self::getCurrentPlan();
        $limit = PlanFeatures::getVipTierLimit($plan);
        if ($limit === null) {
            return '';
        }
        if ($limit === 0) {
            return 'VIP menu tiers require the Pro plan or higher.';
        }
        $label = PlanFeatures::getLabel($plan);
        return "Your {$label} plan allows {$limit} VIP menu tier. Upgrade to Premium for unlimited tiers.";
    }

    public static function getCurrentPlanInfo() {
        return PlanFeatures::getPlanInfo(self::getCurrentPlan(), self::getStaffCount());
    }

    public static function requirePlanFeature($feature) {
        if (!self::tenantHasFeature($feature)) {
            PlanFeatures::denyAccess($feature);
        }
    }

    public static function getBrandingVars() {
        return self::getPlatformBrandingVars();
    }

    public static function ensurePlatformSettings() {
        if (platformDb('settings')->count(['where' => ['id' => 'settings_branding']]) === 0) {
            platformDb('settings')->create(['data' => [
                'id' => 'settings_branding',
                'app_name' => self::PLATFORM_APP_NAME,
                'app_tagline' => self::PLATFORM_TAGLINE,
                'logo_url' => '',
                'favicon_url' => '',
                'updated_at' => date('c'),
            ]]);
        }
    }

    public static function getPlatformBranding() {
        self::ensurePlatformSettings();
        return platformDb('settings')->findUnique(['where' => ['id' => 'settings_branding']]) ?: [];
    }

    public static function getPlatformBrandingVars() {
        $b = self::getPlatformBranding();
        $logo = $b['logo_url'] ?? '';
        $ver = '';
        if (!empty($b['updated_at'])) {
            $ts = strtotime((string) $b['updated_at']);
            if ($ts !== false) {
                $ver = (string) $ts;
            }
        }
        if ($ver === '') {
            $ver = (string) time();
        }

        $apiLogo = !empty($logo) ? 'api/platform/branding-image.php?type=logo&v=' . rawurlencode($ver) : '';
        $apiFav = !empty($b['favicon_url'] ?? '')
            ? 'api/platform/branding-image.php?type=favicon&v=' . rawurlencode($ver)
            : $apiLogo;

        return [
            'appName' => !empty($b['app_name']) ? $b['app_name'] : self::PLATFORM_APP_NAME,
            'appTagline' => !empty($b['app_tagline']) ? $b['app_tagline'] : self::PLATFORM_TAGLINE,
            'logoUrl' => $logo,
            'publicLogoUrl' => $apiLogo,
            'faviconUrl' => $apiFav,
        ];
    }

    public static function updatePlatformBranding($key, $value) {
        $allowed = ['app_name', 'app_tagline', 'logo_url', 'favicon_url'];
        if (!in_array($key, $allowed, true)) {
            throw new Exception('Invalid branding key');
        }

        self::ensurePlatformSettings();
        $item = self::getPlatformBranding();
        $item[$key] = $value;
        $item['updated_at'] = date('c');

        platformDb('settings')->updateMany([
            'where' => ['id' => 'settings_branding'],
            'data' => $item,
        ]);

        return self::getPlatformBranding();
    }

    public static function syncTenantNameToPlatform($tenantId, $name) {
        $name = trim((string) $name);
        if ($tenantId === '' || $name === '') {
            return;
        }

        platformDb('tenants')->update([
            'where' => ['id' => $tenantId],
            'data' => ['name' => $name],
        ]);
    }

    public static function registerTenant($hotelName, $slug, $ownerName, $username, $password, $email = '', $plan = null) {
        $hotelName = trim($hotelName);
        $slug = self::slugify($slug ?: $hotelName);
        $ownerName = trim($ownerName);
        $username = self::normalizeUsername($username);
        $email = strtolower(trim($email));
        $password = (string) $password;
        $plan = $plan !== null ? trim((string) $plan) : null;

        $allowedPlans = ['starter', 'pro', 'premium'];
        if ($plan === null || $plan === '') {
            $plan = 'starter';
        }
        if (!in_array($plan, $allowedPlans, true)) {
            $plan = 'starter';
        }

        if ($hotelName === '' || $ownerName === '' || $username === '' || $password === '') {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        if (!self::isValidUsername($username)) {
            return ['success' => false, 'message' => 'Username must be 3–32 characters (letters, numbers, dots, hyphens, underscores)'];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        if (!self::isValidSlug($slug)) {
            return ['success' => false, 'message' => 'Hotel URL must be 3–48 characters (letters, numbers, hyphens)'];
        }
        if (!self::isSlugAvailable($slug)) {
            return ['success' => false, 'message' => 'This hotel URL is already taken'];
        }

        $tenantId = bin2hex(random_bytes(12));
        if (!self::isUsernameAvailableInTenant($tenantId, $username)) {
            return ['success' => false, 'message' => 'This username is already taken'];
        }

        $dbPath = self::getTenantDbPath($tenantId);

        try {
            $paidUntil = date('c', strtotime('+30 days'));
            $tenant = platformDb('tenants')->create(['data' => [
                'id' => $tenantId,
                'slug' => $slug,
                'name' => $hotelName,
                'status' => 'active',
                'plan' => $plan,
                'paid_until' => $paidUntil,
                'billing_status' => 'active',
                'db_path' => $dbPath,
                'owner_username' => $username,
                'owner_email' => $email,
                'owner_name' => $ownerName,
                'owner_plain_password' => $password,
                'created_at' => date('c'),
            ]]);

            $userId = self::initializeTenantDatabase($tenantId, $hotelName, $ownerName, $username, $email, $password);

            platformDb('tenants')->update([
                'where' => ['id' => $tenantId],
                'data' => [
                    'owner_user_id' => $userId,
                ],
            ]);

            return [
                'success' => true,
                'tenant' => $tenant,
                'message' => 'Registration successful',
            ];
        } catch (Exception $e) {
            error_log('Tenant registration failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }

    private static function initializeTenantDatabase($tenantId, $hotelName, $ownerName, $username, $email, $password) {
        $dbPath = self::getTenantDbPath($tenantId);
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        SqliteDB::setActivePath($dbPath);

        $manager = new SettingsManager();
        $manager->initializeFiles();

        db('settings')->updateMany([
            'where' => ['id' => 'settings_branding'],
            'data' => [
                'app_name' => $hotelName,
                'app_tagline' => 'Hotel Management System',
                'updated_at' => date('c'),
            ]
        ]);

        $userId = bin2hex(random_bytes(12));
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        db('users')->create(['data' => [
            'id' => $userId,
            'name' => $ownerName,
            'username' => $username,
            'email' => $email,
            'password' => $hashed,
            'plainPassword' => $password,
            'role' => 'admin',
            'isActive' => true,
            'permissions' => [],
        ]]);

        platformDb('tenant_users')->create(['data' => [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'name' => $ownerName,
            'role' => 'admin',
            'plain_password' => $password,
            'created_at' => date('c'),
        ]]);

        SqliteDB::resetActivePath();
        return $userId;
    }

    public static function loginWithCredentials($hotelInput, $username, $password) {
        $username = self::normalizeUsername($username);
        $hotelInput = trim($hotelInput);

        if ($hotelInput === '' || $username === '' || $password === '') {
            return ['success' => false, 'message' => 'Hotel name, username, and password are required'];
        }

        $tenant = self::findTenantByHotelInput($hotelInput);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Hotel not found'];
        }
        if (($tenant['status'] ?? 'active') !== 'active') {
            return ['success' => false, 'message' => 'This hotel account has been deactivated'];
        }

        $account = platformDb('tenant_users')->findFirst([
            'where' => [
                'tenant_id' => $tenant['id'],
                'username' => ['mode' => 'insensitive', 'equals' => $username],
            ],
        ]);

        if (!$account) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        try {
            self::switchToTenant($tenant['id']);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Hotel account unavailable'];
        }

        $user = db('users')->findUnique(['where' => ['id' => $account['user_id']]]);
        if (!$user || !password_verify($password, $user['password'])) {
            SqliteDB::resetActivePath();
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        if (isset($user['isActive']) && $user['isActive'] === false) {
            return ['success' => false, 'message' => 'Account deactivated'];
        }

        self::captureOwnerPassword($tenant, $user, $password);

        return [
            'success' => true,
            'user' => $user,
            'tenant' => $tenant,
            'account' => $account,
        ];
    }

    public static function loginWithEmail($email, $password, $slug = null) {
        $email = strtolower(trim($email));
        $accounts = self::findAccountsByEmail($email);

        if (empty($accounts)) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        if ($slug !== null && $slug !== '') {
            $slug = self::slugify($slug);
            $tenant = platformDb('tenants')->findUnique(['where' => ['slug' => $slug]]);
            if (!$tenant) {
                return ['success' => false, 'message' => 'Hotel not found'];
            }
            if (!self::isTenantActive($tenant)) {
                return ['success' => false, 'message' => 'This hotel account has been deactivated'];
            }
            $accounts = array_values(array_filter($accounts, function ($a) use ($tenant) {
                return ($a['tenant_id'] ?? '') === $tenant['id'];
            }));
            if (empty($accounts)) {
                return ['success' => false, 'message' => 'No account for this email at the selected hotel'];
            }
        }

        if (count($accounts) > 1 && ($slug === null || $slug === '')) {
            $tenants = [];
            foreach ($accounts as $account) {
                $t = self::getTenant($account['tenant_id']);
                if ($t && self::isTenantActive($t)) {
                    $tenants[] = [
                        'slug' => $t['slug'],
                        'name' => $t['name'],
                    ];
                }
            }
            if (empty($tenants)) {
                return ['success' => false, 'message' => 'This hotel account has been deactivated'];
            }
            return [
                'success' => false,
                'message' => 'Multiple hotels found for this email',
                'requires_slug' => true,
                'tenants' => $tenants,
            ];
        }

        $account = $accounts[0];
        try {
            self::switchToTenant($account['tenant_id']);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Hotel account unavailable'];
        }

        $user = db('users')->findUnique(['where' => ['id' => $account['user_id']]]);
        if (!$user || !password_verify($password, $user['password'])) {
            SqliteDB::resetActivePath();
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        if (isset($user['isActive']) && $user['isActive'] === false) {
            return ['success' => false, 'message' => 'Account deactivated'];
        }

        $tenant = self::getTenant($account['tenant_id']);
        if (!self::isTenantActive($tenant)) {
            SqliteDB::resetActivePath();
            return ['success' => false, 'message' => 'This hotel account has been deactivated'];
        }

        return [
            'success' => true,
            'user' => $user,
            'tenant' => $tenant,
            'account' => $account,
        ];
    }

    public static function setSessionTenant($tenant, $user) {
        unset($_SESSION['is_platform_super_admin']);
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['tenant_slug'] = $tenant['slug'] ?? '';
        $_SESSION['tenant_name'] = $tenant['name'] ?? '';
        $_SESSION['tenant_plan'] = PlanFeatures::normalizePlan($tenant['plan'] ?? 'starter');
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['role'] = $user['role'];
        $_SESSION['permissions'] = is_array($user['permissions'] ?? [])
            ? ($user['permissions'] ?? [])
            : (json_decode($user['permissions'] ?? '[]', true) ?: []);
        $_SESSION['floorId'] = $user['floorId'] ?? null;
        self::switchToTenant($tenant['id']);
    }

    private static function syncAllTenantUsernames() {
        $tenants = platformDb('tenants')->findMany();
        foreach ($tenants as $tenant) {
            if (empty($tenant['db_path']) || !file_exists($tenant['db_path'])) {
                continue;
            }
            try {
                self::switchToTenant($tenant['id']);
            } catch (Exception $e) {
                continue;
            }

            $users = db('users')->findMany(['where' => ['isDeleted' => false]]);
            foreach ($users as $user) {
                $username = $user['username'] ?? '';
                if ($username === '' && !empty($user['email'])) {
                    $username = self::normalizeUsername(strstr($user['email'], '@', true) ?: $user['email']);
                }
                if ($username === '') {
                    continue;
                }

                if (empty($user['username'])) {
                    db('users')->update([
                        'where' => ['id' => $user['id']],
                        'data' => ['username' => $username],
                    ]);
                }

                $mapped = platformDb('tenant_users')->findFirst([
                    'where' => [
                        'tenant_id' => $tenant['id'],
                        'user_id' => $user['id'],
                    ],
                ]);
                if (!$mapped) {
                    platformDb('tenant_users')->create(['data' => [
                        'tenant_id' => $tenant['id'],
                        'user_id' => $user['id'],
                        'username' => $username,
                        'email' => strtolower($user['email'] ?? ''),
                        'name' => $user['name'] ?? '',
                        'role' => $user['role'] ?? 'staff',
                        'created_at' => date('c'),
                    ]]);
                } elseif (empty($mapped['username'])) {
                    platformDb('tenant_users')->update([
                        'where' => ['id' => $mapped['id']],
                        'data' => ['username' => $username],
                    ]);
                }
            }
            SqliteDB::resetActivePath();
        }
    }

    private static function ensureDefaultTenant() {
        $defaultPath = SqliteDB::getDefaultPath();
        $existing = platformDb('tenants')->findUnique(['where' => ['id' => self::DEFAULT_TENANT_ID]]);

        if (!$existing) {
            platformDb('tenants')->create(['data' => [
                'id' => self::DEFAULT_TENANT_ID,
                'slug' => 'default',
                'name' => 'Default Hotel',
                'status' => 'active',
                'plan' => 'legacy',
                'db_path' => $defaultPath,
                'created_at' => date('c'),
            ]]);
            $existing = platformDb('tenants')->findUnique(['where' => ['id' => self::DEFAULT_TENANT_ID]]);
        }

        if (!file_exists($defaultPath)) {
            return;
        }

        SqliteDB::setActivePath($defaultPath);
        $users = db('users')->findMany(['where' => ['isDeleted' => false]]);
        SqliteDB::resetActivePath();

        foreach ($users as $user) {
            $username = $user['username'] ?? '';
            if ($username === '' && !empty($user['email'])) {
                $username = self::normalizeUsername(strstr($user['email'], '@', true) ?: $user['email']);
            }
            if ($username === '') {
                continue;
            }

            $mapped = platformDb('tenant_users')->findFirst([
                'where' => [
                    'tenant_id' => self::DEFAULT_TENANT_ID,
                    'user_id' => $user['id'],
                ],
            ]);
            if (!$mapped) {
                platformDb('tenant_users')->create(['data' => [
                    'tenant_id' => self::DEFAULT_TENANT_ID,
                    'user_id' => $user['id'],
                    'username' => $username,
                    'email' => strtolower($user['email'] ?? ''),
                    'name' => $user['name'] ?? '',
                    'role' => $user['role'] ?? 'staff',
                    'created_at' => date('c'),
                ]]);
            } elseif (empty($mapped['username'])) {
                platformDb('tenant_users')->update([
                    'where' => ['id' => $mapped['id']],
                    'data' => ['username' => $username],
                ]);
            }

            if (empty($user['username'])) {
                SqliteDB::setActivePath($defaultPath);
                db('users')->update([
                    'where' => ['id' => $user['id']],
                    'data' => ['username' => $username],
                ]);
                SqliteDB::resetActivePath();
            }
        }
    }

    public static function getTenantOwnerInfo($tenant) {
        $info = [
            'owner_user_id' => $tenant['owner_user_id'] ?? '',
            'owner_name' => $tenant['owner_name'] ?? '',
            'owner_username' => $tenant['owner_username'] ?? $tenant['owner_email'] ?? '',
            'owner_email' => $tenant['owner_email'] ?? '',
            'owner_password' => $tenant['owner_plain_password'] ?? '',
        ];

        $mapped = null;
        if (!empty($info['owner_user_id'])) {
            $mapped = platformDb('tenant_users')->findFirst([
                'where' => ['tenant_id' => $tenant['id'], 'user_id' => $info['owner_user_id']],
            ]);
        }
        if (!$mapped && !empty($info['owner_username'])) {
            $mapped = platformDb('tenant_users')->findFirst([
                'where' => [
                    'tenant_id' => $tenant['id'],
                    'username' => ['mode' => 'insensitive', 'equals' => $info['owner_username']],
                ],
            ]);
        }
        if ($mapped) {
            if (!empty($mapped['plain_password'])) {
                $info['owner_password'] = $mapped['plain_password'];
            }
            if (empty($info['owner_user_id'])) {
                $info['owner_user_id'] = $mapped['user_id'] ?? '';
            }
        }

        if (empty($tenant['db_path']) || !file_exists($tenant['db_path'])) {
            return $info;
        }

        try {
            self::switchToTenant($tenant['id']);
        } catch (Exception $e) {
            SqliteDB::resetActivePath();
            return $info;
        }

        $owner = null;
        if (!empty($info['owner_user_id'])) {
            $owner = db('users')->findUnique(['where' => ['id' => $info['owner_user_id']]]);
        }
        if (!$owner && !empty($info['owner_username'])) {
            $owner = db('users')->findFirst([
                'where' => ['username' => ['mode' => 'insensitive', 'equals' => $info['owner_username']]],
            ]);
        }
        if (!$owner) {
            $admins = db('users')->findMany(['where' => ['role' => 'admin', 'isDeleted' => false]]);
            $owner = $admins[0] ?? null;
        }

        if ($owner) {
            $info['owner_user_id'] = $owner['id'];
            $info['owner_name'] = $owner['name'] ?? $info['owner_name'];
            $info['owner_username'] = $owner['username'] ?? $info['owner_username'];
            $info['owner_email'] = $owner['email'] ?? $info['owner_email'];
            if (!empty($owner['plainPassword'])) {
                $info['owner_password'] = $owner['plainPassword'];
            }
        }

        SqliteDB::resetActivePath();
        return $info;
    }

    public static function captureOwnerPassword($tenant, $user, $password) {
        if ($password === '') {
            return;
        }

        $isOwner = ($tenant['owner_user_id'] ?? '') === ($user['id'] ?? '')
            || ($user['role'] ?? '') === 'admin';

        if (!$isOwner) {
            return;
        }

        $tenantUpdate = [];
        if (empty($tenant['owner_user_id'])) {
            $tenantUpdate['owner_user_id'] = $user['id'];
        }
        if (empty($tenant['owner_username'])) {
            $tenantUpdate['owner_username'] = $user['username'] ?? '';
        }
        if (empty($tenant['owner_name'])) {
            $tenantUpdate['owner_name'] = $user['name'] ?? '';
        }
        $tenantUpdate['owner_plain_password'] = $password;

        platformDb('tenants')->update([
            'where' => ['id' => $tenant['id']],
            'data' => $tenantUpdate,
        ]);

        $mapped = platformDb('tenant_users')->findFirst([
            'where' => ['tenant_id' => $tenant['id'], 'user_id' => $user['id']],
        ]);
        if ($mapped) {
            platformDb('tenant_users')->update([
                'where' => ['id' => $mapped['id']],
                'data' => ['plain_password' => $password],
            ]);
        }

        if (empty($user['plainPassword'])) {
            db('users')->update([
                'where' => ['id' => $user['id']],
                'data' => ['plainPassword' => $password],
            ]);
        }
    }

    private static function backfillOwnerPlainPasswords() {
        $tenants = platformDb('tenants')->findMany();
        foreach ($tenants as $tenant) {
            if (!empty($tenant['isDeleted'])) {
                continue;
            }

            $info = self::getTenantOwnerInfo($tenant);
            $password = $info['owner_password'] ?? '';
            if ($password === '') {
                continue;
            }

            $update = [];
            if (empty($tenant['owner_plain_password'])) {
                $update['owner_plain_password'] = $password;
            }
            if (empty($tenant['owner_user_id']) && !empty($info['owner_user_id'])) {
                $update['owner_user_id'] = $info['owner_user_id'];
            }
            if (empty($tenant['owner_name']) && !empty($info['owner_name'])) {
                $update['owner_name'] = $info['owner_name'];
            }
            if (empty($tenant['owner_username']) && !empty($info['owner_username'])) {
                $update['owner_username'] = $info['owner_username'];
            }

            if (!empty($update)) {
                platformDb('tenants')->update([
                    'where' => ['id' => $tenant['id']],
                    'data' => $update,
                ]);
            }

            if (!empty($info['owner_user_id'])) {
                $mapped = platformDb('tenant_users')->findFirst([
                    'where' => ['tenant_id' => $tenant['id'], 'user_id' => $info['owner_user_id']],
                ]);
                if ($mapped && empty($mapped['plain_password'])) {
                    platformDb('tenant_users')->update([
                        'where' => ['id' => $mapped['id']],
                        'data' => ['plain_password' => $password],
                    ]);
                }
            }
        }
    }

    public static function verifyAndStoreOwnerPassword($tenantId, $password) {
        $tenant = self::getTenant($tenantId);
        if (!$tenant || !empty($tenant['isDeleted'])) {
            return ['success' => false, 'message' => 'Hotel not found'];
        }
        if ($password === '') {
            return ['success' => false, 'message' => 'Password is required'];
        }

        if (empty($tenant['db_path']) || !file_exists($tenant['db_path'])) {
            return ['success' => false, 'message' => 'Hotel database unavailable'];
        }

        try {
            self::switchToTenant($tenantId);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Hotel database unavailable'];
        }

        $owner = null;
        if (!empty($tenant['owner_user_id'])) {
            $owner = db('users')->findUnique(['where' => ['id' => $tenant['owner_user_id']]]);
        }
        if (!$owner && !empty($tenant['owner_username'])) {
            $owner = db('users')->findFirst([
                'where' => ['username' => ['mode' => 'insensitive', 'equals' => $tenant['owner_username']]],
            ]);
        }
        if (!$owner) {
            $admins = db('users')->findMany(['where' => ['role' => 'admin', 'isDeleted' => false]]);
            $owner = $admins[0] ?? null;
        }

        if (!$owner || !password_verify($password, $owner['password'] ?? '')) {
            SqliteDB::resetActivePath();
            return ['success' => false, 'message' => 'Incorrect password'];
        }

        self::captureOwnerPassword($tenant, $owner, $password);
        SqliteDB::resetActivePath();

        return [
            'success' => true,
            'message' => 'Password verified and saved for viewing',
            'owner_password' => $password,
        ];
    }

    public static function listTenantsForPlatform() {
        $tenants = platformDb('tenants')->findMany();
        $result = [];

        foreach ($tenants as $tenant) {
            if (!empty($tenant['isDeleted'])) {
                continue;
            }
            $owner = self::getTenantOwnerInfo($tenant);
            $result[] = array_merge($tenant, $owner);
        }

        usort($result, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        return $result;
    }

    public static function updateTenantForPlatform($tenantId, $data) {
        $tenant = self::getTenant($tenantId);
        if (!$tenant || !empty($tenant['isDeleted'])) {
            return ['success' => false, 'message' => 'Hotel not found'];
        }

        $update = [];

        if (!empty($data['name'])) {
            $update['name'] = trim($data['name']);
        }
        if (!empty($data['slug'])) {
            $slug = self::slugify($data['slug']);
            if (!self::isValidSlug($slug)) {
                return ['success' => false, 'message' => 'Invalid hotel URL slug'];
            }
            $existing = platformDb('tenants')->findUnique(['where' => ['slug' => $slug]]);
            if ($existing && ($existing['id'] ?? '') !== $tenantId) {
                return ['success' => false, 'message' => 'This hotel URL is already taken'];
            }
            $update['slug'] = $slug;
        }
        if (isset($data['plan'])) {
            $plan = PlanFeatures::normalizePlan(trim($data['plan']));
            $update['plan'] = $plan;
        }
        if (isset($data['status'])) {
            $status = $data['status'] === 'active' ? 'active' : 'inactive';
            $update['status'] = $status;
        }

        if (isset($data['paid_until'])) {
            $paidUntil = trim((string) $data['paid_until']);
            if ($paidUntil !== '') {
                $update['paid_until'] = $paidUntil;
            }
        }

        $ownerUsername = isset($data['owner_username']) ? self::normalizeUsername($data['owner_username']) : null;
        $ownerPassword = array_key_exists('owner_password', $data) ? (string) $data['owner_password'] : null;
        $ownerName = isset($data['owner_name']) ? trim($data['owner_name']) : null;

        if ($ownerUsername !== null) {
            if (!self::isValidUsername($ownerUsername)) {
                return ['success' => false, 'message' => 'Invalid owner username'];
            }
            $conflict = platformDb('tenant_users')->findFirst([
                'where' => [
                    'tenant_id' => $tenantId,
                    'username' => ['mode' => 'insensitive', 'equals' => $ownerUsername],
                ],
            ]);
            if ($conflict && ($conflict['user_id'] ?? '') !== ($tenant['owner_user_id'] ?? '')) {
                return ['success' => false, 'message' => 'Username already taken in this hotel'];
            }
            $update['owner_username'] = $ownerUsername;
        }

        if ($ownerName !== null && $ownerName !== '') {
            $update['owner_name'] = $ownerName;
        }

        if ($ownerPassword !== null && $ownerPassword !== '') {
            if (strlen($ownerPassword) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            $update['owner_plain_password'] = $ownerPassword;
        }

        if (!empty($update)) {
            platformDb('tenants')->update([
                'where' => ['id' => $tenantId],
                'data' => $update,
            ]);
            $tenant = self::getTenant($tenantId);
        }

        $ownerUserId = $tenant['owner_user_id'] ?? '';
        if ($ownerUserId && !empty($tenant['db_path']) && file_exists($tenant['db_path'])) {
            try {
                self::switchToTenant($tenantId);
                $userUpdate = [];
                if ($ownerUsername !== null) {
                    $userUpdate['username'] = $ownerUsername;
                }
                if ($ownerName !== null && $ownerName !== '') {
                    $userUpdate['name'] = $ownerName;
                }
                if ($ownerPassword !== null && $ownerPassword !== '') {
                    $userUpdate['password'] = password_hash($ownerPassword, PASSWORD_BCRYPT);
                    $userUpdate['plainPassword'] = $ownerPassword;
                }
                if (!empty($userUpdate)) {
                    db('users')->update(['where' => ['id' => $ownerUserId], 'data' => $userUpdate]);
                }

                $mapped = platformDb('tenant_users')->findFirst([
                    'where' => ['tenant_id' => $tenantId, 'user_id' => $ownerUserId],
                ]);
                if ($mapped) {
                    $mapUpdate = [];
                    if ($ownerUsername !== null) {
                        $mapUpdate['username'] = $ownerUsername;
                        $mapUpdate['name'] = $ownerName ?: ($mapped['name'] ?? '');
                    }
                    if ($ownerPassword !== null && $ownerPassword !== '') {
                        $mapUpdate['plain_password'] = $ownerPassword;
                    }
                    if (!empty($mapUpdate)) {
                        platformDb('tenant_users')->update([
                            'where' => ['id' => $mapped['id']],
                            'data' => $mapUpdate,
                        ]);
                    }
                }
            } catch (Exception $e) {
                SqliteDB::resetActivePath();
                return ['success' => false, 'message' => 'Failed to update owner credentials'];
            }
            SqliteDB::resetActivePath();
        }

        if (!empty($update['name']) && !empty($tenant['db_path']) && file_exists($tenant['db_path'])) {
            try {
                self::switchToTenant($tenantId);
                db('settings')->updateMany([
                    'where' => ['id' => 'settings_branding'],
                    'data' => ['app_name' => $update['name'], 'updated_at' => date('c')],
                ]);
            } catch (Exception $e) {
                // non-fatal
            }
            SqliteDB::resetActivePath();
        }

        return ['success' => true, 'tenant' => array_merge($tenant, self::getTenantOwnerInfo($tenant))];
    }

    public static function ensureTenantBillingStatus($tenantId) {
        $tenant = self::getTenant($tenantId);
        if (!$tenant || !empty($tenant['isDeleted'])) {
            return null;
        }

        $paidUntil = $tenant['paid_until'] ?? '';
        if ($paidUntil === '') {
            return $tenant;
        }

        $now = time();
        $untilTs = strtotime($paidUntil) ?: 0;
        if ($untilTs > 0 && $now > $untilTs) {
            if (($tenant['status'] ?? 'active') === 'active') {
                platformDb('tenants')->update([
                    'where' => ['id' => $tenantId],
                    'data' => [
                        'status' => 'inactive',
                        'billing_status' => 'expired',
                        'updated_at' => date('c'),
                    ],
                ]);
                return self::getTenant($tenantId);
            }
        }

        return $tenant;
    }

    public static function confirmTenantPayment($tenantId, $months = 1) {
        $tenant = self::getTenant($tenantId);
        if (!$tenant || !empty($tenant['isDeleted'])) {
            return ['success' => false, 'message' => 'Hotel not found'];
        }

        $months = max(1, (int) $months);
        $now = time();
        $paidUntil = $tenant['paid_until'] ?? '';
        $base = $now;
        $untilTs = strtotime($paidUntil) ?: 0;
        if ($untilTs > $now) {
            $base = $untilTs;
        }

        $newUntil = date('c', strtotime('+' . $months . ' month', $base));

        platformDb('tenants')->update([
            'where' => ['id' => $tenantId],
            'data' => [
                'paid_until' => $newUntil,
                'billing_status' => 'active',
                'status' => 'active',
                'last_payment_confirmed_at' => date('c'),
                'updated_at' => date('c'),
            ],
        ]);

        $tenant = self::getTenant($tenantId);
        return ['success' => true, 'tenant' => $tenant, 'paid_until' => $newUntil];
    }

    public static function deleteTenantForPlatform($tenantId) {
        if ($tenantId === self::DEFAULT_TENANT_ID) {
            return ['success' => false, 'message' => 'The default hotel cannot be deleted'];
        }

        $tenant = self::getTenant($tenantId);
        if (!$tenant || !empty($tenant['isDeleted'])) {
            return ['success' => false, 'message' => 'Hotel not found'];
        }

        platformDb('tenant_users')->deleteMany(['where' => ['tenant_id' => $tenantId]]);
        platformDb('tenants')->delete(['where' => ['id' => $tenantId]]);

        self::deleteTenantStorage($tenantId, $tenant['db_path'] ?? null);

        return ['success' => true, 'message' => 'Hotel deleted'];
    }

    private static function deleteTenantStorage($tenantId, $dbPath = null) {
        $dirsToRemove = [];

        if (!empty($dbPath)) {
            $dirsToRemove[] = dirname($dbPath);
        }
        $dirsToRemove[] = self::getTenantDbPath($tenantId);
        $dirsToRemove[] = TENANTS_DIR . DIRECTORY_SEPARATOR . $tenantId;

        $tenantsRoot = realpath(TENANTS_DIR);
        if (!$tenantsRoot) {
            return;
        }

        $seen = [];
        foreach ($dirsToRemove as $dir) {
            $resolved = realpath($dir);
            if (!$resolved || isset($seen[$resolved])) {
                continue;
            }
            $seen[$resolved] = true;

            $root = str_replace('\\', '/', strtolower($tenantsRoot));
            $target = str_replace('\\', '/', strtolower($resolved));
            if ($target !== $root && strpos($target, $root . '/') === 0) {
                self::deleteDirectory($resolved);
            }
        }
    }

    private static function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
