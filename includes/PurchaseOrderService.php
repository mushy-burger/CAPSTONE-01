<?php
/**
 * Purchase Order (PO) service.
 *
 * Provides auto-generation of draft POs when product stock drops to or
 * below min_stock. Admin then reviews, approves, and tracks them through
 * to "received" in admin/purchase-orders.php.
 *
 * Design rules:
 *  - One draft PO per valid active supplier per auto-generate run.
 *  - Products with no, missing, or inactive suppliers are reported and never
 *    inserted into a purchase order.
 *  - Re-generating when a draft already exists for that supplier just
 *    upserts the line items — it does NOT create a second PO.
 *  - Approved/ordered/received POs are never touched by auto-generation.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/GmailService.php';
require_once __DIR__ . '/SupplierReplyInterpreter.php';

/**
 * Find all products that are at or below their min_stock threshold
 * and include supplier state so auto-generation can report blocked products.
 *
 * @return array  Array of product rows with supplier info.
 */
function poGetLowStockProducts(): array {
    return fetchAllRows(
        "SELECT p.id, p.name, p.stock, p.min_stock, p.reorder_qty,
                p.supplier_id,
                s.name AS supplier_name,
                s.is_active AS supplier_is_active
         FROM products p
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.stock <= p.min_stock
         ORDER BY p.supplier_id, p.name"
    );
}

/**
 * Auto-generate draft POs for all low/critical stock products.
 *
 * Groups eligible products by valid active supplier. Products blocked by
 * supplier state or an existing open PO stay eligible for a later run.
 *
 * @return array  Counts for created, updated, covered, and blocked products.
 */
