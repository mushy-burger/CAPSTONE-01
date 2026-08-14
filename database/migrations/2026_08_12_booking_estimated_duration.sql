ALTER TABLE `bookings`
  ADD COLUMN `estimated_duration_minutes` INT UNSIGNED DEFAULT NULL AFTER `tech_notes`;
