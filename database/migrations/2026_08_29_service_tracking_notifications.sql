-- Technician service tracking + customer notification log.
--
-- `bookings` already carries `technician_id`, `assigned_at`, `completed_at` and
-- `estimated_duration_minutes`, so only the actual start timestamp is missing.
-- `completed_at` is reused as the actual completion time rather than adding a
-- second column that would mean the same thing.
--
-- Actual duration is NOT stored: it is derived from the two timestamps, so it
-- can never disagree with them or be tampered with independently.

ALTER TABLE `bookings`
  ADD COLUMN `actual_start_time` DATETIME NULL DEFAULT NULL AFTER `assigned_at`;

-- Delivery log for customer-facing messages (SMS / email).
--
-- Notification delivery is deliberately separate from booking state: a failed
-- SMS is recorded here and never rolls back a confirmed or completed job.
-- The unique key gives idempotency — one event per booking per channel — so a
-- double-clicked "Complete Job" cannot send the same SMS twice.
CREATE TABLE IF NOT EXISTS `notification_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NULL DEFAULT NULL,
  `user_id` INT UNSIGNED NULL DEFAULT NULL,
  `notification_type` ENUM('appointment_confirmed','appointment_reminder','job_started','job_completed') NOT NULL,
  `channel` ENUM('sms','email') NOT NULL,
  `recipient` VARCHAR(150) NOT NULL,
  `provider` VARCHAR(40) NOT NULL DEFAULT 'system',
  `status` ENUM('sent','failed','skipped') NOT NULL,
  `provider_message_id` VARCHAR(80) NULL DEFAULT NULL,
  `error_message` VARCHAR(255) NULL DEFAULT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  -- One delivery per booking per event per channel: the idempotency guard.
  UNIQUE KEY `uniq_booking_event_channel` (`booking_id`, `notification_type`, `channel`),
  KEY `booking_id` (`booking_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_notification_log_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
