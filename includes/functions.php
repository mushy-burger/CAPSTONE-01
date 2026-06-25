<?php
function baseUrl(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/CAPSTONE-01/index.php');
        $marker = '/CAPSTONE-01/';
        $pos = strrpos($script, $marker);
        if ($pos !== false) {
            $base = substr($script, 0, $pos + strlen($marker));
        } else {
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            if (in_array(basename($dir), ['admin', 'api'], true)) {
                $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
            }
            $base = ($dir === '' || $dir === '.' || $dir === '\\') ? '/' : $dir . '/';
        }
    }
    $url = $base . ltrim($path, '/');

    if (function_exists('currentAuthContext')) {
        $ctx = currentAuthContext();
        $urlPath = parse_url($url, PHP_URL_PATH) ?? '';
        if ($ctx !== 'default' && str_ends_with($urlPath, '.php')) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'ctx=' . rawurlencode($ctx);
        }
    }

    return $url;
}

function formatPrice(float $amount): string {
    return 'PHP ' . number_format($amount, 2);
}

function slug(string $text): string {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', trim($text)), '-'));
}

function starRating(int $rating = 5): string {
    $html = '<span class="stars" aria-label="' . (int)$rating . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating ? '&#9733;' : '&#9734;';
    }
    $html .= '</span>';
    return $html;
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function authContextField(): string {
    if (!function_exists('currentAuthContext')) {
        return '';
    }

    $ctx = currentAuthContext();
    if ($ctx === 'default') {
        return '';
    }

    return '<input type="hidden" name="ctx" value="' . htmlspecialchars($ctx, ENT_QUOTES, 'UTF-8') . '">';
}

function flashMessage(string $key, string $message): void {
    $_SESSION[$key] = $message;
}

function getFlash(string $key): string {
    $msg = $_SESSION[$key] ?? '';
    unset($_SESSION[$key]);
    return $msg;
}

function fetchOne(string $sql, array $params = []): ?array {
    require_once __DIR__ . '/db.php';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetchAllRows(string $sql, array $params = []): array {
    require_once __DIR__ . '/db.php';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function loadEnvFile(string $path): void {
    static $loaded = [];
    if (isset($loaded[$path]) || !is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }

        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }

        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }
    }

    $loaded[$path] = true;
}

function envValue(string $key, ?string $default = null): ?string {
    $rootEnv = dirname(__DIR__) . '/.env';
    loadEnvFile($rootEnv);

    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return is_string($value) ? $value : $default;
}

function normalizeMotorcycleText(string $text): string {
    $text = trim($text);
    $text = preg_replace('/([A-Za-z])([0-9])/', '$1 $2', $text);
    $text = preg_replace('/([0-9])([A-Za-z])/', '$1 $2', $text);
    $text = preg_replace('/[^A-Za-z0-9]+/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return strtolower(trim((string)$text));
}

function cleanMotorcycleLabel(string $text): string {
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim((string)$text);
}

function getProducts(int $limit = 8, ?int $categoryId = null, bool $featured = false, bool $discounted = false): array {
    require_once __DIR__ . '/db.php';
    $where = ["p.status != 'out_of_stock'"];
    $params = [];

    if ($categoryId) {
        $where[] = 'p.category_id = ?';
        $params[] = $categoryId;
    }
    if ($featured) {
        $where[] = 'p.featured = 1';
    }
    if ($discounted) {
        $where[] = 'p.original_price IS NOT NULL AND p.original_price > p.price';
    }

    $sql = "SELECT p.*, c.name AS category_name
            FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT " . (int)$limit;

    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getCategories(): array {
    require_once __DIR__ . '/db.php';
    return getDB()->query("SELECT * FROM categories ORDER BY name")->fetchAll();
}

function getMotorcycleTypes(bool $activeOnly = false): array {
    $sql = "SELECT * FROM motorcycle_types";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name";
    return fetchAllRows($sql);
}

function getMotorcycleBrands(bool $activeOnly = false): array {
    $sql = "SELECT * FROM motorcycle_brands";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name";
    return fetchAllRows($sql);
}

function getMotorcycleModels(bool $activeOnly = false): array {
    $sql = "SELECT * FROM motorcycle_models";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name";
    return fetchAllRows($sql);
}

function productImageHtml(string $image = '', string $name = '', string $class = ''): string {
    if ($image && file_exists(__DIR__ . '/../uploads/' . $image)) {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $image)));
        return '<img src="' . baseUrl('uploads/' . $encodedPath) . '" alt="' . htmlspecialchars($name) . '" class="' . htmlspecialchars($class) . '">';
    }

    $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : 'M';
    return '<div class="img-placeholder ' . htmlspecialchars($class) . '"><span>' . htmlspecialchars($initial) . '</span></div>';
}

