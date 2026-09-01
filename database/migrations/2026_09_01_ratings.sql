-- Booking ratings: customers rate their service + mechanic after job completion.
--
-- One rating per booking (UNIQUE KEY). The rating token is a one-time-use
-- SHA-256 hash stored on the booking so the URL cannot be guessed and cannot
-- be replayed after submission.

-- 1) Rating token column on bookings
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `rating_token` VARCHAR(64) DEFAULT NULL AFTER `tech_notes`,
  ADD COLUMN IF NOT EXISTS `rating_token_used` TINYINT(1) NOT NULL DEFAULT 0 AFTER `rating_token`;

-- 2) Ratings table
CREATE TABLE IF NOT EXISTS `booking_ratings` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`       INT UNSIGNED NOT NULL,
  `user_id`          INT UNSIGNED NOT NULL,
  `technician_id`    INT UNSIGNED DEFAULT NULL,
  `service_rating`   TINYINT UNSIGNED NOT NULL COMMENT '1-5 overall service',
  `mechanic_rating`  TINYINT UNSIGNED DEFAULT NULL COMMENT '1-5 mechanic specifically',
  `comment`          TEXT DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_booking` (`booking_id`),
  KEY `user_id` (`user_id`),
  KEY `technician_id` (`technician_id`),
  CONSTRAINT `fk_rating_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rating_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) Extend notification_log ENUM to include rating_request
ALTER TABLE `notification_log`
  MODIFY COLUMN `notification_type`
    ENUM('appointment_confirmed','appointment_reminder','job_started',
         'job_completed','rating_request') NOT NULL;
