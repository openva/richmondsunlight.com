-- MySQL dump 10.13  Distrib 5.7.42, for Linux (x86_64)
--
-- Host: richmondsunlight.crok4xr9pagp.us-east-1.rds.amazonaws.com    Database: richmondsunlight
-- ------------------------------------------------------
-- Server version	5.5.5-10.6.14-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bills`
--

DROP TABLE IF EXISTS `bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(7) NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `chamber` enum('senate','house') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'house',
  `catch_line` varchar(255) DEFAULT NULL,
  `chief_patron_id` smallint(5) unsigned NOT NULL,
  `summary` text DEFAULT NULL,
  `full_text` mediumtext DEFAULT NULL,
  `impact_statement_id` smallint(5) unsigned DEFAULT NULL,
  `last_committee_id` tinyint(3) unsigned DEFAULT NULL,
  `current_chamber` enum('house','senate') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `status` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `outcome` enum('passed','failed') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `view_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `identical` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `summary_hash` char(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `interestingness` smallint(5) unsigned DEFAULT NULL COMMENT 'How much interest there was in this bill throughout the session.',
  `hotness` smallint(5) unsigned DEFAULT NULL COMMENT 'For ranking bills by popularity.',
  `copatrons` tinyint(3) unsigned DEFAULT NULL COMMENT 'Number',
  `incorporated_into` mediumint(8) unsigned DEFAULT NULL COMMENT 'The ID of the bill.',
  `dls_prepared` tinyint(1) DEFAULT NULL,
  `date_introduced` date DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bills` (`number`,`session_id`),
  KEY `number` (`number`),
  KEY `session_id` (`session_id`),
  KEY `chief_patron_id` (`chief_patron_id`),
  KEY `summary_hash` (`summary_hash`),
  KEY `view_count` (`view_count`),
  KEY `hotness` (`hotness`),
  KEY `outcome` (`outcome`),
  KEY `incorporated_into` (`incorporated_into`),
  KEY `copatrons` (`copatrons`),
  KEY `duplicates` (`session_id`,`summary_hash`,`id`),
  KEY `interestingness` (`interestingness`),
  KEY `dls_prepared` (`dls_prepared`)
) ENGINE=InnoDB AUTO_INCREMENT=75536 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bills_copatrons`
--

DROP TABLE IF EXISTS `bills_copatrons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills_copatrons` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `legislator_id` smallint(5) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_legislator` (`bill_id`,`legislator_id`),
  KEY `bill_id` (`bill_id`),
  KEY `legislator_id` (`legislator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2610156 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bills_full_text`
--

DROP TABLE IF EXISTS `bills_full_text`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills_full_text` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `number` varchar(10) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `date_introduced` date NOT NULL,
  `text` mediumtext DEFAULT NULL,
  `failed_retrievals` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of times we''ve queried this text.',
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_id_2` (`bill_id`,`number`),
  KEY `bill_id` (`bill_id`),
  KEY `monitor_attempts` (`failed_retrievals`,`text`(1)),
  KEY `text_nullness` (`text`(1)),
  FULLTEXT KEY `text` (`text`)
) ENGINE=InnoDB AUTO_INCREMENT=1045888 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bills_places`
--

DROP TABLE IF EXISTS `bills_places`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills_places` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `placename` varchar(128) NOT NULL,
  `latitude` float NOT NULL,
  `longitude` float NOT NULL,
  `coordinates` point DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_place` (`bill_id`,`placename`),
  KEY `bill_id` (`bill_id`,`latitude`,`longitude`)
) ENGINE=InnoDB AUTO_INCREMENT=11320 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bills_places_test`
--

DROP TABLE IF EXISTS `bills_places_test`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills_places_test` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `placename` varchar(128) NOT NULL,
  `latitude` float NOT NULL,
  `longitude` float NOT NULL,
  `coordinates` point NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`,`latitude`,`longitude`),
  SPATIAL KEY `coordinates` (`coordinates`)
) ENGINE=MyISAM AUTO_INCREMENT=9408 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bills_section_numbers`
--

DROP TABLE IF EXISTS `bills_section_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills_section_numbers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_text_id` mediumint(9) NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `section_number` varchar(16) NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`,`section_number`),
  KEY `full_text_id` (`full_text_id`)
) ENGINE=InnoDB AUTO_INCREMENT=107056 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bills_status`
--

DROP TABLE IF EXISTS `bills_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `status` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `translation` varchar(120) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date` date NOT NULL,
  `lis_vote_id` varchar(10) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `combo` (`bill_id`,`session_id`,`status`,`date`),
  KEY `lis_vote_id` (`lis_vote_id`),
  KEY `session_id` (`session_id`),
  KEY `date` (`date`),
  KEY `bill_id` (`bill_id`),
  FULLTEXT KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=125612477 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bills_views`
