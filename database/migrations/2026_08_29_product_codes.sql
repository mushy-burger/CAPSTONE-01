-- Product identification codes (barcode / QR) for MotoTrack.
--
-- A product may carry several codes at once: the manufacturer barcode printed
-- on the item, plus a MotoTrack-generated code for items that ship without a
-- usable one. That is a one-to-many relationship, so the codes live in their
-- own table instead of columns on `products`.
--
-- `products`.`id` remains the product identity. A row here is only a lookup
-- key pointing back at it.

CREATE TABLE IF NOT EXISTS `product_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `code_type` ENUM('manufacturer','mototrack') NOT NULL DEFAULT 'manufacturer',
  `symbology` ENUM('qr','barcode','both') NOT NULL DEFAULT 'both',
  `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  -- Duplicate protection: no two products can ever hold the same code.
  -- Enforced by the database so concurrent saves cannot race past a PHP check.
  UNIQUE KEY `uniq_product_code` (`code`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_product_codes_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
