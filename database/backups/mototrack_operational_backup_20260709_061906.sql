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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_products`
--

LOCK TABLES `booking_products` WRITE;
/*!40000 ALTER TABLE `booking_products` DISABLE KEYS */;
INSERT INTO `booking_products` VALUES (11,12,26,29,365.00,'CLUTCH DAMPER','2026-06-29 10:28:40'),(12,12,24,25,100.00,'LIQUI MOLY','2026-06-29 10:28:40'),(13,13,24,22,500.00,'SHELL ENGINE OIL','2026-06-29 11:18:10'),(14,13,25,21,160.00,'KOBY CVT CLEANER','2026-06-29 11:18:10'),(15,14,24,25,100.00,'LIQUI MOLY','2026-07-07 22:42:04'),(16,14,25,21,160.00,'KOBY CVT CLEANER','2026-07-07 22:42:04'),(17,19,24,22,500.00,'SHELL ENGINE OIL','2026-07-08 08:56:37'),(18,19,25,21,160.00,'KOBY CVT CLEANER','2026-07-08 08:56:37'),(19,20,26,29,365.00,'CLUTCH DAMPER','2026-07-08 09:02:33'),(20,21,26,29,365.00,'CLUTCH DAMPER','2026-07-08 20:50:15'),(21,21,24,26,450.00,'MOTUL ENGINE OIL','2026-07-08 20:50:15'),(22,26,24,25,100.00,'LIQUI MOLY','2026-07-09 05:14:05'),(23,26,25,21,160.00,'KOBY CVT CLEANER','2026-07-09 05:14:05'),(24,27,24,25,100.00,'LIQUI MOLY','2026-07-09 05:15:07'),(25,27,25,21,160.00,'KOBY CVT CLEANER','2026-07-09 05:15:07'),(26,28,26,29,365.00,'CLUTCH DAMPER','2026-07-09 05:21:48'),(27,28,24,25,100.00,'LIQUI MOLY','2026-07-09 05:21:48'),(28,29,26,29,365.00,'CLUTCH DAMPER','2026-07-09 11:07:37'),(29,29,24,32,200.00,'AMSOIL','2026-07-09 11:07:37');
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
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_services`
--

