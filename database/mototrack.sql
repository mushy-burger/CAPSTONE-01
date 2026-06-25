-- MotoTrack Database Schema
-- MariaDB / MySQL

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `mototrack` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mototrack`;

-- --------------------------------------------------------
-- USERS
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `google_id` VARCHAR(100) DEFAULT NULL,
  `auth_provider` ENUM('local','google') NOT NULL DEFAULT 'local',
  `role` ENUM('admin','staff','technician','customer') NOT NULL DEFAULT 'customer',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `otp_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- CATEGORIES
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- PRODUCTS
-- --------------------------------------------------------
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `brand` VARCHAR(80) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `original_price` DECIMAL(10,2) DEFAULT NULL,
  `stock` INT UNSIGNED NOT NULL DEFAULT 0,
  `image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('available','low_stock','out_of_stock') NOT NULL DEFAULT 'available',
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- ORDERS
-- --------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- CART
-- --------------------------------------------------------
CREATE TABLE `cart_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`user_id`,`product_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- SITE SETTINGS
-- --------------------------------------------------------
CREATE TABLE `site_settings` (
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- MOTORCYCLE TYPES / BRANDS / MODELS
-- --------------------------------------------------------
CREATE TABLE `motorcycle_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `motorcycle_brands` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `motorcycle_models` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `brand_id` INT UNSIGNED NOT NULL,
  `type_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `cc` SMALLINT UNSIGNED NOT NULL DEFAULT 125,
  `cc_source` VARCHAR(255) DEFAULT NULL,
  `cc_confidence` DECIMAL(4,2) DEFAULT NULL,
  `last_verified_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `brand_id` (`brand_id`),
  KEY `type_id` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `customer_vehicles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type_id` INT UNSIGNED NOT NULL,
  `brand_id` INT UNSIGNED NOT NULL,
  `model_id` INT UNSIGNED NOT NULL,
  `cc` SMALLINT UNSIGNED NOT NULL,
  `year` YEAR DEFAULT NULL,
  `plate_number` VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- SERVICES