function poAutoGenerate(): array {
    $products = poGetLowStockProducts();
    if (!$products) {
        return ['created' => 0, 'updated' => 0, 'items' => 0, 'covered' => 0, 'needs_supplier' => 0, 'inactive_supplier' => 0, 'invalid_supplier' => 0];
    }

    $covered = 0;
    $needsSupplier = 0;
    $inactiveSupplier = 0;
    $invalidSupplier = 0;
    $groups = [];
    foreach ($products as $p) {
        if (empty($p['supplier_id'])) {
            $needsSupplier++;
            continue;
        }
        if ($p['supplier_name'] === null) {
            $invalidSupplier++;
            continue;
        }
        if ((int)($p['supplier_is_active'] ?? 0) !== 1) {
            $inactiveSupplier++;
            continue;
        }

        $alreadyCovered = fetchOne(
            "SELECT poi.id
             FROM purchase_order_items poi
             JOIN purchase_orders po ON po.id = poi.po_id
             WHERE poi.product_id = ?
               AND po.supplier_id = ?
               AND po.status IN ('draft','approved','ordered')
             LIMIT 1",
            [(int)$p['id'], (int)$p['supplier_id']]
        );
        if ($alreadyCovered) {
            $covered++;
            continue;
        }

        $key = (int)$p['supplier_id'];
        $groups[$key][] = $p;
    }

    $created = 0;
    $updated = 0;
    $items   = 0;

    foreach ($groups as $supplierId => $group) {
        $supplierId = (int)$supplierId;

        // Find existing open draft for this supplier
        $existingPo = fetchOne(
            "SELECT id FROM purchase_orders
             WHERE status = 'draft'
               AND supplier_id = ?
             ORDER BY generated_at DESC LIMIT 1",
            [$supplierId]
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
            getDB()->prepare(
                "UPDATE purchase_orders SET po_reference = ? WHERE id = ? AND (po_reference IS NULL OR po_reference = '')"
            )->execute(['PO-' . str_pad((string)$poId, 6, '0', STR_PAD_LEFT), $poId]);
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

    return [
        'created' => $created,
        'updated' => $updated,
        'items' => $items,
        'covered' => $covered,
        'needs_supplier' => $needsSupplier,
        'inactive_supplier' => $inactiveSupplier,
        'invalid_supplier' => $invalidSupplier,
    ];
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
    $stmt = getDB()->prepare(
        "UPDATE purchase_orders
         SET status = 'approved', approved_by = ?, approved_at = NOW()
         WHERE id = ? AND status = 'draft'"
    );
    $stmt->execute([$approvedBy, $poId]);
    return $stmt->rowCount() === 1;
}

/**
 * Move a PO to the next status step.
 * approved → ordered → received
 */
function poAdvanceStatus(int $poId, string $newStatus): bool {
    $allowedFrom = [
        'ordered'   => ['approved'],
        'received'  => ['ordered'],
        'cancelled' => ['draft', 'approved', 'ordered'],
    ];
    if (!isset($allowedFrom[$newStatus])) {
        return false;
    }

    $db = getDB();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        $stmt = $db->prepare("SELECT id, status FROM purchase_orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$poId]);
        $po = $stmt->fetch();
        if (!$po || !in_array($po['status'], $allowedFrom[$newStatus], true)) {
            if ($ownsTransaction) {
                $db->rollBack();
            }
            return false;
        }

        $stmt = $db->prepare("UPDATE purchase_orders SET status = ? WHERE id = ? AND status = ?");
        $stmt->execute([$newStatus, $poId, $po['status']]);
        if ($stmt->rowCount() !== 1) {
            if ($ownsTransaction) {
                $db->rollBack();
            }
            return false;
        }

        // Add received quantities exactly once, inside the same locked transaction.
        if ($newStatus === 'received') {
            $stmt = $db->prepare(
                "SELECT product_id, quantity FROM purchase_order_items WHERE po_id = ?"
            );
            $stmt->execute([$poId]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $db->prepare(
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

        if ($ownsTransaction) {
            $db->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function poEmailItems(int $poId): array
{
    return fetchAllRows(
        "SELECT poi.product_id, poi.quantity, p.name AS product_name,
                p.stock, p.min_stock, p.reorder_qty
         FROM purchase_order_items poi
         JOIN products p ON p.id = poi.product_id
         WHERE poi.po_id = ? ORDER BY p.name",
        [$poId]
    );
}

function poEmailBody(array $po, array $items): string
{
    $supplier = (string)($po['supplier_name'] ?? 'Supplier');
    $body = "Hello {$supplier},\n\n";
    $body .= "MotoTrack would like to place the following purchase order.\n\n";
    $body .= "Purchase Order: {$po['po_reference']}\n\nItems:\n";
    foreach ($items as $item) {
        $body .= "- {$item['product_name']}\n";
        $body .= "  Quantity: " . (int)$item['quantity'] . "\n";
        $body .= "  Current stock: " . (int)$item['stock'] . "; minimum stock: " . (int)$item['min_stock'] . "\n";
    }
    $body .= "\nPlease reply to this email to confirm availability and provide an expected delivery date if available.\n\n";
    $body .= "Thank you,\nMotoTrack\n";
    return $body;
}

function poSendToSupplier(int $poId, bool $resend = false): array
{
    $po = fetchOne(
        "SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, s.is_active
         FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id
         WHERE po.id = ?",
        [$poId]
    );
    if (!$po) {
        throw new RuntimeException('Purchase order not found.');
    }
    if (trim((string)($po['po_reference'] ?? '')) === '') {
        $reference = 'PO-' . str_pad((string)$poId, 6, '0', STR_PAD_LEFT);
        getDB()->prepare(
            "UPDATE purchase_orders SET po_reference = ? WHERE id = ? AND (po_reference IS NULL OR po_reference = '')"
        )->execute([$reference, $poId]);
        $po['po_reference'] = $reference;
    }
    if (!in_array($po['status'], ['approved', 'ordered'], true)) {
        throw new RuntimeException('Only approved or ordered purchase orders can be sent.');
    }
    if (!$resend && $po['communication_status'] !== 'not_sent') {
        throw new RuntimeException('Purchase order was already sent. Use explicit resend if needed.');
    }
    if (!$po['supplier_id'] || (int)$po['is_active'] !== 1) {
        throw new RuntimeException('Purchase order supplier is missing or inactive.');
    }
    if (!filter_var($po['supplier_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Supplier email is missing or invalid.');
    }
    $items = poEmailItems($poId);
    if (!$items) {
        throw new RuntimeException('Purchase order has no valid product items.');
    }
    $db = getDB();
    if (!$resend) {
        $claim = $db->prepare(
            "UPDATE purchase_orders SET communication_status = 'awaiting_response'
             WHERE id = ? AND communication_status = 'not_sent'"
        );
        $claim->execute([$poId]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException('Purchase order send is already in progress or was already sent.');
        }
    }
    $gmail = new GmailService();
    try {
        $sent = $gmail->sendMessage(
            (string)$po['supplier_email'],
            'MotoTrack Purchase Order ' . $po['po_reference'],
            poEmailBody($po, $items)
        );
    } catch (Throwable $e) {
        if (!$resend) {
            $db->prepare(
                "UPDATE purchase_orders SET communication_status = 'not_sent'
                 WHERE id = ? AND gmail_message_id IS NULL"
            )->execute([$poId]);
        }
        throw $e;
    }
    if ($sent['message_id'] === '') {
        if (!$resend) {
            $db->prepare(
                "UPDATE purchase_orders SET communication_status = 'not_sent'
                 WHERE id = ? AND gmail_message_id IS NULL"
            )->execute([$poId]);
        }
        throw new RuntimeException('Gmail did not return a message ID.');
    }

    $db->beginTransaction();
    try {
        $db->prepare(
            "INSERT INTO purchase_order_emails
                (po_id, po_reference, supplier_id, recipient_email, gmail_message_id, gmail_thread_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$poId, $po['po_reference'], $po['supplier_id'], $po['supplier_email'], $sent['message_id'], $sent['thread_id'] ?: null]);
        $db->prepare(
            "UPDATE purchase_orders
             SET communication_status = 'awaiting_response', last_sent_at = NOW(),
                 gmail_message_id = ?, gmail_thread_id = ?, supplier_email_sent_to = ?
             WHERE id = ?"
        )->execute([$sent['message_id'], $sent['thread_id'] ?: null, $po['supplier_email'], $poId]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
    return $sent;
}

function poMatchSupplierReply(array $message): ?array
{
    $gmail = new GmailService();
    $sender = $gmail->senderEmail((string)$message['sender']);
    $thread = (string)$message['thread_id'];
    if ($sender === '' || $thread === '') {
        return null;
    }
    $haystack = (string)$message['subject'] . "\n" . (string)$message['body'];
    preg_match_all('/\b(PO-\d{6,})\b/i', $haystack, $referenceMatches);
    $references = array_values(array_unique(array_map('strtoupper', $referenceMatches[1] ?? [])));
    // A supplier reply is eligible only when it belongs to the exact
    // outbound conversation for one PO. Never fall back to a PO reference or
    // sender-only inbox search: both can cross-contaminate separate POs.
    $threadMatches = fetchAllRows(
        "SELECT po.id, po.po_reference, po.supplier_id, po.last_sent_at,
                s.name AS supplier_name, s.email AS supplier_email,
                COALESCE(NULLIF(po.supplier_email_sent_to, ''), s.email) AS expected_supplier_email
         FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id
         WHERE po.gmail_thread_id = ? OR EXISTS
           (SELECT 1 FROM purchase_order_emails poe
            WHERE poe.po_id = po.id AND poe.gmail_thread_id = ?)
         ORDER BY po.id DESC",
        [$thread, $thread]
    );
    $threadMatches = array_values(array_filter(
        $threadMatches,
        static function (array $candidate) use ($sender, $references): bool {
            $expected = strtolower(trim((string)($candidate['expected_supplier_email'] ?? '')));
            if ($expected === '' || $expected !== $sender) {
                return false;
            }
            return !$references || in_array(strtoupper((string)$candidate['po_reference']), $references, true);
        }
    ));
    return count($threadMatches) === 1 ? $threadMatches[0] : null;
}

function poRecomputeCommunicationStatus(int $poId): ?array
{
    $reply = fetchOne(
        "SELECT classification, needs_admin_review, received_at, id
         FROM purchase_order_replies
         WHERE po_id = ? AND processed_at IS NOT NULL
         ORDER BY received_at DESC, id DESC LIMIT 1",
        [$poId]
    );
    if (!$reply) {
        return null;
    }
    $status = poReplyCommunicationStatus((string)$reply['classification'], (bool)$reply['needs_admin_review']);
    getDB()->prepare(
        "UPDATE purchase_orders SET communication_status=?
         WHERE id=? AND status NOT IN ('received','cancelled')"
    )->execute([$status, $poId]);
    return $reply;
}

function poReplyCommunicationStatus(string $classification, bool $needsReview): string
{
    if ($needsReview && in_array($classification, ['unknown', 'needs_clarification'], true)) {
        return 'needs_review';
    }
    return match ($classification) {
        'confirmed' => 'supplier_confirmed',
        'partially_available' => 'partially_available',
        'out_of_stock' => 'out_of_stock',
        'declined' => 'declined',
        'needs_clarification' => 'needs_clarification',
        'delivery_update' => 'delivery_update',
        'preparing' => 'preparing',
        'shipped' => 'shipped',
        'in_transit' => 'in_transit',
        'out_for_delivery' => 'out_for_delivery',
        'delayed' => 'delayed',
        'supplier_says_delivered' => 'supplier_says_delivered',
        'unknown' => 'unknown',
        default => 'awaiting_response',
    };
}

function poReplyNotificationType(string $classification, bool $needsReview): string
{
    if ($needsReview) {
        return 'purchase_order_reply_review';
    }
    return 'purchase_order_reply_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($classification));
}

function poNotifyAdmins(array $match, array $message, array $interpretation): void
{
    $poReference = (string)$match['po_reference'];
    $supplierName = trim((string)($match['supplier_name'] ?? '')) ?: 'Supplier';
    $reply = mb_substr(trim((string)($message['clean_body'] ?? $message['body'] ?? '')), 0, 240);
    $classification = (string)$interpretation['classification'];
    $needsReview = (bool)$interpretation['needs_admin_review'];
    $labels = [
        'confirmed' => 'Supplier confirmed',
        'partially_available' => 'Supplier can only partially fulfill',
        'out_of_stock' => 'Supplier reports items unavailable',
        'declined' => 'Supplier declined',
        'delivery_update' => 'Delivery update',
        'preparing' => 'Supplier is preparing',
        'shipped' => 'Supplier shipped',
        'in_transit' => 'PO is in transit',
        'out_for_delivery' => 'PO is out for delivery',
        'delayed' => 'Delivery delayed',
        'supplier_says_delivered' => 'Supplier reports delivery — verify physical delivery',
        'needs_clarification' => 'Supplier needs clarification',
        'unknown' => 'Unrecognized supplier reply',
    ];
    $headline = $classification === 'supplier_says_delivered'
        ? "Supplier reports {$poReference} delivered — verify physical delivery"
        : ($needsReview
            ? "Supplier reply for {$poReference} requires review"
            : (($labels[$classification] ?? 'Supplier reply received') . " {$poReference}"));
    $details = $headline . '. ' . $supplierName . ' replied';
    if ($reply !== '') {
        $details .= ': "' . $reply . '"';
    }
    $details .= '. AI: ' . ucfirst(str_replace('_', ' ', $classification));
    if ($interpretation['confirmed_quantity'] !== null) {
        $details .= '; Quantity: ' . (int)$interpretation['confirmed_quantity'];
    }
    if (!empty($interpretation['expected_delivery_date'])) {
        $details .= '; Expected delivery: ' . date('M j, Y', strtotime((string)$interpretation['expected_delivery_date']));
    }
    $details .= '; Needs review: ' . ($needsReview ? 'Yes' : 'No');
    $type = poReplyNotificationType($classification, $needsReview);
    $actionUrl = baseUrl('admin/purchase-orders.php?id=' . (int)$match['id']);
    foreach (fetchAllRows("SELECT id FROM users WHERE role = 'admin' AND is_active = 1") as $admin) {
        getDB()->prepare(
            "INSERT INTO notifications (user_id, type, message, booking_id, action_url) VALUES (?, ?, ?, NULL, ?)"
        )->execute([(int)$admin['id'], $type, $details, $actionUrl]);
    }
}

/**
 * Final persistence guard: the PO must still own the exact Gmail thread.
 * This protects against metadata changing between match and insert.
 */
function poReplyThreadBelongsToMatch(array $match, string $thread): bool
{
    if ($thread === '' || empty($match['id'])) {
        return false;
    }
    return (bool)fetchOne(
        "SELECT po.id
         FROM purchase_orders po
         WHERE po.id = ? AND (
             po.gmail_thread_id = ? OR EXISTS (
                 SELECT 1 FROM purchase_order_emails poe
                 WHERE poe.po_id = po.id AND poe.gmail_thread_id = ?
             )
         )
         LIMIT 1",
        [(int)$match['id'], $thread, $thread]
    );
}

function poProcessSupplierReplies(): array
{
    $gmail = new GmailService();
    $seen = 0;
    $processed = 0;
    $skipped = 0;
    $newReplies = [];
    $affectedPoIds = [];
    $messages = [];
    foreach ($gmail->listMessages('in:anywhere newer_than:365d') as $stub) {
        $messages[] = $gmail->extractMessage($gmail->getMessage((string)$stub['id']));
    }
    usort($messages, static function (array $left, array $right): int {
        $timeCompare = ((int)($left['internal_date_ms'] ?? 0)) <=> ((int)($right['internal_date_ms'] ?? 0));
        return $timeCompare !== 0 ? $timeCompare : strcmp((string)$left['message_id'], (string)$right['message_id']);
    });
    foreach ($messages as $message) {
        if (!$message['message_id'] || $gmail->senderEmail($message['sender']) === strtolower($gmail->senderEmailAddress())) {
            continue;
        }
        $match = poMatchSupplierReply($message);
        if (!$match) {
            continue;
        }
        $seen++;
        $affectedPoIds[(int)$match['id']] = true;
        $existing = fetchOne("SELECT id, processed_at FROM purchase_order_replies WHERE gmail_message_id = ?", [$message['message_id']]);
        if ($existing && $existing['processed_at'] !== null) {
            $skipped++;
            continue;
        }
        $senderEmail = $gmail->senderEmail($message['sender']);
        $expectedSupplierEmail = strtolower(trim((string)($match['expected_supplier_email'] ?? $match['supplier_email'] ?? '')));
        $senderValid = $senderEmail !== '' && $expectedSupplierEmail !== '' && $expectedSupplierEmail === $senderEmail;
        if (!$senderValid || (string)$message['thread_id'] === '' || !poReplyThreadBelongsToMatch($match, (string)$message['thread_id'])) {
            continue;
        }
        $items = poEmailItems((int)$match['id']);
        $interpretation = interpretSupplierReply((string)$match['supplier_name'], (string)$match['po_reference'], (string)($message['clean_body'] ?? $message['body']), $items, $match['last_sent_at'] ?? null);
        $db = getDB();
        if ($existing) {
            $db->prepare(
                "UPDATE purchase_order_replies SET po_id=?,po_reference=?,gmail_thread_id=?,sender=?,recipient=?,subject=?,raw_body=?,clean_body=?,clean_body_needs_review=?,received_at=?,processed_at=NOW(),classification=?,confirmed_quantity=?,expected_delivery_date=?,supplier_message_summary=?,confidence=?,needs_admin_review=?,processing_error=? WHERE id=?"
            )->execute([(int)$match['id'], $match['po_reference'], $message['thread_id'] ?: null, $message['sender'], $message['recipient'] ?: null, $message['subject'] ?: null, $message['body'], $message['clean_body'] ?? $message['body'], !empty($message['clean_body_needs_review']) ? 1 : 0, $message['timestamp'], $interpretation['classification'], $interpretation['confirmed_quantity'], $interpretation['expected_delivery_date'], $interpretation['supplier_message_summary'], $interpretation['confidence'], $interpretation['needs_admin_review'] ? 1 : 0, $interpretation['error'] ?? null, (int)$existing['id']]);
        } else {
            $db->prepare(
                "INSERT INTO purchase_order_replies (po_id,po_reference,gmail_message_id,gmail_thread_id,sender,recipient,subject,raw_body,clean_body,clean_body_needs_review,received_at,processed_at,classification,confirmed_quantity,expected_delivery_date,supplier_message_summary,confidence,needs_admin_review,processing_error) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?)"
            )->execute([(int)$match['id'], $match['po_reference'], $message['message_id'], $message['thread_id'] ?: null, $message['sender'], $message['recipient'] ?: null, $message['subject'] ?: null, $message['body'], $message['clean_body'] ?? $message['body'], !empty($message['clean_body_needs_review']) ? 1 : 0, $message['timestamp'], $interpretation['classification'], $interpretation['confirmed_quantity'], $interpretation['expected_delivery_date'], $interpretation['supplier_message_summary'], $interpretation['confidence'], $interpretation['needs_admin_review'] ? 1 : 0, $interpretation['error'] ?? null]);
        }
        $status = poReplyCommunicationStatus($interpretation['classification'], (bool)$interpretation['needs_admin_review']);
        $db->prepare("UPDATE purchase_orders SET communication_status=? WHERE id=? AND status NOT IN ('received','cancelled')")->execute([$status, (int)$match['id']]);
        poNotifyAdmins($match, $message, $interpretation);
        $newReplies[] = [
            'po_id' => (int)$match['id'],
            'po_reference' => (string)$match['po_reference'],
            'supplier_name' => (string)($match['supplier_name'] ?? 'Supplier'),
            'supplier_email' => (string)($match['supplier_email'] ?? ''),
            'reply' => trim((string)($message['clean_body'] ?? $message['body'] ?? '')),
            'classification' => (string)$interpretation['classification'],
            'confirmed_quantity' => $interpretation['confirmed_quantity'],
            'expected_delivery_date' => $interpretation['expected_delivery_date'],
            'confidence' => $interpretation['confidence'],
            'needs_admin_review' => (bool)$interpretation['needs_admin_review'],
        ];
        $processed++;
    }
    foreach (array_keys($affectedPoIds) as $poId) {
        poRecomputeCommunicationStatus((int)$poId);
    }
    return ['seen' => $seen, 'processed' => $processed, 'skipped' => $skipped, 'new_replies' => $newReplies];
}
