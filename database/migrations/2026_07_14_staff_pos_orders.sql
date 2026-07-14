ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `walk_in_customer_name` VARCHAR(120) DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `walk_in_customer_phone` VARCHAR(30) DEFAULT NULL AFTER `walk_in_customer_name`,
  ADD COLUMN IF NOT EXISTS `walk_in_customer_email` VARCHAR(150) DEFAULT NULL AFTER `walk_in_customer_phone`,
  ADD COLUMN IF NOT EXISTS `served_by_staff_id` INT UNSIGNED DEFAULT NULL AFTER `walk_in_customer_email`,
  ADD COLUMN IF NOT EXISTS `pos_amount_tendered` DECIMAL(10,2) DEFAULT NULL AFTER `total`,
  ADD COLUMN IF NOT EXISTS `pos_change_due` DECIMAL(10,2) DEFAULT NULL AFTER `pos_amount_tendered`,
  ADD COLUMN IF NOT EXISTS `order_notes` TEXT DEFAULT NULL AFTER `pos_change_due`,
  ADD KEY IF NOT EXISTS `served_by_staff_id` (`served_by_staff_id`);
