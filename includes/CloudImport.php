<?php
/**
 * Import lists from the legacy cloud database (data/database.sqlite).
 */

require_once __DIR__ . '/JsonDB.php';
require_once __DIR__ . '/SqliteDB.php';
require_once __DIR__ . '/TenantManager.php';

class CloudImport {
    public static function getLegacyPath() {
        return SqliteDB::getDefaultPath();
    }

    public static function isLegacyDatabaseAvailable() {
        $path = self::getLegacyPath();
        return is_file($path) && filesize($path) > 100;
    }

    public static function isActiveTenantLegacy() {
        $legacy = realpath(self::getLegacyPath());
        $active = realpath(SqliteDB::getActivePath());
        return $legacy && $active && $legacy === $active;
    }

    public static function canImport() {
        return self::isLegacyDatabaseAvailable() && !self::isActiveTenantLegacy();
    }

    private static function legacyDb($table) {
        return new JsonDB($table, self::getLegacyPath());
    }

    private static function normalizeName($name) {
        return strtolower(trim((string) $name));
    }

    public static function getStats() {
        $stats = [
            'categories_menu' => 0,
            'categories_stock' => 0,
            'categories_distribution' => 0,
            'menu_items' => 0,
            'stocks' => 0,
        ];

        if (!self::isLegacyDatabaseAvailable()) {
            return $stats;
        }

        try {
            foreach (self::legacyDb('categories')->findMany([]) as $cat) {
                if ($cat['isDeleted'] ?? false) {
                    continue;
                }
                $type = $cat['type'] ?? $cat['group'] ?? 'menu';
                $key = 'categories_' . $type;
                if (isset($stats[$key])) {
                    $stats[$key]++;
                }
            }

            if (self::legacyTableExists('menuCategories')) {
                $menuCatNames = [];
                foreach (self::legacyDb('menuCategories')->findMany([]) as $cat) {
                    if ($cat['isDeleted'] ?? false) {
                        continue;
                    }
                    $name = self::normalizeName($cat['name'] ?? '');
                    if ($name !== '') {
                        $menuCatNames[$name] = true;
                    }
                }
                $stats['categories_menu'] += count($menuCatNames);
            }

            if (self::legacyTableExists('menuItems')) {
                $stats['menu_items'] = count(self::getLegacyMenuItems('menuItems'));
            }
            if (self::legacyTableExists('stocks')) {
                $stats['stocks'] = count(self::getLegacyStocks());
            }
        } catch (Exception $e) {
            // Return partial/zero stats
        }

        return $stats;
    }

