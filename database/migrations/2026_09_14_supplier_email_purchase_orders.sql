-- Supplier email communication and reply audit for purchase orders.
ALTER TABLE `purchase_orders`
  ADD COLUMN IF NOT EXISTS `po_reference` VARCHAR(32) DEFAULT NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `communication_status` ENUM('not_sent','awaiting_response','supplier_confirmed','partially_available','supplier_declined','needs_review') NOT NULL DEFAULT 'not_sent' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `last_sent_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `gmail_message_id` VARCHAR(128) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `gmail_thread_id` VARCHAR(128) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `supplier_email_sent_to` VARCHAR(180) DEFAULT NULL;

UPDATE `purchase_orders`
SET `po_reference` = CONCAT('PO-', LPAD(`id`, 6, '0'))
WHERE `po_reference` IS NULL OR `po_reference` = '';

ALTER TABLE `purchase_orders`
  MODIFY COLUMN `po_reference` VARCHAR(32) NOT NULL,
  ADD UNIQUE INDEX IF NOT EXISTS `uniq_purchase_order_reference` (`po_reference`),
  ADD INDEX IF NOT EXISTS `idx_purchase_order_gmail_thread` (`gmail_thread_id`),
  ADD INDEX IF NOT EXISTS `idx_purchase_order_communication_status` (`communication_status`);

CREATE TABLE IF NOT EXISTS `purchase_order_emails` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_id` INT UNSIGNED DEFAULT NULL,
  `po_reference` VARCHAR(32) NOT NULL,
  `supplier_id` INT UNSIGNED DEFAULT NULL,
  `recipient_email` VARCHAR(180) NOT NULL,
  `gmail_message_id` VARCHAR(128) NOT NULL,
  `gmail_thread_id` VARCHAR(128) DEFAULT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_po_email_message` (`gmail_message_id`),
  KEY `idx_po_email_po` (`po_id`),
  KEY `idx_po_email_thread` (`gmail_thread_id`),
  CONSTRAINT `fk_po_email_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `purchase_order_replies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_id` INT UNSIGNED DEFAULT NULL,
  `po_reference` VARCHAR(32) DEFAULT NULL,
  `gmail_message_id` VARCHAR(128) NOT NULL,
  `gmail_thread_id` VARCHAR(128) DEFAULT NULL,
  `sender` VARCHAR(255) NOT NULL,
  `recipient` VARCHAR(255) DEFAULT NULL,
  `subject` VARCHAR(500) DEFAULT NULL,
  `raw_body` MEDIUMTEXT NOT NULL,
  `received_at` DATETIME NOT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  `classification` VARCHAR(40) NOT NULL DEFAULT 'unknown',
  `confirmed_quantity` INT UNSIGNED DEFAULT NULL,
  `expected_delivery_date` DATE DEFAULT NULL,
  `supplier_message_summary` TEXT DEFAULT NULL,
  `confidence` DECIMAL(4,3) NOT NULL DEFAULT 0.000,
  `needs_admin_review` TINYINT(1) NOT NULL DEFAULT 1,
  `processing_error` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_po_reply_message` (`gmail_message_id`),
  KEY `idx_po_reply_po` (`po_id`),
  KEY `idx_po_reply_thread` (`gmail_thread_id`),
  CONSTRAINT `fk_po_reply_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