--

DROP TABLE IF EXISTS `bills_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills_views` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` mediumint(8) NOT NULL,
  `user_id` int(5) DEFAULT NULL,
  `ip` varchar(19) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  KEY `ip` (`ip`)
) ENGINE=MyISAM AUTO_INCREMENT=16835348 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blacklist`
--

DROP TABLE IF EXISTS `blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blacklist` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(19) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `user_id` int(5) unsigned DEFAULT NULL,
  `email` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `score` smallint(5) unsigned NOT NULL,
  `reason` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chamber_status`
--

DROP TABLE IF EXISTS `chamber_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chamber_status` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `chamber` enum('house','senate') NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `text` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=570601 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(5) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `name` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `email` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `url` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `ip` varchar(19) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `type` enum('comment','pingback') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'comment',
  `status` enum('published','spam','awaiting moderation','deleted') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `editors_pick` enum('y','n') NOT NULL DEFAULT 'n',
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `editors_pick` (`editors_pick`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `publishable` (`bill_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=16192 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comments_subscriptions`
--

DROP TABLE IF EXISTS `comments_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments_subscriptions` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(5) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `hash` char(8) NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`bill_id`,`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=1545 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `committees`
--

DROP TABLE IF EXISTS `committees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `committees` (
  `id` tinyint(4) unsigned NOT NULL AUTO_INCREMENT,
  `lis_id` tinyint(32) unsigned DEFAULT NULL,
  `parent_id` tinyint(3) unsigned DEFAULT NULL,
  `name` varchar(120) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `shortname` varchar(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `chamber` enum('house','senate') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `meeting_time` varchar(160) DEFAULT NULL,
  `url` varchar(120) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `chamber` (`chamber`),
  KEY `shortname` (`shortname`),
  KEY `lis_id` (`lis_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `committee_members`
--

DROP TABLE IF EXISTS `committee_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `committee_members` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `committee_id` tinyint(3) unsigned NOT NULL,
  `representative_id` smallint(5) unsigned NOT NULL,
  `position` enum('chair','vice chair') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date_started` date NOT NULL,
  `date_ended` date DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `assignments` (`committee_id`,`representative_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6378 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dashboard_bills`
--

DROP TABLE IF EXISTS `dashboard_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_bills` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(5) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `portfolio_id` mediumint(8) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_portfolio` (`bill_id`,`portfolio_id`),
  KEY `user_id` (`user_id`,`bill_id`),
  KEY `portfoilo_id` (`portfolio_id`),
  FULLTEXT KEY `notes` (`notes`)
) ENGINE=InnoDB AUTO_INCREMENT=52365 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dashboard_portfolios`
--

DROP TABLE IF EXISTS `dashboard_portfolios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_portfolios` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(5) unsigned NOT NULL,
  `name` varchar(120) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `hash` char(5) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `notify` enum('hourly','daily','none') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'none',
  `public` enum('y','n') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'n',
  `watch_list_id` mediumint(8) unsigned DEFAULT NULL,
  `view_count` mediumint(8) unsigned NOT NULL DEFAULT 0,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `user_id` (`user_id`),
  KEY `notify` (`notify`),
  KEY `watch_list_id` (`watch_list_id`),
  KEY `public` (`public`),
  FULLTEXT KEY `notes` (`notes`)
) ENGINE=InnoDB AUTO_INCREMENT=5099 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dashboard_user_data`
--

DROP TABLE IF EXISTS `dashboard_user_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_user_data` (
  `user_id` int(8) unsigned NOT NULL,
  `organization` varchar(128) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `email_active` enum('y','n') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'y',
  `type` enum('paid','free') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'free',
  `last_access` datetime NOT NULL,
  `expires` date DEFAULT NULL,
  `unsub_hash` char(8) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `organization_id` (`organization`,`email_active`,`expires`),
  CONSTRAINT `parent_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dashboard_watch_lists`
--

DROP TABLE IF EXISTS `dashboard_watch_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dashboard_watch_lists` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(5) unsigned NOT NULL,
  `tag` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `patron_id` smallint(6) DEFAULT NULL,
  `committee_id` tinyint(3) unsigned DEFAULT NULL,
  `keyword` varchar(120) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `status` enum('introduced','passed house','passed senate','passed','failed','continued','approved','vetoed') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `current_chamber` enum('house','senate') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `districts`
--

DROP TABLE IF EXISTS `districts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `districts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chamber` enum('house','senate') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `number` tinyint(3) unsigned NOT NULL,
  `date_started` date NOT NULL,
  `date_ended` date DEFAULT NULL,
  `description` varchar(300) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `boundaries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `chamber` (`chamber`,`number`)
) ENGINE=InnoDB AUTO_INCREMENT=421 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dockets`
--