    private static function legacyPdo() {
        static $pdo = null;
        if ($pdo === null) {
            $pdo = new PDO('sqlite:' . self::getLegacyPath());
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return $pdo;
    }

    private static function legacyTableExists($table) {
        if (!self::isLegacyDatabaseAvailable()) {
            return false;
        }
        try {
            $stmt = self::legacyPdo()->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    private static function legacyTableCount($table) {
        if (!self::legacyTableExists($table)) {
            return 0;
        }
        try {
            return (int) self::legacyPdo()->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    private static function getLegacyMenuItems($collection = 'menuItems') {
        if (!self::legacyTableExists($collection)) {
            return [];
        }
        $items = self::legacyDb($collection)->findMany(['where' => ['isDeleted' => false]]);
        if ($collection !== 'menuItems') {
            return $items;
        }

        return array_values(array_filter($items, function ($item) {
            $name = strtolower($item['name'] ?? '');
            $cat = strtolower($item['category'] ?? '');
            return !(strpos($name, 'vip') !== false || strpos($cat, 'vip') !== false || ($item['isVIP'] ?? false));
        }));
    }

    private static function getLegacyStocks() {
        if (!self::legacyTableExists('stocks')) {
            return [];
        }
        return array_values(array_filter(
            self::legacyDb('stocks')->findMany([]),
            fn($item) => !($item['isDeleted'] ?? false) && trim($item['name'] ?? '') !== ''
        ));
    }

    public static function importCategories($type) {
        $allowed = ['menu', 'stock', 'distribution'];
        if (!in_array($type, $allowed, true)) {
            throw new Exception('Invalid category type');
        }
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        $legacyItems = self::legacyDb('categories')->findMany([]);
        if ($type === 'menu' && self::legacyTableExists('menuCategories')) {
            foreach (self::legacyDb('menuCategories')->findMany([]) as $legacyCat) {
                $legacyItems[] = [
                    'name' => $legacyCat['name'] ?? '',
                    'type' => 'menu',
                    'group' => 'menu',
                    'description' => $legacyCat['description'] ?? '',
                    'isDeleted' => $legacyCat['isDeleted'] ?? false,
                ];
            }
        }

        $existingNames = [];
        foreach (db('categories')->findMany(['where' => ['type' => $type]]) as $existing) {
            if ($existing['isDeleted'] ?? false) {
                continue;
            }
            $existingNames[self::normalizeName($existing['name'] ?? '')] = true;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($legacyItems as $item) {
            if ($item['isDeleted'] ?? false) {
                continue;
            }

            $itemType = $item['type'] ?? $item['group'] ?? 'menu';
            if ($itemType !== $type) {
                continue;
            }

            $name = trim($item['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $norm = self::normalizeName($name);
            if (isset($existingNames[$norm])) {
                $skipped++;
                continue;
            }

            db('categories')->create(['data' => [
                'name' => $name,
                'type' => $type,
                'group' => $type,
                'description' => $item['description'] ?? '',
                'created_at' => date('c'),
                'imported_from_cloud' => true,
            ]]);
            $existingNames[$norm] = true;
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'type' => $type];
    }

    public static function importMenus($collection = 'menuItems') {
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        $legacyItems = self::getLegacyMenuItems($collection);
        $existing = db($collection)->findMany(['where' => ['isDeleted' => false]]);

        $existingMenuIds = [];
        $existingNames = [];
        foreach ($existing as $row) {
            $menuId = (string) ($row['menuId'] ?? '');
            if ($menuId !== '') {
                $existingMenuIds[$menuId] = true;
            }
            $existingNames[self::normalizeName($row['name'] ?? '')] = true;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($legacyItems as $item) {
            $name = trim($item['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $menuId = (string) ($item['menuId'] ?? '');
            $normName = self::normalizeName($name);
            if (($menuId !== '' && isset($existingMenuIds[$menuId])) || isset($existingNames[$normName])) {
                $skipped++;
                continue;
            }

            $copy = $item;
            unset($copy['id']);
            $copy['id'] = bin2hex(random_bytes(16));
            $copy['isDeleted'] = false;
            $copy['imported_from_cloud'] = true;
            $copy['imported_at'] = date('c');

            db($collection)->create(['data' => $copy]);

            if ($menuId !== '') {
                $existingMenuIds[$menuId] = true;
            }
            $existingNames[$normName] = true;
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'collection' => $collection];
    }

    public static function importStocks() {
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        $legacyItems = self::getLegacyStocks();
        $existingNames = [];
        foreach (db('stocks')->findMany([]) as $existing) {
            if ($existing['isDeleted'] ?? false) {
                continue;
            }
            $existingNames[self::normalizeName($existing['name'] ?? '')] = true;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($legacyItems as $item) {
            $name = trim($item['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $norm = self::normalizeName($name);
            if (isset($existingNames[$norm])) {
                $skipped++;
                continue;
            }

            $bulkQty = floatval($item['storeQuantity'] ?? $item['quantity'] ?? 0);
            $unitPrice = floatval($item['averagePurchasePrice'] ?? $item['unitCost'] ?? 0);

            db('stocks')->create(['data' => [
                'name' => $name,
                'category' => $item['category'] ?? 'General',
                'unit' => $item['unit'] ?? 'pcs',
                'unitType' => $item['unitType'] ?? 'count',
                'quantity' => floatval($item['quantity'] ?? 0),
                'storeQuantity' => $bulkQty,
                'minLimit' => floatval($item['minLimit'] ?? 5),
                'storeMinLimit' => floatval($item['storeMinLimit'] ?? 20),
                'averagePurchasePrice' => $unitPrice,
                'unitCost' => floatval($item['unitCost'] ?? $unitPrice),
                'totalInvestment' => floatval($item['totalInvestment'] ?? ($bulkQty * $unitPrice)),
                'totalPurchased' => floatval($item['totalPurchased'] ?? $bulkQty),
                'totalConsumed' => floatval($item['totalConsumed'] ?? 0),
                'trackQuantity' => $item['trackQuantity'] ?? true,
                'showStatus' => $item['showStatus'] ?? true,
                'status' => $item['status'] ?? (($bulkQty > 0) ? 'active' : 'out_of_stock'),
                'isVIP' => $item['isVIP'] ?? false,
                'vipLevel' => $item['vipLevel'] ?? 1,
                'restockHistory' => $item['restockHistory'] ?? [],
                'imported_from_cloud' => true,
                'imported_at' => date('c'),
            ]]);

            $existingNames[$norm] = true;
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
