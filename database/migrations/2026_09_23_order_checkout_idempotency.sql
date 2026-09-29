-- One browser checkout attempt must map to exactly one MotoTrack order.
-- NULL keeps existing historical orders valid while the unique index prevents
-- duplicate inserts for the same newly generated attempt key.
ALTER TABLE `orders`
  ADD COLUMN `checkout_attempt_key` CHAR(64) NULL AFTER `checkout_session_id`,
  ADD UNIQUE KEY `uq_orders_checkout_attempt_key` (`checkout_attempt_key`);