DROP TABLE IF EXISTS `dockets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dockets` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `committee_id` tinyint(3) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `date` (`date`,`committee_id`,`bill_id`),
  KEY `bill_id` (`bill_id`,`date`)
) ENGINE=InnoDB AUTO_INCREMENT=1787765 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `chamber` enum('house','senate') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `committee_id` smallint(5) unsigned DEFAULT NULL,
  `author_name` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `title` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `html` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `path` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `license` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `type` enum('video','audio') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `length` time DEFAULT NULL,
  `fps` decimal(4,2) unsigned DEFAULT NULL,
  `capture_rate` tinyint(3) unsigned DEFAULT NULL,
  `capture_directory` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `width` smallint(5) unsigned DEFAULT NULL COMMENT 'Video width',
  `height` smallint(5) unsigned DEFAULT NULL COMMENT 'Video height',
  `date` date NOT NULL,
  `sponsor` text DEFAULT NULL,
  `youtube_id` char(11) DEFAULT NULL,
  `srt` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `webvtt` text DEFAULT NULL,
  `transcript` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `video_index_cache` blob DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `chamber` (`chamber`),
  FULLTEXT KEY `description` (`description`)
) ENGINE=InnoDB AUTO_INCREMENT=1992 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gazetteer`
--

DROP TABLE IF EXISTS `gazetteer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gazetteer` (
  `id` mediumint(8) unsigned NOT NULL,
  `name` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `municipality` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `latitude` float NOT NULL,
  `longitude` float NOT NULL,
  `coordinates` point NOT NULL,
  `elevation` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `county` (`municipality`,`latitude`,`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lobbyists`
--

DROP TABLE IF EXISTS `lobbyists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lobbyists` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `sc_id` char(36) NOT NULL COMMENT 'Secretary of the Commonwealth ID',
  `id_hash` char(32) NOT NULL,
  `principal` varchar(120) NOT NULL,
  `principal_hash` char(32) NOT NULL,
  `organization` varchar(120) DEFAULT NULL,
  `address` tinytext NOT NULL,
  `phone` char(12) NOT NULL,
  `year` smallint(3) unsigned NOT NULL,
  `statement` text NOT NULL,
  `date_registered` date NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sbe_id` (`sc_id`,`year`),
  KEY `id_hash` (`id_hash`),
  KEY `principal_hash` (`principal_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=13736 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `meetings`
--

DROP TABLE IF EXISTS `meetings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meetings` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `committee_id` smallint(5) unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `time` time DEFAULT NULL,
  `timedesc` varchar(120) DEFAULT NULL COMMENT 'A description in lieu of a time',
  `description` mediumtext NOT NULL,
  `location` varchar(120) NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `triumverate` (`date`,`time`,`committee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=517047 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `minutes`
--

DROP TABLE IF EXISTS `minutes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `minutes` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL COMMENT 'dat',
  `chamber` enum('house','senate') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `text` text CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `date` (`date`,`chamber`)
) ENGINE=InnoDB AUTO_INCREMENT=1431 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `polls`
--

DROP TABLE IF EXISTS `polls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `polls` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` mediumint(5) unsigned NOT NULL,
  `vote` enum('y','n') NOT NULL,
  `user_id` int(5) unsigned DEFAULT NULL,
  `ip` varchar(19) NOT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_id_4` (`bill_id`,`ip`),
  UNIQUE KEY `one_vote` (`bill_id`,`user_id`),
  KEY `bill_id` (`bill_id`)
) ENGINE=InnoDB AUTO_INCREMENT=113235 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `representatives`
--

