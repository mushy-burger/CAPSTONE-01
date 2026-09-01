<?php
/**
 * Purchase Order (PO) service.
 *
 * Provides auto-generation of draft POs when product stock drops to or
 * below min_stock. Admin then reviews, approves, and tracks them through
 * to "received" in admin/purchase-orders.php.
 *
 * Design rules:
 *  - One draft PO per supplier per auto-generate run (products with no
 *    supplier are grouped into a single "No Supplier" draft PO).
 *  - Re-generating when a draft already exists for that supplier just
 *    upserts the line items — it does NOT create a second PO.
 *  - Approved/ordered/received POs are never touched by auto-generation.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Find all products that are at or below their min_stock threshold
 * and have no open (draft/approved/ordered) PO line item covering them.
 *
 * @return array  Array of product rows with supplier info.
 */
function poGetLowStockProducts(): array {
    return fetchAllRows(
        "SELECT p.id, p.name, p.stock, p.min_stock, p.reorder_qty,
                p.supplier_id,
                s.name AS supplier_name
         FROM products p
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.stock <= p.min_stock
           AND p.status != 'out_of_stock' OR p.stock = 0
           AND NOT EXISTS (
               SELECT 1 FROM purchase_order_items poi
               JOIN purchase_orders po ON po.id = poi.po_id
               WHERE poi.product_id = p.id
                 AND po.status IN ('draft','approved','ordered')
           )
         ORDER BY p.supplier_id, p.name"
    );
}

/**
 * Auto-generate draft POs for all low/critical stock products.
 *
 * Groups products by supplier_id (NULL = "No Supplier" group).
 * For each group, finds or creates an open draft PO, then upserts
 * line items.
 *
 * @return array  ['created' => int, 'updated' => int, 'items' => int]
 */
function poAutoGenerate(): array {
    $products = poGetLowStockProducts();
    if (!$products) {
        return ['created' => 0, 'updated' => 0, 'items' => 0];
    }

    // Group by supplier_id (null → key 'none')
    $groups = [];
    foreach ($products as $p) {
        $key = $p['supplier_id'] ?? 'none';
        $groups[$key][] = $p;
    }

    $created = 0;
    $updated = 0;
    $items   = 0;

    foreach ($groups as $supplierId => $group) {
        $supplierId = $supplierId === 'none' ? null : (int)$supplierId;

        // Find existing open draft for this supplier
        $existingPo = fetchOne(
            "SELECT id FROM purchase_orders
             WHERE status = 'draft'
               AND " . ($supplierId ? "supplier_id = ?" : "supplier_id IS NULL") . "
             ORDER BY generated_at DESC LIMIT 1",
            $supplierId ? [$supplierId] : []
        );

        if ($existingPo) {
            $poId = (int)$existingPo['id'];
            $updated++;
        } else {
            getDB()->prepare(
                "INSERT INTO purchase_orders (supplier_id, status, notes)
                 VALUES (?, 'draft', 'Auto-generated for low/critical stock')"
            )->execute([$supplierId]);
            $poId = (int)getDB()->lastInsertId();
            $created++;
        }

        // Upsert line items
        $stmt = getDB()->prepare(
            "INSERT INTO purchase_order_items (po_id, product_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)"
        );
        foreach ($group as $product) {
            $qty = max(1, (int)$product['reorder_qty']);
            $stmt->execute([$poId, (int)$product['id'], $qty]);
            $items++;
        }
    }

    return ['created' => $created, 'updated' => $updated, 'items' => $items];
}

/**
 * Trigger PO check for a single product (called after any stock deduction).
 * Only auto-generates if the product is now at or below min_stock.
 */
function poCheckAndGenerateForProduct(int $productId): void {
    $product = fetchOne(
        "SELECT id, stock, min_stock FROM products WHERE id = ?",
        [$productId]
    );
    if (!$product) {
        return;
    }
    if ((int)$product['stock'] <= (int)$product['min_stock']) {
        // Check if this specific product already has an open PO item
        $existing = fetchOne(
            "SELECT poi.id FROM purchase_order_items poi
             JOIN purchase_orders po ON po.id = poi.po_id
             WHERE poi.product_id = ? AND po.status IN ('draft','approved','ordered')
             LIMIT 1",
            [$productId]
        );
        if ($existing) {
            return; // Already covered
        }
        poAutoGenerate();
    }
}

/**
 * Approve a PO.
 *
 * @param int $poId
 * @param int $approvedBy  User ID of approving admin.
 */
function poApprove(int $poId, int $approvedBy): bool {
    $po = fetchOne("SELECT id, status FROM purchase_orders WHERE id = ?", [$poId]);
    if (!$po || $po['status'] !== 'draft') {
        return false;
    }
    getDB()->prepare(
        "UPDATE purchase_orders SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?"
    )->execute([$approvedBy, $poId]);
    return true;
}

/**
 * Move a PO to the next status step.
 * approved → ordered → received
 */
function poAdvanceStatus(int $poId, string $newStatus): bool {
    $allowed = ['ordered', 'received', 'cancelled'];
    if (!in_array($newStatus, $allowed, true)) {
        return false;
    }
    $po = fetchOne("SELECT id, status FROM purchase_orders WHERE id = ?", [$poId]);
    if (!$po) {
        return false;
    }

    getDB()->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?")
           ->execute([$newStatus, $poId]);

    // When received, bump product stock by reorder_qty
    if ($newStatus === 'received') {
        $items = fetchAllRows(
            "SELECT poi.product_id, poi.quantity
             FROM purchase_order_items poi
             WHERE poi.po_id = ?",
            [$poId]
        );
        foreach ($items as $item) {
            getDB()->prepare(
                "UPDATE products
                 SET stock = stock + ?,
                     status = CASE
                       WHEN stock + ? = 0 THEN 'out_of_stock'
                       WHEN stock + ? <= min_stock THEN 'low_stock'
                       ELSE 'available'
                     END
                 WHERE id = ?"
            )->execute([$item['quantity'], $item['quantity'], $item['quantity'], $item['product_id']]);
        }
    }

    return true;
}
