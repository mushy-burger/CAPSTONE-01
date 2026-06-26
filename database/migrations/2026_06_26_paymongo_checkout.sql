ALTER TABLE `orders`
  ADD COLUMN `payment_reference` VARCHAR(120) DEFAULT NULL AFTER `payment_method`,
  ADD COLUMN `payment_status` VARCHAR(50) DEFAULT NULL AFTER `payment_reference`,
  ADD COLUMN `checkout_session_id` VARCHAR(120) DEFAULT NULL AFTER `payment_status`,
  ADD COLUMN `paid_at` DATETIME DEFAULT NULL AFTER `checkout_session_id`;