function productCard(array $product): string {
    $detailUrl = baseUrl('product.php?id=' . (int)$product['id']);
    $price = formatPrice((float)$product['price']);
    $oldPrice = !empty($product['original_price']) ? '<span class="old-price">' . formatPrice((float)$product['original_price']) . '</span>' : '';

    return '<article class="product-card">
        <a href="' . $detailUrl . '" class="product-media">' . productImageHtml($product['image'] ?? '', $product['name'], '') . '</a>
        <div class="product-info">
            <span class="eyebrow">' . htmlspecialchars($product['category_name'] ?? $product['brand'] ?? 'Product') . '</span>
            <h3><a href="' . $detailUrl . '">' . htmlspecialchars($product['name']) . '</a></h3>
            <div class="price-line">' . $oldPrice . '<strong>' . $price . '</strong></div>
            <form method="post" action="' . baseUrl('cart.php') . '">
                ' . authContextField() . '
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="' . (int)$product['id'] . '">
                <button type="submit" class="btn btn-dark">Add to cart</button>
            </form>
        </div>
    </article>';
}

function compatibleServices(int $typeId): array {
    $services = fetchAllRows("SELECT * FROM service_types ORDER BY name");
    return array_values(array_filter($services, function ($service) use ($typeId) {
        $appliesTo = trim((string)($service['applies_to'] ?? 'all'));
        if ($appliesTo === '' || strtolower($appliesTo) === 'all') {
            return true;
        }

        $allowed = array_values(array_filter(array_map(
            'intval',
            preg_split('/\s*,\s*/', $appliesTo) ?: []
        )));

        if (!$allowed) {
            return true;
        }

        return in_array($typeId, $allowed, true);
    }));
}

function estimateServiceCost(int $serviceId, int $cc): array {
    $service = fetchOne("SELECT * FROM service_types WHERE id = ?", [$serviceId]);
    if (!$service) {
        return ['service' => null, 'items' => [], 'parts' => 0.0, 'labor' => 0.0, 'total' => 0.0];
    }

    $items = fetchAllRows(
        "SELECT r.*, p.name AS product_name, p.price, p.stock
         FROM service_material_rules r
         LEFT JOIN products p ON p.id = r.product_id
         WHERE r.service_id = ? AND ? BETWEEN r.cc_min AND r.cc_max
         ORDER BY r.id",
        [$serviceId, $cc]
    );

    $parts = 0.0;
    foreach ($items as &$item) {
        $item['unit_price'] = (float)($item['price'] ?? 0);
        $item['line_total'] = $item['unit_price'] * (float)$item['quantity'];
        $parts += $item['line_total'];
    }

    $labor = (float)$service['labor_fee'];
    return [
        'service' => $service,
        'items' => $items,
        'parts' => $parts,
        'labor' => $labor,
        'total' => $parts + $labor,
    ];
}

function ensureMultiServiceBookingSchema(): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    $ready = true;
}

function getServiceProducts(int $serviceId, int $cc): array {
    ensureMultiServiceBookingSchema();

    $service = fetchOne(
        "SELECT st.required_category, st.required_category_id, c.name AS required_category_name
         FROM service_types st
         LEFT JOIN categories c ON c.id = st.required_category_id
         WHERE st.id = ?",
        [$serviceId]
    );
    $requiredCategoryId = (int)($service['required_category_id'] ?? 0);
    $requiredCategory = trim((string)($service['required_category_name'] ?? $service['required_category'] ?? ''));

    if ($requiredCategoryId > 0) {
        $rows = fetchAllRows(
            "SELECT
                p.id,
                p.name,
                p.brand,
                p.description,
                p.price,
                p.image,
                p.stock,
                c.name AS category_name
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status != 'out_of_stock'
               AND p.category_id = ?
             ORDER BY p.name",
            [$requiredCategoryId]
        );

        if ($rows) {
            return $rows;
        }
    } elseif ($requiredCategory !== '') {
        $rows = fetchAllRows(
            "SELECT
                p.id,
                p.name,
                p.brand,
                p.description,
                p.price,
                p.image,
                p.stock,
                c.name AS category_name
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status != 'out_of_stock'
               AND LOWER(c.name) = LOWER(?)
             ORDER BY p.name",
            [$requiredCategory]
        );

        if ($rows) {
            return $rows;
        }
    }

    $rows = fetchAllRows(
        "SELECT
            p.id,
            p.name,
            p.brand,
            p.description,
            p.price,
            p.image,
            p.stock,
            c.name AS category_name
         FROM service_products sp
         INNER JOIN products p ON p.id = sp.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN service_material_rules r
            ON r.service_id = sp.service_id
           AND r.product_id = sp.product_id
         WHERE sp.service_id = ?
           AND p.status != 'out_of_stock'
           AND (r.id IS NULL OR ? BETWEEN r.cc_min AND r.cc_max)
         GROUP BY p.id, p.name, p.brand, p.description, p.price, p.image, p.stock, c.name
         ORDER BY p.name",
        [$serviceId, $cc]
    );

    if ($rows) {
        return $rows;
    }

    return fetchAllRows(
        "SELECT DISTINCT
            p.id,
            p.name,
            p.brand,
            p.description,
            p.price,
            p.image,
            p.stock,
            c.name AS category_name
         FROM service_material_rules r
         INNER JOIN products p ON p.id = r.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE r.service_id = ?
           AND ? BETWEEN r.cc_min AND r.cc_max
           AND p.status != 'out_of_stock'
         ORDER BY p.name",
        [$serviceId, $cc]
    );
}

