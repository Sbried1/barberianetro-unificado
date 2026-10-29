-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: barberia_db
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `barbero`
--

DROP TABLE IF EXISTS `barbero`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `barbero` (
  `id_barbero` int NOT NULL AUTO_INCREMENT,
  `contrasena` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `url_imagen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `especialidad` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `en_netro_desde` int DEFAULT NULL,
  `estado` enum('Disponible','No disponible') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Disponible',
  `anio_inicio` year NOT NULL,
  PRIMARY KEY (`id_barbero`),
  KEY `idx_barbero_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barbero`
--

LOCK TABLES `barbero` WRITE;
/*!40000 ALTER TABLE `barbero` DISABLE KEYS */;
INSERT INTO `barbero` VALUES (8,'$2y$10$RqLtmI70D4eiWtarRo1w5eVCDsLwFPZCGNxZoe1zQR.lCKIZfuu.S','img/barberos/barbero_6ae201db790238.15111716_2116b4fe.jpg','Damian','Mendieta','Degradado',2022,'Disponible',2022),(9,'$2y$10$0Dy06pFOBAdjnq8UyE6cUeN0JCahG8qw4agpIqYQvHO2sdr1n.IXW','img/barberos/barbero_6ae20201e74fd3.81149288_33e3f45b.png','Joaquin','Dramisino','Degradado',2021,'Disponible',2020),(10,'$2y$10$v.Py9mpnYsmnVZ/jattB6exbAGb2EPrfY4HKOq06jNdLc/PNpYIqG','img/barberos/barbero_6ae20222598107.82629423_2d02429e.jpg','Leandro','Peraza','Degradado',2022,'Disponible',2021);
/*!40000 ALTER TABLE `barbero` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cliente`
--

DROP TABLE IF EXISTS `cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `contrasena` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cliente`
--

LOCK TABLES `cliente` WRITE;
/*!40000 ALTER TABLE `cliente` DISABLE KEYS */;
INSERT INTO `cliente` VALUES (9,'cele@gmail.com','Celeste','Ramirez','$2y$10$vGjro1LDJH2ww72sH1XHOeH7AC1L/yDodEKmWccPBrSZCZSA9VbUC'),(11,'joper@gmail.com','Jose','Peralta','$2y$10$Fusp94Q2xUs0uyfH20r8c.Jvq.NbTTh9rSB7plWtYtBVTWpeASPJ6'),(12,'florencia@gmail.com','Florencia','Acosta','$2y$10$CemRssfmkPshgHPaFS/UB.gZt0xER/IBnpXzM02BRMatryAqkh5Ua'),(13,'castrolu@gmail.com','Ludmila','Castro','$2y$10$MkpC3nxH6gIQzK8BST8dYuqgjLF4U7k6TbiOHuIShiT8uen1EyRBK'),(14,'benfa@gmail.com','Facundo','Benites','$2y$10$8BHc4qDCY9ReJPbS1v0OpOPYveNCN/AmF0W6hoPrUz.m3333XTYXy'),(15,'Cata@gmail.com','Catalina','rodriguez','$2y$10$tVrXv36JL9n3.LvdMAg7beT3VWi3itjpNWUQPWP6GQLGvlMq29hsq'),(16,'amarillos@gmail.com','Geremias','Amarillos','$2y$10$Dg8dj6kwp/9mxHy5adta6uO73Hd4gXhuBNBDPG/C8qJXE6zdeQNOy'),(17,'tomina@gmail.com','Tomas','Naranjo','$2y$10$kOq4RTNR1rtAl7jI2ce84O.4v5MBX4aHFJmgjV3zM6u8r7yBGAF9u'),(18,'jaba@gmail.com','Jazmin','Bazan','$2y$10$pBEogqSagWjhRq7mgpSnlOXUm54G2x.jljy6Z.WoT4T5D70kx1tAu'),(21,'jeanlucasfl@gmail.com','pepito','prueba','$2y$10$otozahYAsQO/Qt4X.Q0YTeAY1nQTZ3YXEOiUE2v9DlcoCFR9xXYT2');
/*!40000 ALTER TABLE `cliente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gerente`
--

DROP TABLE IF EXISTS `gerente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gerente` (
  `id_gerente` int NOT NULL AUTO_INCREMENT,
  `contrasena` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_gerente`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gerente`
--

LOCK TABLES `gerente` WRITE;
/*!40000 ALTER TABLE `gerente` DISABLE KEYS */;
INSERT INTO `gerente` VALUES (1,'$2y$10$ErgBxP5FEOHw/gZQ9HxKMOcmws609SiS4z3gB1h8Bh90SEEAEfPX6','jean','fagundez','jeanlucasfl@gmail.com');
/*!40000 ALTER TABLE `gerente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_general_ci NOT NULL,
  `expira` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `token_hash` (`token_hash`),
  KEY `fk_pr_cliente` (`id_cliente`),
  CONSTRAINT `fk_pr_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,21,'2f0efb5a6e3db93311bf54b93cfd09a509f9fa6204350f1868b00e2065bec37a','2026-10-29 12:49:52',1),(2,21,'1789a17243d798c6f4ee4a0ca9e78b68d390317c4ffc8dcf9304767e418a798b','2026-10-29 12:49:55',0);
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reserva`
--

DROP TABLE IF EXISTS `reserva`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserva` (
  `id_reserva` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int NOT NULL,
  `id_barbero` int NOT NULL,
  `id_gerente` int DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` enum('pendiente','confirmada','completada','cancelada') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id_reserva`),
  KEY `fk_reserva_barbero` (`id_barbero`),
  KEY `fk_reserva_gerente` (`id_gerente`),
  KEY `idx_reserva_fecha` (`fecha`),
  KEY `fk_reserva_cliente` (`id_cliente`),
  CONSTRAINT `fk_reserva_barbero` FOREIGN KEY (`id_barbero`) REFERENCES `barbero` (`id_barbero`),
  CONSTRAINT `fk_reserva_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_reserva_gerente` FOREIGN KEY (`id_gerente`) REFERENCES `gerente` (`id_gerente`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reserva`
--

LOCK TABLES `reserva` WRITE;
/*!40000 ALTER TABLE `reserva` DISABLE KEYS */;
INSERT INTO `reserva` VALUES (6,9,8,NULL,'2026-10-28','12:00:00','teamojean@gmail.com','completada'),(7,17,10,NULL,'2026-10-28','11:30:00','peranbfd@gmail.com','completada');
/*!40000 ALTER TABLE `reserva` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicio`
--

DROP TABLE IF EXISTS `servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicio` (
  `id_servicio` int NOT NULL AUTO_INCREMENT,
  `categoria` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `duracion` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `precio` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_servicio`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicio`
--

LOCK TABLES `servicio` WRITE;
/*!40000 ALTER TABLE `servicio` DISABLE KEYS */;
INSERT INTO `servicio` VALUES (6,'Cortes','Corte cl├ísico','30','','350'),(7,'Cortes','Corte degradado (Fade)','45','','450'),(8,'Cortes','Corte con tijera','45','','400'),(9,'Cortes','Corte con tijera','45','','400'),(10,'Cortes','Corte infantil','30','','300'),(11,'Cortes','Arreglo de barba','20','','250'),(12,'Cortes','Arreglo de barba','20','','250');
/*!40000 ALTER TABLE `servicio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicioreserva`
--

DROP TABLE IF EXISTS `servicioreserva`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicioreserva` (
  `id_sr` int NOT NULL AUTO_INCREMENT,
  `id_servicio` int NOT NULL,
  `id_reserva` int NOT NULL,
  PRIMARY KEY (`id_sr`),
  KEY `fk_sr_servicio` (`id_servicio`),
  KEY `fk_sr_reserva` (`id_reserva`),
  CONSTRAINT `fk_sr_reserva` FOREIGN KEY (`id_reserva`) REFERENCES `reserva` (`id_reserva`),
  CONSTRAINT `fk_sr_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicioreserva`
--

LOCK TABLES `servicioreserva` WRITE;
/*!40000 ALTER TABLE `servicioreserva` DISABLE KEYS */;
INSERT INTO `servicioreserva` VALUES (8,6,6),(9,10,6),(10,6,7);
/*!40000 ALTER TABLE `servicioreserva` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `venta`
--

DROP TABLE IF EXISTS `venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `venta` (
  `id_venta` int NOT NULL AUTO_INCREMENT,
  `id_barbero` int NOT NULL,
  `id_cliente` int NOT NULL,
  `id_servicio` int NOT NULL,
  `total` int NOT NULL,
  `fecha` date NOT NULL,
  `servicio` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_reserva` int DEFAULT NULL,
  PRIMARY KEY (`id_venta`),
  KEY `fk_venta_barbero` (`id_barbero`),
  KEY `fk_venta_servicio` (`id_servicio`),
  KEY `idx_venta_fecha` (`fecha`),
  KEY `fk_venta_cliente` (`id_cliente`),
  CONSTRAINT `fk_venta_barbero` FOREIGN KEY (`id_barbero`) REFERENCES `barbero` (`id_barbero`),
  CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_venta_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `venta`
--

LOCK TABLES `venta` WRITE;
/*!40000 ALTER TABLE `venta` DISABLE KEYS */;
INSERT INTO `venta` VALUES (4,8,9,6,350,'2026-10-28','Corte cl├ísico',6),(5,8,9,10,300,'2026-10-28','Corte infantil',6),(6,10,17,6,350,'2026-10-29','Corte cl├ísico',7);
/*!40000 ALTER TABLE `venta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'barberia_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-29 14:50:39
