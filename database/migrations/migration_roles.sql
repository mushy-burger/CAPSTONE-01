-- MotoTrack Role Separation Migration
-- Safe to run on an existing database — no data is dropped.
-- Run this once in phpMyAdmin or via MySQL CLI.

USE `mototrack`;

-- --------------------------------------------------------
-- Add technician assignment + tech notes to bookings
-- --------------------------------------------------------
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `technician_id` INT UNSIGNED DEFAULT NULL AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `tech_notes` TEXT DEFAULT NULL AFTER `technician_id`,
  ADD KEY IF NOT EXISTS `technician_id` (`technician_id`);

-- --------------------------------------------------------
-- Notifications table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'booking',
  `message` TEXT NOT NULL,
  `booking_id` INT UNSIGNED DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
