/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.23-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: sb_tech
-- ------------------------------------------------------
-- Server version	10.6.23-MariaDB-0ubuntu0.22.04.1

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
-- Table structure for table `tbl_account_confirmation_letters`
--

DROP TABLE IF EXISTS `tbl_account_confirmation_letters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_account_confirmation_letters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `letter_no` varchar(32) NOT NULL,
  `letter_date` date NOT NULL,
  `party_name` varchar(191) DEFAULT NULL,
  `party_address` varchar(255) DEFAULT NULL,
  `balance_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `narration` varchar(2048) DEFAULT NULL,
  `status` enum('Draft','Sent','Acknowledged') NOT NULL DEFAULT 'Draft',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conf_fy` (`fiscal_year_id`),
  CONSTRAINT `fk_conf_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_account_dr_cr_notes`
--

DROP TABLE IF EXISTS `tbl_account_dr_cr_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_account_dr_cr_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `note_type` enum('Debit Note','Credit Note') NOT NULL,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `note_no` varchar(32) NOT NULL,
  `note_date` date NOT NULL,
  `party_name` varchar(191) DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `narration` varchar(2048) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fy_note_no` (`fiscal_year_id`,`note_type`,`note_no`),
  CONSTRAINT `fk_drcr_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_account_groups`
--

DROP TABLE IF EXISTS `tbl_account_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_account_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_account_group_title` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_account_sub_groups`
--

