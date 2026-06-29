<?php
/**
 * Subscription plan definitions and feature gating.
 */

class PlanFeatures {
    public const PLANS = ['starter', 'pro', 'premium'];

    private static $definitions = [
        'starter' => [
            'label' => 'Starter',
            'price' => 1000,
            'staff_limit' => 10,
            'vip_tier_limit' => 0,
            'features' => [
                'pos',
                'orders',
                'kitchen',
                'menu',
                'tables',
                'reports_basic',
                'settings',
            ],
        ],
        'pro' => [
            'label' => 'Pro',
            'price' => 1500,
            'staff_limit' => 25,
            'vip_tier_limit' => 1,
            'features' => [
                'reception',
                'store',
                'stock',
                'distribution',
                'floors',
                'reports_advanced',
                'cloud_import',
                'vip_tiers',
            ],
        ],
        'premium' => [
            'label' => 'Premium',
            'price' => 2000,
            'staff_limit' => null,
            'vip_tier_limit' => null,
            'features' => [
                'custom_permissions',
                'bedroom_revenue',
            ],
        ],
    ];

    private static $legacyMap = [
        'trial' => 'pro',
        'legacy' => 'pro',
        'basic' => 'starter',
        'enterprise' => 'premium',
    ];

    private static $pathFeatures = [
        // Services hub contains Standard Menu (Starter) + Reception/VIP (Pro+)
        'services.php' => 'menu',
        'store.php' => 'store',
        'stock.php' => 'stock',
        'vip-menu.php' => 'vip_tiers',
        'api/reception-requests.php' => 'reception',
        'api/admin/rooms.php' => 'reception',
        'api/room-orders.php' => 'reception',
        'api/stock.php' => 'stock',
        'api/inventory-transfers.php' => 'store',
        'api/admin/cloud-import.php' => 'cloud_import',
        'api/admin/floors.php' => 'floors',
        'api/reports/menu-sales.php' => 'reports_advanced',
        'api/reports/stock-usage.php' => 'reports_advanced',
        'api/reports/bedroom-revenue.php' => 'bedroom_revenue',
    ];

    private static $navFeatures = [
        'services.php' => 'menu',
        'store.php' => 'store',
        'stock.php' => 'stock',
        'reports.php' => 'reports_basic',
    ];

    public static function normalizePlan($plan) {
        $plan = strtolower(trim((string) $plan));
        if (isset(self::$legacyMap[$plan])) {
            return self::$legacyMap[$plan];
        }
        if (in_array($plan, self::PLANS, true)) {
            return $plan;
        }
        return 'starter';
    }

    public static function getDefinition($plan) {
        $plan = self::normalizePlan($plan);
        return self::$definitions[$plan];
    }

    public static function getLabel($plan) {
        return self::getDefinition($plan)['label'];
    }

    public static function getStaffLimit($plan) {
        return self::getDefinition($plan)['staff_limit'];
    }

    public static function getVipTierLimit($plan) {
        return self::getDefinition($plan)['vip_tier_limit'];
    }

    public static function getFeaturesForPlan($plan) {
        $plan = self::normalizePlan($plan);
        $features = [];
        foreach (self::PLANS as $tier) {
            $features = array_merge($features, self::$definitions[$tier]['features']);
            if ($tier === $plan) {
                break;
            }
        }
        return array_values(array_unique($features));
    }

    public static function hasFeature($plan, $feature) {
        return in_array($feature, self::getFeaturesForPlan($plan), true);
    }

    public static function getUpgradePlanForFeature($feature) {
        foreach (self::PLANS as $plan) {
            if (self::hasFeature($plan, $feature)) {
                return $plan;
            }
        }
        return 'premium';
    }

    public static function featureForCategoryType($type) {
        $type = strtolower(trim((string) $type));
        if ($type === 'stock') {
            return 'store';
        }
        if ($type === 'distribution') {
            return 'distribution';
        }
        return null;
    }

    public static function resolveFeatureForPath($script) {
        $script = str_replace('\\', '/', (string) $script);
        $script = ltrim($script, '/');

        foreach (self::$pathFeatures as $path => $feature) {
            if (substr($script, -strlen($path)) === $path) {
                return $feature;
            }
        }

        return null;
    }

    public static function navFeatureForUrl($url) {
        $basename = basename(parse_url($url, PHP_URL_PATH) ?: $url);
        return self::$navFeatures[$basename] ?? null;
    }

    public static function canAccessNavLink($plan, $url) {
        $feature = self::navFeatureForUrl($url);
        if ($feature === null) {
            return true;
        }
        return self::hasFeature($plan, $feature);
    }

    public static function enforceForRequest($plan) {
        if ($plan === null || $plan === '') {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $feature = self::resolveFeatureForPath($script);
        if ($feature && !self::hasFeature($plan, $feature)) {
            self::denyAccess($feature);
        }

        $basename = basename($script);
        if ($basename === 'cashier.php' && !empty($_GET['tier']) && !self::hasFeature($plan, 'vip_tiers')) {
            header('Location: /cashier.php');
            exit;
        }
    }

    public static function denyAccess($feature = null) {
        $upgrade = $feature ? self::getUpgradePlanForFeature($feature) : 'pro';
        $label = self::getLabel($upgrade);
        $message = "This feature requires the {$label} plan or higher.";

        if (self::isApiRequest()) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => $message,
                'code' => 'plan_required',
                'required_plan' => $upgrade,
            ]);
            exit;
        }

        header('Location: /unauthorized.php?reason=plan&required_plan=' . urlencode($upgrade));
        exit;
    }

    public static function isApiRequest() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api/') !== false) {
            return true;
        }
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        return strpos($script, '/api/') !== false;
    }

    public static function getPlanInfo($plan, $staffCount = null) {
        $plan = self::normalizePlan($plan);
        $def = self::getDefinition($plan);

        return [
            'plan' => $plan,
            'label' => $def['label'],
            'price' => $def['price'],
            'staff_limit' => $def['staff_limit'],
            'staff_count' => $staffCount,
            'vip_tier_limit' => $def['vip_tier_limit'],
            'features' => self::getFeaturesForPlan($plan),
        ];
    }
}
