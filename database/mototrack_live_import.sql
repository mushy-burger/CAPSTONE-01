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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_products`
--

LOCK TABLES `booking_products` WRITE;
/*!40000 ALTER TABLE `booking_products` DISABLE KEYS */;
INSERT INTO `booking_products` VALUES (1,1,19,14,585.00,'SHELL - CITY SCOOTER OIL','2026-06-27 22:14:56'),(2,1,18,18,149.99,'KOBY CVT CLEANER','2026-06-27 22:14:56'),(4,3,19,16,800.00,'LIQUI MOLY ENGINE OIL','2026-06-29 00:05:48'),(5,4,19,16,800.00,'LIQUI MOLY ENGINE OIL','2026-06-29 08:39:41'),(6,4,18,18,149.99,'KOBY CVT CLEANER','2026-06-29 08:39:41'),(7,5,19,16,800.00,'LIQUI MOLY ENGINE OIL','2026-06-29 08:40:30'),(8,5,18,18,149.99,'KOBY CVT CLEANER','2026-06-29 08:40:30'),(9,6,19,16,800.00,'LIQUI MOLY ENGINE OIL','2026-06-29 08:46:05'),(10,6,18,18,149.99,'KOBY CVT CLEANER','2026-06-29 08:46:05');
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_services`
--

