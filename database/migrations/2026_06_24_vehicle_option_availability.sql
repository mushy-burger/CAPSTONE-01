USE `mototrack`;

ALTER TABLE `motorcycle_types`
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `description`;

ALTER TABLE `motorcycle_brands`
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `name`;

ALTER TABLE `motorcycle_models`
  ADD COLUMN IF NOT EXISTS `cc_source` VARCHAR(255) DEFAULT NULL AFTER `cc`,
  ADD COLUMN IF NOT EXISTS `cc_confidence` DECIMAL(4,2) DEFAULT NULL AFTER `cc_source`,
  ADD COLUMN IF NOT EXISTS `last_verified_at` DATETIME DEFAULT NULL AFTER `cc_confidence`,
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `last_verified_at`;
