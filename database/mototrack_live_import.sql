-- MotoTrack — full database import for a fresh deployment.
--
-- Importing this file alone produces a complete, working database: schema plus
-- current catalogue data, including `product_codes` (barcode / QR identifiers)
-- with every product's code already assigned.
--
-- Because the schema here is already current, a fresh import does NOT need the
-- files in database/migrations/ — those exist to upgrade an EXISTING database
-- that predates a feature. Do not run both against the same database.
--
-- Regenerate with:
--   mysqldump -u root mototrack --add-drop-table --single-transaction \
--     --default-character-set=utf8mb4 --skip-dump-date > database/mototrack_live_import.sql
--
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: mototrack
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `blogs`
--

DROP TABLE IF EXISTS `blogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blogs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blogs`
--

LOCK TABLES `blogs` WRITE;
/*!40000 ALTER TABLE `blogs` DISABLE KEYS */;
INSERT INTO `blogs` VALUES (1,'How to Know When to Change Your Motorcycle Oil','how-to-know-change-oil','Engine oil is the lifeblood of your motorcycle. Learn the signs that tell you it is time for an oil change.',NULL,'2026-06-21 06:46:58'),(2,'CVT vs Chain Drive: What is the Difference?','cvt-vs-chain-drive','Understanding the difference between CVT scooters and chain-drive motorcycles can help you make a better buying decision.',NULL,'2026-06-21 06:46:58');
/*!40000 ALTER TABLE `blogs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_products`
--

DROP TABLE IF EXISTS `booking_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `product_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `product_name` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `service_id` (`service_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_products`
--

LOCK TABLES `booking_products` WRITE;
/*!40000 ALTER TABLE `booking_products` DISABLE KEYS */;
INSERT INTO `booking_products` VALUES (115,75,35,68,1400.00,'RK CHAIN - 428','2026-07-16 18:15:46'),(116,75,33,47,500.00,'SHELL - LONG RIDE','2026-07-16 18:15:46'),(117,75,36,53,150.00,'PRESONE - COOLANT','2026-07-16 18:15:46'),(118,75,37,70,170.00,'WYNN\'S - THROTTLE BODY CLEANER','2026-07-16 18:15:46'),(119,76,35,68,1400.00,'RK CHAIN - 428','2026-07-16 18:28:31'),(120,76,33,47,500.00,'SHELL - LONG RIDE','2026-07-16 18:28:31'),(121,76,36,55,160.00,'AMSOIL - COOLANT','2026-07-16 18:28:31'),(122,76,37,69,200.00,'CRC - THROTTLE BODY CLEANER','2026-07-16 18:28:31'),(123,77,35,68,1400.00,'RK CHAIN - 428','2026-07-16 18:30:13'),(124,77,33,52,600.00,'LIQUI MOLY - SCOOTER MB','2026-07-16 18:30:13'),(125,77,36,57,140.00,'SHELL - COOLANT','2026-07-16 18:30:13'),(126,77,37,69,200.00,'CRC - THROTTLE BODY CLEANER','2026-07-16 18:30:13'),(127,78,33,46,500.00,'SHELL - CITY SCOOTER OIL','2026-07-16 18:31:14'),(128,78,36,53,150.00,'PRESONE - COOLANT','2026-07-16 18:31:14'),(129,78,32,61,160.00,'KOBY - CVT CLEANER','2026-07-16 18:31:14'),(130,78,37,70,170.00,'WYNN\'S - THROTTLE BODY CLEANER','2026-07-16 18:31:14'),(131,79,33,47,500.00,'SHELL - LONG RIDE','2026-07-16 18:33:42'),(132,79,36,53,150.00,'PRESONE - COOLANT','2026-07-16 18:33:42'),(133,79,32,62,140.00,'AEROPAK - CVT CLEANER','2026-07-16 18:33:42'),(134,79,37,69,200.00,'CRC - THROTTLE BODY CLEANER','2026-07-16 18:33:42'),(135,80,35,68,1400.00,'RK CHAIN - 428','2026-07-16 20:55:38'),(136,81,35,68,1400.00,'RK CHAIN - 428','2026-07-16 20:56:32'),(137,82,32,61,160.00,'KOBY - CVT CLEANER','2026-07-17 07:18:38'),(138,83,32,61,160.00,'KOBY - CVT CLEANER','2026-07-17 07:18:56'),(139,84,35,68,1400.00,'RK CHAIN - 428','2026-07-17 14:55:46'),(141,85,32,61,160.00,'KOBY - CVT CLEANER','2026-07-17 14:56:58'),(142,86,35,68,1400.00,'RK CHAIN - 428','2026-07-17 22:10:44'),(143,86,33,47,500.00,'SHELL - LONG RIDE','2026-07-17 22:10:44'),(144,86,36,53,150.00,'PRESONE - COOLANT','2026-07-17 22:10:44'),(145,86,37,69,200.00,'CRC - THROTTLE BODY CLEANER','2026-07-17 22:10:44'),(146,87,37,69,200.00,'CRC - THROTTLE BODY CLEANER','2026-07-17 22:13:05'),(147,88,33,52,600.00,'LIQUI MOLY - SCOOTER MB','2026-07-17 22:16:57'),(148,89,33,48,500.00,'SHELL - ADVANCE ULTRA OIL','2026-07-18 09:38:35'),(149,89,36,54,160.00,'PETRON -  COOLANT','2026-07-18 09:38:35'),(150,89,32,61,160.00,'KOBY - CVT CLEANER','2026-07-18 09:38:35'),(151,89,37,70,170.00,'WYNN\'S - THROTTLE BODY CLEANER','2026-07-18 09:38:35'),(152,90,33,52,600.00,'LIQUI MOLY - SCOOTER MB','2026-08-12 10:23:54'),(153,90,32,61,160.00,'KOBY - CVT CLEANER','2026-08-12 10:23:54'),(154,91,33,52,600.00,'LIQUI MOLY - SCOOTER MB','2026-08-29 21:48:02');
/*!40000 ALTER TABLE `booking_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_services`
--

DROP TABLE IF EXISTS `booking_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `labor_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `service_name` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_services`
--

LOCK TABLES `booking_services` WRITE;
/*!40000 ALTER TABLE `booking_services` DISABLE KEYS */;
INSERT INTO `booking_services` VALUES (137,75,35,250.00,'CHANGE DRIVE TRAIN','2026-07-16 18:15:46'),(138,75,33,50.00,'CHANGE ENGINE OIL','2026-07-16 18:15:46'),(139,75,36,100.00,'COOLANT FLUSHING','2026-07-16 18:15:46'),(140,75,37,300.00,'THROTTLE BODY CLEANING','2026-07-16 18:15:46'),(141,76,35,250.00,'CHANGE DRIVE TRAIN','2026-07-16 18:28:31'),(142,76,33,50.00,'CHANGE ENGINE OIL','2026-07-16 18:28:31'),(143,76,36,100.00,'COOLANT FLUSHING','2026-07-16 18:28:31'),(144,76,37,300.00,'THROTTLE BODY CLEANING','2026-07-16 18:28:31'),(145,77,35,250.00,'CHANGE DRIVE TRAIN','2026-07-16 18:30:13'),(146,77,33,50.00,'CHANGE ENGINE OIL','2026-07-16 18:30:13'),(147,77,36,100.00,'COOLANT FLUSHING','2026-07-16 18:30:13'),(148,77,37,300.00,'THROTTLE BODY CLEANING','2026-07-16 18:30:13'),(149,78,33,50.00,'CHANGE ENGINE OIL','2026-07-16 18:31:14'),(150,78,36,100.00,'COOLANT FLUSHING','2026-07-16 18:31:14'),(151,78,32,300.00,'CVT CLEANING','2026-07-16 18:31:14'),(152,78,37,300.00,'THROTTLE BODY CLEANING','2026-07-16 18:31:14'),(153,79,33,50.00,'CHANGE ENGINE OIL','2026-07-16 18:33:42'),(154,79,36,100.00,'COOLANT FLUSHING','2026-07-16 18:33:42'),(155,79,32,300.00,'CVT CLEANING','2026-07-16 18:33:42'),(156,79,37,300.00,'THROTTLE BODY CLEANING','2026-07-16 18:33:42'),(157,80,35,250.00,'CHANGE DRIVE TRAIN','2026-07-16 20:55:38'),(158,81,35,250.00,'CHANGE DRIVE TRAIN','2026-07-16 20:56:32'),(159,82,32,300.00,'CVT CLEANING','2026-07-17 07:18:38'),(160,83,32,300.00,'CVT CLEANING','2026-07-17 07:18:56'),(161,84,35,250.00,'CHANGE DRIVE TRAIN','2026-07-17 14:55:46'),(163,85,32,300.00,'CVT CLEANING','2026-07-17 14:56:58'),(164,86,35,250.00,'CHANGE DRIVE TRAIN','2026-07-17 22:10:44'),(165,86,33,50.00,'CHANGE ENGINE OIL','2026-07-17 22:10:44'),(166,86,36,100.00,'COOLANT FLUSHING','2026-07-17 22:10:44'),(167,86,37,300.00,'THROTTLE BODY CLEANING','2026-07-17 22:10:44'),(168,87,37,300.00,'THROTTLE BODY CLEANING','2026-07-17 22:13:05'),(169,88,33,50.00,'CHANGE ENGINE OIL','2026-07-17 22:16:57'),(170,89,33,50.00,'CHANGE ENGINE OIL','2026-07-18 09:38:35'),(171,89,36,100.00,'COOLANT FLUSHING','2026-07-18 09:38:35'),(172,89,32,300.00,'CVT CLEANING','2026-07-18 09:38:35'),(173,89,37,300.00,'THROTTLE BODY CLEANING','2026-07-18 09:38:35'),(174,90,33,50.00,'CHANGE ENGINE OIL','2026-08-12 10:23:54'),(175,90,32,300.00,'CVT CLEANING','2026-08-12 10:23:54'),(176,91,33,50.00,'CHANGE ENGINE OIL','2026-08-29 21:48:02');
/*!40000 ALTER TABLE `booking_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time DEFAULT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `technician_id` int(10) unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `actual_start_time` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `tech_notes` text DEFAULT NULL,
  `estimated_duration_minutes` int(10) unsigned DEFAULT NULL,
  `labor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `products_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `status` (`status`),
  KEY `technician_id` (`technician_id`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (75,5,21,'2026-07-31','08:00:00','completed','',7,'2026-07-16 18:16:06',NULL,'2026-07-16 18:17:03',NULL,NULL,700.00,2220.00,2920.00,'2026-07-16 18:15:46'),(76,5,21,'2026-07-31','08:00:00','completed','',8,'2026-07-16 18:28:37',NULL,'2026-07-16 18:29:11',NULL,NULL,700.00,2260.00,2960.00,'2026-07-16 18:28:31'),(77,5,21,'2026-07-31','08:00:00','completed','',8,'2026-07-16 18:30:21',NULL,'2026-07-16 18:30:36',NULL,NULL,700.00,2340.00,3040.00,'2026-07-16 18:30:13'),(78,5,20,'2026-07-31','09:00:00','completed','',3,'2026-07-16 18:31:34',NULL,'2026-07-16 18:31:59',NULL,NULL,750.00,980.00,1730.00,'2026-07-16 18:31:14'),(79,5,20,'2026-07-31','09:00:00','completed','',17,'2026-07-16 18:33:46',NULL,'2026-07-16 18:33:54',NULL,NULL,750.00,990.00,1740.00,'2026-07-16 18:33:42'),(80,5,21,'2026-07-31','09:00:00','completed','',7,'2026-07-16 20:55:47',NULL,'2026-07-16 20:56:07',NULL,NULL,250.00,1400.00,1650.00,'2026-07-16 20:55:38'),(81,5,21,'2026-07-31','10:00:00','completed','',8,'2026-07-16 20:56:37',NULL,'2026-07-16 20:56:50',NULL,NULL,250.00,1400.00,1650.00,'2026-07-16 20:56:32'),(82,10,22,'2026-07-31','10:00:00','cancelled','',17,'2026-07-17 07:19:29',NULL,NULL,NULL,NULL,300.00,160.00,460.00,'2026-07-17 07:18:38'),(83,5,20,'2026-07-31','10:00:00','cancelled','',17,'2026-07-17 07:19:21',NULL,NULL,NULL,NULL,300.00,160.00,460.00,'2026-07-17 07:18:56'),(84,5,24,'2026-07-31','11:00:00','cancelled','',NULL,NULL,NULL,NULL,NULL,NULL,250.00,1400.00,1650.00,'2026-07-17 14:55:46'),(85,5,23,'2026-07-31','11:00:00','cancelled','',NULL,NULL,NULL,NULL,NULL,NULL,300.00,160.00,460.00,'2026-07-17 14:56:46'),(86,5,25,'2026-07-31','10:00:00','completed','',8,'2026-07-17 22:10:57',NULL,'2026-07-17 22:12:08','hi',NULL,700.00,2250.00,2950.00,'2026-07-17 22:10:44'),(87,5,25,'2026-07-31','10:00:00','completed','',8,'2026-07-17 22:13:12',NULL,'2026-07-17 22:13:32',NULL,NULL,300.00,200.00,500.00,'2026-07-17 22:13:05'),(88,5,26,'2026-07-31','11:00:00','completed','',3,NULL,NULL,'2026-07-17 22:17:18',NULL,NULL,50.00,600.00,650.00,'2026-07-17 22:16:57'),(89,5,27,'2026-07-19','08:00:00','completed','',3,'2026-07-18 09:53:27',NULL,'2026-07-18 09:54:57',NULL,NULL,750.00,990.00,1740.00,'2026-07-18 09:38:35'),(90,5,27,'2026-08-13','08:00:00','in_progress','',3,'2026-08-12 10:24:12',NULL,NULL,NULL,86,350.00,760.00,1110.00,'2026-08-12 10:23:54'),(91,5,27,'2026-08-31','08:00:00','in_progress','',7,'2026-08-29 21:49:19',NULL,NULL,NULL,240,50.00,600.00,650.00,'2026-08-29 21:48:02');
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`user_id`,`product_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (25,'ENGINE OIL','engine-oil',NULL),(26,'COOLANT','coolant',NULL),(27,'CVT CLEANER','cvt-cleaner',NULL),(28,'CLUTCH SYSTEM','clutch-system',NULL),(29,'DRIVE TRAIN','drive-train',NULL),(31,'THROTTLE BODY CLEANER','throttle-body-cleaner',NULL);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `subject` varchar(200) NOT NULL DEFAULT 'General Inquiry',
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_vehicles`
--

DROP TABLE IF EXISTS `customer_vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_vehicles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `brand_id` int(10) unsigned NOT NULL,
  `model_id` int(10) unsigned NOT NULL,
  `cc` smallint(5) unsigned NOT NULL,
  `year` year(4) DEFAULT NULL,
  `plate_number` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_vehicles`
--

LOCK TABLES `customer_vehicles` WRITE;
/*!40000 ALTER TABLE `customer_vehicles` DISABLE KEYS */;
INSERT INTO `customer_vehicles` VALUES (22,10,10,8,52,108,2021,'123ABC','2026-07-17 07:18:26'),(27,5,10,9,56,114,2024,'123ABC','2026-07-18 09:37:50');
/*!40000 ALTER TABLE `customer_vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `motorcycle_brands`
--

DROP TABLE IF EXISTS `motorcycle_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `motorcycle_brands` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_brands`
--

LOCK TABLES `motorcycle_brands` WRITE;
/*!40000 ALTER TABLE `motorcycle_brands` DISABLE KEYS */;
INSERT INTO `motorcycle_brands` VALUES (8,'Honda',1),(9,'Yamaha',1);
/*!40000 ALTER TABLE `motorcycle_brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `motorcycle_models`
--

DROP TABLE IF EXISTS `motorcycle_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `motorcycle_models` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `brand_id` int(10) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `cc` smallint(5) unsigned NOT NULL DEFAULT 125,
  `cc_source` varchar(255) DEFAULT NULL,
  `cc_confidence` decimal(4,2) DEFAULT NULL,
  `last_verified_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `brand_id` (`brand_id`),
  KEY `type_id` (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_models`
--

LOCK TABLES `motorcycle_models` WRITE;
/*!40000 ALTER TABLE `motorcycle_models` DISABLE KEYS */;
INSERT INTO `motorcycle_models` VALUES (51,8,10,'Click 125i',125,NULL,NULL,NULL,1),(52,8,10,'BeAT FI',108,NULL,NULL,NULL,1),(53,8,10,'PCX 160',160,NULL,NULL,NULL,1),(55,8,10,'Zoomer-X',108,NULL,NULL,NULL,1),(56,9,10,'Mio Sporty',114,NULL,NULL,NULL,1),(57,9,10,'Mio i125',125,NULL,NULL,NULL,1),(58,9,10,'Mio Gravis',125,NULL,NULL,NULL,1),(59,9,11,'Sniper 155',155,NULL,NULL,NULL,1),(60,8,11,'Supra GTR150',150,NULL,NULL,NULL,1),(61,9,12,'YZF R15',150,NULL,NULL,NULL,1),(62,8,12,'CBR 150R',150,NULL,NULL,NULL,1),(63,9,10,'NMAX',125,NULL,NULL,NULL,1),(64,9,10,'NMAX 125',125,NULL,NULL,NULL,1),(65,9,10,'NMAX 155',155,NULL,NULL,NULL,1),(66,9,10,'NMAX 160',155,NULL,NULL,NULL,1);
/*!40000 ALTER TABLE `motorcycle_models` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `motorcycle_types`
--

DROP TABLE IF EXISTS `motorcycle_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `motorcycle_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_types`
--

LOCK TABLES `motorcycle_types` WRITE;
/*!40000 ALTER TABLE `motorcycle_types` DISABLE KEYS */;
INSERT INTO `motorcycle_types` VALUES (10,'SCOOTER',NULL,1),(11,'UNDERBONE',NULL,1),(12,'BACKBONE',NULL,1);
/*!40000 ALTER TABLE `motorcycle_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_log`
--

DROP TABLE IF EXISTS `notification_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `notification_type` enum('appointment_confirmed','appointment_reminder','job_started','job_completed') NOT NULL,
  `channel` enum('sms','email') NOT NULL,
  `recipient` varchar(150) NOT NULL,
  `provider` varchar(40) NOT NULL DEFAULT 'system',
  `status` enum('sent','failed','skipped') NOT NULL,
  `provider_message_id` varchar(80) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_booking_event_channel` (`booking_id`,`notification_type`,`channel`),
  KEY `booking_id` (`booking_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_notification_log_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_log`
--

LOCK TABLES `notification_log` WRITE;
/*!40000 ALTER TABLE `notification_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'booking',
  `message` text NOT NULL,
  `booking_id` int(10) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=179 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (135,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #75).',75,1,'2026-07-16 18:15:46'),(136,7,'assignment','New job assigned to you: Booking #75 for FARES DAWOUD on Jul 31, 2026.',75,1,'2026-07-16 18:16:06'),(137,2,'completion','Job #75 has been marked as Completed by MECH MICO.',75,1,'2026-07-16 18:17:03'),(138,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #76).',76,1,'2026-07-16 18:28:31'),(139,8,'assignment','New job assigned to you: Booking #76 for FARES DAWOUD on Jul 31, 2026.',76,1,'2026-07-16 18:28:37'),(140,2,'completion','Job #76 has been marked as Completed by MECH JUN.',76,1,'2026-07-16 18:29:11'),(141,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #77).',77,1,'2026-07-16 18:30:13'),(142,8,'assignment','New job assigned to you: Booking #77 for FARES DAWOUD on Jul 31, 2026.',77,1,'2026-07-16 18:30:21'),(143,2,'completion','Job #77 has been marked as Completed by MECH JUN.',77,1,'2026-07-16 18:30:36'),(144,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #78).',78,1,'2026-07-16 18:31:14'),(145,3,'assignment','New job assigned to you: Booking #78 for FARES DAWOUD on Jul 31, 2026.',78,1,'2026-07-16 18:31:34'),(146,2,'completion','Job #78 has been marked as Completed by Tech User.',78,1,'2026-07-16 18:31:59'),(147,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #79).',79,1,'2026-07-16 18:33:42'),(148,17,'assignment','New job assigned to you: Booking #79 for FARES DAWOUD on Jul 31, 2026.',79,1,'2026-07-16 18:33:46'),(149,2,'completion','Job #79 has been marked as Completed by MECH NEYB.',79,1,'2026-07-16 18:33:54'),(150,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #80).',80,1,'2026-07-16 20:55:38'),(151,7,'assignment','New job assigned to you: Booking #80 for FARES DAWOUD on Jul 31, 2026.',80,1,'2026-07-16 20:55:47'),(152,2,'completion','Job #80 has been marked as Completed by MECH MICO.',80,1,'2026-07-16 20:56:07'),(153,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #81).',81,1,'2026-07-16 20:56:32'),(154,8,'assignment','New job assigned to you: Booking #81 for FARES DAWOUD on Jul 31, 2026.',81,0,'2026-07-16 20:56:37'),(155,2,'completion','Job #81 has been marked as Completed by MECH JUN.',81,1,'2026-07-16 20:56:50'),(156,2,'booking','New booking request from KATH scheduled for Jul 31, 2026 (Booking #82).',82,1,'2026-07-17 07:18:38'),(157,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #83).',83,1,'2026-07-17 07:18:56'),(158,17,'assignment','New job assigned to you: Booking #83 for FARES DAWOUD on Jul 31, 2026.',83,1,'2026-07-17 07:19:21'),(159,17,'assignment','New job assigned to you: Booking #82 for KATH on Jul 31, 2026.',82,1,'2026-07-17 07:19:29'),(160,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #84).',84,1,'2026-07-17 14:55:46'),(161,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #85).',85,1,'2026-07-17 14:56:48'),(162,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #86).',86,1,'2026-07-17 22:10:44'),(163,8,'assignment','New job assigned to you: Booking #86 for FARES DAWOUD on Jul 31, 2026.',86,0,'2026-07-17 22:10:57'),(164,2,'completion','Job #86 has been marked as Completed by MECH JUN.',86,1,'2026-07-17 22:12:08'),(165,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 31, 2026 (Booking #87).',87,1,'2026-07-17 22:13:05'),(166,8,'assignment','New job assigned to you: Booking #87 for FARES DAWOUD on Jul 31, 2026.',87,0,'2026-07-17 22:13:12'),(167,2,'completion','Job #87 has been marked as Completed by MECH JUN.',87,1,'2026-07-17 22:13:32'),(168,3,'booking','You have been assigned to Booking #88 by staff.',88,1,'2026-07-17 22:16:57'),(169,5,'booking','A service booking (#88) has been created for you by the shop.',88,0,'2026-07-17 22:16:57'),(170,2,'completion','Job #88 has been marked as Completed by Tech User.',88,1,'2026-07-17 22:17:18'),(171,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 19, 2026 (Booking #89).',89,1,'2026-07-18 09:38:35'),(172,3,'assignment','New job assigned to you: Booking #89 for FARES DAWOUD on Jul 19, 2026.',89,1,'2026-07-18 09:53:27'),(173,2,'completion','Job #89 has been marked as Completed by Tech User.',89,1,'2026-07-18 09:54:57'),(174,2,'booking','New booking request from FARES DAWOUD scheduled for Aug 13, 2026 (Booking #90).',90,1,'2026-08-12 10:23:54'),(175,3,'assignment','New job assigned to you: Booking #90 for FARES DAWOUD on Aug 13, 2026.',90,0,'2026-08-12 10:24:12'),(176,2,'booking','New booking request from FARES DAWOUD scheduled for Aug 31, 2026 (Booking #91).',91,0,'2026-08-29 21:48:02'),(177,7,'assignment','New job assigned to you: Booking #91 for FARES DAWOUD on Aug 31, 2026.',91,0,'2026-08-29 21:49:19');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `cart_item_id` int(10) unsigned DEFAULT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `cart_item_id` (`cart_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (60,52,25,59,6,350.00),(61,53,25,59,6,350.00),(62,54,25,59,6,350.00),(63,55,25,59,6,350.00),(64,56,25,59,1,350.00),(65,57,25,59,1,350.00),(66,58,25,59,1,350.00),(67,59,26,59,6,350.00),(69,61,26,59,6,350.00),(70,62,28,68,1,1400.00),(71,63,28,68,1,1400.00),(72,64,29,68,1,1400.00),(73,65,30,65,1,400.00),(74,66,31,63,1,330.00),(75,67,32,65,1,400.00),(76,68,NULL,65,1,400.00),(77,69,NULL,60,1,150.00),(78,70,33,59,1,350.00),(79,71,35,60,1,150.00),(80,72,36,59,5,350.00);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(120) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT NULL,
  `checkout_session_id` varchar(120) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `status` enum('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (52,5,2100.00,2100.00,'paymongo','MT-52','checkout_created','cs_7ypT5Ru9LvkHTVfiLfnvNPMh',NULL,'pending','2026-07-16 18:38:33'),(53,5,2100.00,2100.00,'paymongo','MT-53','checkout_created','cs_s67i1pYs6535F1v8Bqb44tYP',NULL,'pending','2026-07-16 18:38:46'),(54,5,2100.00,2100.00,'paymongo','MT-54','checkout_created','cs_F4akrGEVvSavDM1XGqptRfyc',NULL,'pending','2026-07-16 18:39:48'),(55,5,2100.00,2100.00,'paymongo','MT-55','checkout_created','cs_vn9k8Xi9UjNpoMmWpfWtx5Vd',NULL,'pending','2026-07-16 18:44:15'),(56,5,350.00,350.00,'paymongo','MT-56','checkout_created','cs_mr8Gey9JmzUCDuAgbZDZRPWg',NULL,'pending','2026-07-16 18:45:30'),(57,5,350.00,350.00,'paymongo','MT-57','checkout_created','cs_4de5n4WpCcZRsL8qchTPTxvS',NULL,'pending','2026-07-16 18:50:55'),(58,5,350.00,350.00,'paymongo','MT-58','checkout_created','cs_nfTbiYLWAJP3Pq9LABmYjZsi',NULL,'pending','2026-07-16 18:51:13'),(59,10,2100.00,2100.00,'paymongo','MT-59','checkout_created','cs_Yn8k9FNEccdYkgbV9gW6ErHx',NULL,'pending','2026-07-16 18:57:22'),(61,10,2100.00,2100.00,'paymongo','MT-61-TEST','paid','SANDBOX-TEST','2026-07-16 19:06:34','completed','2026-07-16 19:06:34'),(62,10,1400.00,1400.00,'paymongo','MT-62','checkout_created','cs_rXc6eBfB2BDEC38B8yGywFPg',NULL,'pending','2026-07-16 19:06:50'),(63,10,1400.00,1400.00,'paymongo','MT-63','checkout_created','cs_CaJhzpiLrGNPEireJK7coACe',NULL,'pending','2026-07-16 19:08:41'),(64,5,1400.00,1400.00,'paymongo','MT-64','checkout_created','cs_Qo89BYDpeJvjzGc3Hehbmpwq',NULL,'pending','2026-07-16 20:03:24'),(65,5,400.00,400.00,'paymongo','MT-65','checkout_created','cs_DS6WwzHMxwNzRcPowtunQiHL',NULL,'pending','2026-07-16 20:04:31'),(66,5,330.00,330.00,'paymongo','MT-66','checkout_created','cs_5a02cc71fbecc257e23463e2',NULL,'pending','2026-07-16 20:44:44'),(67,5,400.00,400.00,'paymongo','MT-67','paid','cs_a6860cf3aa7292f0e2e4ad14','2026-07-16 20:58:03','completed','2026-07-16 20:57:34'),(68,NULL,400.00,400.00,'cash','POS-000068','paid',NULL,'2026-07-16 15:25:13','completed','2026-07-16 21:25:13'),(69,NULL,150.00,150.00,'cash','POS-000069','paid',NULL,'2026-07-17 16:16:09','completed','2026-07-17 22:16:09'),(70,5,350.00,350.00,'paymongo','MT-70','paid','cs_b8aac714ac5bd3c019b8853f','2026-07-17 22:18:02','completed','2026-07-17 22:17:42'),(71,5,150.00,150.00,'paymongo','MT-71','paid','cs_8c2c7c7aa947771c0d1e7fb7','2026-07-18 09:40:28','completed','2026-07-18 09:40:06'),(72,5,1750.00,1750.00,'paymongo','MT-72','paid','cs_4c6c75f9839d38842190f490','2026-07-18 09:59:07','completed','2026-07-18 09:57:30');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `email` varchar(150) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,10,'castillokathlynanne@gmail.com','$2y$10$jFjBf0In0JK.cvDjX.Vc9OKrV5SfFer2r86LW1Itguc7ulz5wXWpG','2026-07-09 07:47:00',NULL,NULL,'2026-07-09 07:37:00');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_codes`
--

DROP TABLE IF EXISTS `product_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `code_type` enum('manufacturer','mototrack') NOT NULL DEFAULT 'manufacturer',
  `symbology` enum('qr','barcode','both') NOT NULL DEFAULT 'both',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_code` (`code`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_product_codes_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_codes`
--

LOCK TABLES `product_codes` WRITE;
/*!40000 ALTER TABLE `product_codes` DISABLE KEYS */;
INSERT INTO `product_codes` VALUES (11,77,'MT-P-000077','mototrack','qr','2026-08-29 20:52:38'),(12,46,'MT-P-000046','mototrack','both','2026-08-29 20:55:31'),(13,47,'MT-P-000047','mototrack','both','2026-08-29 20:55:31'),(14,48,'MT-P-000048','mototrack','both','2026-08-29 20:55:31'),(15,50,'MT-P-000050','mototrack','both','2026-08-29 20:55:31'),(16,51,'MT-P-000051','mototrack','both','2026-08-29 20:55:31'),(17,52,'MT-P-000052','mototrack','both','2026-08-29 20:55:31'),(18,53,'MT-P-000053','mototrack','both','2026-08-29 20:55:31'),(19,54,'MT-P-000054','mototrack','both','2026-08-29 20:55:31'),(20,55,'MT-P-000055','mototrack','both','2026-08-29 20:55:31'),(21,57,'MT-P-000057','mototrack','both','2026-08-29 20:55:31'),(22,58,'MT-P-000058','mototrack','both','2026-08-29 20:55:31'),(23,59,'MT-P-000059','mototrack','both','2026-08-29 20:55:31'),(24,60,'MT-P-000060','mototrack','both','2026-08-29 20:55:31'),(25,61,'MT-P-000061','mototrack','both','2026-08-29 20:55:31'),(26,62,'MT-P-000062','mototrack','both','2026-08-29 20:55:31'),(27,63,'MT-P-000063','mototrack','both','2026-08-29 20:55:31'),(28,65,'MT-P-000065','mototrack','both','2026-08-29 20:55:31'),(29,68,'MT-P-000068','mototrack','both','2026-08-29 20:55:31'),(30,69,'MT-P-000069','mototrack','both','2026-08-29 20:55:31'),(31,70,'MT-P-000070','mototrack','both','2026-08-29 20:55:31');
/*!40000 ALTER TABLE `product_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `brand` varchar(80) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `stock` int(10) unsigned NOT NULL DEFAULT 0,
  `min_stock` int(10) unsigned NOT NULL DEFAULT 10,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('available','low_stock','out_of_stock') NOT NULL DEFAULT 'available',
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (46,25,'SHELL - CITY SCOOTER OIL','SHELL','',500.00,NULL,99,10,'products/prod_6a58851762e85.jpg','available',0,'2026-07-16 15:15:35'),(47,25,'SHELL - LONG RIDE','SHELL','',500.00,NULL,96,10,'products/prod_6a588618e60d9.jpg','available',0,'2026-07-16 15:19:52'),(48,25,'SHELL - ADVANCE ULTRA OIL','SHELL','',500.00,NULL,99,10,'products/prod_6a58867c53c3a.jpg','available',0,'2026-07-16 15:21:32'),(50,25,'LIQUI MOLY - STREET','LIQUI MOLY','',600.00,NULL,100,10,'products/prod_6a5887277c4fc.jpg','available',0,'2026-07-16 15:24:23'),(51,25,'LIQUI MOLY - STREET RACE','LIQUI MOLY','',600.00,NULL,100,10,'products/prod_6a588772172cd.jpg','available',0,'2026-07-16 15:25:38'),(52,25,'LIQUI MOLY - SCOOTER MB','LIQUI MOLY','',600.00,NULL,98,10,'products/prod_6a5887cfa7939.jpg','available',0,'2026-07-16 15:27:11'),(53,26,'PRESONE - COOLANT','PRESTONE','',150.00,NULL,96,10,'products/prod_6a5888f19bdc7.jpg','available',0,'2026-07-16 15:32:01'),(54,26,'PETRON -  COOLANT','PETRON','',160.00,NULL,99,10,'products/prod_6a588a163a363.jpg','available',0,'2026-07-16 15:36:54'),(55,26,'AMSOIL - COOLANT','AMSOIL','',160.00,NULL,99,10,'products/prod_6a588a4911fcb.jpg','available',0,'2026-07-16 15:37:45'),(57,26,'SHELL - COOLANT','SHELL','',140.00,NULL,99,10,'products/prod_6a588b0f34e8f.jpg','available',0,'2026-07-16 15:41:03'),(58,25,'RS8 - SCOOTER OIL','RS8','',300.00,NULL,100,10,'products/prod_6a588b6db616d.webp','available',0,'2026-07-16 15:42:37'),(59,25,'RS8 - SCOOTER ULTRA OIL','RS8','',350.00,NULL,7,10,'products/prod_6a588bbd7ed10.webp','available',0,'2026-07-16 15:43:57'),(60,27,'RS8 - CVT CLEANER','RS8','',150.00,NULL,98,10,'products/prod_6a588c91f3dd3.jpg','available',0,'2026-07-16 15:47:30'),(61,27,'KOBY - CVT CLEANER','KOBY','',160.00,NULL,98,10,'products/prod_6a588f0ff2bdd.jpg','available',0,'2026-07-16 15:58:07'),(62,27,'AEROPAK - CVT CLEANER','AEROPAK','',140.00,NULL,99,10,'products/prod_6a588f74990d3.jpg','available',0,'2026-07-16 15:59:48'),(63,28,'YAMAHA GENUINE - CLUTCH DAMPER','GENUINE YAMAHA','',330.00,NULL,100,10,'products/prod_6a58903bf12e7.jpg','available',0,'2026-07-16 16:02:24'),(65,28,'YAMAHA GENUINE - CLUTCH LINING','YAMAHA GENUINE','',400.00,NULL,98,10,'products/prod_6a58a54eb9ff9.webp','available',0,'2026-07-16 17:33:02'),(68,29,'RK CHAIN - 428','RK','',1400.00,NULL,94,10,'products/prod_6a58a91a50f9c.jpg','available',0,'2026-07-16 17:49:14'),(69,31,'CRC - THROTTLE BODY CLEANER','CRC','',200.00,NULL,95,10,'products/prod_6a58a9e91234a.jpeg','available',0,'2026-07-16 17:52:41'),(70,31,'WYNN\'S - THROTTLE BODY CLEANER','WYNN\'S','',170.00,NULL,97,10,'products/prod_6a58aa307636e.jpg','available',0,'2026-07-16 17:53:52'),(77,29,'CHAIN GREASE','JIANLING','',100.00,NULL,30,10,'products/prod_6a92d6169a42c.jpg','available',0,'2026-08-29 20:52:38');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_booking_items`
--

DROP TABLE IF EXISTS `service_booking_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_booking_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `material_label` varchar(100) NOT NULL,
  `quantity` decimal(6,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_booking_items`
--

LOCK TABLES `service_booking_items` WRITE;
/*!40000 ALTER TABLE `service_booking_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_booking_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_bookings`
--

DROP TABLE IF EXISTS `service_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `service_type_id` int(10) unsigned NOT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time DEFAULT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `parts_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `labor_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `service_type_id` (`service_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_bookings`
--

LOCK TABLES `service_bookings` WRITE;
/*!40000 ALTER TABLE `service_bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_material_rules`
--

DROP TABLE IF EXISTS `service_material_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_material_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `material_label` varchar(100) NOT NULL,
  `cc_min` smallint(5) unsigned NOT NULL DEFAULT 0,
  `cc_max` smallint(5) unsigned NOT NULL DEFAULT 9999,
  `quantity` decimal(6,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_material_rules`
--

LOCK TABLES `service_material_rules` WRITE;
/*!40000 ALTER TABLE `service_material_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_material_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_products`
--

DROP TABLE IF EXISTS `service_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_products` (
  `service_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`service_id`,`product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_products`
--

LOCK TABLES `service_products` WRITE;
/*!40000 ALTER TABLE `service_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_types`
--

DROP TABLE IF EXISTS `service_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `labor_fee` decimal(8,2) NOT NULL DEFAULT 0.00,
  `applies_to` varchar(50) NOT NULL DEFAULT 'all',
  `required_category` varchar(120) DEFAULT NULL,
  `required_category_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_types`
--

LOCK TABLES `service_types` WRITE;
/*!40000 ALTER TABLE `service_types` DISABLE KEYS */;
INSERT INTO `service_types` VALUES (32,'CVT CLEANING','',300.00,'10','CVT CLEANER',27),(33,'CHANGE ENGINE OIL','',50.00,'all','ENGINE OIL',25),(34,'CHANGE CLUTCH SYSTEM','',200.00,'11,12','CLUTCH SYSTEM',28),(35,'CHANGE DRIVE TRAIN','',250.00,'11,12','DRIVE TRAIN',29),(36,'COOLANT FLUSHING','',100.00,'all','COOLANT',26),(37,'THROTTLE BODY CLEANING','',300.00,'all','THROTTLE BODY CLEANER',31);
/*!40000 ALTER TABLE `service_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL DEFAULT '',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES ('hero_background_image','hero_background_image.png'),('hero_eyebrow','Parts, accessories, and maintenance'),('hero_heading','Keep your motorcycle ready for every ride.'),('hero_image','hero_image_1782488480.jpg'),('hero_subtext','BUILT FOR THE RACE');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `technician_services`
--

DROP TABLE IF EXISTS `technician_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `technician_services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `technician_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tech_service` (`technician_id`,`service_id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `technician_services`
--

LOCK TABLES `technician_services` WRITE;
/*!40000 ALTER TABLE `technician_services` DISABLE KEYS */;
INSERT INTO `technician_services` VALUES (67,8,34,'2026-07-16 18:09:41'),(68,8,35,'2026-07-16 18:09:41'),(69,8,33,'2026-07-16 18:09:41'),(70,8,36,'2026-07-16 18:09:41'),(71,8,37,'2026-07-16 18:09:41'),(72,7,34,'2026-07-16 18:09:51'),(73,7,35,'2026-07-16 18:09:51'),(74,7,33,'2026-07-16 18:09:51'),(75,7,36,'2026-07-16 18:09:51'),(76,7,37,'2026-07-16 18:09:51'),(77,3,33,'2026-07-16 18:10:03'),(78,3,36,'2026-07-16 18:10:03'),(79,3,32,'2026-07-16 18:10:03'),(80,3,37,'2026-07-16 18:10:03'),(81,17,33,'2026-07-18 09:51:20'),(82,17,36,'2026-07-18 09:51:20'),(83,17,32,'2026-07-18 09:51:20'),(84,17,37,'2026-07-18 09:51:20');
/*!40000 ALTER TABLE `technician_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testimonials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author_name` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `rating` tinyint(3) unsigned DEFAULT 5,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testimonials`
--

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `auth_provider` enum('local','google') NOT NULL DEFAULT 'local',
  `role` enum('admin','staff','technician','customer') NOT NULL DEFAULT 'customer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `availability_status` enum('ready','off_duty') NOT NULL DEFAULT 'off_duty',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','admin@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234567',NULL,'local','admin',1,'off_duty','2026-06-21 06:46:57'),(2,'Staff User','staff@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234568',NULL,'local','staff',1,'off_duty','2026-06-21 06:46:57'),(3,'Tech User','tech@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234569',NULL,'local','technician',1,'ready','2026-06-21 06:46:57'),(4,'Juan dela Cruz','juan@gmail.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09171234567',NULL,'local','customer',1,'off_duty','2026-06-21 06:46:57'),(5,'FARES DAWOUD','pippo.fares@gmail.com','$2y$10$7xHDTqtx64/G15zpgT64NuG01AMwEXTW0EHGIa6jieZZMScPiTNLm','09922550043','110525539002572377824','google','customer',1,'off_duty','2026-06-21 14:50:15'),(6,'FARES WAEL IBRAHIM DAWOUD','dawoud_fareswaelibrahim@plpasig.edu.ph','$2y$10$hrp0QfvQv3.rpehCM83dRuwNeOTpw/44Sk/JvoQq78LR6rWV1Ls1a',NULL,'111815386491218525648','google','customer',1,'off_duty','2026-06-24 14:54:35'),(7,'MECH MICO','mech@mototrack.com','$2y$10$yFSQ4EHnOQCvxPjzujX5m.TMCtvdXPk9wy25768kdeWGAzhfULC86',NULL,NULL,'local','technician',1,'ready','2026-06-29 00:05:19'),(8,'MECH JUN','mech2@mototrack.com','$2y$10$lrmHzHMnWtG7kWJP5neZ7O1J7442Uj4zMC78scMBj5y0oL.Wh3Itm',NULL,NULL,'local','technician',1,'off_duty','2026-07-07 12:02:57'),(10,'KATH','castillokathlynanne@gmail.com','$2y$10$HV9geRxA011MyAh3jhk4Cud7TTp/X/DQPj6KdQAclGeBAeKSprijm','09922550043',NULL,'local','customer',1,'off_duty','2026-07-09 07:36:36'),(17,'MECH NEYB','mechneyb@mototrack.com','$2y$10$v8AbPv34AvXA7ylQJXuL7uxGu7gqeEEXS24tpCo/39axaKc3j/Bgi',NULL,NULL,'local','technician',1,'off_duty','2026-07-09 13:22:06');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