-- --------------------------------------------------------
CREATE TABLE `service_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `labor_fee` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `applies_to` VARCHAR(50) NOT NULL DEFAULT 'all',
  `required_category` VARCHAR(120) DEFAULT NULL,
  `required_category_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `service_products` (
  `service_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`service_id`,`product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rules: which products/materials are needed per service + CC range
CREATE TABLE `service_material_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `material_label` VARCHAR(100) NOT NULL,
  `cc_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `cc_max` SMALLINT UNSIGNED NOT NULL DEFAULT 9999,
  `quantity` DECIMAL(6,2) NOT NULL DEFAULT 1,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `vehicle_id` INT UNSIGNED DEFAULT NULL,
  `scheduled_date` DATE NOT NULL,
  `scheduled_time` TIME DEFAULT NULL,
  `status` ENUM('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `labor_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `products_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `booking_services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `labor_fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `service_name` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `booking_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED DEFAULT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `product_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `product_name` VARCHAR(150) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `service_id` (`service_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TESTIMONIALS & BLOGS
-- --------------------------------------------------------
CREATE TABLE `testimonials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `author_name` VARCHAR(100) NOT NULL,
  `content` TEXT NOT NULL,
  `rating` TINYINT UNSIGNED DEFAULT 5,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `blogs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `excerpt` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `published_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- SEED DATA
-- --------------------------------------------------------

-- Admin user (password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`) VALUES
('Admin', 'admin@mototrack.com', '$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2', '09001234567', 'admin'),
('Staff User', 'staff@mototrack.com', '$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2', '09001234568', 'staff'),
('Tech User', 'tech@mototrack.com', '$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2', '09001234569', 'technician'),
('Juan dela Cruz', 'juan@gmail.com', '$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2', '09171234567', 'customer');

-- Categories
INSERT INTO `categories` (`name`, `slug`) VALUES
('Body Parts', 'body-parts'),
('Electronics', 'electronics'),
('Spare Parts', 'spare-parts'),
('Cleaners', 'cleaners'),
('Helmets', 'helmets'),
('Accessories', 'accessories');

-- Products
INSERT INTO `products` (`category_id`, `name`, `brand`, `description`, `price`, `original_price`, `stock`, `featured`) VALUES
(3, 'Motul 3000 Engine Oil 4T', 'Motul', 'High performance 4-stroke engine oil. Ideal for 100cc-125cc motorcycles. 0.8L bottle.', 280.00, NULL, 50, 1),
(3, 'Motul 3000 Engine Oil 4T 1L', 'Motul', 'High performance 4-stroke engine oil. 1L bottle for 150cc and above motorcycles.', 320.00, NULL, 40, 1),
(4, 'CVT Cleaner Spray', 'APC', 'Professional CVT belt and pulley cleaner spray. 500ml aerosol can.', 120.00, 150.00, 80, 1),
(3, 'Chain Lubricant Spray', 'Liqui-Moly', 'Premium chain lubricant for underbone and backbone motorcycles.', 180.00, 220.00, 60, 1),
(3, 'Brake Pads Set - Front', 'EBC', 'Heavy-duty front brake pads compatible with most Honda/Yamaha models.', 450.00, 550.00, 30, 1),
(2, 'LED Headlight Bulb H4', 'Philips', 'Ultra-bright LED replacement headlight. 6000K white light.', 850.00, 1000.00, 25, 1),
(1, 'Side Mirror Universal', 'Generic', 'Universal fit chrome side mirrors. Pair.', 350.00, NULL, 45, 0),
(5, 'Full-Face Helmet', 'MT Helmets', 'ECE-certified full-face helmet. Available in black and white.', 2500.00, 3000.00, 15, 1),
(6, 'Tank Bag Waterproof', 'SW-Motech', 'Magnetic tank bag, 16L capacity, waterproof.', 1200.00, NULL, 20, 0),
(3, 'Spark Plug NGK CR7HSA', 'NGK', 'Standard spark plug for 100cc-150cc 4-stroke engines.', 95.00, NULL, 100, 0),
(3, 'Air Filter Element', 'K&N', 'High-flow washable air filter. Universal fit for 125cc engines.', 650.00, 800.00, 22, 0),
(4, 'Carb Cleaner Spray', 'CRC', 'Carburetor and choke cleaner. Fast-acting formula.', 150.00, NULL, 55, 0);

-- Motorcycle Types
INSERT INTO `motorcycle_types` (`name`, `description`) VALUES
('Underbone', 'Step-through frame motorcycles (e.g., Honda XRM, Yamaha Mio series underbone)'),
('Backbone', 'Traditional backbone frame motorcycles (e.g., Honda TMX, Yamaha Sniper)'),
('Scooter', 'Automatic transmission scooters with CVT (e.g., Honda Click, Yamaha NMAX)');

-- Motorcycle Brands
INSERT INTO `motorcycle_brands` (`name`) VALUES
('Honda'),
('Yamaha'),
('Suzuki'),
('Kawasaki'),
('Rusi'),
('Kymco');

-- Motorcycle Models (brand_id, type_id, name, cc)
INSERT INTO `motorcycle_models` (`brand_id`, `type_id`, `name`, `cc`) VALUES
-- Honda Underbone
(1, 1, 'XRM 125', 125),
(1, 1, 'XRM RS 125', 125),
(1, 1, 'Wave 100', 100),
(1, 1, 'Wave 125', 125),
-- Honda Backbone
(1, 2, 'TMX 125 Alpha', 125),
(1, 2, 'TMX Supremo 150', 150),
(1, 2, 'CB150R', 150),
(1, 2, 'Rebel 500', 500),
-- Honda Scooter
(1, 3, 'Click 125i', 125),
(1, 3, 'Click 150i', 150),
(1, 3, 'ADV 150', 150),
(1, 3, 'PCX 160', 160),
(1, 3, 'Vario 125', 125),
-- Yamaha Underbone
(2, 1, 'Jupiter MX', 115),
(2, 1, 'Jupiter Z1', 115),
-- Yamaha Backbone
(2, 2, 'Sniper 150', 150),
(2, 2, 'YZF R15', 155),
(2, 2, 'FZ150i', 150),
-- Yamaha Scooter
(2, 3, 'Mio i 125', 125),
(2, 3, 'Mio Aerox 155', 155),
(2, 3, 'NMAX 155', 155),
(2, 3, 'XMAX 300', 300),
-- Suzuki Underbone
(3, 1, 'Smash 115', 115),
-- Suzuki Backbone
(3, 2, 'Raider R150', 150),
(3, 2, 'GSX-R150', 150),
-- Suzuki Scooter
(3, 3, 'Skydrive 125', 125),
(3, 3, 'Burgman Street 125', 125),
-- Kawasaki Backbone
(4, 2, 'Barako II 175', 175),
(4, 2, 'Rouser NS160', 160),
(4, 2, 'KLX 150', 150);

-- Service Types
-- applies_to: 'all' means all motorcycle types, or comma-separated type IDs
INSERT INTO `service_types` (`name`, `description`, `labor_fee`, `applies_to`) VALUES
('Change Oil', 'Engine oil replacement service. Includes draining old oil, replacing oil filter if needed, and refilling with fresh engine oil.', 50.00, 'all'),
('CVT Cleaning', 'Cleaning of Continuously Variable Transmission belt, rollers, and housing. For scooters only.', 300.00, '3'),
('Chain & Sprocket Cleaning', 'Cleaning and lubrication of drive chain and sprocket set. For underbone and backbone motorcycles.', 150.00, '1,2'),
('General Check-up', 'Full motorcycle inspection including brakes, tires, lights, and engine.', 200.00, 'all'),
('Brake Service', 'Brake pad inspection and replacement, brake fluid check and flush.', 250.00, 'all');

-- Service Material Rules
-- Change Oil (service_id=1)
-- 110cc–125cc → 0.8L oil (product_id=1, qty=1 bottle of 0.8L)
-- 126cc+ → 1L oil (product_id=2, qty=1 bottle of 1L)
INSERT INTO `service_material_rules` (`service_id`, `product_id`, `material_label`, `cc_min`, `cc_max`, `quantity`, `unit`) VALUES
(1, 1, 'Engine Oil 0.8L (110cc–125cc)', 100, 125, 1, 'bottle'),
(1, 2, 'Engine Oil 1L (126cc and above)', 126, 9999, 1, 'bottle'),
-- CVT Cleaning (service_id=2)
-- ≤125cc → 2 cans
-- ≥126cc → 3 cans
(2, 3, 'CVT Cleaner Spray (≤125cc)', 0, 125, 2, 'can'),
(2, 3, 'CVT Cleaner Spray (≥126cc)', 126, 9999, 3, 'can'),
-- Chain & Sprocket Cleaning (service_id=3)
(3, 4, 'Chain Lubricant Spray', 0, 9999, 1, 'can');

INSERT INTO `service_products` (`service_id`, `product_id`) VALUES
(1, 1),
(1, 2),
(2, 3),
(3, 4);

-- Testimonials
INSERT INTO `testimonials` (`author_name`, `content`, `rating`) VALUES
('Maria Santos', 'MotoTrack has been my go-to shop for all my motorcycle needs. Fast, reliable, and affordable!', 5),
('Carlos Reyes', 'Great service! The staff really knows their stuff. My Honda Click runs perfectly now.', 5),
('Ana Gonzales', 'I love how easy it is to book a service appointment online. No more waiting in long queues!', 5);

-- Blogs
INSERT INTO `blogs` (`title`, `slug`, `excerpt`, `published_at`) VALUES
('How to Know When to Change Your Motorcycle Oil', 'how-to-know-change-oil', 'Engine oil is the lifeblood of your motorcycle. Learn the signs that tell you it is time for an oil change.', NOW()),
('CVT vs Chain Drive: What is the Difference?', 'cvt-vs-chain-drive', 'Understanding the difference between CVT scooters and chain-drive motorcycles can help you make a better buying decision.', NOW());
