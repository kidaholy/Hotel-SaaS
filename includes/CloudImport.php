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

    private static function categoryKey($type, $name) {
        return $type . ':' . self::normalizeName($name);
    }

    private static function menuKey($item) {
        $menuId = (string) ($item['menuId'] ?? '');
        if ($menuId !== '') {
            return 'menuId:' . $menuId;
        }
        return 'name:' . self::normalizeName($item['name'] ?? '');
    }

    private static function stockKey($item) {
        return 'name:' . self::normalizeName($item['name'] ?? '');
    }

    private static function getLegacyCategoryItems($type) {
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

        $seen = [];
        $items = [];
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
            $key = self::categoryKey($type, $name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = [
                'key' => $key,
                'name' => $name,
                'description' => $item['description'] ?? '',
            ];
        }

        usort($items, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $items;
    }

    private static function getExistingCategoryNames($type) {
        $existingNames = [];
        foreach (db('categories')->findMany(['where' => ['type' => $type]]) as $existing) {
            if ($existing['isDeleted'] ?? false) {
                continue;
            }
            $existingNames[self::normalizeName($existing['name'] ?? '')] = true;
        }
        return $existingNames;
    }

    private static function getExistingMenuKeys($collection) {
        $existingMenuIds = [];
        $existingNames = [];
        foreach (db($collection)->findMany(['where' => ['isDeleted' => false]]) as $row) {
            $menuId = (string) ($row['menuId'] ?? '');
            if ($menuId !== '') {
                $existingMenuIds[$menuId] = true;
            }
            $existingNames[self::normalizeName($row['name'] ?? '')] = true;
        }
        return [$existingMenuIds, $existingNames];
    }

    private static function menuItemExists($item, $existingMenuIds, $existingNames) {
        $menuId = (string) ($item['menuId'] ?? '');
        $normName = self::normalizeName($item['name'] ?? '');
        return ($menuId !== '' && isset($existingMenuIds[$menuId])) || isset($existingNames[$normName]);
    }

    public static function listCategories($type) {
        $allowed = ['menu', 'stock', 'distribution'];
        if (!in_array($type, $allowed, true)) {
            throw new Exception('Invalid category type');
        }
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        $existingNames = self::getExistingCategoryNames($type);
        $items = [];
        foreach (self::getLegacyCategoryItems($type) as $item) {
            $norm = self::normalizeName($item['name']);
            $items[] = [
                'key' => $item['key'],
                'name' => $item['name'],
                'description' => $item['description'],
                'exists' => isset($existingNames[$norm]),
            ];
        }
        return ['type' => $type, 'items' => $items];
    }

    public static function listMenus($collection = 'menuItems') {
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        [$existingMenuIds, $existingNames] = self::getExistingMenuKeys($collection);
        $items = [];
        foreach (self::getLegacyMenuItems($collection) as $item) {
            $name = trim($item['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $items[] = [
                'key' => self::menuKey($item),
                'name' => $name,
                'menuId' => (string) ($item['menuId'] ?? ''),
                'category' => $item['category'] ?? '',
                'mainCategory' => $item['mainCategory'] ?? '',
                'price' => floatval($item['price'] ?? 0),
                'exists' => self::menuItemExists($item, $existingMenuIds, $existingNames),
            ];
        }

        usort($items, function ($a, $b) {
            $idA = intval($a['menuId'] ?: PHP_INT_MAX);
            $idB = intval($b['menuId'] ?: PHP_INT_MAX);
            if ($idA !== $idB) {
                return $idA <=> $idB;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return ['collection' => $collection, 'items' => $items];
    }

    public static function listStocks() {
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        $existingNames = [];
        foreach (db('stocks')->findMany([]) as $existing) {
            if ($existing['isDeleted'] ?? false) {
                continue;
            }
            $existingNames[self::normalizeName($existing['name'] ?? '')] = true;
        }

        $items = [];
        foreach (self::getLegacyStocks() as $item) {
            $name = trim($item['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $norm = self::normalizeName($name);
            $items[] = [
                'key' => self::stockKey($item),
                'name' => $name,
                'category' => $item['category'] ?? 'General',
                'unit' => $item['unit'] ?? 'pcs',
                'exists' => isset($existingNames[$norm]),
            ];
        }

        usort($items, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return ['items' => $items];
    }

    private static function groupByCategory($items, $categories, $defaultLabel = 'Uncategorized') {
        $catByNorm = [];
        foreach ($categories as $cat) {
            $catByNorm[self::normalizeName($cat['name'])] = $cat;
        }

        $groups = [];
        foreach ($items as $item) {
            $catName = trim($item['category'] ?? '') ?: $defaultLabel;
            $norm = self::normalizeName($catName);
            if (!isset($groups[$norm])) {
                $groups[$norm] = [
                    'name' => $catName,
                    'category' => $catByNorm[$norm] ?? null,
                    'items' => [],
                ];
            }
            $groups[$norm]['items'][] = $item;
        }

        foreach ($categories as $cat) {
            $norm = self::normalizeName($cat['name']);
            if (!isset($groups[$norm])) {
                $groups[$norm] = [
                    'name' => $cat['name'],
                    'category' => $cat,
                    'items' => [],
                ];
            } elseif ($groups[$norm]['category'] === null) {
                $groups[$norm]['category'] = $cat;
            }
        }

        $out = array_values($groups);
        usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    private static function mergeDerivedCategories($categories, $items, $type, $skipNames = []) {
        $existingNames = self::getExistingCategoryNames($type);
        $seen = [];
        foreach ($categories as $cat) {
            $seen[self::normalizeName($cat['name'])] = true;
        }

        $merged = $categories;
        foreach ($items as $item) {
            $name = trim($item['category'] ?? '');
            if ($name === '' || in_array($name, $skipNames, true)) {
                continue;
            }
            $norm = self::normalizeName($name);
            if (isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $merged[] = [
                'key' => self::categoryKey($type, $name),
                'name' => $name,
                'description' => '',
                'exists' => isset($existingNames[$norm]),
            ];
        }

        usort($merged, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $merged;
    }

    public static function listBundle($collection = 'menuItems') {
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        $menuItems = self::listMenus($collection)['items'];
        $stockItems = self::listStocks()['items'];
        $menuCategories = self::mergeDerivedCategories(
            self::listCategories('menu')['items'],
            $menuItems,
            'menu',
            ['Uncategorized']
        );
        $stockCategories = self::mergeDerivedCategories(
            self::listCategories('stock')['items'],
            $stockItems,
            'stock',
            ['General']
        );

        return [
            'collection' => $collection,
            'menu' => [
                'categories' => $menuCategories,
                'groups' => self::groupByCategory($menuItems, $menuCategories, 'Uncategorized'),
            ],
            'store' => [
                'categories' => $stockCategories,
                'groups' => self::groupByCategory($stockItems, $stockCategories, 'General'),
            ],
        ];
    }

    public static function importBundle($selection, $collection = 'menuItems') {
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        if (!is_array($selection)) {
            throw new Exception('Invalid import selection');
        }

        $results = [];
        $totalImported = 0;
        $totalSkipped = 0;

        $catMenu = $selection['categories']['menu'] ?? [];
        if (!empty($catMenu)) {
            $results['categories_menu'] = self::importCategories('menu', $catMenu);
            $totalImported += $results['categories_menu']['imported'];
            $totalSkipped += $results['categories_menu']['skipped'];
        }

        $catStock = $selection['categories']['stock'] ?? [];
        if (!empty($catStock)) {
            $results['categories_stock'] = self::importCategories('stock', $catStock);
            $totalImported += $results['categories_stock']['imported'];
            $totalSkipped += $results['categories_stock']['skipped'];
        }

        $menus = $selection['menus'] ?? [];
        if (!empty($menus)) {
            $results['menus'] = self::importMenus($collection, $menus);
            $totalImported += $results['menus']['imported'];
            $totalSkipped += $results['menus']['skipped'];
        }

        $stocks = $selection['stocks'] ?? [];
        if (!empty($stocks)) {
            $results['stocks'] = self::importStocks($stocks);
            $totalImported += $results['stocks']['imported'];
            $totalSkipped += $results['stocks']['skipped'];
        }

        return [
            'imported' => $totalImported,
            'skipped' => $totalSkipped,
            'details' => $results,
            'collection' => $collection,
        ];
    }

    private static function selectedKeySet($selected) {
        if ($selected === null) {
            return null;
        }
        if (!is_array($selected)) {
            return [];
        }
        return array_fill_keys(array_map('strval', $selected), true);
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

    public static function importCategories($type, $selected = null) {
        $allowed = ['menu', 'stock', 'distribution'];
        if (!in_array($type, $allowed, true)) {
            throw new Exception('Invalid category type');
        }
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        $selectedKeys = self::selectedKeySet($selected);
        $existingNames = self::getExistingCategoryNames($type);

        $imported = 0;
        $skipped = 0;

        foreach (self::getLegacyCategoryItems($type) as $item) {
            if ($selectedKeys !== null && !isset($selectedKeys[$item['key']])) {
                continue;
            }

            $name = $item['name'];
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

    public static function importMenus($collection = 'menuItems', $selected = null) {
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        $selectedKeys = self::selectedKeySet($selected);
        $legacyItems = self::getLegacyMenuItems($collection);
        [$existingMenuIds, $existingNames] = self::getExistingMenuKeys($collection);

        $imported = 0;
        $skipped = 0;

        foreach ($legacyItems as $item) {
            $key = self::menuKey($item);
            if ($selectedKeys !== null && !isset($selectedKeys[$key])) {
                continue;
            }

            $name = trim($item['name'] ?? '');
            if ($name === '') {
                continue;
            }

            if (self::menuItemExists($item, $existingMenuIds, $existingNames)) {
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

            $menuId = (string) ($item['menuId'] ?? '');
            if ($menuId !== '') {
                $existingMenuIds[$menuId] = true;
            }
            $existingNames[self::normalizeName($name)] = true;
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'collection' => $collection];
    }

    public static function importStocks($selected = null) {
        if (!self::canImport()) {
            throw new Exception('Cloud import is not available for this hotel');
        }

        $selectedKeys = self::selectedKeySet($selected);
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
            $key = self::stockKey($item);
            if ($selectedKeys !== null && !isset($selectedKeys[$key])) {
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
