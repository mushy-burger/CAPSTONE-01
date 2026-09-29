-- Shop-order delivery state. Payment and fulfillment status remain unchanged.
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `tracking_url` VARCHAR(2048) NULL AFTER `paid_at`,
  ADD COLUMN IF NOT EXISTS `delivery_status` ENUM('pending','delivering','delivered') NOT NULL DEFAULT 'pending' AFTER `tracking_url`,
  ADD COLUMN IF NOT EXISTS `delivered_at` DATETIME NULL AFTER `delivery_status`,
  ADD KEY IF NOT EXISTS `idx_orders_delivery_status` (`delivery_status`);

-- Rollback only before delivery data is needed:
-- ALTER TABLE `orders`
--   DROP INDEX `idx_orders_delivery_status`,
--   DROP COLUMN `delivered_at`,
--   DROP COLUMN `delivery_status`,
--   DROP COLUMN `tracking_url`;
