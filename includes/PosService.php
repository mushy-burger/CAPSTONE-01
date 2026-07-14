<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function posTableColumns(string $table): array {
    static $cache = [];
    if (!isset($cache[$table])) {
        $stmt = getDB()->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        $cache[$table] = array_fill_keys(array_column($stmt->fetchAll(), 'COLUMN_NAME'), true);
    }
    return $cache[$table];
}

function posColumnExists(string $table, string $column): bool {
    $columns = posTableColumns($table);
    return isset($columns[$column]);
}

function createPosSale(array $requestedItems, array $customer, string $paymentMethod, float $amountTendered, int $staffId, string $notes = ''): int {
    $paymentMethod = strtolower(trim($paymentMethod));
    $allowedMethods = ['cash', 'gcash', 'card', 'bank_transfer', 'other'];
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        throw new RuntimeException('Select a valid payment method.');
    }

    $quantities = [];
    foreach ($requestedItems as $productId => $qty) {
        $productId = (int)$productId;
        $qty = (int)$qty;
        if ($productId > 0 && $qty > 0) {
            $quantities[$productId] = ($quantities[$productId] ?? 0) + $qty;
        }
    }

    if (!$quantities) {
        throw new RuntimeException('Add at least one product to the POS cart.');
    }

    $db = getDB();
    $db->beginTransaction();

    try {
        $ids = array_keys($quantities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $products = fetchAllRows(
            "SELECT *
             FROM products
             WHERE id IN ($placeholders)
             FOR UPDATE",
            $ids
        );

        $productsById = [];
        foreach ($products as $product) {
            $productsById[(int)$product['id']] = $product;
        }

        $lineItems = [];
        $subtotal = 0.0;
        foreach ($quantities as $productId => $qty) {
            $product = $productsById[$productId] ?? null;
            if (!$product) {
                throw new RuntimeException('One of the selected products no longer exists.');
            }
            if (($product['status'] ?? '') === 'out_of_stock' || (int)$product['stock'] <= 0) {
                throw new RuntimeException($product['name'] . ' is out of stock.');
            }
            if ((int)$product['stock'] < $qty) {
                throw new RuntimeException($product['name'] . ' only has ' . (int)$product['stock'] . ' left in stock.');
            }

            $price = (float)$product['price'];
            $subtotal += $price * $qty;
            $lineItems[] = [
                'id' => $productId,
                'name' => $product['name'],
                'quantity' => $qty,
                'price' => $price,
            ];
        }

        $total = $subtotal;
        if ($paymentMethod === 'cash' && $amountTendered + 0.0001 < $total) {
            throw new RuntimeException('Cash tendered is less than the order total.');
        }
        $changeDue = $paymentMethod === 'cash' ? max(0, $amountTendered - $total) : 0.0;

        $orderColumns = posTableColumns('orders');
        $insert = [
            'user_id' => null,
            'subtotal' => $subtotal,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'status' => 'completed',
        ];

        $optionalValues = [
            'payment_status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
            'walk_in_customer_name' => trim((string)($customer['name'] ?? '')),
            'walk_in_customer_phone' => trim((string)($customer['phone'] ?? '')),
            'walk_in_customer_email' => trim((string)($customer['email'] ?? '')),
            'served_by_staff_id' => $staffId,
            'pos_amount_tendered' => $paymentMethod === 'cash' ? $amountTendered : null,
            'pos_change_due' => $paymentMethod === 'cash' ? $changeDue : null,
            'order_notes' => trim($notes),
        ];
        foreach ($optionalValues as $column => $value) {
            if (isset($orderColumns[$column])) {
                $insert[$column] = $value;
            }
        }

        $columns = array_keys($insert);
        $sql = "INSERT INTO orders (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")";
        $db->prepare($sql)->execute(array_values($insert));
        $orderId = (int)$db->lastInsertId();

        if (isset($orderColumns['payment_reference'])) {
            $db->prepare("UPDATE orders SET payment_reference = ? WHERE id = ?")
                ->execute(['POS-' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT), $orderId]);
        }

        $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stockStmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
        $hasMinStock = posColumnExists('products', 'min_stock');
        $statusSql = $hasMinStock
            ? "UPDATE products SET status = CASE WHEN stock = 0 THEN 'out_of_stock' WHEN stock <= min_stock THEN 'low_stock' ELSE 'available' END WHERE id = ?"
            : "UPDATE products SET status = CASE WHEN stock = 0 THEN 'out_of_stock' WHEN stock <= 5 THEN 'low_stock' ELSE 'available' END WHERE id = ?";
        $statusStmt = $db->prepare($statusSql);

        foreach ($lineItems as $item) {
            $itemStmt->execute([$orderId, $item['id'], $item['quantity'], $item['price']]);
            $stockStmt->execute([$item['quantity'], $item['id'], $item['quantity']]);
            if ($stockStmt->rowCount() !== 1) {
                throw new RuntimeException($item['name'] . ' does not have enough stock.');
            }
            $statusStmt->execute([$item['id']]);
        }

        $db->commit();
        return $orderId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
