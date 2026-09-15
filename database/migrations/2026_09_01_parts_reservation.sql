-- Parts reservation: hold product stock when a booking is confirmed,
-- release on cancellation, deduct actual stock on job completion.

CREATE TABLE IF NOT EXISTS `parts_reservations` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity`   DECIMAL(6,2) NOT NULL DEFAULT 1,
  `status`     ENUM('held','consumed','released') NOT NULL DEFAULT 'held',
  `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_booking_product` (`booking_id`,`product_id`),
  KEY `booking_id` (`booking_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_parts_res_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_parts_res_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
