-- PMS (Preventive Maintenance System) reminder based on last service date.
--
-- After a job completes, we stamp the vehicle's last_service_date.
-- A separate CLI worker fires reminders when that date + the configured
-- interval falls on or before today, ensuring customers come back on time.

-- 1) Track last service on customer vehicles
ALTER TABLE `customer_vehicles`
  ADD COLUMN IF NOT EXISTS `last_service_date` DATE DEFAULT NULL AFTER `plate_number`,
  ADD COLUMN IF NOT EXISTS `last_service_booking_id` INT UNSIGNED DEFAULT NULL AFTER `last_service_date`;

-- 2) Admin-configurable PMS interval (default 90 days) + enable toggle
INSERT INTO `site_settings` (`key`, `value`) VALUES ('pms_reminder_interval_days', '90')
  ON DUPLICATE KEY UPDATE `key` = `key`;

INSERT INTO `site_settings` (`key`, `value`) VALUES ('pms_reminder_enabled', '1')
  ON DUPLICATE KEY UPDATE `key` = `key`;

-- 3) Track which vehicles already received a PMS reminder (prevent spam)
--    We re-use the notifications table so no new table is needed.
--    The check is: has a 'pms_reminder' notification been sent to this
--    user_id for this vehicle_id in the last 30 days?
--    We store vehicle_id in booking_id column as a convention here (no FK).
