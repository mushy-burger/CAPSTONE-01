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
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_products`
--

LOCK TABLES `booking_products` WRITE;
/*!40000 ALTER TABLE `booking_products` DISABLE KEYS */;
INSERT INTO `booking_products` VALUES (30,30,24,36,400.00,'DELO GOLD','2026-07-09 14:09:06'),(31,30,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 14:09:06'),(32,31,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 14:17:01'),(33,31,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 14:17:01'),(34,31,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 14:17:01'),(35,32,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 16:34:54'),(36,32,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 16:34:54'),(37,33,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 16:34:54'),(38,33,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 16:34:54'),(39,34,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 16:34:54'),(40,35,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 16:34:54'),(41,35,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 16:34:54'),(42,36,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 16:41:53'),(43,37,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 16:41:53'),(44,38,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 16:41:53'),(45,39,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 16:41:53'),(46,39,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 16:41:53'),(47,40,26,39,500.00,'YAMAHA GENUIINE CLUTCH LINING - SNIPER 150/155','2026-07-09 16:41:53'),(48,42,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 16:41:53'),(49,42,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 16:41:53'),(50,43,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 16:41:53'),(51,44,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 16:41:53'),(52,45,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 16:41:53'),(53,46,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 16:41:53'),(54,47,26,39,500.00,'YAMAHA GENUIINE CLUTCH LINING - SNIPER 150/155','2026-07-09 16:41:53'),(55,48,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 16:41:53'),(56,48,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 16:41:53'),(57,49,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 16:41:53'),(58,50,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 17:00:08'),(59,50,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 17:00:08'),(60,50,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 17:00:08'),(61,51,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 17:19:53'),(62,51,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 17:19:53'),(63,51,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 17:19:53'),(64,52,24,34,350.00,'MOTUL SCOOTER OIL','2026-07-09 17:27:22'),(65,52,29,45,190.00,'PETRON - RADIATOR COOLANT','2026-07-09 17:27:22'),(66,52,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 17:27:22'),(67,53,26,40,350.00,'HONDA GENUINE CLUTCH LINING - RS - WINNER X - GTR SUPRA / 150','2026-07-09 17:29:47'),(68,53,24,37,600.00,'AMSOIL MOTOR OIL','2026-07-09 17:29:47'),(69,53,29,45,190.00,'PETRON - RADIATOR COOLANT','2026-07-09 17:29:47'),(70,54,24,37,600.00,'AMSOIL MOTOR OIL','2026-07-09 17:32:14'),(71,54,29,45,190.00,'PETRON - RADIATOR COOLANT','2026-07-09 17:32:14'),(72,54,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 17:32:14'),(73,55,24,34,350.00,'MOTUL SCOOTER OIL','2026-07-09 17:34:45'),(74,55,29,45,190.00,'PETRON - RADIATOR COOLANT','2026-07-09 17:34:45'),(75,55,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 17:34:45'),(76,56,24,33,458.00,'SHELL CITY SCOOTER OIL','2026-07-09 17:35:58'),(77,56,29,44,200.00,'PRESTONE - RADIATOR COOLANT','2026-07-09 17:35:58'),(78,56,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 17:35:58'),(79,57,24,37,600.00,'AMSOIL MOTOR OIL','2026-07-09 17:39:49'),(80,57,29,45,190.00,'PETRON - RADIATOR COOLANT','2026-07-09 17:39:49'),(81,57,25,38,156.00,'KOBY CVT CLEANER','2026-07-09 17:39:49');
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
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_services`
--

