-- Mandatory reservation deposit for service bookings (PayMongo).
--
-- A customer may cancel or fail a payment and retry, so one booking can have
-- several payment attempts. That is one-to-many, which is why attempts live in
-- their own table instead of columns on `bookings`.
--
-- `bookings.status` already models the lifecycle (pending -> confirmed -> ...),
-- so no parallel status system is introduced: a booking simply cannot leave
-- 'pending' until a deposit row for it reaches 'paid'.

CREATE TABLE IF NOT EXISTS `booking_deposits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  -- The amount is captured when the attempt is created, so later changes to the
  -- configured deposit never rewrite a payment the customer already made.
  `amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pending','paid','failed','cancelled','expired') NOT NULL DEFAULT 'pending',
  `checkout_session_id` VARCHAR(120) DEFAULT NULL,
  `payment_id` VARCHAR(120) DEFAULT NULL,
  `payment_reference` VARCHAR(120) DEFAULT NULL,
  `checkout_url` TEXT DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  `updated_at` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  -- A PayMongo session belongs to exactly one attempt: this is what stops a
  -- successful payment for one booking being replayed to unlock another.
  UNIQUE KEY `uniq_checkout_session` (`checkout_session_id`),
  KEY `booking_id` (`booking_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_booking_deposits_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_deposits_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default deposit amount. Staff/admin edit this from Settings; it is read from
-- the database at runtime and never hard-coded in PHP or JavaScript.
INSERT INTO `site_settings` (`key`, `value`)
VALUES ('reservation_deposit_amount', '200.00')
ON DUPLICATE KEY UPDATE `key` = `key`;