DROP TABLE IF EXISTS `representatives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `representatives` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name_formal` varchar(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `name` varchar(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `name_formatted` varchar(64) NOT NULL COMMENT 'i.e. Del. Jon Doe (R-1)',
  `shortname` varchar(16) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `lis_shortname` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `chamber` enum('house','senate') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `district_id` smallint(5) unsigned NOT NULL,
  `date_started` date NOT NULL,
  `date_ended` date DEFAULT NULL,
  `party` enum('D','R','I') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'D',
  `bio` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `race` set('american indian','asian','pacific islander','white','black','latino','other') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'white',
  `sex` enum('male','female') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'male',
  `notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `phone_district` varchar(12) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `phone_richmond` varchar(12) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `address_district` varchar(80) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `address_richmond` varchar(80) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `email` varchar(64) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `url` varchar(64) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `rss_url` varchar(64) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `twitter` varchar(96) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `sbe_id` varchar(11) DEFAULT NULL,
  `lis_id` varchar(5) DEFAULT NULL,
  `place` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL COMMENT 'District office location',
  `longitude` float NOT NULL,
  `latitude` float NOT NULL,
  `contributions` mediumtext DEFAULT NULL COMMENT 'A Serialized Array',
  `partisanship` tinyint(3) unsigned DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shortname` (`shortname`),
  KEY `name` (`name_formal`),
  KEY `lis_shortname` (`lis_shortname`),
  KEY `lis_id` (`lis_id`),
  KEY `place` (`place`),
  KEY `partisanship` (`partisanship`),
  KEY `coordinates` (`longitude`,`latitude`) USING BTREE,
  FULLTEXT KEY `name_2` (`name_formal`,`bio`,`notes`)
) ENGINE=InnoDB AUTO_INCREMENT=541 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `representatives_districts`
--

DROP TABLE IF EXISTS `representatives_districts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `representatives_districts` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `representative_id` smallint(5) unsigned NOT NULL,
  `district_id` smallint(5) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `representative_id` (`representative_id`,`district_id`)
) ENGINE=InnoDB AUTO_INCREMENT=441 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `representatives_terms`
--

DROP TABLE IF EXISTS `representatives_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `representatives_terms` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `representative_id` smallint(5) unsigned NOT NULL,
  `chamber` enum('house','senate') NOT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chamber_list` (`chamber`,`date_end`),
  KEY `representative_id` (`representative_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `representatives_votes`
--

DROP TABLE IF EXISTS `representatives_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `representatives_votes` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `representative_id` int(10) unsigned NOT NULL,
  `vote_id` mediumint(8) unsigned NOT NULL,
  `vote` enum('Y','N','X','A') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rep_id` (`representative_id`,`vote_id`),
  KEY `representative_id` (`representative_id`),
  KEY `vote_id` (`vote_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4353066 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `lis_id` varchar(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `year` int(10) unsigned NOT NULL,
  `suffix` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date_started` date NOT NULL,
  `date_ended` date DEFAULT NULL,
  `notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `year` (`year`,`suffix`),
  KEY `lis_id` (`lis_id`),
  FULLTEXT KEY `notes` (`notes`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(5) unsigned DEFAULT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `tag` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `ip` varchar(19) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pairing` (`bill_id`,`tag`),
  KEY `bill_id` (`bill_id`),
  KEY `tag` (`tag`)
) ENGINE=InnoDB AUTO_INCREMENT=120422 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `cookie_hash` char(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `name` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `password` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `email` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `url` varchar(60) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `zip` char(10) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `city` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `state` char(2) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `house_district_id` smallint(5) unsigned DEFAULT NULL,
  `senate_district_id` smallint(5) unsigned DEFAULT NULL,
  `representative_id` smallint(5) unsigned DEFAULT NULL COMMENT 'If this user is a legislator',
  `trusted` enum('y','n') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'n',
  `mailing_list` enum('y','n') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'n',
  `ip` varchar(19) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `private_hash` char(8) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `latitude` float DEFAULT NULL,
  `longitude` float DEFAULT NULL,
  `coordinates` point DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cookie_hash` (`cookie_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=90523 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vacode`
--

DROP TABLE IF EXISTS `vacode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vacode` (
  `title_number` varchar(16) DEFAULT NULL,
  `title_name` varchar(128) DEFAULT NULL,
  `chapter_number` varchar(16) DEFAULT NULL,
  `chapter_name` varchar(128) DEFAULT NULL,
  `section_number` varchar(16) NOT NULL,
  `section_name` varchar(255) NOT NULL,
  UNIQUE KEY `section_number` (`section_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `video_clips`
--

DROP TABLE IF EXISTS `video_clips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_clips` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `legislator_id` smallint(5) unsigned DEFAULT NULL,
  `bill_id` mediumint(8) unsigned DEFAULT NULL,
  `file_id` smallint(5) unsigned NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `screenshot` varchar(128) NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `legislator_id` (`legislator_id`,`bill_id`,`file_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `video_clips_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `video_clips_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `video_clips_ibfk_3` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `video_clips_ibfk_4` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `video_clips_ibfk_5` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=224470 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `video_index`
--

DROP TABLE IF EXISTS `video_index`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_index` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` smallint(5) unsigned NOT NULL,
  `time` time NOT NULL,
  `screenshot` varchar(30) NOT NULL,
  `raw_text` varchar(100) NOT NULL,
  `type` enum('bill','legislator') NOT NULL,
  `linked_id` int(10) unsigned DEFAULT NULL,
  `ignored` enum('y','n') NOT NULL DEFAULT 'n',
  `face_json` mediumtext DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `Distinct` (`file_id`,`screenshot`,`type`),
  KEY `Player` (`type`,`linked_id`),
  KEY `subquery` (`file_id`,`linked_id`),
  KEY `ignore` (`ignored`),
  CONSTRAINT `video_index_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `video_index_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1195772 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `video_index_faces`
--

DROP TABLE IF EXISTS `video_index_faces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_index_faces` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `video_index_id` int(10) unsigned NOT NULL,
  `legislator_id` smallint(5) unsigned NOT NULL,
  `confidence` decimal(2,0) unsigned NOT NULL,
  `width` decimal(2,0) NOT NULL,
  `height` decimal(2,0) NOT NULL,
  `center` varchar(11) NOT NULL,
  `mood` varchar(9) DEFAULT NULL,
  `mood_confidence` decimal(2,0) DEFAULT NULL,
  `smiling` enum('true','false') DEFAULT NULL,
  `smiling_confidence` decimal(2,0) DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `video_index_id` (`video_index_id`,`legislator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1165 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `video_transcript`
--

DROP TABLE IF EXISTS `video_transcript`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_transcript` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` smallint(5) unsigned NOT NULL,
  `text` tinytext NOT NULL,
  `time_start` time(2) NOT NULL,
  `time_end` time(2) NOT NULL,
  `new_speaker` enum('y','n') NOT NULL DEFAULT 'n',
  `legislator_id` smallint(5) unsigned DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`,`time_start`,`time_end`),
  KEY `speaker` (`legislator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=614480 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `votes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lis_id` varchar(12) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `bill_id` int(10) unsigned DEFAULT NULL,
  `chamber` enum('house','senate') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `committee_id` int(10) unsigned DEFAULT NULL,
  `outcome` enum('pass','fail') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `tally` varchar(8) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `contested` float(3,2) unsigned DEFAULT NULL COMMENT 'How contested that vote was',
  `total` tinyint(3) unsigned DEFAULT NULL,
  `partisanship` float(5,4) unsigned DEFAULT NULL,
  `notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `main` (`session_id`,`lis_id`),
  KEY `bill_id` (`bill_id`),
  KEY `session_id` (`session_id`),
  KEY `date` (`date`),
  KEY `contested` (`contested`),
  KEY `lis_id` (`lis_id`)
) ENGINE=InnoDB AUTO_INCREMENT=99855 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'richmondsunlight'
--
/*!50003 DROP FUNCTION IF EXISTS `LEVENSHTEIN` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`ricsun`@`%` FUNCTION `LEVENSHTEIN`(s1 VARCHAR(255) CHARACTER SET utf8, s2 VARCHAR(255) CHARACTER SET utf8) RETURNS int(11)
    DETERMINISTIC
BEGIN
    DECLARE s1_len, s2_len, i, j, c, c_temp, cost INT;
    DECLARE s1_char CHAR CHARACTER SET utf8;
    
    DECLARE cv0, cv1 VARBINARY(256);

    SET s1_len = CHAR_LENGTH(s1),
        s2_len = CHAR_LENGTH(s2),
        cv1 = 0x00,
        j = 1,
        i = 1,
        c = 0;

    IF (s1 = s2) THEN
      RETURN (0);
    ELSEIF (s1_len = 0) THEN
      RETURN (s2_len);
    ELSEIF (s2_len = 0) THEN
      RETURN (s1_len);
    END IF;

    WHILE (j <= s2_len) DO
      SET cv1 = CONCAT(cv1, CHAR(j)),
          j = j + 1;
    END WHILE;

    WHILE (i <= s1_len) DO
      SET s1_char = SUBSTRING(s1, i, 1),
          c = i,
          cv0 = CHAR(i),
          j = 1;

      WHILE (j <= s2_len) DO
        SET c = c + 1,
            cost = IF(s1_char = SUBSTRING(s2, j, 1), 0, 1);

        SET c_temp = ORD(SUBSTRING(cv1, j, 1)) + cost;
        IF (c > c_temp) THEN
          SET c = c_temp;
        END IF;

        SET c_temp = ORD(SUBSTRING(cv1, j+1, 1)) + 1;
        IF (c > c_temp) THEN
          SET c = c_temp;
        END IF;

        SET cv0 = CONCAT(cv0, CHAR(c)),
            j = j + 1;
      END WHILE;

      SET cv1 = cv0,
          i = i + 1;
    END WHILE;

    RETURN (c);
  END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-02-07  2:16:14
