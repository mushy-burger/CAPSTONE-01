-- Reuse notifications table with optional direct navigation target.
ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `action_url` VARCHAR(255) NULL AFTER `booking_id`;

CREATE INDEX IF NOT EXISTS `idx_notifications_action_url` ON `notifications` (`action_url`);
