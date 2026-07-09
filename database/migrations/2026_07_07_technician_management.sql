-- Technician Management + Auto-Assignment
-- 1) Availability toggle for technicians (only 'ready' techs are auto-assignable)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `availability_status` ENUM('ready','off_duty') NOT NULL DEFAULT 'off_duty' AFTER `is_active`;

-- 2) Which services each technician is qualified to perform
CREATE TABLE IF NOT EXISTS `technician_services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `technician_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tech_service` (`technician_id`, `service_id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Exact assignment/completion timestamps (feed the fairness tie-breakers)
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `assigned_at` DATETIME DEFAULT NULL AFTER `technician_id`,
  ADD COLUMN IF NOT EXISTS `completed_at` DATETIME DEFAULT NULL AFTER `assigned_at`;