LOCK TABLES `booking_services` WRITE;
/*!40000 ALTER TABLE `booking_services` DISABLE KEYS */;
INSERT INTO `booking_services` VALUES (48,30,24,70.00,'CHANGE ENGINE OIL','2026-07-09 14:09:06'),(49,30,25,350.00,'CVT CLEANING','2026-07-09 14:09:06'),(50,31,24,70.00,'CHANGE ENGINE OIL','2026-07-09 14:17:01'),(51,31,29,60.00,'COOLANT FLUSHING','2026-07-09 14:17:01'),(52,31,25,350.00,'CVT CLEANING','2026-07-09 14:17:01'),(53,32,24,70.00,'CHANGE ENGINE OIL','2026-07-09 16:34:54'),(54,32,25,350.00,'CVT CLEANING','2026-07-09 16:34:54'),(55,33,24,70.00,'CHANGE ENGINE OIL','2026-07-09 16:34:54'),(56,33,29,60.00,'COOLANT FLUSHING','2026-07-09 16:34:54'),(57,34,25,350.00,'CVT CLEANING','2026-07-09 16:34:54'),(58,35,24,70.00,'CHANGE ENGINE OIL','2026-07-09 16:34:54'),(59,35,29,60.00,'COOLANT FLUSHING','2026-07-09 16:34:54'),(60,36,24,70.00,'CHANGE ENGINE OIL','2026-07-09 16:41:53'),(61,37,25,350.00,'CVT CLEANING','2026-07-09 16:41:53'),(62,38,29,60.00,'COOLANT FLUSHING','2026-07-09 16:41:53'),(63,39,24,70.00,'CHANGE ENGINE OIL','2026-07-09 16:41:53'),(64,39,29,60.00,'COOLANT FLUSHING','2026-07-09 16:41:53'),(65,40,26,500.00,'CHANGE CLUTCH SYSTEM','2026-07-09 16:41:53'),(66,41,28,150.00,'CHAIN CLEANING','2026-07-09 16:41:53'),(67,42,25,350.00,'CVT CLEANING','2026-07-09 16:41:53'),(68,42,24,70.00,'CHANGE ENGINE OIL','2026-07-09 16:41:53'),(69,43,29,60.00,'COOLANT FLUSHING','2026-07-09 16:41:53'),(70,44,24,70.00,'CHANGE ENGINE OIL','2026-07-09 16:41:53'),(71,45,25,350.00,'CVT CLEANING','2026-07-09 16:41:53'),(72,46,29,60.00,'COOLANT FLUSHING','2026-07-09 16:41:53'),(73,47,26,500.00,'CHANGE CLUTCH SYSTEM','2026-07-09 16:41:53'),(74,48,24,70.00,'CHANGE ENGINE OIL','2026-07-09 16:41:53'),(75,48,29,60.00,'COOLANT FLUSHING','2026-07-09 16:41:53'),(76,49,24,70.00,'CHANGE ENGINE OIL','2026-07-09 16:41:53'),(77,50,24,70.00,'CHANGE ENGINE OIL','2026-07-09 17:00:08'),(78,50,29,60.00,'COOLANT FLUSHING','2026-07-09 17:00:08'),(79,50,25,350.00,'CVT CLEANING','2026-07-09 17:00:08'),(80,51,24,70.00,'CHANGE ENGINE OIL','2026-07-09 17:19:53'),(81,51,29,60.00,'COOLANT FLUSHING','2026-07-09 17:19:53'),(82,51,25,350.00,'CVT CLEANING','2026-07-09 17:19:53'),(83,52,24,70.00,'CHANGE ENGINE OIL','2026-07-09 17:27:22'),(84,52,29,60.00,'COOLANT FLUSHING','2026-07-09 17:27:22'),(85,52,25,350.00,'CVT CLEANING','2026-07-09 17:27:22'),(86,53,28,150.00,'CHAIN CLEANING','2026-07-09 17:29:47'),(87,53,26,500.00,'CHANGE CLUTCH SYSTEM','2026-07-09 17:29:47'),(88,53,24,70.00,'CHANGE ENGINE OIL','2026-07-09 17:29:47'),(89,53,29,60.00,'COOLANT FLUSHING','2026-07-09 17:29:47'),(90,54,24,70.00,'CHANGE ENGINE OIL','2026-07-09 17:32:14'),(91,54,29,60.00,'COOLANT FLUSHING','2026-07-09 17:32:14'),(92,54,25,350.00,'CVT CLEANING','2026-07-09 17:32:14'),(93,55,24,70.00,'CHANGE ENGINE OIL','2026-07-09 17:34:45'),(94,55,29,60.00,'COOLANT FLUSHING','2026-07-09 17:34:45'),(95,55,25,350.00,'CVT CLEANING','2026-07-09 17:34:45'),(96,56,24,70.00,'CHANGE ENGINE OIL','2026-07-09 17:35:58'),(97,56,29,60.00,'COOLANT FLUSHING','2026-07-09 17:35:58'),(98,56,25,350.00,'CVT CLEANING','2026-07-09 17:35:58'),(99,57,24,70.00,'CHANGE ENGINE OIL','2026-07-09 17:39:49'),(100,57,29,60.00,'COOLANT FLUSHING','2026-07-09 17:39:49'),(101,57,25,350.00,'CVT CLEANING','2026-07-09 17:39:49');
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
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (30,5,10,'2026-07-12','10:00:00','completed','',3,'2026-07-09 14:15:38','2026-07-09 14:16:05',NULL,420.00,556.00,976.00,'2026-07-09 14:09:06'),(31,5,11,'2026-07-12','08:00:00','completed','',17,'2026-07-09 14:23:09','2026-07-09 14:23:37',NULL,480.00,814.00,1294.00,'2026-07-09 14:17:01'),(32,4,8,'2026-07-10','09:00:00','completed','DEMO-SEED-20260709-103454 - Customer requested morning service estimate.',17,'2026-07-09 17:25:22','2026-07-09 17:26:50',NULL,420.00,614.00,1034.00,'2026-07-09 07:34:54'),(33,5,10,'2026-07-10','11:00:00','completed','DEMO-SEED-20260709-103454 - Confirmed by staff; parts prepared at counter.',3,'2026-07-09 10:34:54','2026-07-09 17:24:43',NULL,130.00,658.00,788.00,'2026-07-08 10:34:54'),(34,4,8,'2026-07-09','14:00:00','in_progress','DEMO-SEED-20260709-103454 - Walk-in CVT cleaning currently being handled.',7,'2026-07-09 08:34:54',NULL,NULL,350.00,156.00,506.00,'2026-07-09 06:34:54'),(35,5,10,'2026-07-08','10:00:00','completed','DEMO-SEED-20260709-103454 - Completed demo maintenance package.',3,'2026-07-08 08:34:54','2026-07-08 10:34:54','Demo job completed; no stock was deducted by this seed.',130.00,658.00,788.00,'2026-07-07 10:34:54'),(36,5,10,'2026-07-10','08:00:00','completed','DUMMY-SEED-20260709-104153-BOOKING-1 - Quick oil change request from walk-in inquiry.',17,'2026-07-09 17:27:57','2026-07-09 17:36:25',NULL,70.00,458.00,528.00,'2026-07-09 04:41:53'),(37,6,8,'2026-07-10','10:00:00','cancelled','DUMMY-SEED-20260709-104153-BOOKING-2 - Customer wants CVT inspection before weekend ride.',NULL,NULL,NULL,NULL,350.00,156.00,506.00,'2026-07-09 05:41:53'),(38,5,10,'2026-07-11','13:00:00','cancelled','DUMMY-SEED-20260709-104153-BOOKING-3 - Coolant flushing estimate requested.',NULL,NULL,NULL,NULL,60.00,200.00,260.00,'2026-07-09 07:41:53'),(39,6,8,'2026-07-09','09:00:00','completed','DUMMY-SEED-20260709-104153-BOOKING-4 - Confirmed morning maintenance slot.',3,'2026-07-09 08:41:53','2026-07-09 17:24:31',NULL,130.00,658.00,788.00,'2026-07-08 10:41:53'),(40,5,10,'2026-07-09','11:00:00','confirmed','DUMMY-SEED-20260709-104153-BOOKING-5 - Assigned to technician for clutch diagnosis.',7,'2026-07-09 09:11:53',NULL,NULL,500.00,500.00,1000.00,'2026-07-08 10:41:53'),(41,6,8,'2026-07-12','15:00:00','cancelled','DUMMY-SEED-20260709-104153-BOOKING-6 - Future chain service appointment.',8,'2026-07-09 09:56:53',NULL,NULL,150.00,0.00,150.00,'2026-07-07 10:41:53'),(42,5,10,'2026-07-09','14:00:00','completed','DUMMY-SEED-20260709-104153-BOOKING-7 - Currently being serviced in bay one.',3,'2026-07-09 07:41:53','2026-07-09 17:24:22',NULL,420.00,614.00,1034.00,'2026-07-09 06:41:53'),(43,6,8,'2026-07-09','16:00:00','in_progress','DUMMY-SEED-20260709-104153-BOOKING-8 - Coolant flushing and inspection ongoing.',7,'2026-07-09 08:41:53',NULL,NULL,60.00,200.00,260.00,'2026-07-09 07:41:53'),(44,5,10,'2026-07-09','12:00:00','completed','DUMMY-SEED-20260709-104153-BOOKING-9 - Completed today for dashboard sample.',3,'2026-07-09 05:41:53','2026-07-09 09:41:53','Dummy completed job note. Inventory was not adjusted.',70.00,458.00,528.00,'2026-07-09 02:41:53'),(45,6,8,'2026-07-08','10:00:00','completed','DUMMY-SEED-20260709-104153-BOOKING-10 - Completed yesterday; no stock deducted by seed.',7,'2026-07-08 07:41:53','2026-07-08 09:41:53','Dummy completed job note. Inventory was not adjusted.',350.00,156.00,506.00,'2026-07-07 10:41:53'),(46,5,10,'2026-07-07','11:00:00','completed','DUMMY-SEED-20260709-104153-BOOKING-11 - Completed coolant service.',8,'2026-07-07 08:41:53','2026-07-07 09:41:53','Dummy completed job note. Inventory was not adjusted.',60.00,200.00,260.00,'2026-07-06 10:41:53'),(47,6,8,'2026-07-05','13:00:00','completed','DUMMY-SEED-20260709-104153-BOOKING-12 - Completed clutch service.',3,'2026-07-05 08:41:53','2026-07-05 09:41:53','Dummy completed job note. Inventory was not adjusted.',500.00,500.00,1000.00,'2026-07-04 10:41:53'),(48,5,10,'2026-07-01','15:00:00','completed','DUMMY-SEED-20260709-104153-BOOKING-13 - Completed service outside current week.',7,'2026-07-01 08:41:53','2026-07-01 09:41:53','Dummy completed job note. Inventory was not adjusted.',130.00,658.00,788.00,'2026-06-30 10:41:53'),(49,6,8,'2026-07-06','08:00:00','cancelled','DUMMY-SEED-20260709-104153-BOOKING-14 - Customer cancelled before assignment.',NULL,NULL,NULL,NULL,70.00,458.00,528.00,'2026-07-05 10:41:53'),(50,5,12,'2026-07-10','14:00:00','cancelled','maingay makina',NULL,NULL,NULL,NULL,480.00,814.00,1294.00,'2026-07-09 17:00:08'),(51,5,12,'2026-07-11','08:00:00','cancelled','',NULL,NULL,NULL,NULL,480.00,814.00,1294.00,'2026-07-09 17:19:53'),(52,5,12,'2026-07-11','08:00:00','cancelled','',NULL,NULL,NULL,NULL,480.00,696.00,1176.00,'2026-07-09 17:27:22'),(53,5,13,'2026-07-10','16:00:00','confirmed','',8,'2026-07-09 17:30:09',NULL,NULL,780.00,1140.00,1920.00,'2026-07-09 17:29:47'),(54,5,12,'2026-07-13','08:00:00','completed','wala sa tono ang motor',3,'2026-07-09 17:33:09','2026-07-09 17:36:19',NULL,480.00,946.00,1426.00,'2026-07-09 17:32:14'),(55,5,14,'2026-07-11','08:00:00','completed','wqwqw',17,'2026-07-09 17:34:56','2026-07-09 17:36:34',NULL,480.00,696.00,1176.00,'2026-07-09 17:34:45'),(56,5,15,'2026-07-17','08:00:00','completed','pms',17,'2026-07-09 17:36:52','2026-07-09 17:38:03',NULL,480.00,814.00,1294.00,'2026-07-09 17:35:58'),(57,5,16,'2026-07-16','09:00:00','confirmed','maingay cvt side',17,'2026-07-09 17:40:00',NULL,NULL,480.00,946.00,1426.00,'2026-07-09 17:39:49');
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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
INSERT INTO `cart_items` VALUES (15,4,38,1),(17,4,44,1),(19,6,38,2),(20,10,43,1);
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
INSERT INTO `categories` VALUES (16,'CVT CLEANERS','cvt-cleaners',NULL),(17,'ENGINE OIL','engine-oil',NULL),(18,'COOLANT','coolant',NULL),(19,'THROTTLE BODY CLEANER','throttle-body-cleaner',NULL),(20,'CLUTCH SYSTEM','clutch-system',NULL),(22,'CHAIN CLEANERS','chain-cleaners',NULL);
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
INSERT INTO `contact_messages` VALUES (1,'Mark Reyes','mark.reyes@example.com','DEMO-SEED-20260709-103454 - Chain service inquiry','Do you accept same-day chain cleaning and adjustment?',0,'2026-07-09 05:34:54'),(2,'Angela Cruz','angela.cruz@example.com','DEMO-SEED-20260709-103454 - Product availability','I would like to ask about scooter oil availability before visiting the shop.',0,'2026-07-09 08:34:54'),(3,'Rico Santos','rico.santos@example.com','DEMO-SEED-20260709-103454 - Booking follow-up','Please confirm if my motorcycle can be checked this weekend.',0,'2026-07-09 10:34:54'),(4,'Mario Santos','mario.santos@example.com','DUMMY-SEED-20260709-104153 - Bulk maintenance inquiry','Do you offer fleet maintenance for delivery motorcycles?',0,'2026-07-09 04:41:53'),(5,'Liza Ramos','liza.ramos@example.com','DUMMY-SEED-20260709-104153 - CVT service schedule','Can I book CVT cleaning this Friday afternoon?',0,'2026-07-08 10:41:53'),(6,'Noel Cruz','noel.cruz@example.com','DUMMY-SEED-20260709-104153 - Oil recommendation','Which oil is best for a daily commuter scooter?',1,'2026-07-07 10:41:53'),(7,'Paolo Reyes','paolo.reyes@example.com','DUMMY-SEED-20260709-104153 - Coolant flushing','How long does coolant flushing usually take?',0,'2026-07-05 10:41:53'),(8,'Janine Lee','janine.lee@example.com','DUMMY-SEED-20260709-104153 - Service quote','Please send an estimate for engine oil and chain cleaning.',1,'2026-07-01 10:41:53');
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_vehicles`
--

LOCK TABLES `customer_vehicles` WRITE;
/*!40000 ALTER TABLE `customer_vehicles` DISABLE KEYS */;
INSERT INTO `customer_vehicles` VALUES (8,6,7,4,27,125,2022,'578QIM','2026-07-09 05:14:43'),(15,5,7,4,27,125,2022,'578QIM','2026-07-09 17:35:34'),(16,5,7,5,48,155,2025,'abc123','2026-07-09 17:39:22');
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
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motorcycle_models`
--