LOCK TABLES `booking_services` WRITE;
/*!40000 ALTER TABLE `booking_services` DISABLE KEYS */;
INSERT INTO `booking_services` VALUES (20,12,26,500.00,'CHANGE CLUTCH SYSTEM','2026-06-29 10:28:40'),(21,12,24,70.00,'CHANGE ENGINE OIL','2026-06-29 10:28:40'),(22,13,24,70.00,'CHANGE ENGINE OIL','2026-06-29 11:18:10'),(23,13,25,350.00,'CVT CLEANING','2026-06-29 11:18:10'),(24,14,24,70.00,'CHANGE ENGINE OIL','2026-07-07 22:42:04'),(25,14,25,350.00,'CVT CLEANING','2026-07-07 22:42:04'),(31,19,24,70.00,'CHANGE ENGINE OIL','2026-07-08 08:56:37'),(32,19,25,350.00,'CVT CLEANING','2026-07-08 08:56:37'),(33,20,28,150.00,'CHAIN CLEANING','2026-07-08 09:02:33'),(34,20,26,500.00,'CHANGE CLUTCH SYSTEM','2026-07-08 09:02:33'),(35,21,28,150.00,'CHAIN CLEANING','2026-07-08 20:50:15'),(36,21,26,500.00,'CHANGE CLUTCH SYSTEM','2026-07-08 20:50:15'),(37,21,24,70.00,'CHANGE ENGINE OIL','2026-07-08 20:50:15'),(38,26,24,70.00,'CHANGE ENGINE OIL','2026-07-09 05:14:05'),(39,26,25,350.00,'CVT CLEANING','2026-07-09 05:14:05'),(40,27,24,70.00,'CHANGE ENGINE OIL','2026-07-09 05:15:07'),(41,27,25,350.00,'CVT CLEANING','2026-07-09 05:15:07'),(42,28,28,150.00,'CHAIN CLEANING','2026-07-09 05:21:48'),(43,28,26,500.00,'CHANGE CLUTCH SYSTEM','2026-07-09 05:21:48'),(44,28,24,70.00,'CHANGE ENGINE OIL','2026-07-09 05:21:48'),(45,29,28,150.00,'CHAIN CLEANING','2026-07-09 11:07:37'),(46,29,26,500.00,'CHANGE CLUTCH SYSTEM','2026-07-09 11:07:37'),(47,29,24,70.00,'CHANGE ENGINE OIL','2026-07-09 11:07:37');
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
  `completed_at` datetime DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (12,5,5,'2026-06-30','02:33:00','completed','',7,NULL,NULL,NULL,570.00,465.00,1035.00,'2026-06-29 10:28:40'),(13,5,6,'2026-06-30','11:20:00','completed','',7,NULL,NULL,NULL,420.00,660.00,1080.00,'2026-06-29 11:18:10'),(14,5,6,'2026-07-08','01:45:00','completed','',3,NULL,NULL,NULL,420.00,260.00,680.00,'2026-07-07 22:42:04'),(19,5,6,'2026-07-09','10:58:00','completed','',7,'2026-07-08 09:00:43','2026-07-08 09:01:20',NULL,420.00,660.00,1080.00,'2026-07-08 08:56:37'),(20,5,7,'2026-07-09','11:05:00','completed','',8,'2026-07-08 09:25:52','2026-07-08 09:26:04',NULL,650.00,365.00,1015.00,'2026-07-08 09:02:33'),(21,5,7,'2026-07-10','09:00:00','completed','8UHU',8,'2026-07-09 05:29:06','2026-07-09 05:32:23',NULL,720.00,815.00,1535.00,'2026-07-08 20:50:15'),(26,5,6,'2026-07-10','08:00:00','confirmed','',7,'2026-07-09 05:25:49',NULL,NULL,420.00,260.00,680.00,'2026-07-09 05:14:05'),(27,6,8,'2026-07-10','08:00:00','confirmed','dasds',7,'2026-07-09 05:29:44',NULL,NULL,420.00,260.00,680.00,'2026-07-09 05:15:07'),(28,5,9,'2026-07-10','08:00:00','completed','haha',8,'2026-07-09 05:31:43','2026-07-09 05:32:13',NULL,720.00,465.00,1185.00,'2026-07-09 05:21:48'),(29,5,9,'2026-07-17','08:00:00','pending','',NULL,NULL,NULL,NULL,720.00,565.00,1285.00,'2026-07-09 11:07:37');
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (16,'CVT CLEANERS','cvt-cleaners',NULL),(17,'ENGINE OIL','engine-oil',NULL),(18,'COOLANT','coolant',NULL),(19,'THROTTLE BODY CLEANER','throttle-body-cleaner',NULL),(20,'CLUTCH SYSTEM','clutch-system',NULL),(22,'CHAIN CLEANERS','chain-cleaners',NULL),(23,'TIRES','tires',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_vehicles`
--

LOCK TABLES `customer_vehicles` WRITE;
/*!40000 ALTER TABLE `customer_vehicles` DISABLE KEYS */;
INSERT INTO `customer_vehicles` VALUES (8,6,7,4,27,125,2022,'578QIM','2026-07-09 05:14:43'),(9,5,8,4,35,150,2020,'578QIM','2026-07-09 05:21:20');
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_brands`
--

LOCK TABLES `motorcycle_brands` WRITE;
/*!40000 ALTER TABLE `motorcycle_brands` DISABLE KEYS */;
INSERT INTO `motorcycle_brands` VALUES (4,'Honda',1),(5,'Yamaha',1),(6,'Suzuki',1);
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_models`
--

LOCK TABLES `motorcycle_models` WRITE;
/*!40000 ALTER TABLE `motorcycle_models` DISABLE KEYS */;
INSERT INTO `motorcycle_models` VALUES (27,4,7,'Click 125i',125,NULL,NULL,NULL,1),(28,4,7,'BeAT FI',108,NULL,NULL,NULL,1),(29,5,7,'Mio i125',125,NULL,NULL,NULL,1),(30,5,7,'Mio Sporty',114,NULL,NULL,NULL,1),(32,5,8,'Sniper 155',155,NULL,NULL,NULL,1),(33,5,8,'Sniper 150',150,NULL,NULL,NULL,1),(34,6,8,'Raider R150',147,NULL,NULL,NULL,1),(35,4,8,'Supra GTR150',150,NULL,NULL,NULL,1),(36,5,9,'YZF R15',150,NULL,NULL,NULL,1),(37,4,9,'CBR150R',149,NULL,NULL,NULL,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_types`
--

LOCK TABLES `motorcycle_types` WRITE;
/*!40000 ALTER TABLE `motorcycle_types` DISABLE KEYS */;
INSERT INTO `motorcycle_types` VALUES (7,'SCOOTER',NULL,1),(8,'UNDERBONE',NULL,1),(9,'BACKBONE',NULL,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (25,2,'booking','New booking request from FARES DAWOUD scheduled for Jun 30, 2026 (Booking #12).',12,1,'2026-06-29 10:28:40'),(26,7,'assignment','New job assigned to you: Booking #12 for FARES DAWOUD on Jun 30, 2026.',12,1,'2026-06-29 10:29:05'),(27,2,'completion','Job #12 has been marked as Completed by MECH MICO.',12,1,'2026-06-29 10:29:38'),(28,2,'booking','New booking request from FARES DAWOUD scheduled for Jun 30, 2026 (Booking #13).',13,1,'2026-06-29 11:18:10'),(29,7,'assignment','New job assigned to you: Booking #13 for FARES DAWOUD on Jun 30, 2026.',13,1,'2026-06-29 11:18:16'),(30,2,'completion','Job #13 has been marked as Completed by MECH MICO.',13,1,'2026-06-29 11:18:26'),(31,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 8, 2026 (Booking #14).',14,1,'2026-07-07 22:42:04'),(32,3,'assignment','New job assigned to you: Booking #14 for FARES DAWOUD on Jul 8, 2026.',14,1,'2026-07-07 22:42:13'),(33,2,'completion','Job #14 has been marked as Completed by Tech User.',14,1,'2026-07-07 22:42:46'),(34,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 9, 2026 (Booking #19).',19,1,'2026-07-08 08:56:37'),(35,7,'assignment','New job assigned to you: Booking #19 for FARES DAWOUD on Jul 9, 2026.',19,1,'2026-07-08 09:00:43'),(36,2,'completion','Job #19 has been marked as Completed by MECH MICO.',19,1,'2026-07-08 09:01:20'),(37,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 9, 2026 (Booking #20).',20,1,'2026-07-08 09:02:33'),(38,8,'assignment','New job assigned to you: Booking #20 for FARES DAWOUD on Jul 9, 2026.',20,1,'2026-07-08 09:25:52'),(39,2,'completion','Job #20 has been marked as Completed by MECH JUN.',20,1,'2026-07-08 09:26:04'),(40,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 10, 2026 (Booking #21).',21,1,'2026-07-08 20:50:15'),(41,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 10, 2026 (Booking #26).',26,1,'2026-07-09 05:14:05'),(42,2,'booking','New booking request from FARES WAEL IBRAHIM DAWOUD scheduled for Jul 10, 2026 (Booking #27).',27,1,'2026-07-09 05:15:07'),(43,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 10, 2026 (Booking #28).',28,1,'2026-07-09 05:21:48'),(44,7,'assignment','New job assigned to you: Booking #26 for FARES DAWOUD on Jul 10, 2026.',26,1,'2026-07-09 05:25:50'),(45,8,'assignment','New job assigned to you: Booking #21 for FARES DAWOUD on Jul 10, 2026.',21,1,'2026-07-09 05:29:06'),(46,7,'assignment','New job assigned to you: Booking #27 for FARES WAEL IBRAHIM DAWOUD on Jul 10, 2026.',27,1,'2026-07-09 05:29:44'),(47,8,'assignment','New job assigned to you: Booking #28 for FARES DAWOUD on Jul 10, 2026.',28,1,'2026-07-09 05:31:43'),(48,2,'completion','Job #28 has been marked as Completed by MECH JUN.',28,1,'2026-07-09 05:32:13'),(49,2,'completion','Job #21 has been marked as Completed by MECH JUN.',21,1,'2026-07-09 05:32:23'),(50,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 17, 2026 (Booking #29).',29,1,'2026-07-09 11:07:37');
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (13,13,7,29,1,365.00),(14,14,8,28,1,400.00),(16,16,9,30,1,850.00),(17,17,10,25,1,100.00),(18,18,10,25,3,100.00),(19,19,12,32,1,200.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (13,5,365.00,365.00,'paymongo','MT-13','paid','cs_46e4fd0f1eb7477d35692acc','2026-06-29 10:30:06','completed','2026-06-29 10:29:52'),(14,5,400.00,400.00,'paymongo','MT-14','paid','cs_47c023251ed7a001bc56d43a','2026-06-29 10:34:56','completed','2026-06-29 10:34:43'),(16,5,850.00,850.00,'paymongo','MT-16','checkout_created','cs_a219d4ebaa8e8b29cac435f7',NULL,'pending','2026-07-08 20:45:42'),(17,5,100.00,100.00,'paymongo','MT-17','checkout_created','cs_d38286f1d27997e379451e32',NULL,'pending','2026-07-09 05:22:04'),(18,5,300.00,300.00,'paymongo','MT-18','paid','cs_13fc629071abf0b02899daf6','2026-07-09 05:23:19','completed','2026-07-09 05:23:02'),(19,10,200.00,200.00,'paymongo','MT-19','checkout_created','cs_37e7443b9e61cebb853e34b1',NULL,'pending','2026-07-09 11:04:27');
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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (21,16,'KOBY CVT CLEANER','KOBY','',160.00,NULL,97,10,'products/prod_6a41d461939a1.jpg','available',0,'2026-06-29 10:11:45'),(22,17,'SHELL ENGINE OIL','SHELL','',500.00,NULL,98,10,'products/prod_6a41d49dc3f6a.jpg','available',0,'2026-06-29 10:12:45'),(23,17,'RS8 ENGINE OIL','','',300.00,NULL,100,10,'products/prod_6a41d53899289.jpg','available',0,'2026-06-29 10:15:20'),(24,18,'PRESTONE','','1 LITER',350.00,NULL,100,10,'products/prod_6a41d55bdb5be.jpg','available',0,'2026-06-29 10:15:55'),(25,17,'LIQUI MOLY','','',100.00,NULL,94,10,'products/prod_6a41d577d043c.webp','available',0,'2026-06-29 10:16:23'),(26,17,'MOTUL ENGINE OIL','','',450.00,NULL,99,10,'products/prod_6a41d5ae1f6aa.jpg','available',0,'2026-06-29 10:17:18'),(27,19,'AEROPAK THROTTLE BODY CLEANER','','',150.00,NULL,100,10,'products/prod_6a41d5fc7689a.webp','available',0,'2026-06-29 10:18:36'),(28,20,'GENUINE YAMAHA CLUTCH LINING','','',400.00,NULL,99,10,'products/prod_6a41d6a407e3f.jpg','available',0,'2026-06-29 10:21:24'),(29,20,'CLUTCH DAMPER','','',365.00,NULL,2,10,'products/prod_6a41d728cbb72.jpg','low_stock',0,'2026-06-29 10:23:36'),(30,20,'CLUTCH LINING','','',850.00,NULL,100,10,'products/prod_6a41d776c64df.jpg','available',0,'2026-06-29 10:24:54'),(31,23,'BEAST TIRE','BEAST','',1280.00,NULL,20,10,'products/prod_6a4ec03ed6f3b.jpg','available',0,'2026-07-09 05:25:18'),(32,17,'AMSOIL','AMSOIL','',200.00,NULL,90,10,'products/prod_6a4ecf33203eb.jpg','available',0,'2026-07-09 06:29:07');
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
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_types`
--

LOCK TABLES `service_types` WRITE;
/*!40000 ALTER TABLE `service_types` DISABLE KEYS */;
INSERT INTO `service_types` VALUES (24,'CHANGE ENGINE OIL','',70.00,'all','ENGINE OIL',17),(25,'CVT CLEANING','',350.00,'7','CVT CLEANERS',16),(26,'CHANGE CLUTCH SYSTEM','',500.00,'8,9','CLUTCH SYSTEM',20),(28,'CHAIN CLEANING','',150.00,'8,9','CHAIN CLEANERS',22);
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `technician_services`
--

LOCK TABLES `technician_services` WRITE;
/*!40000 ALTER TABLE `technician_services` DISABLE KEYS */;
INSERT INTO `technician_services` VALUES (10,7,24,'2026-07-08 08:59:29'),(11,7,25,'2026-07-08 08:59:29'),(12,8,28,'2026-07-09 05:29:02'),(13,8,26,'2026-07-09 05:29:02'),(14,8,24,'2026-07-09 05:29:02');
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
  `availability_status` enum('ready','off_duty') NOT NULL DEFAULT 'off_duty',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','admin@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234567',NULL,'local','admin',1,'off_duty','2026-06-21 06:46:57'),(2,'Staff User','staff@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234568',NULL,'local','staff',1,'off_duty','2026-06-21 06:46:57'),(3,'Tech User','tech@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234569',NULL,'local','technician',1,'ready','2026-06-21 06:46:57'),(4,'Juan dela Cruz','juan@gmail.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09171234567',NULL,'local','customer',1,'off_duty','2026-06-21 06:46:57'),(5,'FARES DAWOUD','pippo.fares@gmail.com','$2y$10$7xHDTqtx64/G15zpgT64NuG01AMwEXTW0EHGIa6jieZZMScPiTNLm','09922550043','110525539002572377824','google','customer',1,'off_duty','2026-06-21 14:50:15'),(6,'FARES WAEL IBRAHIM DAWOUD','dawoud_fareswaelibrahim@plpasig.edu.ph','$2y$10$hrp0QfvQv3.rpehCM83dRuwNeOTpw/44Sk/JvoQq78LR6rWV1Ls1a',NULL,'111815386491218525648','google','customer',1,'off_duty','2026-06-24 14:54:35'),(7,'MECH MICO','mech@mototrack.com','$2y$10$yFSQ4EHnOQCvxPjzujX5m.TMCtvdXPk9wy25768kdeWGAzhfULC86',NULL,NULL,'local','technician',1,'ready','2026-06-29 00:05:19'),(8,'MECH JUN','mech2@mototrack.com','$2y$10$lrmHzHMnWtG7kWJP5neZ7O1J7442Uj4zMC78scMBj5y0oL.Wh3Itm',NULL,NULL,'local','technician',1,'ready','2026-07-07 12:02:57'),(10,'KATH','castillokathlynanne@gmail.com','$2y$10$Chi4zGBcBpEfNrClqQDYj.ymBaXBi.Cz5P80oY0gbh8n6NCnmtaU2','09922550043',NULL,'local','customer',1,'off_duty','2026-07-09 07:36:36');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'mototrack'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-09 12:19:07
