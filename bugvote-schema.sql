-- MySQL dump 10.14  Distrib 10.0.8-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: bugvote
-- ------------------------------------------------------
-- Server version	10.0.8-MariaDB-1~raring-log

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
-- Table structure for table `activity`
--

DROP TABLE IF EXISTS `activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity` (
  `activityId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` int(10) unsigned NOT NULL,
  `refId` int(10) unsigned NOT NULL,
  `happenedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `appId` int(10) unsigned NOT NULL,
  `userId` int(10) unsigned NOT NULL,
  PRIMARY KEY (`activityId`),
  KEY `typeIdx` (`type`),
  KEY `timeIdx` (`happenedAt`),
  KEY `appId` (`appId`),
  KEY `userId` (`userId`),
  KEY `refIdx` (`refId`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `appAdmins`
--

DROP TABLE IF EXISTS `appAdmins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appAdmins` (
  `appId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `role` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`appId`,`userId`),
  KEY `userId` (`userId`),
  CONSTRAINT `appAdmins_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`),
  CONSTRAINT `appAdmins_ibfk_2` FOREIGN KEY (`appId`) REFERENCES `apps` (`appId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `appFollowers`
--

DROP TABLE IF EXISTS `appFollowers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appFollowers` (
  `appId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  PRIMARY KEY (`appId`,`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `apps`
--

DROP TABLE IF EXISTS `apps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `apps` (
  `appId` int(11) NOT NULL AUTO_INCREMENT,
  `appName` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `developerId` int(11) NOT NULL,
  `score` float NOT NULL DEFAULT '0',
  `ratings` int(11) NOT NULL DEFAULT '0',
  `marketplaceDeveloperId` int(11) DEFAULT NULL,
  `marketplaceAppId` int(11) DEFAULT NULL,
  `marketplaceId` int(11) DEFAULT NULL,
  `thumbnailMediumAssetId` int(11) DEFAULT NULL,
  `lastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `websiteUrl` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`appId`),
  UNIQUE KEY `marketplaceUnique` (`marketplaceId`,`marketplaceAppId`),
  KEY `publisherid` (`developerId`),
  FULLTEXT KEY `appNameIdx` (`appName`)
) ENGINE=InnoDB AUTO_INCREMENT=50521 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assetTypes`
--

DROP TABLE IF EXISTS `assetTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assetTypes` (
  `assetType` int(11) NOT NULL,
  `assetTypeDescription` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`assetType`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assets` (
  `assetId` int(11) NOT NULL AUTO_INCREMENT,
  `isValid` int(11) NOT NULL DEFAULT '0',
  `assetType` int(11) DEFAULT NULL,
  `lastModified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `originalFilename` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `mimeType` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`assetId`)
) ENGINE=InnoDB AUTO_INCREMENT=487 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attachmentTypes`
--

DROP TABLE IF EXISTS `attachmentTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attachmentTypes` (
  `attachmentType` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `typeName` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `mimeType` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `isBinary` int(10) unsigned NOT NULL DEFAULT '0',
  `forceDownload` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`attachmentType`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auditActions`
--

DROP TABLE IF EXISTS `auditActions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditActions` (
  `actionId` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`actionId`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auditHistory`
--

DROP TABLE IF EXISTS `auditHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditHistory` (
  `auditId` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `actionId` int(11) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`auditId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auditLog`
--

DROP TABLE IF EXISTS `auditLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditLog` (
  `auditId` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `action` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `subject` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`auditId`)
) ENGINE=InnoDB AUTO_INCREMENT=2143 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bugs`
--

DROP TABLE IF EXISTS `bugs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bugs` (
  `bugId` int(11) NOT NULL AUTO_INCREMENT,
  `appId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `bugTitle` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `bugBody` mediumtext COLLATE utf8_unicode_ci,
  `votes` int(11) NOT NULL,
  PRIMARY KEY (`bugId`),
  KEY `appId` (`appId`) USING HASH,
  KEY `userId` (`userId`) USING HASH,
  CONSTRAINT `bugs_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`),
  CONSTRAINT `bugs_ibfk_2` FOREIGN KEY (`appId`) REFERENCES `apps` (`appId`),
  CONSTRAINT `bugs_ibfk_3` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cacheHitHistory`
--

DROP TABLE IF EXISTS `cacheHitHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cacheHitHistory` (
  `searchQueryId` int(11) NOT NULL,
  `marketplaceId` int(11) NOT NULL,
  `isCacheHit` int(11) NOT NULL DEFAULT '0',
  `queryTime` int(11) NOT NULL DEFAULT '0',
  `errorState` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`searchQueryId`,`marketplaceId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `developerUsers`
--

DROP TABLE IF EXISTS `developerUsers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `developerUsers` (
  `developerId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  PRIMARY KEY (`developerId`,`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `developers`
--

DROP TABLE IF EXISTS `developers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `developers` (
  `developerId` int(11) NOT NULL AUTO_INCREMENT,
  `developerName` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`developerId`),
  UNIQUE KEY `publisherName` (`developerName`),
  FULLTEXT KEY `ftPublisherName` (`developerName`)
) ENGINE=MyISAM AUTO_INCREMENT=148396 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `devices`
--

DROP TABLE IF EXISTS `devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `devices` (
  `deviceId` int(11) NOT NULL AUTO_INCREMENT,
  `deviceName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `manufacturerId` int(11) NOT NULL,
  `assetId` int(11) NOT NULL DEFAULT '0',
  `deviceType` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`deviceId`),
  KEY `manufacturerId` (`manufacturerId`),
  FULLTEXT KEY `phoneNameFTI` (`deviceName`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `devlogs`
--

DROP TABLE IF EXISTS `devlogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `devlogs` (
  `devlogId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projectId` int(10) unsigned NOT NULL,
  `userId` int(10) unsigned NOT NULL,
  `logMarkup` text,
  `logHTML` text,
  `postedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `title` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`devlogId`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entity`
--

DROP TABLE IF EXISTS `entity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity` (
  `entityId` int(11) NOT NULL AUTO_INCREMENT,
  `typeId` int(11) NOT NULL,
  `entityName` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `appId` int(11) NOT NULL,
  PRIMARY KEY (`entityId`),
  KEY `entityName` (`entityName`),
  KEY `appId` (`appId`),
  FULLTEXT KEY `entityNameFTI` (`entityName`)
) ENGINE=MyISAM AUTO_INCREMENT=200001 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entity_types`
--

DROP TABLE IF EXISTS `entity_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity_types` (
  `typeId` int(11) NOT NULL AUTO_INCREMENT,
  `typeName` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`typeId`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eventHistory`
--

DROP TABLE IF EXISTS `eventHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventHistory` (
  `suggestionId` int(10) unsigned NOT NULL,
  `eventType` int(10) unsigned NOT NULL,
  `eventTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `appId` int(10) unsigned NOT NULL,
  `userId` int(10) unsigned NOT NULL,
  `eventId` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`eventId`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `manufacturers`
--

DROP TABLE IF EXISTS `manufacturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manufacturers` (
  `manufacturerId` int(11) NOT NULL AUTO_INCREMENT,
  `manufacturerName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `assetId` int(11) NOT NULL,
  PRIMARY KEY (`manufacturerId`),
  FULLTEXT KEY `manufacturerName` (`manufacturerName`),
  FULLTEXT KEY `manufacturerNameFTI` (`manufacturerName`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketplaceDevelopers`
--

DROP TABLE IF EXISTS `marketplaceDevelopers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marketplaceDevelopers` (
  `developerId` int(11) NOT NULL,
  `marketplaceId` int(11) NOT NULL,
  `marketplaceDeveloperId` int(11) NOT NULL,
  `marketplaceDeveloperName` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`marketplaceId`,`marketplaceDeveloperId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketplaceSearchHistory`
--

DROP TABLE IF EXISTS `marketplaceSearchHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marketplaceSearchHistory` (
  `marketplaceId` int(11) NOT NULL,
  `searchQueryId` int(11) NOT NULL,
  `lastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `queryTimeMsec` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`marketplaceId`,`searchQueryId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketplaces`
--

DROP TABLE IF EXISTS `marketplaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marketplaces` (
  `marketplaceId` int(11) NOT NULL AUTO_INCREMENT,
  `marketplaceName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `marketplaceStoreName` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`marketplaceId`),
  FULLTEXT KEY `marketplaceNameFTI` (`marketplaceName`)
) ENGINE=MyISAM AUTO_INCREMENT=1201 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ownershipRoles`
--

DROP TABLE IF EXISTS `ownershipRoles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ownershipRoles` (
  `roleId` int(10) unsigned NOT NULL,
  `roleTitle` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`roleId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `paths`
--

DROP TABLE IF EXISTS `paths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paths` (
  `pathId` int(11) NOT NULL AUTO_INCREMENT,
  `pathPosition` int(11) NOT NULL,
  `entityId` int(11) NOT NULL,
  PRIMARY KEY (`pathId`,`entityId`),
  KEY `entityId` (`entityId`)
) ENGINE=InnoDB AUTO_INCREMENT=50093 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plan`
--

DROP TABLE IF EXISTS `plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan` (
  `planId` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `description` mediumtext COLLATE utf8_unicode_ci,
  `createdAt` datetime NOT NULL,
  `tags` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`planId`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `platformCompatibility`
--

DROP TABLE IF EXISTS `platformCompatibility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platformCompatibility` (
  `appId` int(11) NOT NULL,
  `platformId` int(11) NOT NULL,
  PRIMARY KEY (`appId`,`platformId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `platforms`
--

DROP TABLE IF EXISTS `platforms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platforms` (
  `platformId` int(11) NOT NULL AUTO_INCREMENT,
  `platformName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `platformDescription` text COLLATE utf8_unicode_ci,
  `platformShortcode` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `platformLogoUrl` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `platformNames` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`platformId`),
  FULLTEXT KEY `publisherNameFTI` (`platformName`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `privileges`
--

DROP TABLE IF EXISTS `privileges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `privileges` (
  `userId` int(10) unsigned NOT NULL,
  `projectId` int(10) unsigned NOT NULL,
  `privilege` int(10) unsigned NOT NULL,
  PRIMARY KEY (`userId`,`projectId`,`privilege`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projectOwners`
--

DROP TABLE IF EXISTS `projectOwners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projectOwners` (
  `projectId` int(10) unsigned NOT NULL,
  `userId` int(10) unsigned NOT NULL,
  `role` int(10) unsigned NOT NULL,
  PRIMARY KEY (`projectId`,`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `projectId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projectTitle` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `createdAt` datetime DEFAULT NULL,
  `thumbnailAssetId` int(10) unsigned DEFAULT NULL,
  `projectUrl` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `seoUrlTitle` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `seoUrlId` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`projectId`)
) ENGINE=InnoDB AUTO_INCREMENT=1005 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `releases`
--

DROP TABLE IF EXISTS `releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `releases` (
  `releaseId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `releaseTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `body` text,
  `version` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`releaseId`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roadmapCategories`
--

DROP TABLE IF EXISTS `roadmapCategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roadmapCategories` (
  `projectId` int(10) unsigned NOT NULL,
  `categoryId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`categoryId`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roadmaps`
--

DROP TABLE IF EXISTS `roadmaps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roadmaps` (
  `projectId` int(10) unsigned NOT NULL,
  `suggestionId` int(10) unsigned NOT NULL,
  `categoryId` int(10) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `searchHistory`
--

DROP TABLE IF EXISTS `searchHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `searchHistory` (
  `searchQueryId` int(11) NOT NULL,
  `searchTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `queryTimeMsec` int(11) NOT NULL DEFAULT '0',
  `cacheState` int(11) NOT NULL DEFAULT '0',
  `searchHistoryId` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`searchHistoryId`),
  KEY `searchQueryId` (`searchQueryId`),
  KEY `queryTimeMsec` (`queryTimeMsec`)
) ENGINE=MyISAM AUTO_INCREMENT=812 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `searchQueries`
--

DROP TABLE IF EXISTS `searchQueries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `searchQueries` (
  `searchQueryId` int(11) NOT NULL AUTO_INCREMENT,
  `searchQuery` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `isDirty` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`searchQueryId`),
  UNIQUE KEY `searchQueriesUnique` (`searchQuery`)
) ENGINE=MyISAM AUTO_INCREMENT=88 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `socialAccounts`
--

DROP TABLE IF EXISTS `socialAccounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `socialAccounts` (
  `socialUserId` bigint(20) NOT NULL,
  `userId` int(11) NOT NULL,
  `fullName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `nickName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `socialProviderId` int(11) NOT NULL,
  `profilePicAssetId` int(11) DEFAULT NULL,
  `credentials` tinytext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`socialUserId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `socialProviders`
--

DROP TABLE IF EXISTS `socialProviders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `socialProviders` (
  `socialProviderId` int(11) NOT NULL AUTO_INCREMENT,
  `socialProvider` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`socialProviderId`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionAttachments`
--

DROP TABLE IF EXISTS `suggestionAttachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionAttachments` (
  `suggestionId` int(10) unsigned NOT NULL,
  `attachmentName` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extension` varchar(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `attachmentType` int(10) unsigned NOT NULL DEFAULT '0',
  `assetId` int(10) unsigned NOT NULL DEFAULT '0',
  `comment` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`suggestionId`,`assetId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionCommentHistory`
--

DROP TABLE IF EXISTS `suggestionCommentHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionCommentHistory` (
  `commentId` int(10) unsigned NOT NULL,
  `commentEvent` int(10) unsigned NOT NULL,
  `eventTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionComments`
--

DROP TABLE IF EXISTS `suggestionComments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionComments` (
  `commentId` int(11) NOT NULL AUTO_INCREMENT,
  `suggestionId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `comment` mediumtext,
  `postedAt` datetime NOT NULL,
  `editedAt` datetime DEFAULT NULL,
  `commentMarkup` mediumtext,
  PRIMARY KEY (`commentId`),
  KEY `suggestionId` (`suggestionId`),
  KEY `userId` (`userId`),
  CONSTRAINT `suggestionComments_ibfk_1` FOREIGN KEY (`suggestionId`) REFERENCES `suggestions` (`suggestionId`),
  CONSTRAINT `suggestionComments_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionDevlog`
--

DROP TABLE IF EXISTS `suggestionDevlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionDevlog` (
  `devlogId` int(10) unsigned NOT NULL,
  `suggestionId` int(10) unsigned NOT NULL,
  `mentionedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionPermissions`
--

DROP TABLE IF EXISTS `suggestionPermissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionPermissions` (
  `suggestionId` int(10) unsigned NOT NULL,
  `userid` int(10) unsigned NOT NULL,
  `attachFiles` tinyint(1) DEFAULT NULL,
  `editSuggestion` tinyint(1) DEFAULT NULL,
  `deleteSuggestion` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionRevisions`
--

DROP TABLE IF EXISTS `suggestionRevisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionRevisions` (
  `suggestionId` int(11) NOT NULL,
  `revisionId` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` mediumtext COLLATE utf8_unicode_ci,
  `revisionDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `userId` int(11) NOT NULL,
  `revisionReason` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`revisionId`),
  KEY `suggestionRevisionIdx` (`suggestionId`,`revisionId`)
) ENGINE=InnoDB AUTO_INCREMENT=2138 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionStateHistory`
--

DROP TABLE IF EXISTS `suggestionStateHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionStateHistory` (
  `historyId` int(11) NOT NULL AUTO_INCREMENT,
  `suggestionId` int(11) NOT NULL,
  `date` datetime DEFAULT NULL,
  `newValue` int(11) NOT NULL,
  PRIMARY KEY (`historyId`),
  KEY `suggestionId` (`suggestionId`),
  CONSTRAINT `suggestionStateHistory_ibfk_1` FOREIGN KEY (`suggestionId`) REFERENCES `suggestions` (`suggestionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionStates`
--

DROP TABLE IF EXISTS `suggestionStates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionStates` (
  `suggestionStateId` int(11) NOT NULL AUTO_INCREMENT,
  `suggestionState` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` mediumtext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`suggestionStateId`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionTagReferences`
--

DROP TABLE IF EXISTS `suggestionTagReferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionTagReferences` (
  `suggestionId` int(10) unsigned NOT NULL,
  `tagId` int(10) unsigned NOT NULL,
  `postedById` int(10) unsigned NOT NULL,
  `postedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionTags`
--

DROP TABLE IF EXISTS `suggestionTags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionTags` (
  `suggestionId` int(10) unsigned NOT NULL,
  `tagId` int(10) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionTypeHistory`
--

DROP TABLE IF EXISTS `suggestionTypeHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionTypeHistory` (
  `historyId` int(11) NOT NULL AUTO_INCREMENT,
  `suggestionId` int(11) NOT NULL,
  `date` datetime DEFAULT NULL,
  `newValue` int(11) NOT NULL,
  PRIMARY KEY (`historyId`),
  KEY `suggestionId` (`suggestionId`),
  CONSTRAINT `suggestionTypeHistory_ibfk_1` FOREIGN KEY (`suggestionId`) REFERENCES `suggestions` (`suggestionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionTypes`
--

DROP TABLE IF EXISTS `suggestionTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionTypes` (
  `suggestionTypeId` int(11) NOT NULL AUTO_INCREMENT,
  `suggestionType` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` mediumtext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`suggestionTypeId`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionViews`
--

DROP TABLE IF EXISTS `suggestionViews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionViews` (
  `suggestionId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `viewed` datetime NOT NULL,
  PRIMARY KEY (`suggestionId`,`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionVoteHistory`
--

DROP TABLE IF EXISTS `suggestionVoteHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionVoteHistory` (
  `suggestionId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `vote` int(11) NOT NULL,
  `votedAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestionVotes`
--

DROP TABLE IF EXISTS `suggestionVotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestionVotes` (
  `suggestionId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `vote` int(11) NOT NULL,
  PRIMARY KEY (`suggestionId`,`userId`),
  KEY `userId` (`userId`),
  KEY `suggestionId` (`suggestionId`),
  KEY `vote` (`vote`),
  CONSTRAINT `suggestionVotes_ibfk_1` FOREIGN KEY (`suggestionId`) REFERENCES `suggestions` (`suggestionId`),
  CONSTRAINT `suggestionVotes_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggestions`
--

DROP TABLE IF EXISTS `suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestions` (
  `suggestionId` int(11) NOT NULL AUTO_INCREMENT,
  `appId` int(11) NOT NULL,
  `suggestion` text,
  `rating` int(11) NOT NULL DEFAULT '0',
  `postedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `userId` int(11) NOT NULL,
  `deviceId` int(11) NOT NULL,
  `title` varchar(128) DEFAULT NULL,
  `suggestionTypeId` int(11) NOT NULL,
  `suggestionStateId` int(11) NOT NULL,
  `needsAttention` int(11) NOT NULL DEFAULT '0',
  `progress` int(11) NOT NULL DEFAULT '0',
  `seoUrlTitle` varchar(64) DEFAULT NULL,
  `seoUrlId` varchar(16) DEFAULT NULL,
  `headRevisionId` int(10) unsigned NOT NULL,
  `numOfVotes` int(10) unsigned NOT NULL DEFAULT '0',
  `numOfComments` int(10) unsigned NOT NULL DEFAULT '0',
  `revisionReason` varchar(64) DEFAULT NULL,
  `revisionDate` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `formattedDescription` text,
  `categoryId` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`suggestionId`),
  KEY `rating` (`rating`),
  KEY `appId` (`appId`),
  KEY `userId` (`userId`),
  KEY `numOfVotes` (`numOfVotes`),
  FULLTEXT KEY `titleIdx` (`title`),
  FULLTEXT KEY `bodyIdx` (`suggestion`),
  FULLTEXT KEY `titleAndSuggestionIdx` (`title`,`suggestion`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `tagId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`tagId`),
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userDevices`
--

DROP TABLE IF EXISTS `userDevices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userDevices` (
  `userId` int(11) NOT NULL,
  `deviceId` int(11) NOT NULL,
  `deviceName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`userId`,`deviceId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userSessions`
--

DROP TABLE IF EXISTS `userSessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userSessions` (
  `authId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `signOn` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `signature` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `device` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`authId`),
  KEY `userId` (`userId`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `userId` int(11) NOT NULL AUTO_INCREMENT,
  `fullName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `profileMediumAssetId` int(11) DEFAULT NULL,
  PRIMARY KEY (`userId`),
  KEY `profileMediumAssetId` (`profileMediumAssetId`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`profileMediumAssetId`) REFERENCES `assets` (`assetId`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'bugvote'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-03-24 12:55:30