LOCK TABLES `motorcycle_models` WRITE;
/*!40000 ALTER TABLE `motorcycle_models` DISABLE KEYS */;
INSERT INTO `motorcycle_models` VALUES (27,4,7,'Click 125i',125,NULL,NULL,NULL,1),(28,4,7,'BeAT FI',108,NULL,NULL,NULL,1),(29,5,7,'Mio i125',125,NULL,NULL,NULL,1),(30,5,7,'Mio Sporty',114,NULL,NULL,NULL,1),(32,5,8,'Sniper 155',155,NULL,NULL,NULL,1),(33,5,8,'Sniper 150',150,NULL,NULL,NULL,1),(34,6,8,'Raider R150',147,NULL,NULL,NULL,1),(35,4,8,'Supra GTR150',150,NULL,NULL,NULL,1),(36,5,9,'YZF R15',150,NULL,NULL,NULL,1),(37,4,9,'CBR150R',149,NULL,NULL,NULL,1),(46,4,9,'CBR 150R',150,NULL,NULL,NULL,1),(47,5,7,'NMAX 155',155,NULL,NULL,NULL,1),(48,5,7,'NMAX 155 ABS',155,NULL,NULL,NULL,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (51,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 12, 2026 (Booking #30).',30,1,'2026-07-09 14:09:06'),(52,3,'assignment','New job assigned to you: Booking #30 for FARES DAWOUD on Jul 12, 2026.',30,1,'2026-07-09 14:15:38'),(53,2,'completion','Job #30 has been marked as Completed by Tech User.',30,1,'2026-07-09 14:16:05'),(54,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 12, 2026 (Booking #31).',31,1,'2026-07-09 14:17:01'),(55,17,'assignment','New job assigned to you: Booking #31 for FARES DAWOUD on Jul 12, 2026.',31,1,'2026-07-09 14:23:09'),(56,2,'completion','Job #31 has been marked as Completed by MECH NEYB.',31,1,'2026-07-09 14:23:37'),(57,2,'booking','DEMO-SEED-20260709-103454 - New pending booking requires review.',32,1,'2026-07-09 16:34:54'),(58,1,'booking','DEMO-SEED-20260709-103454 - New pending booking requires review.',32,0,'2026-07-09 16:34:54'),(59,3,'booking','DEMO-SEED-20260709-103454 - Demo technician workload notification.',33,1,'2026-07-09 16:34:54'),(60,7,'booking','DEMO-SEED-20260709-103454 - Demo technician workload notification.',33,0,'2026-07-09 16:34:54'),(61,8,'booking','DEMO-SEED-20260709-103454 - Demo technician workload notification.',33,1,'2026-07-09 16:34:54'),(62,2,'booking','DUMMY-SEED-20260709-104153 - Dummy dashboard notification #1',36,1,'2026-07-09 09:41:53'),(63,1,'completion','DUMMY-SEED-20260709-104153 - Dummy dashboard notification #2',37,0,'2026-07-09 08:41:53'),(64,3,'booking','DUMMY-SEED-20260709-104153 - Dummy dashboard notification #3',38,1,'2026-07-09 07:41:53'),(65,7,'completion','DUMMY-SEED-20260709-104153 - Dummy dashboard notification #4',39,1,'2026-07-09 06:41:53'),(66,8,'booking','DUMMY-SEED-20260709-104153 - Dummy dashboard notification #5',40,1,'2026-07-09 05:41:53'),(67,17,'completion','DUMMY-SEED-20260709-104153 - Dummy dashboard notification #6',41,1,'2026-07-09 04:41:53'),(68,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 10, 2026 (Booking #50).',50,1,'2026-07-09 17:00:08'),(69,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 11, 2026 (Booking #51).',51,1,'2026-07-09 17:19:53'),(70,2,'completion','Job #42 has been marked as Completed by Tech User.',42,1,'2026-07-09 17:24:22'),(71,2,'completion','Job #39 has been marked as Completed by Tech User.',39,1,'2026-07-09 17:24:31'),(72,2,'completion','Job #33 has been marked as Completed by Tech User.',33,1,'2026-07-09 17:24:43'),(73,17,'assignment','New job assigned to you: Booking #32 for Juan dela Cruz on Jul 10, 2026.',32,1,'2026-07-09 17:25:22'),(74,2,'completion','Job #32 has been marked as Completed by MECH NEYB.',32,1,'2026-07-09 17:26:50'),(75,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 11, 2026 (Booking #52).',52,1,'2026-07-09 17:27:22'),(76,17,'assignment','New job assigned to you: Booking #36 for FARES DAWOUD on Jul 10, 2026.',36,1,'2026-07-09 17:27:57'),(77,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 10, 2026 (Booking #53).',53,1,'2026-07-09 17:29:47'),(78,8,'assignment','New job assigned to you: Booking #53 for FARES DAWOUD on Jul 10, 2026.',53,1,'2026-07-09 17:30:09'),(79,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 13, 2026 (Booking #54).',54,1,'2026-07-09 17:32:14'),(80,3,'assignment','New job assigned to you: Booking #54 for FARES DAWOUD on Jul 13, 2026.',54,1,'2026-07-09 17:33:09'),(81,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 11, 2026 (Booking #55).',55,1,'2026-07-09 17:34:45'),(82,17,'assignment','New job assigned to you: Booking #55 for FARES DAWOUD on Jul 11, 2026.',55,1,'2026-07-09 17:34:56'),(83,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 17, 2026 (Booking #56).',56,1,'2026-07-09 17:35:58'),(84,2,'completion','Job #54 has been marked as Completed by Tech User.',54,1,'2026-07-09 17:36:19'),(85,2,'completion','Job #36 has been marked as Completed by MECH NEYB.',36,1,'2026-07-09 17:36:25'),(86,2,'completion','Job #55 has been marked as Completed by MECH NEYB.',55,1,'2026-07-09 17:36:34'),(87,17,'assignment','New job assigned to you: Booking #56 for FARES DAWOUD on Jul 17, 2026.',56,1,'2026-07-09 17:36:52'),(88,2,'completion','Job #56 has been marked as Completed by MECH NEYB.',56,1,'2026-07-09 17:38:03'),(89,2,'booking','New booking request from FARES DAWOUD scheduled for Jul 16, 2026 (Booking #57).',57,1,'2026-07-09 17:39:49'),(90,17,'assignment','New job assigned to you: Booking #57 for FARES DAWOUD on Jul 16, 2026.',57,0,'2026-07-09 17:40:00');
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (20,20,14,44,2,200.00),(21,21,NULL,33,1,458.00),(22,21,NULL,44,1,200.00),(23,22,NULL,38,2,156.00),(24,23,NULL,33,1,458.00),(25,24,NULL,33,1,458.00),(26,24,NULL,38,1,156.00),(27,25,NULL,44,2,200.00),(28,25,NULL,43,1,320.00),(29,26,NULL,39,1,500.00),(30,27,NULL,33,2,458.00),(31,28,NULL,38,3,156.00),(32,29,NULL,44,1,200.00),(33,29,NULL,33,1,458.00),(34,30,NULL,43,1,320.00),(35,31,NULL,39,1,500.00),(36,31,NULL,44,1,200.00),(37,32,NULL,33,1,458.00),(38,32,NULL,44,2,200.00),(39,33,NULL,38,2,156.00),(40,33,NULL,43,1,320.00),(41,34,NULL,33,1,458.00),(42,35,NULL,39,1,500.00),(43,36,18,33,1,458.00),(44,36,16,44,2,200.00),(45,37,21,33,1,458.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (20,5,400.00,400.00,'paymongo','MT-20','paid','cs_7050a459eff50ddee44d09c3','2026-07-09 14:27:50','completed','2026-07-09 14:27:31'),(21,4,658.00,658.00,'paymongo','DEMO-SEED-20260709-103454-ORDER','paid',NULL,'2026-07-07 16:34:54','completed','2026-07-07 16:34:54'),(22,5,312.00,312.00,'paymongo','DEMO-SEED-20260709-103454-ORDER','paid',NULL,'2026-07-08 16:34:54','processing','2026-07-08 16:34:54'),(23,4,458.00,458.00,'paymongo','DEMO-SEED-20260709-103454-ORDER','awaiting_payment',NULL,NULL,'pending','2026-07-09 16:34:54'),(24,4,614.00,614.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-1','paid',NULL,'2026-07-09 05:41:53','completed','2026-07-09 05:41:53'),(25,5,720.00,720.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-2','paid',NULL,'2026-07-09 06:41:53','completed','2026-07-09 06:41:53'),(26,6,500.00,500.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-3','paid',NULL,'2026-07-09 07:41:53','processing','2026-07-09 07:41:53'),(27,10,916.00,916.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-4','awaiting_payment',NULL,NULL,'pending','2026-07-09 08:41:53'),(28,4,468.00,468.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-5','paid',NULL,'2026-07-08 10:41:53','completed','2026-07-08 10:41:53'),(29,5,658.00,658.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-6','paid',NULL,'2026-07-07 10:41:53','completed','2026-07-07 10:41:53'),(30,6,320.00,320.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-7','paid',NULL,'2026-07-05 10:41:53','processing','2026-07-05 10:41:53'),(31,10,700.00,700.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-8','cancelled',NULL,NULL,'cancelled','2026-07-03 10:41:53'),(32,4,858.00,858.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-9','paid',NULL,'2026-06-30 10:41:53','completed','2026-06-30 10:41:53'),(33,5,632.00,632.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-10','paid',NULL,'2026-06-24 10:41:53','completed','2026-06-24 10:41:53'),(34,6,458.00,458.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-11','awaiting_payment',NULL,NULL,'pending','2026-06-19 10:41:53'),(35,10,500.00,500.00,'paymongo','DUMMY-SEED-20260709-104153-ORDER-12','paid',NULL,'2026-06-07 10:41:53','completed','2026-06-07 10:41:53'),(36,5,858.00,858.00,'paymongo','MT-36','checkout_created','cs_80adeae68d9689fd46c16305',NULL,'pending','2026-07-09 16:51:53'),(37,5,458.00,458.00,'paymongo','MT-37','paid','cs_bcae87be126c85f57098935b','2026-07-09 17:02:33','completed','2026-07-09 17:01:55');
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (33,17,'SHELL CITY SCOOTER OIL','SHELL','',458.00,NULL,92,10,'products/prod_6a4f315c7d147.jpg','available',0,'2026-07-09 13:27:56'),(34,17,'MOTUL SCOOTER OIL','MOTUL','',350.00,NULL,99,10,'products/prod_6a4f31aeceea2.jpg','available',0,'2026-07-09 13:29:18'),(35,17,'LIQUI MOLY MOLYGEN','LIQUI MOLY','',890.00,NULL,100,10,'products/prod_6a4f31e9a2451.webp','available',0,'2026-07-09 13:30:17'),(36,17,'DELO GOLD','DELO','',400.00,NULL,99,10,'products/prod_6a4f3230c1768.jpg','available',0,'2026-07-09 13:31:28'),(37,17,'AMSOIL MOTOR OIL','AMSOIL','',600.00,NULL,12,10,'products/prod_6a4f326021da5.jpg','available',0,'2026-07-09 13:32:16'),(38,16,'KOBY CVT CLEANER','KOBY','',156.00,NULL,93,10,'products/prod_6a4f32b90bd55.jpg','available',0,'2026-07-09 13:33:45'),(39,20,'YAMAHA GENUIINE CLUTCH LINING - SNIPER 150/155','YAMAHA GENUINE','',500.00,NULL,100,10,'products/prod_6a4f338743dbb.jpg','available',0,'2026-07-09 13:37:11'),(40,20,'HONDA GENUINE CLUTCH LINING - RS - WINNER X - GTR SUPRA / 150','HONDA GEUNINE','',350.00,NULL,100,10,'products/prod_6a4f33c6e04eb.jpg','available',0,'2026-07-09 13:38:14'),(41,16,'RS8 CVT CLEANER','RS8','',140.00,NULL,100,10,'products/prod_6a4f34085fd83.jpg','available',0,'2026-07-09 13:39:20'),(42,16,'MOTOTEK CVT CLEANER','MOTOTEK','',150.00,NULL,100,10,'products/prod_6a4f344e51691.jpg','available',0,'2026-07-09 13:40:19'),(43,19,'WD - 40 THROTTLE BODY CLEANER','WD - 40','',320.00,NULL,100,10,'products/prod_6a4f34c3ef874.webp','available',0,'2026-07-09 13:42:27'),(44,18,'PRESTONE - RADIATOR COOLANT','PRESTONE','',200.00,NULL,94,10,'products/prod_6a4f34fd7e846.jpg','available',0,'2026-07-09 13:43:25'),(45,18,'PETRON - RADIATOR COOLANT','PETRON','',190.00,NULL,98,10,'products/prod_6a4f3535e3dd1.jpg','available',0,'2026-07-09 13:44:21');
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
INSERT INTO `service_booking_items` VALUES (1,1,33,'SHELL CITY SCOOTER OIL',1.00,458.00),(2,2,33,'SHELL CITY SCOOTER OIL',1.00,458.00),(3,3,44,'PRESTONE - RADIATOR COOLANT',1.00,200.00);
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
INSERT INTO `service_bookings` VALUES (1,4,8,24,'2026-07-11','15:00:00','pending','DEMO-SEED-20260709-103454 - Legacy service booking compatibility record.',458.00,70.00,528.00,'2026-07-09 16:34:54'),(2,5,10,24,'2026-07-13','10:00:00','pending','DUMMY-SEED-20260709-104153 - Legacy dummy service booking 1',458.00,70.00,528.00,'2026-07-09 08:41:53'),(3,6,8,29,'2026-07-06','14:00:00','completed','DUMMY-SEED-20260709-104153 - Legacy dummy service booking 2',200.00,60.00,260.00,'2026-07-06 10:41:53');
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_types`
--

LOCK TABLES `service_types` WRITE;
/*!40000 ALTER TABLE `service_types` DISABLE KEYS */;
INSERT INTO `service_types` VALUES (24,'CHANGE ENGINE OIL','',70.00,'all','ENGINE OIL',17),(25,'CVT CLEANING','',350.00,'7','CVT CLEANERS',16),(26,'CHANGE CLUTCH SYSTEM','',500.00,'8,9','CLUTCH SYSTEM',20),(28,'CHAIN CLEANING','',150.00,'8,9','CHAIN CLEANERS',22),(29,'COOLANT FLUSHING','',60.00,'all','COOLANT',18);
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
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `technician_services`
--

LOCK TABLES `technician_services` WRITE;
/*!40000 ALTER TABLE `technician_services` DISABLE KEYS */;
INSERT INTO `technician_services` VALUES (19,3,24,'2026-07-09 13:59:56'),(20,3,29,'2026-07-09 13:59:56'),(21,3,25,'2026-07-09 13:59:56'),(22,7,28,'2026-07-09 14:00:10'),(23,7,26,'2026-07-09 14:00:10'),(24,7,24,'2026-07-09 14:00:10'),(25,7,29,'2026-07-09 14:00:10'),(36,17,24,'2026-07-09 17:21:12'),(37,17,29,'2026-07-09 17:21:12'),(38,17,25,'2026-07-09 17:21:12'),(39,8,28,'2026-07-09 17:21:25'),(40,8,26,'2026-07-09 17:21:25'),(41,8,24,'2026-07-09 17:21:25'),(42,8,29,'2026-07-09 17:21:25');
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','admin@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234567',NULL,'local','admin',1,'off_duty','2026-06-21 06:46:57'),(2,'Staff User','staff@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234568',NULL,'local','staff',1,'off_duty','2026-06-21 06:46:57'),(3,'Tech User','tech@mototrack.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09001234569',NULL,'local','technician',1,'off_duty','2026-06-21 06:46:57'),(4,'Juan dela Cruz','juan@gmail.com','$2y$10$Lx.3FXOgDKRvdohX8y/JG.1n6EeD0g0rZEF6ZxV94wSVN/Bs//8u2','09171234567',NULL,'local','customer',1,'off_duty','2026-06-21 06:46:57'),(5,'FARES DAWOUD','pippo.fares@gmail.com','$2y$10$7xHDTqtx64/G15zpgT64NuG01AMwEXTW0EHGIa6jieZZMScPiTNLm','09922550043','110525539002572377824','google','customer',1,'off_duty','2026-06-21 14:50:15'),(6,'FARES WAEL IBRAHIM DAWOUD','dawoud_fareswaelibrahim@plpasig.edu.ph','$2y$10$hrp0QfvQv3.rpehCM83dRuwNeOTpw/44Sk/JvoQq78LR6rWV1Ls1a',NULL,'111815386491218525648','google','customer',1,'off_duty','2026-06-24 14:54:35'),(7,'MECH MICO','mech@mototrack.com','$2y$10$yFSQ4EHnOQCvxPjzujX5m.TMCtvdXPk9wy25768kdeWGAzhfULC86',NULL,NULL,'local','technician',1,'ready','2026-06-29 00:05:19'),(8,'MECH JUN','mech2@mototrack.com','$2y$10$lrmHzHMnWtG7kWJP5neZ7O1J7442Uj4zMC78scMBj5y0oL.Wh3Itm',NULL,NULL,'local','technician',1,'ready','2026-07-07 12:02:57'),(10,'KATH','castillokathlynanne@gmail.com','$2y$10$Chi4zGBcBpEfNrClqQDYj.ymBaXBi.Cz5P80oY0gbh8n6NCnmtaU2','09922550043',NULL,'local','customer',1,'off_duty','2026-07-09 07:36:36'),(17,'MECH NEYB','mechneyb@mototrack.com','$2y$10$v8AbPv34AvXA7ylQJXuL7uxGu7gqeEEXS24tpCo/39axaKc3j/Bgi',NULL,NULL,'local','technician',1,'ready','2026-07-09 13:22:06');
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

-- Dump completed on 2026-07-10 12:33:09
