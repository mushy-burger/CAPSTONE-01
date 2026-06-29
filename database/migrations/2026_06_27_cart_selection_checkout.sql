USE `mototrack`;

ALTER TABLE `order_items`
  ADD COLUMN IF NOT EXISTS `cart_item_id` INT UNSIGNED DEFAULT NULL AFTER `order_id`,
  ADD KEY IF NOT EXISTS `cart_item_id` (`cart_item_id`);