LOCK TABLES `booking_services` WRITE;
/*!40000 ALTER TABLE `booking_services` DISABLE KEYS */;
INSERT INTO `booking_services` VALUES (1,1,19,70.00,'CHANGE ENGINE OIL','2026-06-27 22:14:56'),(2,1,18,350.00,'CVT CLEANING','2026-06-27 22:14:56'),(4,3,19,70.00,'CHANGE ENGINE OIL','2026-06-29 00:05:48'),(5,4,19,70.00,'CHANGE ENGINE OIL','2026-06-29 08:39:41'),(6,4,18,350.00,'CVT CLEANING','2026-06-29 08:39:41'),(7,5,19,70.00,'CHANGE ENGINE OIL','2026-06-29 08:40:30'),(8,5,18,350.00,'CVT CLEANING','2026-06-29 08:40:30'),(9,6,19,70.00,'CHANGE ENGINE OIL','2026-06-29 08:46:05'),(10,6,18,350.00,'CVT CLEANING','2026-06-29 08:46:05'),(11,7,19,70.00,'CHANGE ENGINE OIL','2026-06-29 09:07:04'),(12,8,19,70.00,'CHANGE ENGINE OIL','2026-06-29 09:07:35'),(13,9,19,70.00,'CHANGE ENGINE OIL','2026-06-29 09:11:00'),(14,10,18,350.00,'CVT CLEANING','2026-06-29 09:11:26'),(15,10,19,70.00,'CHANGE ENGINE OIL','2026-06-29 09:11:26'),(16,10,23,50.00,'COOLANT FLUSHING','2026-06-29 09:11:26'),(17,11,18,350.00,'CVT CLEANING','2026-06-29 09:12:04'),(18,11,19,70.00,'CHANGE ENGINE OIL','2026-06-29 09:12:04'),(19,11,23,50.00,'COOLANT FLUSHING','2026-06-29 09:12:04');
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
  `tech_notes` text DEFAULT NULL,
  `labor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `products_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `status` (`status`),
  KEY `technician_id` (`technician_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,5,NULL,'2026-07-05','23:15:00','completed','',3,NULL,420.00,734.99,1154.99,'2026-06-27 22:14:56'),(3,5,2,'2026-06-29','03:05:00','cancelled','',7,NULL,70.00,800.00,870.00,'2026-06-29 00:05:48'),(4,5,4,'2026-06-30','17:45:00','cancelled','',7,NULL,420.00,949.99,1369.99,'2026-06-29 08:39:41'),(5,5,4,'2026-06-30','22:42:00','cancelled','',3,NULL,420.00,949.99,1369.99,'2026-06-29 08:40:30'),(6,5,4,'2026-06-30','11:50:00','completed','',7,NULL,420.00,949.99,1369.99,'2026-06-29 08:46:05'),(7,6,4,'2026-06-30','10:00:00','confirmed','',7,NULL,0.00,0.00,70.00,'2026-06-29 09:07:04'),(8,6,4,'2026-06-30','10:00:00','confirmed','',7,NULL,0.00,0.00,70.00,'2026-06-29 09:07:35'),(9,6,4,'2026-06-30','10:00:00','confirmed','',7,NULL,0.00,0.00,70.00,'2026-06-29 09:11:00'),(10,5,4,'2026-06-30','17:00:00','confirmed','',7,NULL,0.00,0.00,470.00,'2026-06-29 09:11:26'),(11,5,4,'2026-06-30','17:00:00','confirmed','',3,NULL,0.00,0.00,470.00,'2026-06-29 09:12:04');
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (7,'ENGINE OIL','engine-oil',NULL),(8,'CVT CLEANERS','cvt-cleaners',NULL),(15,'COOLANT','coolant',NULL);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_vehicles`
--

LOCK TABLES `customer_vehicles` WRITE;
/*!40000 ALTER TABLE `customer_vehicles` DISABLE KEYS */;
INSERT INTO `customer_vehicles` VALUES (4,5,1,1,24,125,2022,'578QIM','2026-06-29 08:39:21');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_brands`
--

LOCK TABLES `motorcycle_brands` WRITE;
/*!40000 ALTER TABLE `motorcycle_brands` DISABLE KEYS */;
INSERT INTO `motorcycle_brands` VALUES (1,'HONDA',1),(2,'Yamaha',1),(3,'Suzuki',1);
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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_models`
--

LOCK TABLES `motorcycle_models` WRITE;
/*!40000 ALTER TABLE `motorcycle_models` DISABLE KEYS */;
INSERT INTO `motorcycle_models` VALUES (22,2,4,'Sniper 155',155,NULL,NULL,NULL,1),(24,1,1,'Click 125i',125,NULL,NULL,NULL,1),(25,2,6,'YZF R15',150,NULL,NULL,NULL,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_types`
--

LOCK TABLES `motorcycle_types` WRITE;
/*!40000 ALTER TABLE `motorcycle_types` DISABLE KEYS */;
INSERT INTO `motorcycle_types` VALUES (1,'SCOOTER',NULL,1),(4,'UNDERBONE',NULL,1),(6,'BACKBONE',NULL,1);
/*!40000 ALTER TABLE `motorcycle_types` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 5, 2026 (Booking #1).',1,1,'2026-06-27 22:14:56'),(2,3,'assignment','New job assigned: Booking #1 for FARES DAWOUD on Jul 5, 2026.',1,1,'2026-06-27 22:15:22'),(3,2,'completion','Job #1 has been marked as Completed by Tech User.',1,1,'2026-06-27 22:16:11'),(4,2,'booking','New booking request from FARES DAWOUD scheduled for Jun 30, 2026 (Booking #2).',2,1,'2026-06-29 00:02:07'),(5,3,'assignment','New job assigned to you: Booking #2 for FARES DAWOUD on Jun 30, 2026.',2,1,'2026-06-29 00:02:37'),(6,2,'booking','New booking request from FARES DAWOUD scheduled for Jun 29, 2026 (Booking #3).',3,1,'2026-06-29 00:05:48'),(7,7,'assignment','New job assigned to you: Booking #3 for FARES DAWOUD on Jun 29, 2026.',3,1,'2026-06-29 00:05:53'),(8,2,'booking','New booking request from FARES DAWOUD scheduled for Jun 30, 2026 (Booking #4).',4,1,'2026-06-29 08:39:41'),(9,7,'assignment','New job assigned to you: Booking #4 for FARES DAWOUD on Jun 30, 2026.',4,1,'2026-06-29 08:40:01'),(10,2,'booking','New booking request from FARES DAWOUD scheduled for Jun 30, 2026 (Booking #5).',5,1,'2026-06-29 08:40:30'),(11,3,'assignment','New job assigned to you: Booking #5 for FARES DAWOUD on Jun 30, 2026.',5,1,'2026-06-29 08:40:42'),(12,2,'booking','New booking request from FARES DAWOUD scheduled for Jun 30, 2026 (Booking #6).',6,1,'2026-06-29 08:46:05'),(13,7,'assignment','New job assigned to you: Booking #6 for FARES DAWOUD on Jun 30, 2026.',6,1,'2026-06-29 08:46:10'),(14,2,'completion','Job #6 has been marked as Completed by MECH MICO.',6,1,'2026-06-29 08:47:42'),(15,7,'booking','You have been assigned to Booking #7 by staff.',7,0,'2026-06-29 09:07:04'),(16,6,'booking','A service booking (#7) has been created for you by the shop.',7,0,'2026-06-29 09:07:04'),(17,7,'booking','You have been assigned to Booking #8 by staff.',8,0,'2026-06-29 09:07:35'),(18,6,'booking','A service booking (#8) has been created for you by the shop.',8,0,'2026-06-29 09:07:35'),(19,7,'booking','You have been assigned to Booking #9 by staff.',9,0,'2026-06-29 09:11:00'),(20,6,'booking','A service booking (#9) has been created for you by the shop.',9,0,'2026-06-29 09:11:00'),(21,7,'booking','You have been assigned to Booking #10 by staff.',10,0,'2026-06-29 09:11:26'),(22,5,'booking','A service booking (#10) has been created for you by the shop.',10,0,'2026-06-29 09:11:26'),(23,3,'booking','You have been assigned to Booking #11 by staff.',11,0,'2026-06-29 09:12:04'),(24,5,'booking','A service booking (#11) has been created for you by the shop.',11,0,'2026-06-29 09:12:04');
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,NULL,17,1,500.00),(2,2,NULL,17,1,500.00),(3,3,NULL,17,1,500.00),(4,4,NULL,17,1,500.00),(5,5,NULL,18,1,149.99),(6,6,NULL,17,1,500.00),(7,7,NULL,17,1,500.00),(8,8,NULL,17,1,500.00),(10,10,5,16,1,800.00),(11,11,5,16,1,800.00),(12,12,6,18,1,149.99);
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,5,500.00,500.00,'paymongo','MT-1','checkout_created','cs_5dfc6f73d4e52ed94a6fb1a0',NULL,'pending','2026-06-27 22:21:55'),(2,5,500.00,500.00,'paymongo','MT-2','checkout_created','cs_5307d1f9725984636dfd3286',NULL,'pending','2026-06-27 22:22:17'),(3,5,500.00,500.00,'paymongo','MT-3','checkout_created','cs_7f18d76178de32903004814e',NULL,'pending','2026-06-27 22:22:59'),(4,5,500.00,500.00,'paymongo','MT-4','checkout_created','cs_a8dfce89edea8ef5d157da36',NULL,'pending','2026-06-27 22:35:55'),(5,5,149.99,149.99,'paymongo','MT-5','checkout_created','cs_fdf83bc17ec2a8b9bf3b2626',NULL,'pending','2026-06-27 22:36:38'),(6,5,500.00,500.00,'paymongo','MT-6','checkout_created','cs_742793a301cd7cf33aa3cf3d',NULL,'pending','2026-06-27 23:25:13'),(7,5,500.00,500.00,'paymongo','MT-7','checkout_created','cs_67d7149da9f6fdb76aa80f35',NULL,'pending','2026-06-27 23:26:03'),(8,5,500.00,500.00,'paymongo','MT-8','checkout_created','cs_1081a98c5271d12aea92f29a',NULL,'pending','2026-06-27 23:30:40'),(10,5,800.00,800.00,'paymongo','MT-10','paid','cs_64701d577c687a6fdc009663','2026-06-28 00:11:55','completed','2026-06-27 23:56:26'),(11,5,800.00,800.00,'paymongo','MT-11','paid','cs_0bf51ecbb933d01f64b8915d','2026-06-28 00:09:16','completed','2026-06-27 23:56:59'),(12,5,149.99,149.99,'paymongo','MT-12','paid','cs_d9f6e1b106269067e02a5588','2026-06-28 00:14:19','completed','2026-06-28 00:14:07');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
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
  `image` varchar(255) DEFAULT NULL,
  `status` enum('available','low_stock','out_of_stock') NOT NULL DEFAULT 'available',
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (14,7,'SHELL - CITY SCOOTER OIL','SHELL','',585.00,NULL,49,'products/prod_6a3c8d76ec1c9.jpg','available',0,'2026-06-25 10:07:50'),(16,7,'LIQUI MOLY ENGINE OIL','LIQUI MOLY','',800.00,NULL,497,'products/prod_6a3d31cf28a9e.webp','available',0,'2026-06-25 21:49:03'),(17,7,'MOTUL SCOOTER OIL','MOTUL','',500.00,NULL,4,'products/prod_6a3d32140c4d2.jpg','available',0,'2026-06-25 21:50:12'),(18,8,'KOBY CVT CLEANER','KOBY','',149.99,NULL,67,'products/prod_6a3d40fb0b10f.jpg','available',0,'2026-06-25 22:53:47'),(19,7,'RS8 OIL','RS8','',345.00,NULL,5,'products/prod_6a41c132373d8.jpg','available',0,'2026-06-29 08:49:54'),(20,15,'PRESONE COOLANT','PRESTONE','1 LITER',350.00,NULL,0,'products/prod_6a41c3ba28f82.jpg','available',0,'2026-06-29 09:00:42');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
INSERT INTO `service_material_rules` VALUES (1,1,1,'Engine Oil 0.8L (110ccÔÇô125cc)',100,125,1.00,'bottle'),(2,1,2,'Engine Oil 1L (126cc and above)',126,9999,1.00,'bottle'),(3,2,3,'CVT Cleaner Spray (Ôëñ125cc)',0,125,2.00,'can'),(4,2,3,'CVT Cleaner Spray (ÔëÑ126cc)',126,9999,3.00,'can'),(5,3,4,'Chain Lubricant Spray',0,9999,1.00,'can');
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
INSERT INTO `service_products` VALUES (1,1),(1,2),(2,3),(3,4);
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_types`
--

LOCK TABLES `service_types` WRITE;
/*!40000 ALTER TABLE `service_types` DISABLE KEYS */;
INSERT INTO `service_types` VALUES (18,'CVT CLEANING','',350.00,'1','CVT CLEANERS',8),(19,'CHANGE ENGINE OIL','',70.00,'all','ENGINE OIL',7),(23,'COOLANT FLUSHING','',50.00,'all',NULL,NULL);
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
INSERT INTO `testimonials` VALUES (1,'Maria Santos','MotoTrack has been my go-to shop for all my motorcycle needs. Fast, reliable, and affordable!',5,'2026-06-21 06:46:58'),(2,'Carlos Reyes','Great service! The staff really knows their stuff. My Honda Click runs perfectly now.',5,'2026-06-21 06:46:58'),(3,'Ana Gonzales','I love how easy it is to book a service appointment online. No more waiting in long queues!',5,'2026-06-21 06:46:58');
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','admin@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234567',NULL,'local','admin',1,'2026-06-21 06:46:57'),(2,'Staff User','staff@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234568',NULL,'local','staff',1,'2026-06-21 06:46:57'),(3,'Tech User','tech@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234569',NULL,'local','technician',1,'2026-06-21 06:46:57'),(4,'Juan dela Cruz','juan@gmail.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09171234567',NULL,'local','customer',1,'2026-06-21 06:46:57'),(5,'FARES DAWOUD','pippo.fares@gmail.com','$2y$10$7xHDTqtx64/G15zpgT64NuG01AMwEXTW0EHGIa6jieZZMScPiTNLm','09922550043','110525539002572377824','google','customer',1,'2026-06-21 14:50:15'),(6,'FARES WAEL IBRAHIM DAWOUD','dawoud_fareswaelibrahim@plpasig.edu.ph','$2y$10$hrp0QfvQv3.rpehCM83dRuwNeOTpw/44Sk/JvoQq78LR6rWV1Ls1a',NULL,'111815386491218525648','google','customer',1,'2026-06-24 14:54:35'),(7,'MECH MICO','mech@mototrack.com','$2y$10$61dQ6hZs3lf8D98XHOUVk.Jzs./FepNUtg.9z8EH/tNb7fuBVNEWC',NULL,NULL,'local','technician',1,'2026-06-29 00:05:19');
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

-- Dump completed on 2026-06-29  9:18:05
