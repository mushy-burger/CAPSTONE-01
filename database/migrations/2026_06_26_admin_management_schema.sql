USE `mototrack`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;

ALTER TABLE `service_types`
  ADD COLUMN IF NOT EXISTS `required_category` VARCHAR(120) DEFAULT NULL AFTER `applies_to`,
  ADD COLUMN IF NOT EXISTS `required_category_id` INT UNSIGNED DEFAULT NULL AFTER `required_category`;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `service_products` (
  `service_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`service_id`, `product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `vehicle_id` INT UNSIGNED DEFAULT NULL,
  `scheduled_date` DATE NOT NULL,
  `scheduled_time` TIME DEFAULT NULL,
  `status` ENUM('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `labor_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `products_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `booking_services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `labor_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `service_name` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `booking_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED DEFAULT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `product_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `product_name` VARCHAR(150) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `service_id` (`service_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `service_bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `vehicle_id` INT UNSIGNED DEFAULT NULL,
  `service_type_id` INT UNSIGNED NOT NULL,
  `scheduled_date` DATE NOT NULL,
  `scheduled_time` TIME DEFAULT NULL,
  `status` ENUM('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `parts_cost` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `labor_cost` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_cost` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `service_booking_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `material_label` VARCHAR(100) NOT NULL,
  `quantity` DECIMAL(6,2) NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `service_products` (`service_id`, `product_id`)
SELECT DISTINCT `service_id`, `product_id`
FROM `service_material_rules`
WHERE `product_id` IS NOT NULL;

UPDATE `service_types` st
INNER JOIN `categories` c ON LOWER(c.name) = LOWER(st.required_category)
SET st.required_category_id = c.id
WHERE st.required_category IS NOT NULL
  AND st.required_category != ''
  AND st.required_category_id IS NULL;

INSERT IGNORE INTO `bookings`
  (`id`, `user_id`, `vehicle_id`, `scheduled_date`, `scheduled_time`, `status`, `notes`, `labor_total`, `products_total`, `total_amount`, `created_at`)
SELECT
  sb.`id`,
  sb.`user_id`,
  sb.`vehicle_id`,
  sb.`scheduled_date`,
  sb.`scheduled_time`,
  sb.`status`,
  sb.`notes`,
  sb.`labor_cost`,
  sb.`parts_cost`,
  sb.`total_cost`,
  sb.`created_at`
FROM `service_bookings` sb;

INSERT IGNORE INTO `booking_services`
  (`booking_id`, `service_id`, `labor_fee`, `service_name`, `created_at`)
SELECT
  sb.`id`,
  sb.`service_type_id`,
  sb.`labor_cost`,
  st.`name`,
  sb.`created_at`
FROM `service_bookings` sb
LEFT JOIN `service_types` st ON st.id = sb.service_type_id;

INSERT IGNORE INTO `booking_products`
  (`booking_id`, `service_id`, `product_id`, `product_price`, `product_name`, `created_at`)
SELECT
  sbi.`booking_id`,
  sb.`service_type_id`,
  sbi.`product_id`,
  sbi.`unit_price`,
  COALESCE(p.`name`, sbi.`material_label`),
  sb.`created_at`
FROM `service_booking_items` sbi
JOIN `service_bookings` sb ON sb.id = sbi.booking_id
LEFT JOIN `products` p ON p.id = sbi.product_id
WHERE sbi.`product_id` IS NOT NULL;

DROP TABLE IF EXISTS `service_booking_items`;
DROP TABLE IF EXISTS `service_bookings`;