function getBookingServiceCatalog(int $typeId, int $cc): array {
    ensureMultiServiceBookingSchema();

    $services = compatibleServices($typeId);
    foreach ($services as &$service) {
        $service['labor_fee'] = (float)($service['labor_fee'] ?? 0);
        $service['required_category'] = trim((string)($service['required_category'] ?? ''));
        $service['required_category_id'] = (int)($service['required_category_id'] ?? 0);
        $service['products'] = getServiceProducts((int)$service['id'], $cc);
    }
    unset($service);

    return $services;
}

function calculateBookingSelection(array $catalog, array $selectedServiceIds, array $selectedProducts): array {
    $serviceMap = [];
    foreach ($catalog as $service) {
        $serviceMap[(int)$service['id']] = $service;
    }

    $uniqueServiceIds = array_values(array_unique(array_map('intval', $selectedServiceIds)));
    $services = [];
    $products = [];
    $errors = [];
    $laborTotal = 0.0;
    $productsTotal = 0.0;

    foreach ($uniqueServiceIds as $serviceId) {
        if (!isset($serviceMap[$serviceId])) {
            $errors[] = 'One selected service is not compatible with this motorcycle.';
            continue;
        }

        $service = $serviceMap[$serviceId];
        $laborFee = (float)($service['labor_fee'] ?? 0);
        $laborTotal += $laborFee;

        $productOptions = [];
        foreach (($service['products'] ?? []) as $product) {
            $productOptions[(int)$product['id']] = $product;
        }

        $selectedProductId = (int)($selectedProducts[$serviceId] ?? 0);
        $selectedProduct = null;

        if ($productOptions) {
            if (!$selectedProductId || !isset($productOptions[$selectedProductId])) {
                $errors[] = 'Choose a compatible product for ' . $service['name'] . '.';
            } else {
                $selectedProduct = $productOptions[$selectedProductId];
                $selectedProduct['price'] = (float)($selectedProduct['price'] ?? 0);
                $productsTotal += $selectedProduct['price'];
                $products[] = [
                    'service_id' => $serviceId,
                    'service_name' => $service['name'],
                    'product_id' => (int)$selectedProduct['id'],
                    'product_name' => $selectedProduct['name'],
                    'product_brand' => $selectedProduct['brand'] ?? '',
                    'product_price' => (float)$selectedProduct['price'],
                ];
            }
        }

        $services[] = [
            'id' => $serviceId,
            'name' => $service['name'],
            'description' => $service['description'] ?? '',
            'labor_fee' => $laborFee,
            'products' => array_values($productOptions),
            'selected_product_id' => $selectedProductId,
            'selected_product' => $selectedProduct,
        ];
    }

    return [
        'services' => $services,
        'products' => $products,
        'labor_total' => $laborTotal,
        'products_total' => $productsTotal,
        'total_amount' => $laborTotal + $productsTotal,
        'errors' => $errors,
    ];
}

function getSiteSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = fetchAllRows("SELECT `key`, `value` FROM site_settings");
            $cache = array_column($rows, 'value', 'key');
        } catch (\Throwable $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function setSiteSetting(string $key, string $value): void {
    getDB()->prepare(
        "INSERT INTO site_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    )->execute([$key, $value]);
}

function getCustomerVehicles(int $userId): array {
    return fetchAllRows(
        "SELECT v.*, t.name AS type_name, b.name AS brand_name, m.name AS model_name
         FROM customer_vehicles v
         JOIN motorcycle_types t ON t.id = v.type_id
         JOIN motorcycle_brands b ON b.id = v.brand_id
         JOIN motorcycle_models m ON m.id = v.model_id
         WHERE v.user_id = ?
         ORDER BY v.created_at DESC",
        [$userId]
    );
}

function getCustomerVehicle(int $userId): ?array {
    return fetchOne(
        "SELECT v.*, t.name AS type_name, b.name AS brand_name, m.name AS model_name
         FROM customer_vehicles v
         JOIN motorcycle_types t ON t.id = v.type_id
         JOIN motorcycle_brands b ON b.id = v.brand_id
         JOIN motorcycle_models m ON m.id = v.model_id
         WHERE v.user_id = ?
         ORDER BY v.id DESC
         LIMIT 1",
        [$userId]
    );
}