DROP TABLE IF EXISTS `tbl_account_sub_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_account_sub_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `title` varchar(191) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_account_subgroup` (`group_id`,`title`),
  CONSTRAINT `fk_subgroup_group` FOREIGN KEY (`group_id`) REFERENCES `tbl_account_groups` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_account_sub_terminals`
--

DROP TABLE IF EXISTS `tbl_account_sub_terminals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_account_sub_terminals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_terminal_id` int(11) NOT NULL,
  `title` varchar(191) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_account_subterminal` (`account_terminal_id`,`title`),
  CONSTRAINT `fk_subterminal_terminal` FOREIGN KEY (`account_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_account_tds_report_entries`
--

DROP TABLE IF EXISTS `tbl_account_tds_report_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_account_tds_report_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tds_type_id` int(11) DEFAULT NULL,
  `voucher_type` varchar(64) DEFAULT NULL,
  `voucher_type_id` int(11) DEFAULT NULL,
  `particulars_date` date DEFAULT NULL,
  `fiscal_year_id` int(10) unsigned DEFAULT NULL,
  `party_name` varchar(191) DEFAULT NULL,
  `pan_number` varchar(50) DEFAULT NULL,
  `gross_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tds_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `remarks` varchar(2048) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tds_type` (`tds_type_id`),
  KEY `idx_tds_fy` (`fiscal_year_id`),
  CONSTRAINT `fk_tds_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tds_type` FOREIGN KEY (`tds_type_id`) REFERENCES `tbl_account_tds_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_account_tds_types`
--

DROP TABLE IF EXISTS `tbl_account_tds_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_account_tds_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_account_terminals`
--

DROP TABLE IF EXISTS `tbl_account_terminals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_account_terminals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_subgroup_id` int(11) NOT NULL,
  `title` varchar(191) NOT NULL,
  `alias` varchar(191) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_account_terminal` (`account_subgroup_id`,`title`),
  CONSTRAINT `fk_terminal_subgroup` FOREIGN KEY (`account_subgroup_id`) REFERENCES `tbl_account_sub_groups` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_audit_log`
--

DROP TABLE IF EXISTS `tbl_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `description` text DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_ip` varchar(45) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_module` (`module`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_actor` (`actor_id`),
  KEY `idx_audit_date` (`added_on`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_bank_reconciliation`
--

DROP TABLE IF EXISTS `tbl_bank_reconciliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_bank_reconciliation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `account_terminal_id` int(11) NOT NULL COMMENT 'Bank account terminal from chart of accounts',
  `statement_ref` varchar(128) DEFAULT NULL,
  `statement_date` date DEFAULT NULL,
  `opening_balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_statement_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_matched_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `status` enum('Open','Matched','Closed') NOT NULL DEFAULT 'Open',
  `remarks` varchar(2048) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bankrec_fy` (`fiscal_year_id`),
  KEY `idx_bankrec_terminal` (`account_terminal_id`),
  KEY `idx_bankrec_status` (`status`),
  CONSTRAINT `fk_bankrec_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bankrec_terminal` FOREIGN KEY (`account_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_calendar`
--

DROP TABLE IF EXISTS `tbl_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_calendar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nepali_year` int(11) NOT NULL DEFAULT 0,
  `month_code` int(11) NOT NULL DEFAULT 0,
  `eng_start_date` date DEFAULT NULL,
  `eng_end_date` date DEFAULT NULL,
  `no_days` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cal_year_month` (`nepali_year`,`month_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4418 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_client_contacts`
--

DROP TABLE IF EXISTS `tbl_client_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_client_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL COMMENT 'The client this contact belongs to',
  `name` varchar(191) NOT NULL,
  `designation` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client_contacts_source` (`client_id`),
  CONSTRAINT `fk_client_contacts_source` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_client_projects`
--

DROP TABLE IF EXISTS `tbl_client_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_client_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL COMMENT 'The client this won project provisions',
  `lead_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `package` varchar(191) DEFAULT NULL,
  `db_name` varchar(191) DEFAULT NULL,
  `permitted_modules` text DEFAULT NULL,
  `permitted_submodules` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `value` decimal(18,4) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Completed','On Hold','Cancelled') NOT NULL DEFAULT 'Active',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_lead` (`lead_id`),
  KEY `idx_project_catalog` (`project_id`),
  KEY `idx_project_client` (`client_id`),
  CONSTRAINT `fk_project_catalog` FOREIGN KEY (`project_id`) REFERENCES `tbl_projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_client` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_project_lead` FOREIGN KEY (`lead_id`) REFERENCES `tbl_leads` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_clients`
--

DROP TABLE IF EXISTS `tbl_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('Individual','Company') NOT NULL DEFAULT 'Company' COMMENT 'Classification of the business source',
  `name` varchar(191) NOT NULL,
  `contact_person` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `pan_num` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL COMMENT 'Soft back-reference to originating lead (enforced FK lives on tbl_leads.won_client_id)',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client_lead` (`lead_id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_abouts`
--

DROP TABLE IF EXISTS `tbl_cms_abouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_abouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `mission` text DEFAULT NULL,
  `vision` text DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `image_name` varchar(255) DEFAULT NULL,
  `image_location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cms_abouts_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_career_applications`
--

DROP TABLE IF EXISTS `tbl_cms_career_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_career_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `career_id` int(11) NOT NULL,
  `applicant_name` varchar(191) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `resume_name` varchar(255) DEFAULT NULL,
  `resume_location` varchar(255) DEFAULT NULL,
  `status` enum('New','Shortlisted','Interview','Offer','Rejected') NOT NULL DEFAULT 'New',
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_careerapp_career` (`career_id`),
  KEY `idx_careerapp_status` (`status`),
  CONSTRAINT `fk_careerapp_career` FOREIGN KEY (`career_id`) REFERENCES `tbl_cms_careers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_careers`
--

DROP TABLE IF EXISTS `tbl_cms_careers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_careers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `designation` varchar(191) DEFAULT NULL,
  `location` varchar(191) DEFAULT NULL,
  `job_type` enum('Full-time','Part-time','Contract','Internship') DEFAULT 'Full-time',
  `salary` varchar(100) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_career_slug` (`slug`),
  KEY `idx_career_status` (`status`),
  KEY `fk_career_department` (`department_id`),
  CONSTRAINT `fk_career_department` FOREIGN KEY (`department_id`) REFERENCES `tbl_office_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_contacts_us`
--

DROP TABLE IF EXISTS `tbl_cms_contacts_us`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_contacts_us` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `service_interest` varchar(191) DEFAULT NULL,
  `source_type` varchar(50) DEFAULT 'Website',
  `status` enum('New','Read','Converted') NOT NULL DEFAULT 'New',
  `lead_id` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_status` (`status`),
  KEY `idx_contact_lead` (`lead_id`),
  CONSTRAINT `fk_contact_lead` FOREIGN KEY (`lead_id`) REFERENCES `tbl_leads` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_galleries`
--

DROP TABLE IF EXISTS `tbl_cms_galleries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_galleries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gallery_category_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `image_name` varchar(255) DEFAULT NULL,
  `image_location` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gallery_category` (`gallery_category_id`),
  CONSTRAINT `fk_gallery_category` FOREIGN KEY (`gallery_category_id`) REFERENCES `tbl_cms_gallery_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_gallery_categories`
--

DROP TABLE IF EXISTS `tbl_cms_gallery_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_gallery_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_hero`
--

DROP TABLE IF EXISTS `tbl_cms_hero`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_hero` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `photo_name` varchar(255) DEFAULT NULL,
  `photo_location` varchar(255) DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_link` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cms_hero_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_messages`
--

DROP TABLE IF EXISTS `tbl_cms_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('New','Read','Replied') NOT NULL DEFAULT 'New',
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cmsmsg_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_news`
--

DROP TABLE IF EXISTS `tbl_cms_news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_news` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `image_name` varchar(255) DEFAULT NULL,
  `image_location` varchar(255) DEFAULT NULL,
  `news_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_news_slug` (`slug`),
  KEY `idx_cms_news_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_notices`
--

DROP TABLE IF EXISTS `tbl_cms_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_location` varchar(255) DEFAULT NULL,
  `notice_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cms_notices_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_projects`
--

DROP TABLE IF EXISTS `tbl_cms_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `client_name` varchar(191) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `technologies` text DEFAULT NULL,
  `project_url` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `image_name` varchar(255) DEFAULT NULL,
  `image_location` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_projects_slug` (`slug`),
  KEY `idx_cmsproject_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_services`
--

DROP TABLE IF EXISTS `tbl_cms_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `image_name` varchar(255) DEFAULT NULL,
  `image_location` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_services_slug` (`slug`),
  KEY `idx_cms_services_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_setup`
--

DROP TABLE IF EXISTS `tbl_cms_setup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_setup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_title` varchar(255) DEFAULT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `template` varchar(50) DEFAULT 'classic',
  `primary_color` varchar(20) DEFAULT NULL,
  `secondary_color` varchar(20) DEFAULT NULL,
  `maps_embed` text DEFAULT NULL,
  `contact_email` varchar(191) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_staffs`
--

DROP TABLE IF EXISTS `tbl_cms_staffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_staffs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `position` int(11) DEFAULT NULL,
  `designation` varchar(191) DEFAULT NULL,
  `short_bio` longtext DEFAULT NULL,
  `image_name` varchar(255) DEFAULT NULL,
  `image_location` varchar(255) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cmsstaff_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_cms_testimonials`
--

DROP TABLE IF EXISTS `tbl_cms_testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cms_testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(191) NOT NULL,
  `client_position` varchar(191) DEFAULT NULL,
  `client_company` varchar(191) DEFAULT NULL,
  `testimonial` text DEFAULT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `image_name` varchar(255) DEFAULT NULL,
  `image_location` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_meta_keywords` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cms_testimonials_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_communication_campaigns`
--

DROP TABLE IF EXISTS `tbl_communication_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_communication_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `type` enum('Email','SMS') NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `recipients` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('Draft','Scheduled','Sending','Sent','Failed') NOT NULL DEFAULT 'Draft',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_commcampaign_status` (`status`),
  KEY `fk_commcampaign_template` (`template_id`),
  CONSTRAINT `fk_commcampaign_template` FOREIGN KEY (`template_id`) REFERENCES `tbl_communication_templates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_communication_logs`
--

DROP TABLE IF EXISTS `tbl_communication_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_communication_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) DEFAULT NULL,
  `type` enum('Email','SMS') NOT NULL,
  `recipient` varchar(191) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `status` enum('Queued','Sent','Failed') NOT NULL DEFAULT 'Queued',
  `error_message` text DEFAULT NULL,
  `sent_on` datetime DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_commlog_campaign` (`campaign_id`),
  KEY `idx_commlog_status` (`status`),
  CONSTRAINT `fk_commlog_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `tbl_communication_campaigns` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_communication_settings`
--

DROP TABLE IF EXISTS `tbl_communication_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_communication_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `smtp_host` varchar(191) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_username` varchar(191) DEFAULT NULL,
  `smtp_password_enc` text DEFAULT NULL,
  `smtp_from_name` varchar(191) DEFAULT NULL,
  `smtp_from_email` varchar(191) DEFAULT NULL,
  `sms_provider` varchar(50) DEFAULT NULL,
  `sms_api_key_enc` text DEFAULT NULL,
  `sms_sender_id` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_communication_signatures`
--

DROP TABLE IF EXISTS `tbl_communication_signatures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_communication_signatures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) DEFAULT NULL,
  `html` mediumtext DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_communication_templates`
--

DROP TABLE IF EXISTS `tbl_communication_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_communication_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `type` enum('Email','SMS') NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `sms_body` text DEFAULT NULL,
  `placeholders` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_commtmpl_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_contra_vouchers`
--

DROP TABLE IF EXISTS `tbl_contra_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_contra_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `voucher_no` varchar(32) NOT NULL,
  `voucher_date` date NOT NULL,
  `reference_no` varchar(128) DEFAULT NULL,
  `narration` varchar(2048) DEFAULT NULL,
  `description` varchar(2048) DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `fx_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `entry_type` enum('Manual','Auto') NOT NULL DEFAULT 'Manual',
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `file_name` varchar(512) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fy_contra_voucher_no` (`fiscal_year_id`,`voucher_no`),
  KEY `idx_cv_date` (`voucher_date`),
  KEY `idx_cv_status` (`status`),
  KEY `fk_cv_approved_by` (`approved_by`),
  CONSTRAINT `fk_cv_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cv_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_daily_tasks`
--

DROP TABLE IF EXISTS `tbl_daily_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_daily_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) DEFAULT NULL,
  `fullname` varchar(191) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `tasks` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dailytask_staff_date` (`staff_id`,`date`),
  CONSTRAINT `fk_dailytask_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_document_files`
--

DROP TABLE IF EXISTS `tbl_document_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_document_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL COMMENT 'User-provided title/description for the file',
  `file_location` varchar(255) NOT NULL,
  `file_extension` varchar(10) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_df_doc` (`document_id`),
  CONSTRAINT `fk_document_files_doc` FOREIGN KEY (`document_id`) REFERENCES `tbl_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shared file attachments for all document types';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_document_items`
--

DROP TABLE IF EXISTS `tbl_document_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_document_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `item_type` varchar(30) DEFAULT 'item' COMMENT 'item|milestone|clause|section',
  `meta_json` text DEFAULT NULL COMMENT 'Extra metadata per item type',
  PRIMARY KEY (`id`),
  KEY `idx_di_doc` (`document_id`),
  CONSTRAINT `fk_document_items_doc` FOREIGN KEY (`document_id`) REFERENCES `tbl_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shared line items for all document types';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_document_settings`
--

DROP TABLE IF EXISTS `tbl_document_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_document_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paper_size` enum('A4','Letter','Legal') NOT NULL DEFAULT 'A4',
  `orientation` enum('Portrait','Landscape') NOT NULL DEFAULT 'Portrait',
  `margin_top_mm` decimal(5,2) NOT NULL DEFAULT 15.00,
  `margin_right_mm` decimal(5,2) NOT NULL DEFAULT 15.00,
  `margin_bottom_mm` decimal(5,2) NOT NULL DEFAULT 15.00,
  `margin_left_mm` decimal(5,2) NOT NULL DEFAULT 15.00,
  `font_family` enum('helvetica','times','courier','dejavusans') NOT NULL DEFAULT 'helvetica',
  `font_size_pt` tinyint(3) unsigned NOT NULL DEFAULT 11,
  `header_mode` enum('office_logo','custom_logo','text_only','none') NOT NULL DEFAULT 'office_logo',
  `letterhead_style` enum('logo_left_details_right','logo_left_details_left','details_right_logo_right','details_left_logo_right','centered','logo_left_details_center','details_center_logo_right','logo_top_details_bottom') NOT NULL DEFAULT 'logo_left_details_right',
  `header_logo_location` varchar(255) DEFAULT NULL,
  `header_title` varchar(191) DEFAULT NULL,
  `header_subtitle` varchar(255) DEFAULT NULL,
  `show_header_line` tinyint(1) NOT NULL DEFAULT 1,
  `footer_text` varchar(255) DEFAULT NULL,
  `show_page_numbers` tinyint(1) NOT NULL DEFAULT 1,
  `page_number_format` varchar(50) NOT NULL DEFAULT 'Page {PAGE} of {PAGES}',
  `show_generated_stamp` tinyint(1) NOT NULL DEFAULT 1,
  `watermark_text` varchar(100) DEFAULT NULL,
  `watermark_opacity` decimal(3,2) NOT NULL DEFAULT 0.08,
  `default_terms` text DEFAULT NULL,
  `signature_block` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `show_address` tinyint(1) NOT NULL DEFAULT 1,
  `show_phone` tinyint(1) NOT NULL DEFAULT 1,
  `show_email` tinyint(1) NOT NULL DEFAULT 1,
  `show_website` tinyint(1) NOT NULL DEFAULT 1,
  `show_vat` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_documents`
--

DROP TABLE IF EXISTS `tbl_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type` varchar(30) NOT NULL COMMENT 'quotation|invoice|proforma|proposal|contract|price_list|brochure|credit_note',
  `document_number` varchar(50) NOT NULL COMMENT 'Unique number: QTN-2026-0001, INV-2026-0001, etc.',
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(191) DEFAULT NULL,
  `client_email` varchar(191) DEFAULT NULL,
  `client_phone` varchar(50) DEFAULT NULL,
  `client_address` text DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `document_date` date NOT NULL,
  `valid_until` date DEFAULT NULL COMMENT 'For quotations/proposals/contracts',
  `due_date` date DEFAULT NULL COMMENT 'For invoices/proforma',
  `subtotal` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discount_type` enum('percentage','fixed') DEFAULT NULL,
  `discount_value` decimal(18,4) DEFAULT NULL,
  `tax_type` enum('percentage','fixed') DEFAULT NULL,
  `tax_value` decimal(18,4) DEFAULT NULL,
  `total` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `payment_terms` text DEFAULT NULL COMMENT 'Invoice: payment terms text',
  `bank_name` varchar(191) DEFAULT NULL COMMENT 'Invoice: bank name',
  `bank_account` varchar(50) DEFAULT NULL COMMENT 'Invoice: account number',
  `bank_routing` varchar(50) DEFAULT NULL COMMENT 'Invoice: routing/swift code',
  `late_fee_pct` decimal(5,2) DEFAULT NULL COMMENT 'Invoice: late fee percentage per month',
  `exec_summary` longtext DEFAULT NULL COMMENT 'Proposal: executive summary',
  `problem_statement` longtext DEFAULT NULL COMMENT 'Proposal: problem we solve',
  `proposed_solution` longtext DEFAULT NULL COMMENT 'Proposal: our solution',
  `timeline_text` longtext DEFAULT NULL COMMENT 'Proposal: timeline/milestones',
  `team_text` longtext DEFAULT NULL COMMENT 'Proposal: team members bio',
  `case_studies` longtext DEFAULT NULL COMMENT 'Proposal: past work examples',
  `why_us` longtext DEFAULT NULL COMMENT 'Proposal: why choose us',
  `contract_clauses` longtext DEFAULT NULL COMMENT 'Contract: JSON array of clauses',
  `payment_schedule` longtext DEFAULT NULL COMMENT 'Contract: JSON payment milestones',
  `signature_left_name` varchar(191) DEFAULT NULL COMMENT 'Contract: party 1 name',
  `signature_left_title` varchar(191) DEFAULT NULL COMMENT 'Contract: party 1 title',
  `signature_left_date` date DEFAULT NULL COMMENT 'Contract: party 1 signed date',
  `signature_right_name` varchar(191) DEFAULT NULL COMMENT 'Contract: party 2 name',
  `signature_right_title` varchar(191) DEFAULT NULL COMMENT 'Contract: party 2 title',
  `signature_right_date` date DEFAULT NULL COMMENT 'Contract: party 2 signed date',
  `pl_category` varchar(100) DEFAULT NULL COMMENT 'Price List: category filter',
  `brochure_sections` longtext DEFAULT NULL COMMENT 'Brochure: JSON array of sections',
  `hero_image` varchar(255) DEFAULT NULL COMMENT 'Brochure: hero banner image',
  `original_invoice_id` int(11) DEFAULT NULL COMMENT 'Credit Note: linked invoice ID',
  `credit_reason` text DEFAULT NULL COMMENT 'Credit Note: reason for credit',
  `status` varchar(30) NOT NULL DEFAULT 'Draft',
  `lead_id` int(11) DEFAULT NULL COMMENT 'Linked lead',
  `reference_id` int(11) DEFAULT NULL COMMENT 'Linked parent doc (e.g. invoice→quotation)',
  `show_items` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_doc_number` (`document_number`),
  KEY `idx_doc_type` (`document_type`),
  KEY `idx_doc_status` (`status`),
  KEY `idx_doc_client` (`client_id`),
  KEY `idx_doc_lead` (`lead_id`),
  KEY `idx_doc_added` (`added_on`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Unified document engine — all business documents in one table';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_expense_claim_files`
--

DROP TABLE IF EXISTS `tbl_expense_claim_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_expense_claim_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `claim_id` int(11) NOT NULL,
  `file_location` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_extension` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_claimfile_claim` (`claim_id`),
  CONSTRAINT `fk_claimfile_claim` FOREIGN KEY (`claim_id`) REFERENCES `tbl_expense_claims` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_expense_claims`
--

DROP TABLE IF EXISTS `tbl_expense_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_expense_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `claim_no` varchar(32) NOT NULL,
  `category` varchar(191) DEFAULT NULL,
  `expense_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `project_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `status` enum('Draft','Submitted','Approved','Rejected','Paid') NOT NULL DEFAULT 'Draft',
  `reject_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_on` datetime DEFAULT NULL,
  `payment_voucher_id` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_claim_no` (`claim_no`),
  KEY `idx_claim_staff` (`staff_id`),
  KEY `idx_claim_status` (`status`),
  KEY `idx_claim_payment` (`payment_voucher_id`),
  KEY `fk_claim_approved_by` (`approved_by`),
  CONSTRAINT `fk_claim_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_claim_payment` FOREIGN KEY (`payment_voucher_id`) REFERENCES `tbl_payment_vouchers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_claim_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_fiscal_years`
--

DROP TABLE IF EXISTS `tbl_fiscal_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_fiscal_years` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(20) NOT NULL,
  `starting_date` date NOT NULL,
  `ending_date` date NOT NULL,
  `closing` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fy_title` (`title`),
  KEY `idx_fy_dates` (`starting_date`,`ending_date`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_inv_asset_logs`
--

DROP TABLE IF EXISTS `tbl_inv_asset_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inv_asset_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `action` enum('Assigned','Returned','Maintenance','Condition Change','Status Change','Note') NOT NULL,
  `old_value` varchar(500) DEFAULT NULL,
  `new_value` varchar(500) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invassetlog_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_inv_assets`
--

DROP TABLE IF EXISTS `tbl_inv_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inv_assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_tag` varchar(50) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `warranty_expiry` date DEFAULT NULL,
  `warranty_notes` text DEFAULT NULL,
  `condition_status` enum('New','Good','Fair','Poor','Damaged','Retired') NOT NULL DEFAULT 'New',
  `current_status` enum('In Stock','Assigned','Under Maintenance','Retired','Disposed') NOT NULL DEFAULT 'In Stock',
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_on` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_invasset_tag` (`asset_tag`),
  KEY `idx_invasset_status` (`current_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_inv_categories`
--

DROP TABLE IF EXISTS `tbl_inv_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inv_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_invcat_title` (`title`),
  KEY `idx_invcat_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_inv_items`
--

DROP TABLE IF EXISTS `tbl_inv_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inv_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `cost_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `selling_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `min_stock` int(11) NOT NULL DEFAULT 0,
  `max_stock` int(11) NOT NULL DEFAULT 0,
  `reorder_point` int(11) NOT NULL DEFAULT 0,
  `is_serialized` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `photo` varchar(255) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_invitem_sku` (`sku`),
  KEY `idx_invitem_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_inv_purchase_requisition_items`
--

DROP TABLE IF EXISTS `tbl_inv_purchase_requisition_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inv_purchase_requisition_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(191) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `estimated_unit_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_cost` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `received_qty` int(11) NOT NULL DEFAULT 0,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invpritem_pr` (`pr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_inv_purchase_requisitions`
--

DROP TABLE IF EXISTS `tbl_inv_purchase_requisitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inv_purchase_requisitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_no` varchar(30) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `priority` enum('Low','Normal','Urgent') NOT NULL DEFAULT 'Normal',
  `status` enum('Draft','Submitted','Approved','Rejected','Ordered','Received','Cancelled') NOT NULL DEFAULT 'Draft',
  `total_estimated` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `justification` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_on` datetime DEFAULT NULL,
  `reject_reason` text DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `po_no` varchar(50) DEFAULT NULL,
  `received_on` date DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_invpr_no` (`pr_no`),
  KEY `idx_invpr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_inv_stock`
--

DROP TABLE IF EXISTS `tbl_inv_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inv_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reserved` int(11) NOT NULL DEFAULT 0,
  `location` varchar(100) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_invstock_item_loc` (`item_id`,`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_inv_stock_movements`
--

DROP TABLE IF EXISTS `tbl_inv_stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inv_stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('Purchase','Issue','Return','Transfer','Adjustment','Write-off','Opening') NOT NULL,
  `quantity` int(11) NOT NULL,
  `direction` enum('In','Out') NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `from_location` varchar(100) DEFAULT NULL,
  `to_location` varchar(100) DEFAULT NULL,
  `unit_cost` decimal(18,4) DEFAULT NULL,
  `total_cost` decimal(18,4) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `issued_to` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `date` date NOT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invmove_item` (`item_id`),
  KEY `idx_invmove_type` (`movement_type`),
  KEY `idx_invmove_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_inv_suppliers`
--

DROP TABLE IF EXISTS `tbl_inv_suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inv_suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `contact_person` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `pan_num` varchar(30) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_num` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_journal_vouchers`
--

DROP TABLE IF EXISTS `tbl_journal_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_journal_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `voucher_no` varchar(32) NOT NULL,
  `voucher_date` date NOT NULL,
  `reference_no` varchar(128) DEFAULT NULL,
  `narration` varchar(2048) DEFAULT NULL,
  `description` varchar(2048) DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `fx_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `entry_type` enum('Manual','Auto') NOT NULL DEFAULT 'Manual',
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `file_name` varchar(512) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fy_journal_voucher_no` (`fiscal_year_id`,`voucher_no`),
  KEY `idx_jv_date` (`voucher_date`),
  KEY `idx_jv_status` (`status`),
  KEY `fk_jv_approved_by` (`approved_by`),
  CONSTRAINT `fk_jv_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_jv_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_lead_activities`
--

DROP TABLE IF EXISTS `tbl_lead_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_lead_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `type` enum('Call','Email','Note','Meeting','Status Change','Task') NOT NULL DEFAULT 'Note',
  `note` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leadact_lead` (`lead_id`),
  KEY `idx_leadact_added` (`added_on`),
  KEY `fk_leadact_actor` (`added_by`),
  CONSTRAINT `fk_leadact_actor` FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_leadact_lead` FOREIGN KEY (`lead_id`) REFERENCES `tbl_leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=243 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_lead_files`
--

DROP TABLE IF EXISTS `tbl_lead_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_lead_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `file_location` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_extension` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leadfile_lead` (`lead_id`),
  CONSTRAINT `fk_leadfile_lead` FOREIGN KEY (`lead_id`) REFERENCES `tbl_leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_leads`
--

DROP TABLE IF EXISTS `tbl_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` enum('Website','Phone','Email','Walk-in','Referral','Social','Other') NOT NULL DEFAULT 'Website',
  `company` varchar(191) DEFAULT NULL,
  `contact_name` varchar(191) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `service_interest` varchar(191) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `priority` enum('Hot','Warm','Cold') NOT NULL DEFAULT 'Warm',
  `estimated_value` decimal(18,4) DEFAULT NULL,
  `stage` enum('New','Contacted','Qualified','Proposal','Won','Lost') NOT NULL DEFAULT 'New',
  `assigned_to` int(11) DEFAULT NULL,
  `won_client_id` int(11) DEFAULT NULL COMMENT 'Client that won the deal',
  `client_id` int(11) DEFAULT NULL COMMENT 'Client being pursued',
  `project_id` int(11) DEFAULT NULL,
  `lost_reason` varchar(255) DEFAULT NULL,
  `last_activity_on` datetime DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lead_stage` (`stage`),
  KEY `idx_lead_assigned` (`assigned_to`),
  KEY `idx_lead_priority` (`priority`),
  KEY `idx_lead_email` (`email`),
  KEY `idx_lead_phone` (`phone`),
  KEY `idx_lead_project` (`project_id`),
  KEY `idx_lead_client` (`client_id`),
  KEY `idx_lead_won` (`won_client_id`),
  CONSTRAINT `fk_lead_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_lead_client` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_lead_project_catalog` FOREIGN KEY (`project_id`) REFERENCES `tbl_projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_lead_won` FOREIGN KEY (`won_client_id`) REFERENCES `tbl_clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_ledger_closings`
--

DROP TABLE IF EXISTS `tbl_ledger_closings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_ledger_closings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `ledger_id` int(11) DEFAULT NULL COMMENT 'Account terminal carried forward',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `closing_debit` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `closing_credit` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `remarks` varchar(2048) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ledgerclose_fy` (`fiscal_year_id`),
  KEY `idx_ledgerclose_ledger` (`ledger_id`),
  CONSTRAINT `fk_ledgerclose_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ledgerclose_ledger` FOREIGN KEY (`ledger_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_ledger_particulars`
--

DROP TABLE IF EXISTS `tbl_ledger_particulars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_ledger_particulars` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `voucher_type_id` int(11) NOT NULL,
  `voucher_type` varchar(64) NOT NULL COMMENT 'Journal, Receipt, Payment, Contra, Purchase, Sales, Opening Balance',
  `voucher_status` enum('Approved','Pending') NOT NULL DEFAULT 'Pending',
  `particulars_date` date NOT NULL,
  `fiscal_year_id` int(10) unsigned DEFAULT NULL,
  `account_group_id` int(11) DEFAULT NULL,
  `account_subgroup_id` int(11) DEFAULT NULL,
  `account_terminal_id` int(11) DEFAULT NULL,
  `account_group_title` varchar(191) DEFAULT NULL,
  `account_subgroup_title` varchar(191) DEFAULT NULL,
  `account_terminal_title` varchar(255) DEFAULT NULL,
  `account_sub_terminal_id` int(11) DEFAULT NULL,
  `debit` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `credit` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `fx_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `remarks` varchar(2048) DEFAULT NULL,
  `reconcile_ref` varchar(128) DEFAULT NULL,
  `reconciled_on` datetime DEFAULT NULL,
  `reconciled_by` int(11) DEFAULT NULL,
  `contra_terminal_id` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lp_voucher` (`voucher_type`,`voucher_type_id`),
  KEY `idx_lp_date` (`particulars_date`),
  KEY `idx_lp_fy_terminal` (`fiscal_year_id`,`account_terminal_id`),
  KEY `idx_lp_terminal` (`account_terminal_id`),
  KEY `idx_lp_status` (`voucher_status`),
  KEY `fk_lp_subterminal` (`account_sub_terminal_id`),
  KEY `fk_lp_reconciled_by` (`reconciled_by`),
  CONSTRAINT `fk_lp_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lp_reconciled_by` FOREIGN KEY (`reconciled_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_lp_subterminal` FOREIGN KEY (`account_sub_terminal_id`) REFERENCES `tbl_account_sub_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_lp_terminal` FOREIGN KEY (`account_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_login_attempts`
--

DROP TABLE IF EXISTS `tbl_login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(191) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `executed_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username_date` (`username`,`date`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_migrations`
--

DROP TABLE IF EXISTS `tbl_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` text DEFAULT NULL,
  `executed_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_notifications`
--

DROP TABLE IF EXISTS `tbl_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `details` text DEFAULT NULL,
  `ref_id` varchar(255) DEFAULT NULL,
  `receiver` int(11) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `title` varchar(150) NOT NULL DEFAULT '',
  `url` varchar(255) DEFAULT NULL,
  `viewed` int(11) NOT NULL DEFAULT 0,
  `added_on` datetime DEFAULT current_timestamp(),
  `added_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_receiver` (`receiver`,`viewed`),
  CONSTRAINT `fk_notif_receiver` FOREIGN KEY (`receiver`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2178751 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_bank_details`
--

DROP TABLE IF EXISTS `tbl_office_bank_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_bank_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(191) NOT NULL,
  `account_name` varchar(191) NOT NULL,
  `branch` varchar(191) NOT NULL,
  `account_number` varchar(191) NOT NULL,
  `account_type` varchar(100) NOT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `other_detail` mediumtext DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_departments`
--

DROP TABLE IF EXISTS `tbl_office_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_department_title` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_designation`
--

DROP TABLE IF EXISTS `tbl_office_designation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_designation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_designation_title` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_document_category`
--

DROP TABLE IF EXISTS `tbl_office_document_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_document_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_doccat_title` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_document_files`
--

DROP TABLE IF EXISTS `tbl_office_document_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_document_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) DEFAULT NULL,
  `document_id` int(11) NOT NULL,
  `file_location` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_extension` varchar(50) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_docfile_document` (`document_id`),
  CONSTRAINT `fk_docfile_document` FOREIGN KEY (`document_id`) REFERENCES `tbl_office_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_documents`
--

DROP TABLE IF EXISTS `tbl_office_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `renew_date` date DEFAULT NULL,
  `access_type` enum('Public','Private') NOT NULL DEFAULT 'Public',
  `category_id` int(11) DEFAULT NULL,
  `category` varchar(191) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doc_category` (`category_id`),
  KEY `idx_doc_access` (`access_type`),
  KEY `idx_doc_renew` (`renew_date`),
  CONSTRAINT `fk_doc_category` FOREIGN KEY (`category_id`) REFERENCES `tbl_office_document_category` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_event_schedules`
--

DROP TABLE IF EXISTS `tbl_office_event_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_event_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `from_time` time DEFAULT NULL,
  `to_time` time DEFAULT NULL,
  `this_event` varchar(191) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_schedule_event` (`event_id`),
  KEY `idx_schedule_date` (`date`),
  CONSTRAINT `fk_schedule_event` FOREIGN KEY (`event_id`) REFERENCES `tbl_office_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_events`
--

DROP TABLE IF EXISTS `tbl_office_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `privacy` enum('Public','Private') NOT NULL DEFAULT 'Public',
  `schedules` varchar(255) DEFAULT NULL,
  `venue_type` enum('In Office','Out of Office') DEFAULT 'In Office',
  `venue_location` varchar(255) DEFAULT NULL,
  `attendees_staffs` text DEFAULT NULL,
  `attendees_department` int(11) DEFAULT NULL,
  `other_attendees` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`type`),
  KEY `idx_event_privacy` (`privacy`),
  KEY `idx_event_added_by` (`added_by`),
  CONSTRAINT `fk_event_author` FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_grievance_files`
--

DROP TABLE IF EXISTS `tbl_office_grievance_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_grievance_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref_id` int(11) NOT NULL,
  `type` enum('grievance','Update') DEFAULT 'grievance',
  `file_location` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grievancefile_ref` (`ref_id`),
  CONSTRAINT `fk_grievancefile_ref` FOREIGN KEY (`ref_id`) REFERENCES `tbl_office_grievances` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_grievances`
--

DROP TABLE IF EXISTS `tbl_office_grievances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_grievances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` varchar(64) DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `author` int(11) DEFAULT NULL,
  `assigned` int(11) DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `status` enum('Pending','In Progress','Done','Rejected','Acknowledged') DEFAULT 'Pending',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grievance_author` (`author`),
  KEY `idx_grievance_assigned` (`assigned`),
  KEY `idx_grievance_status` (`status`),
  CONSTRAINT `fk_grievance_assigned` FOREIGN KEY (`assigned`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_grievance_author` FOREIGN KEY (`author`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_holidays`
--

DROP TABLE IF EXISTS `tbl_office_holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `gender_to` enum('Male','Female','Both') NOT NULL DEFAULT 'Both',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_holiday_dates` (`from_date`,`to_date`),
  KEY `idx_holiday_department` (`department_id`),
  CONSTRAINT `fk_holiday_department` FOREIGN KEY (`department_id`) REFERENCES `tbl_office_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_leave_configs`
--

DROP TABLE IF EXISTS `tbl_office_leave_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_leave_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `max_allowed` int(11) NOT NULL DEFAULT 0,
  `leave_year` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `carry_forward` tinyint(1) NOT NULL DEFAULT 0,
  `max_carry_forward` int(11) NOT NULL DEFAULT 0,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 1,
  `gender_specific` enum('Male','Female','Both') DEFAULT 'Both',
  `documentation_required` tinyint(1) NOT NULL DEFAULT 0,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_meeting_hall_setup`
--

DROP TABLE IF EXISTS `tbl_office_meeting_hall_setup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_meeting_hall_setup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hall_name` varchar(191) NOT NULL,
  `occupancy` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_profiles`
--

DROP TABLE IF EXISTS `tbl_office_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `accronym` varchar(100) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone1` varchar(50) DEFAULT NULL,
  `phone2` varchar(50) DEFAULT NULL,
  `vat_no` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `logo_extension` varchar(50) DEFAULT NULL,
  `allow_ips` varchar(255) DEFAULT 'All',
  `use_date` enum('AD','BS') DEFAULT 'AD',
  `leave_year_mode` enum('AD','BS') DEFAULT 'AD',
  `plan_name` varchar(100) DEFAULT NULL,
  `slogan` varchar(255) DEFAULT NULL,
  `estd` varchar(20) DEFAULT NULL,
  `certificate_regd_numbers` text DEFAULT NULL,
  `payment_qr_code` varchar(255) DEFAULT NULL,
  `backup_email` varchar(191) DEFAULT NULL,
  `otp_email` varchar(191) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_spaces`
--

DROP TABLE IF EXISTS `tbl_office_spaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_spaces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_staff_leave_allocation`
--

DROP TABLE IF EXISTS `tbl_office_staff_leave_allocation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_staff_leave_allocation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL,
  `leave_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `allocated_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `used_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `carry_forward_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_leave_allocation` (`year`,`leave_id`,`staff_id`),
  KEY `idx_leavealloc_staff` (`staff_id`),
  KEY `idx_leavealloc_leave` (`leave_id`),
  CONSTRAINT `fk_leavealloc_leave` FOREIGN KEY (`leave_id`) REFERENCES `tbl_office_leave_configs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_leavealloc_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_task_assignees`
--

DROP TABLE IF EXISTS `tbl_office_task_assignees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_task_assignees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `status` enum('Pending','In Progress','Done','Rejected') DEFAULT 'Pending',
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_task_assignee` (`task_id`,`staff_id`),
  KEY `idx_taskassign_staff` (`staff_id`),
  CONSTRAINT `fk_taskassign_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_taskassign_task` FOREIGN KEY (`task_id`) REFERENCES `tbl_office_tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_task_files`
--

DROP TABLE IF EXISTS `tbl_office_task_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_task_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref_id` int(11) NOT NULL,
  `type` enum('Task','Update') DEFAULT 'Task',
  `file_location` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_taskfile_task` (`ref_id`),
  CONSTRAINT `fk_taskfile_task` FOREIGN KEY (`ref_id`) REFERENCES `tbl_office_tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_office_tasks`
--

DROP TABLE IF EXISTS `tbl_office_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_office_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` varchar(64) DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `author` int(11) DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `status` enum('Pending','In Progress','Done','Rejected','Cancelled') DEFAULT 'Pending',
  `department_id` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task_author` (`author`),
  KEY `idx_task_status` (`status`),
  KEY `idx_task_deadline` (`deadline`),
  KEY `idx_task_department` (`department_id`),
  CONSTRAINT `fk_task_author` FOREIGN KEY (`author`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_task_department` FOREIGN KEY (`department_id`) REFERENCES `tbl_office_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_payment_vouchers`
--

DROP TABLE IF EXISTS `tbl_payment_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_payment_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `voucher_no` varchar(32) NOT NULL,
  `voucher_date` date NOT NULL,
  `reference_no` varchar(128) DEFAULT NULL,
  `narration` varchar(2048) DEFAULT NULL,
  `description` varchar(2048) DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `fx_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `entry_type` enum('Manual','Auto') NOT NULL DEFAULT 'Manual',
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `file_name` varchar(512) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fy_payment_voucher_no` (`fiscal_year_id`,`voucher_no`),
  KEY `idx_pv_date` (`voucher_date`),
  KEY `idx_pv_status` (`status`),
  KEY `fk_pv_approved_by` (`approved_by`),
  CONSTRAINT `fk_pv_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pv_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_projects`
--

DROP TABLE IF EXISTS `tbl_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `category` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_name` (`name`),
  KEY `idx_project_category` (`category`),
  KEY `idx_project_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_purchase_vouchers`
--

DROP TABLE IF EXISTS `tbl_purchase_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_purchase_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `voucher_no` varchar(32) NOT NULL,
  `voucher_date` date NOT NULL,
  `reference_no` varchar(128) DEFAULT NULL,
  `narration` varchar(2048) DEFAULT NULL,
  `description` varchar(2048) DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `fx_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `entry_type` enum('Manual','Auto') NOT NULL DEFAULT 'Manual',
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `file_name` varchar(512) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fy_purchase_voucher_no` (`fiscal_year_id`,`voucher_no`),
  KEY `idx_puv_date` (`voucher_date`),
  KEY `idx_puv_status` (`status`),
  KEY `fk_puv_approved_by` (`approved_by`),
  CONSTRAINT `fk_puv_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_puv_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_quotation_files`
--

DROP TABLE IF EXISTS `tbl_quotation_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_quotation_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_location` varchar(255) NOT NULL,
  `file_extension` varchar(10) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quotation_files_quotation` (`quotation_id`),
  CONSTRAINT `fk_quotation_files_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `tbl_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_quotation_items`
--

DROP TABLE IF EXISTS `tbl_quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_quotation_id` (`quotation_id`),
  CONSTRAINT `fk_quotation_items_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `tbl_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_quotations`
--

DROP TABLE IF EXISTS `tbl_quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(191) NOT NULL,
  `client_email` varchar(191) DEFAULT NULL,
  `client_phone` varchar(50) DEFAULT NULL,
  `client_address` text DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `quotation_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `subtotal` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discount_type` enum('percentage','fixed') DEFAULT NULL,
  `discount_value` decimal(18,4) DEFAULT NULL,
  `tax_type` enum('percentage','fixed') DEFAULT NULL,
  `tax_value` decimal(18,4) DEFAULT NULL,
  `total` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `status` enum('Draft','Sent','Accepted','Rejected','Expired') NOT NULL DEFAULT 'Draft',
  `lead_id` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotation_number` (`quotation_number`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_added_on` (`added_on`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_receipt_vouchers`
--

DROP TABLE IF EXISTS `tbl_receipt_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_receipt_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `voucher_no` varchar(32) NOT NULL,
  `voucher_date` date NOT NULL,
  `reference_no` varchar(128) DEFAULT NULL,
  `narration` varchar(2048) DEFAULT NULL,
  `description` varchar(2048) DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `fx_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `entry_type` enum('Manual','Auto') NOT NULL DEFAULT 'Manual',
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `file_name` varchar(512) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fy_receipt_voucher_no` (`fiscal_year_id`,`voucher_no`),
  KEY `idx_rv_date` (`voucher_date`),
  KEY `idx_rv_status` (`status`),
  KEY `fk_rv_approved_by` (`approved_by`),
  CONSTRAINT `fk_rv_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rv_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_sales_vouchers`
--

DROP TABLE IF EXISTS `tbl_sales_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_sales_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(10) unsigned NOT NULL,
  `voucher_no` varchar(32) NOT NULL,
  `voucher_date` date NOT NULL,
  `reference_no` varchar(128) DEFAULT NULL,
  `narration` varchar(2048) DEFAULT NULL,
  `description` varchar(2048) DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `fx_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_currency_code` char(3) NOT NULL DEFAULT 'NPR',
  `entry_type` enum('Manual','Auto') NOT NULL DEFAULT 'Manual',
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `file_name` varchar(512) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `added_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fy_sales_voucher_no` (`fiscal_year_id`,`voucher_no`),
  KEY `idx_sv_date` (`voucher_date`),
  KEY `idx_sv_status` (`status`),
  KEY `fk_sv_approved_by` (`approved_by`),
  CONSTRAINT `fk_sv_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sv_fy` FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_staff_attendances`
--

DROP TABLE IF EXISTS `tbl_staff_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_staff_attendances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `checkin` time DEFAULT NULL,
  `checkout` time DEFAULT NULL,
  `checkin_delay` int(11) DEFAULT NULL,
  `checkout_early` int(11) DEFAULT NULL,
  `reason_checkin` text DEFAULT NULL,
  `reason_checkout` text DEFAULT NULL,
  `config_checkin` time DEFAULT NULL,
  `config_checkout` time DEFAULT NULL,
  `status` enum('present','absent','leave','holiday') NOT NULL DEFAULT 'present',
  `late_checkin` tinyint(1) NOT NULL DEFAULT 0,
  `early_checkout` tinyint(1) NOT NULL DEFAULT 0,
  `late_checkin_minutes` int(11) NOT NULL DEFAULT 0,
  `working_hours` float DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_attendance_user_date` (`user_id`,`date`),
  KEY `idx_attendance_date` (`date`),
  KEY `idx_attendance_status` (`status`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_staff_documents`
--

DROP TABLE IF EXISTS `tbl_staff_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_staff_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `size` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staffdoc_staff` (`staff_id`),
  CONSTRAINT `fk_staffdoc_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_staff_history`
--

DROP TABLE IF EXISTS `tbl_staff_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_staff_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staffhist_staff` (`staff_id`),
  KEY `idx_staffhist_actor` (`actor_id`),
  CONSTRAINT `fk_staffhist_actor` FOREIGN KEY (`actor_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_staffhist_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_staff_leave_applications`
--

DROP TABLE IF EXISTS `tbl_staff_leave_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_staff_leave_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `absence_filler` int(11) DEFAULT NULL,
  `half_day` tinyint(1) NOT NULL DEFAULT 0,
  `first_half` tinyint(1) NOT NULL DEFAULT 0,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `leave_days` decimal(5,1) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Verified','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `reject_reason` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `year` varchar(20) DEFAULT NULL,
  `leave_year` varchar(50) DEFAULT NULL,
  `leave_year_id` int(11) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leaveapp_staff` (`staff_id`),
  KEY `idx_leaveapp_type` (`leave_type_id`),
  KEY `idx_leaveapp_status` (`status`),
  KEY `idx_leaveapp_dates` (`from_date`,`to_date`),
  KEY `fk_leaveapp_filler` (`absence_filler`),
  KEY `fk_leaveapp_verified` (`verified_by`),
  KEY `fk_leaveapp_approved` (`approved_by`),
  CONSTRAINT `fk_leaveapp_approved` FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_leaveapp_filler` FOREIGN KEY (`absence_filler`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_leaveapp_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_leaveapp_type` FOREIGN KEY (`leave_type_id`) REFERENCES `tbl_office_leave_configs` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_leaveapp_verified` FOREIGN KEY (`verified_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_staff_social_medias`
--

DROP TABLE IF EXISTS `tbl_staff_social_medias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_staff_social_medias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) DEFAULT NULL,
  `title` varchar(191) DEFAULT NULL,
  `media_link` varchar(255) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staffsocial_staff` (`staff_id`),
  CONSTRAINT `fk_staffsocial_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_sub_ledger_particulars`
--

DROP TABLE IF EXISTS `tbl_sub_ledger_particulars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_sub_ledger_particulars` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `ledger_particular_id` bigint(20) NOT NULL,
  `voucher_type` varchar(64) DEFAULT NULL,
  `voucher_type_id` int(11) DEFAULT NULL,
  `particulars_date` date DEFAULT NULL,
  `account_terminal_id` int(11) DEFAULT NULL,
  `sub_ledger_name` varchar(191) DEFAULT NULL,
  `debit` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `credit` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `remarks` varchar(2048) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_slp_particular` (`ledger_particular_id`),
  KEY `idx_slp_terminal` (`account_terminal_id`),
  CONSTRAINT `fk_slp_particular` FOREIGN KEY (`ledger_particular_id`) REFERENCES `tbl_ledger_particulars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_user_profiles`
--

DROP TABLE IF EXISTS `tbl_user_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_user_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `blood_group` varchar(100) DEFAULT NULL,
  `work_experience` varchar(255) DEFAULT NULL,
  `skill` text DEFAULT NULL,
  `hobby_interest` text DEFAULT NULL,
  `award_reward` text DEFAULT NULL,
  `emergency_contact_name` varchar(191) DEFAULT NULL,
  `emergency_contact_mobile` varchar(50) DEFAULT NULL,
  `emergency_contact_relation` varchar(100) DEFAULT NULL,
  `special_training_skill` text DEFAULT NULL,
  `height` decimal(14,4) DEFAULT NULL,
  `weight` decimal(14,4) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_profile_user` (`user_id`),
  CONSTRAINT `fk_userprofile_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_user_registered_devices`
--

DROP TABLE IF EXISTS `tbl_user_registered_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_user_registered_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_name` varchar(191) DEFAULT NULL,
  `device_token` varchar(255) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('Active','Block') DEFAULT 'Active',
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_device_user` (`user_id`),
  CONSTRAINT `fk_device_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_users_login`
--

DROP TABLE IF EXISTS `tbl_users_login`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_users_login` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(191) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `password` char(128) NOT NULL,
  `salt` char(128) NOT NULL,
  `fullname` varchar(191) NOT NULL,
  `permitted_modules` mediumtext DEFAULT NULL,
  `permitted_submodules` mediumtext DEFAULT NULL,
  `special_permission` mediumtext DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `staff_type` enum('Admin','Service') NOT NULL DEFAULT 'Admin',
  `phone1` varchar(50) DEFAULT NULL,
  `phone2` varchar(50) DEFAULT NULL,
  `phone3` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `physically_challenged` enum('Yes','No') NOT NULL DEFAULT 'No',
  `marital_status` enum('Married','Unmarried','Divorced','') NOT NULL DEFAULT '',
  `citizenship` varchar(191) DEFAULT NULL,
  `education` mediumtext DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `designation_id` int(11) DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `pan_num` varchar(50) DEFAULT NULL,
  `bank` varchar(191) DEFAULT NULL,
  `bank_account_num` varchar(191) DEFAULT NULL,
  `bank_account_name` varchar(191) DEFAULT NULL,
  `ssf_number` varchar(100) DEFAULT NULL,
  `pf_number` varchar(100) DEFAULT NULL,
  `cit_number` varchar(100) DEFAULT NULL,
  `checkin` time DEFAULT NULL,
  `checkout` time DEFAULT NULL,
  `daily_working_hour` int(11) DEFAULT NULL,
  `allow_checkin_by_other` enum('Yes','No') DEFAULT 'No',
  `off_day` varchar(100) DEFAULT NULL,
  `status` enum('Active','Block','Terminated') NOT NULL DEFAULT 'Active',
  `image_extension` varchar(50) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`),
  KEY `idx_department` (`department_id`),
  KEY `idx_designation` (`designation_id`),
  KEY `idx_status` (`status`),
  KEY `fk_users_added_by` (`added_by`),
  KEY `fk_users_updated_by` (`updated_by`),
  CONSTRAINT `fk_users_added_by` FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `tbl_office_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users_designation` FOREIGN KEY (`designation_id`) REFERENCES `tbl_office_designation` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_voucher_logs`
--

DROP TABLE IF EXISTS `tbl_voucher_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_voucher_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_type` varchar(64) DEFAULT NULL,
  `voucher_type_id` int(11) DEFAULT NULL,
  `voucher_no` varchar(32) DEFAULT NULL,
  `action` enum('Create','Update','Approve','Reject','Unapprove','Void','Delete') DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `remarks` varchar(2048) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vlog_voucher` (`voucher_type`,`voucher_type_id`),
  KEY `idx_vlog_actor` (`added_by`)
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'sb_tech'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-10 17:58:51
