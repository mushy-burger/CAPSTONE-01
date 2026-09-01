-- Purchase Order (PO) autogeneration for low/critical stock.
--
-- When a product's stock drops to or below min_stock, the system creates
-- a draft PO. Admin reviews and approves it. Supplier is optional; POs
-- without a supplier are still created as internal draft requests.

-- 1) Suppliers table
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `contact`    VARCHAR(100) DEFAULT NULL,
  `email`      VARCHAR(150) DEFAULT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `address`    TEXT DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Link preferred supplier + reorder quantity to each product
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `supplier_id` INT UNSIGNED DEFAULT NULL AFTER `min_stock`,
  ADD COLUMN IF NOT EXISTS `reorder_qty` INT UNSIGNED NOT NULL DEFAULT 20 AFTER `supplier_id`;

-- 3) Purchase orders header
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id`  INT UNSIGNED DEFAULT NULL,
  `status`       ENUM('draft','approved','ordered','received','cancelled') NOT NULL DEFAULT 'draft',
  `notes`        TEXT DEFAULT NULL,
  `generated_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  `approved_by`  INT UNSIGNED DEFAULT NULL,
  `approved_at`  DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4) PO line items
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_id`      INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity`   INT UNSIGNED NOT NULL,
  `unit_cost`  DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `po_id` (`po_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_po_items_po`
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
