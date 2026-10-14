-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 14, 2026 at 08:04 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tournaments`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `adminID` varchar(255) NOT NULL,
  `admin_password` varchar(50) NOT NULL,
  `admin_name` varchar(255) NOT NULL,
  `admin_organization` varchar(255) DEFAULT NULL,
  `admin_ic_passport` varchar(50) NOT NULL,
  `admin_gender` varchar(6) NOT NULL,
  `admin_contact` varchar(50) NOT NULL,
  `admin_email` varchar(255) DEFAULT NULL,
  `admin_country` varchar(100) DEFAULT NULL,
  `admin_register` date NOT NULL,
  `admin_status` varchar(7) NOT NULL,
  `admin_active` enum('active','inactive') NOT NULL DEFAULT 'active',
  `admin_role` enum('platform','organizer') NOT NULL DEFAULT 'organizer',
  `admin_profile` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`adminID`, `admin_password`, `admin_name`, `admin_organization`, `admin_ic_passport`, `admin_gender`, `admin_contact`, `admin_email`, `admin_country`, `admin_register`, `admin_status`, `admin_active`, `admin_role`, `admin_profile`) VALUES
('AD0001', 'AD0001', 'ZAHRAH BINTI MD YUSOF', NULL, '721003-01-5188', 'female', '013-2582299', 'zahrah@yahoo.com', 'Malaysia', '2023-05-06', 'offline', 'active', 'platform', 'images/logo.jpg'),
('AD0002', 'Leong02', 'Leong', NULL, 'MR456732', 'male', '01123431243', 'leong02@gmail.com', 'Philippines ', '2026-10-05', 'offline', 'active', 'organizer', NULL),
('AD0003', 'eleton', 'Ele Ton', 'Club', 'MR456732', 'female', '01128797665', 'eleton@gmail.com', 'Korea', '2026-10-06', 'offline', 'active', 'organizer', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `matchID` int(11) NOT NULL,
  `tournamentID` int(11) NOT NULL,
  `category_registered` varchar(255) NOT NULL,
  `round_number` int(11) NOT NULL,
  `round_name` varchar(50) NOT NULL,
  `match_number` int(11) NOT NULL,
  `player1ID` varchar(255) DEFAULT NULL,
  `player2ID` varchar(255) DEFAULT NULL,
  `player1_seed` int(11) DEFAULT NULL,
  `player2_seed` int(11) DEFAULT NULL,
  `player1_score` int(11) DEFAULT NULL,
  `player2_score` int(11) DEFAULT NULL,
  `winnerID` varchar(255) DEFAULT NULL,
  `next_matchID` int(11) DEFAULT NULL,
  `next_match_position` enum('player1','player2') DEFAULT NULL,
  `match_status` enum('Pending','Completed') DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `matches`
--

INSERT INTO `matches` (`matchID`, `tournamentID`, `category_registered`, `round_number`, `round_name`, `match_number`, `player1ID`, `player2ID`, `player1_seed`, `player2_seed`, `player1_score`, `player2_score`, `winnerID`, `next_matchID`, `next_match_position`, `match_status`, `created_at`) VALUES
(29, 2, 'BU13', 1, 'Quarter Final', 1, 'P0009', NULL, 1, NULL, NULL, NULL, 'P0009', 33, 'player1', 'Completed', '2026-09-10 09:23:13'),
(30, 2, 'BU13', 1, 'Quarter Final', 2, 'P0008', 'P0010', 4, 5, NULL, NULL, 'P0008', 33, 'player2', 'Pending', '2026-09-10 09:23:13'),
(31, 2, 'BU13', 1, 'Quarter Final', 3, 'P0002', NULL, 2, NULL, NULL, NULL, 'P0002', 34, 'player1', 'Completed', '2026-09-10 09:23:13'),
(32, 2, 'BU13', 1, 'Quarter Final', 4, 'P0001', NULL, 3, NULL, NULL, NULL, 'P0001', 34, 'player2', 'Completed', '2026-09-10 09:23:13'),
(33, 2, 'BU13', 2, 'Semi Final', 1, 'P0009', NULL, 1, NULL, NULL, NULL, NULL, 35, 'player1', 'Pending', '2026-09-10 09:23:13'),
(34, 2, 'BU13', 2, 'Semi Final', 2, 'P0002', 'P0001', 2, 3, NULL, NULL, NULL, 35, 'player2', 'Pending', '2026-09-10 09:23:13'),
(35, 2, 'BU13', 3, 'Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', '2026-09-10 09:23:13'),
(37, 42, 'BU13', 1, 'Round of 16', 1, 'P0009', NULL, 1, NULL, NULL, NULL, 'P0009', 45, 'player1', 'Completed', '2026-09-14 12:18:30'),
(38, 42, 'BU13', 1, 'Round of 16', 2, 'P0008', 'P0005', 8, 9, NULL, NULL, NULL, 45, 'player2', 'Pending', '2026-09-14 12:18:30'),
(39, 42, 'BU13', 1, 'Round of 16', 3, 'P0003', NULL, 4, NULL, NULL, NULL, 'P0003', 46, 'player1', 'Completed', '2026-09-14 12:18:30'),
(40, 42, 'BU13', 1, 'Round of 16', 4, 'P0007', NULL, 5, NULL, NULL, NULL, 'P0007', 46, 'player2', 'Completed', '2026-09-14 12:18:30'),
(41, 42, 'BU13', 1, 'Round of 16', 5, 'P0002', NULL, 2, NULL, NULL, NULL, 'P0002', 47, 'player1', 'Completed', '2026-09-14 12:18:30'),
(42, 42, 'BU13', 1, 'Round of 16', 6, 'P0004', NULL, 7, NULL, NULL, NULL, 'P0004', 47, 'player2', 'Completed', '2026-09-14 12:18:30'),
(43, 42, 'BU13', 1, 'Round of 16', 7, 'P0001', NULL, 3, NULL, NULL, NULL, 'P0001', 48, 'player1', 'Completed', '2026-09-14 12:18:30'),
(44, 42, 'BU13', 1, 'Round of 16', 8, 'P0006', NULL, 6, NULL, NULL, NULL, 'P0006', 48, 'player2', 'Completed', '2026-09-14 12:18:30'),
(45, 42, 'BU13', 2, 'Quarter Final', 1, 'P0009', NULL, 1, NULL, NULL, NULL, NULL, 49, 'player1', 'Pending', '2026-09-14 12:18:30'),
(46, 42, 'BU13', 2, 'Quarter Final', 2, 'P0003', 'P0007', 4, 5, NULL, NULL, NULL, 49, 'player2', 'Pending', '2026-09-14 12:18:30'),
(47, 42, 'BU13', 2, 'Quarter Final', 3, 'P0002', 'P0004', 2, 7, NULL, NULL, NULL, 50, 'player1', 'Pending', '2026-09-14 12:18:30'),
(48, 42, 'BU13', 2, 'Quarter Final', 4, 'P0001', 'P0006', 3, 6, NULL, NULL, NULL, 50, 'player2', 'Pending', '2026-09-14 12:18:30'),
(49, 42, 'BU13', 3, 'Semi Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 51, 'player1', 'Pending', '2026-09-14 12:18:30'),
(50, 42, 'BU13', 3, 'Semi Final', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 51, 'player2', 'Pending', '2026-09-14 12:18:30'),
(51, 42, 'BU13', 4, 'Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', '2026-09-14 12:18:30'),
(216, 50, 'BU15', 4, 'Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', '2026-10-02 10:50:41'),
(217, 50, 'BU15', 3, 'Semi Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 216, 'player1', 'Pending', '2026-10-02 10:50:41'),
(218, 50, 'BU15', 3, 'Semi Final', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 216, 'player2', 'Pending', '2026-10-02 10:50:41'),
(219, 50, 'BU15', 2, 'Quarter Final', 1, 'P0005', 'P0009', 1, 7, NULL, NULL, NULL, 217, 'player1', 'Pending', '2026-10-02 10:50:41'),
(220, 50, 'BU15', 2, 'Quarter Final', 2, NULL, 'P0002', NULL, 3, NULL, NULL, NULL, 217, 'player2', 'Pending', '2026-10-02 10:50:41'),
(221, 50, 'BU15', 2, 'Quarter Final', 3, 'P0014', 'P0004', 4, 5, NULL, NULL, NULL, 218, 'player1', 'Pending', '2026-10-02 10:50:41'),
(222, 50, 'BU15', 2, 'Quarter Final', 4, 'P0008', 'P0001', 6, 2, NULL, NULL, NULL, 218, 'player2', 'Pending', '2026-10-02 10:50:41'),
(223, 50, 'BU15', 1, 'Round of 16', 1, 'P0005', NULL, 1, NULL, NULL, NULL, 'P0005', 219, 'player1', 'Completed', '2026-10-02 10:50:41'),
(224, 50, 'BU15', 1, 'Round of 16', 2, NULL, 'P0009', NULL, 7, NULL, NULL, 'P0009', 219, 'player2', 'Completed', '2026-10-02 10:50:41'),
(225, 50, 'BU15', 1, 'Round of 16', 3, 'P0010', 'P0013', 8, 9, NULL, NULL, NULL, 220, 'player1', 'Pending', '2026-10-02 10:50:41'),
(226, 50, 'BU15', 1, 'Round of 16', 4, NULL, 'P0002', NULL, 3, NULL, NULL, 'P0002', 220, 'player2', 'Completed', '2026-10-02 10:50:41'),
(227, 50, 'BU15', 1, 'Round of 16', 5, 'P0014', NULL, 4, NULL, NULL, NULL, 'P0014', 221, 'player1', 'Completed', '2026-10-02 10:50:41'),
(228, 50, 'BU15', 1, 'Round of 16', 6, NULL, 'P0004', NULL, 5, NULL, NULL, 'P0004', 221, 'player2', 'Completed', '2026-10-02 10:50:41'),
(229, 50, 'BU15', 1, 'Round of 16', 7, 'P0008', NULL, 6, NULL, NULL, NULL, 'P0008', 222, 'player1', 'Completed', '2026-10-02 10:50:41'),
(230, 50, 'BU15', 1, 'Round of 16', 8, NULL, 'P0001', NULL, 2, NULL, NULL, 'P0001', 222, 'player2', 'Completed', '2026-10-02 10:50:41'),
(336, 57, 'Women', 4, 'Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', '2026-10-09 12:04:16'),
(337, 57, 'Women', 3, 'Semi Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 336, 'player1', 'Pending', '2026-10-09 12:04:16'),
(338, 57, 'Women', 3, 'Semi Final', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 336, 'player2', 'Pending', '2026-10-09 12:04:16'),
(339, 57, 'Women', 2, 'Quarter Final', 1, 'P0028', NULL, 1, NULL, NULL, NULL, NULL, 337, 'player1', 'Pending', '2026-10-09 12:04:16'),
(340, 57, 'Women', 2, 'Quarter Final', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 337, 'player2', 'Pending', '2026-10-09 12:04:16'),
(341, 57, 'Women', 2, 'Quarter Final', 3, 'P0027', NULL, 3, NULL, NULL, NULL, NULL, 338, 'player1', 'Pending', '2026-10-09 12:04:16'),
(342, 57, 'Women', 2, 'Quarter Final', 4, NULL, 'P0026', NULL, 2, NULL, NULL, NULL, 338, 'player2', 'Pending', '2026-10-09 12:04:16'),
(343, 57, 'Women', 1, 'Round of 16', 1, 'P0028', NULL, 1, NULL, NULL, NULL, 'P0028', 339, 'player1', 'Completed', '2026-10-09 12:04:16'),
(344, 57, 'Women', 1, 'Round of 16', 2, 'P0033', 'P0007', 9, 8, NULL, NULL, NULL, 339, 'player2', 'Pending', '2026-10-09 12:04:16'),
(345, 57, 'Women', 1, 'Round of 16', 3, 'P0030', 'P0011', 5, 12, NULL, NULL, NULL, 340, 'player1', 'Pending', '2026-10-09 12:04:16'),
(346, 57, 'Women', 1, 'Round of 16', 4, 'P0035', 'P0032', 13, 4, NULL, NULL, NULL, 340, 'player2', 'Pending', '2026-10-09 12:04:16'),
(347, 57, 'Women', 1, 'Round of 16', 5, 'P0027', NULL, 3, NULL, NULL, NULL, 'P0027', 341, 'player1', 'Completed', '2026-10-09 12:04:16'),
(348, 57, 'Women', 1, 'Round of 16', 6, 'P0031', 'P0034', 11, 6, NULL, NULL, NULL, 341, 'player2', 'Pending', '2026-10-09 12:04:16'),
(349, 57, 'Women', 1, 'Round of 16', 7, 'P0029', 'P0006', 7, 10, NULL, NULL, NULL, 342, 'player1', 'Pending', '2026-10-09 12:04:16'),
(350, 57, 'Women', 1, 'Round of 16', 8, NULL, 'P0026', NULL, 2, NULL, NULL, 'P0026', 342, 'player2', 'Completed', '2026-10-09 12:04:16'),
(351, 57, 'Men', 5, 'Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', '2026-10-09 12:05:25'),
(352, 57, 'Men', 4, 'Semi Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 351, 'player1', 'Pending', '2026-10-09 12:05:25'),
(353, 57, 'Men', 4, 'Semi Final', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 351, 'player2', 'Pending', '2026-10-09 12:05:25'),
(354, 57, 'Men', 3, 'Quarter Final', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 352, 'player1', 'Pending', '2026-10-09 12:05:25'),
(355, 57, 'Men', 3, 'Quarter Final', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 352, 'player2', 'Pending', '2026-10-09 12:05:25'),
(356, 57, 'Men', 3, 'Quarter Final', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 353, 'player1', 'Pending', '2026-10-09 12:05:25'),
(357, 57, 'Men', 3, 'Quarter Final', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 353, 'player2', 'Pending', '2026-10-09 12:05:25'),
(358, 57, 'Men', 2, 'Round of 16', 1, 'P0022', NULL, 1, NULL, NULL, NULL, NULL, 354, 'player1', 'Pending', '2026-10-09 12:05:25'),
(359, 57, 'Men', 2, 'Round of 16', 2, 'P0018', 'P0003', 9, 8, NULL, NULL, NULL, 354, 'player2', 'Pending', '2026-10-09 12:05:25'),
(360, 57, 'Men', 2, 'Round of 16', 3, 'P0016', 'P0023', 5, 12, NULL, NULL, NULL, 355, 'player1', 'Pending', '2026-10-09 12:05:25'),
(361, 57, 'Men', 2, 'Round of 16', 4, 'P0004', 'P0019', 13, 4, NULL, NULL, NULL, 355, 'player2', 'Pending', '2026-10-09 12:05:25'),
(362, 57, 'Men', 2, 'Round of 16', 5, 'P0001', NULL, 3, NULL, NULL, NULL, NULL, 356, 'player1', 'Pending', '2026-10-09 12:05:25'),
(363, 57, 'Men', 2, 'Round of 16', 6, 'P0014', 'P0017', 11, 6, NULL, NULL, NULL, 356, 'player2', 'Pending', '2026-10-09 12:05:25'),
(364, 57, 'Men', 2, 'Round of 16', 7, 'P0002', 'P0020', 7, 10, NULL, NULL, NULL, 357, 'player1', 'Pending', '2026-10-09 12:05:25'),
(365, 57, 'Men', 2, 'Round of 16', 8, NULL, 'P0005', NULL, 2, NULL, NULL, NULL, 357, 'player2', 'Pending', '2026-10-09 12:05:25'),
(366, 57, 'Men', 1, 'Round of 32', 1, 'P0022', NULL, 1, NULL, NULL, NULL, 'P0022', 358, 'player1', 'Completed', '2026-10-09 12:05:25'),
(367, 57, 'Men', 1, 'Round of 32', 2, 'P0013', 'P0025', 17, 16, NULL, NULL, NULL, 358, 'player2', 'Pending', '2026-10-09 12:05:25'),
(368, 57, 'Men', 1, 'Round of 32', 3, 'P0018', NULL, 9, NULL, NULL, NULL, 'P0018', 359, 'player1', 'Completed', '2026-10-09 12:05:25'),
(369, 57, 'Men', 1, 'Round of 32', 4, NULL, 'P0003', NULL, 8, NULL, NULL, 'P0003', 359, 'player2', 'Completed', '2026-10-09 12:05:25'),
(370, 57, 'Men', 1, 'Round of 32', 5, 'P0016', NULL, 5, NULL, NULL, NULL, 'P0016', 360, 'player1', 'Completed', '2026-10-09 12:05:25'),
(371, 57, 'Men', 1, 'Round of 32', 6, NULL, 'P0023', NULL, 12, NULL, NULL, 'P0023', 360, 'player2', 'Completed', '2026-10-09 12:05:25'),
(372, 57, 'Men', 1, 'Round of 32', 7, 'P0004', NULL, 13, NULL, NULL, NULL, 'P0004', 361, 'player1', 'Completed', '2026-10-09 12:05:25'),
(373, 57, 'Men', 1, 'Round of 32', 8, NULL, 'P0019', NULL, 4, NULL, NULL, 'P0019', 361, 'player2', 'Completed', '2026-10-09 12:05:25'),
(374, 57, 'Men', 1, 'Round of 32', 9, 'P0001', NULL, 3, NULL, NULL, NULL, 'P0001', 362, 'player1', 'Completed', '2026-10-09 12:05:25'),
(375, 57, 'Men', 1, 'Round of 32', 10, 'P0024', 'P0009', 19, 14, NULL, NULL, NULL, 362, 'player2', 'Pending', '2026-10-09 12:05:25'),
(376, 57, 'Men', 1, 'Round of 32', 11, 'P0014', NULL, 11, NULL, NULL, NULL, 'P0014', 363, 'player1', 'Completed', '2026-10-09 12:05:25'),
(377, 57, 'Men', 1, 'Round of 32', 12, NULL, 'P0017', NULL, 6, NULL, NULL, 'P0017', 363, 'player2', 'Completed', '2026-10-09 12:05:25'),
(378, 57, 'Men', 1, 'Round of 32', 13, 'P0002', NULL, 7, NULL, NULL, NULL, 'P0002', 364, 'player1', 'Completed', '2026-10-09 12:05:25'),
(379, 57, 'Men', 1, 'Round of 32', 14, NULL, 'P0020', NULL, 10, NULL, NULL, 'P0020', 364, 'player2', 'Completed', '2026-10-09 12:05:25'),
(380, 57, 'Men', 1, 'Round of 32', 15, 'P0021', 'P0010', 15, 18, NULL, NULL, NULL, 365, 'player1', 'Pending', '2026-10-09 12:05:25'),
(381, 57, 'Men', 1, 'Round of 32', 16, NULL, 'P0005', NULL, 2, NULL, NULL, 'P0005', 365, 'player2', 'Completed', '2026-10-09 12:05:25');

-- --------------------------------------------------------

--
-- Table structure for table `organizer_subscriptions`
--

CREATE TABLE `organizer_subscriptions` (
  `subscriptionID` int(11) NOT NULL,
  `adminID` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `gateway` varchar(20) DEFAULT NULL,
  `gateway_reference` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paid annual subscriptions of Tournament Organizers';

--
-- Dumping data for table `organizer_subscriptions`
--

INSERT INTO `organizer_subscriptions` (`subscriptionID`, `adminID`, `amount`, `currency`, `start_date`, `end_date`, `gateway`, `gateway_reference`, `paid_at`) VALUES
(1, 'AD0002', 1000.00, 'MYR', '2026-10-07', '2027-10-06', 'card (test)', 'Visa **** 4242 / TEST-20261007122025', '2026-10-07 12:20:25');

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('subscription_annual_amount', '1000.00', '2026-10-07 12:07:05'),
('subscription_currency', 'MYR', '2026-10-07 12:07:05'),
('subscription_per_tournament_amount', '150.00', '2026-10-07 12:07:05');

-- --------------------------------------------------------

--
-- Table structure for table `players`
--

CREATE TABLE `players` (
  `playerID` varchar(255) NOT NULL COMMENT 'Player ID',
  `player_email` varchar(255) DEFAULT NULL COMMENT 'Email',
  `player_password` varchar(50) NOT NULL COMMENT 'Player Password',
  `player_full_name` varchar(255) NOT NULL COMMENT 'Full Name',
  `player_first_name` varchar(255) NOT NULL COMMENT 'First Name',
  `player_last_name` varchar(255) NOT NULL COMMENT 'Last Name',
  `player_nationalid` varchar(255) DEFAULT NULL COMMENT 'IC',
  `player_passport` varchar(255) NOT NULL COMMENT 'Passport',
  `player_dob` date NOT NULL COMMENT 'Date of Birth',
  `player_gender` varchar(10) NOT NULL COMMENT 'Gender',
  `player_nationality` varchar(255) NOT NULL COMMENT 'Nationality',
  `player_contact` varchar(50) NOT NULL COMMENT 'Contact Number',
  `player_register` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Registration Date',
  `asf_member_no` varchar(255) DEFAULT NULL COMMENT 'ASF Membership No',
  `spin_number` varchar(255) DEFAULT NULL COMMENT 'WSF Spin No',
  `national_ranking` varchar(255) DEFAULT NULL COMMENT 'National Ranking',
  `ajss_ranking` varchar(255) NOT NULL COMMENT 'AJSS Ranking',
  `world_ranking` varchar(255) NOT NULL COMMENT 'World Ranking',
  `player_status` varchar(7) NOT NULL COMMENT 'Status',
  `player_active` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Active Status',
  `player_profile` varchar(255) DEFAULT NULL COMMENT 'Profile Picture'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `players`
--

INSERT INTO `players` (`playerID`, `player_email`, `player_password`, `player_full_name`, `player_first_name`, `player_last_name`, `player_nationalid`, `player_passport`, `player_dob`, `player_gender`, `player_nationality`, `player_contact`, `player_register`, `asf_member_no`, `spin_number`, `national_ranking`, `ajss_ranking`, `world_ranking`, `player_status`, `player_active`, `player_profile`) VALUES
('P0001', 'loopthiam86@gmail.com', 'P0001', 'Aleisters Loo', 'Aleisters', 'Loo', '', 'RA4832677', '2013-11-23', 'Male', 'MALAYSIAN', '', '2026-09-08 16:00:00', '', '8849 6643', '2', '3', '', 'online', 'active', NULL),
('P0002', 'aimeileong@hotmail.com', 'P0002', 'Benjamin Boardman', 'Benjamin', 'Boardman', NULL, 'RA4990228', '2013-03-21', 'Male', 'AUSTRALIAN', '', '2026-09-08 16:00:00', NULL, '76556522', '5', '2', '', 'online', 'active', NULL),
('P0003', 'scotth@gcie.com.au', 'P0003', 'Connor Haberecht', 'Connor', 'Haberecht', NULL, 'RA4756794', '2009-01-19', 'Male', 'AUSTRALIAN', '', '2026-09-08 16:00:00', NULL, '97091952', '5', '', '', 'online', 'active', NULL),
('P0004', 'ameliapittock@gmail.com', 'Jackson', 'Jackson Martin', 'Jackson', 'Martin', '', '', '2011-07-13', 'Male', 'AUSTRALIAN', '0173728438', '2026-09-08 16:00:00', '', '43686664', '13', '', '', 'Active', 'active', NULL),
('P0005', 'quintencurry@yahoo.com', 'Jacob', 'Jacob Curry', 'Jacob', 'Curry', '', '', '2010-02-26', 'Male', 'MALAYSIAN', '0173728438', '2026-09-08 16:00:00', '', '78832969', '1', '', '', 'Active', 'active', NULL),
('P0006', 'josie.jones07@outlook.com', 'Josie', 'Josie Jones', 'Josie', 'Jones', '', 'RA8144292', '2007-01-10', 'Female', 'MALAYSIAN', '0173728438', '2026-09-08 16:00:00', '', '95240314', '9', '', '', 'Active', 'active', NULL),
('P0007', 'menusha.squash@gmail.com', 'Kayla', 'Kayla Goonewardena', 'Kayla', 'Goonewardena', '', '', '2015-09-20', 'Female', 'AUSTRALIAN', '08765432', '2026-09-08 16:00:00', '', '69634149', '6', '', '', 'Active', 'active', NULL),
('P0008', 'shenfang96@qq.com', 'zhe', 'ZHE(Amos) GUAN', 'ZHE(Amos)', 'GUAN', '', 'EK6472402', '2013-04-22', 'Male', 'CANADIAN', '0173728438', '2026-09-08 16:00:00', '', '40089029', '22', '', '', 'online', 'active', NULL),
('P0009', 'ronaduan528@163.com', 'enze', 'Enze Guo', 'Enze', 'Guo', '', '', '2014-07-07', 'Male', 'CHINESE', '0173728438', '2026-09-08 16:00:00', '', '22142878', '', '1', '', 'online', 'active', NULL),
('P0010', 'ronaduan5289@163.com', 'enxi', 'Enxi Guo', 'Enxi', 'Guo', '', '', '2014-07-07', 'Male', 'CHINESE', '098873', '2026-09-08 16:00:00', '', '22142878', '', '', '', 'online', 'active', NULL),
('P0011', 'testing2@gmail.com', 'testing', 'Testing Phyo', 'Testing', 'Phyo', NULL, '', '2008-02-14', 'Female', 'MALAYSIAN', '', '0000-00-00 00:00:00', '', '', NULL, '', '', '', 'active', NULL),
('P0012', 'test3@gmail.com', 'testing', 'Testing Three', 'Testing', 'Three', NULL, '', '2003-03-14', '', 'INDIAN', '', '2026-09-11 06:31:38', NULL, NULL, NULL, '', '', '', 'active', NULL),
('P0013', 'loopthiam@gmail.com', 'P0013', 'Alex Mars', 'Alex', 'Mars', '', '', '2013-11-22', 'Male', 'UNITED KINGDOM', '', '0000-00-00 00:00:00', '', '', '', '', '', 'online', 'active', NULL),
('P0014', 'daniel.lim.test@example.com', 'P0014', 'Daniel Lim', 'Daniel', 'Lim', NULL, '', '2011-03-15', 'Male', 'MALAYSIAN', '', '2026-09-30 02:36:10', '', '', '8', '', '', 'Active', 'active', NULL),
('P0015', 'elethon@gmail.com', 'elethon', 'Ele Thon', 'Ele', 'Thon', NULL, '', '2005-02-12', '', 'KOREAN', '', '2026-10-06 06:57:11', NULL, NULL, NULL, '', '', '', 'active', NULL),
('P0016', 'aiman.hakim@example.com', 'P0016', 'Aiman Hakim', 'Aiman', 'Hakim', '', 'RA5100016', '2011-04-12', 'Male', 'MALAYSIAN', '0121000016', '2026-10-09 04:00:33', '', '80000016', '3', '4', '', 'offline', 'active', NULL),
('P0017', 'ethan.tan@example.com', 'P0017', 'Ethan Tan', 'Ethan', 'Tan', '', 'RA5100017', '2012-08-30', 'Male', 'MALAYSIAN', '0121000017', '2026-10-09 04:00:33', '', '80000017', '4', '6', '', 'offline', 'active', NULL),
('P0018', 'harith.iskandar@example.com', 'P0018', 'Harith Iskandar', 'Harith', 'Iskandar', '', 'RA5100018', '2010-01-25', 'Male', 'MALAYSIAN', '0121000018', '2026-10-09 04:00:33', '', '80000018', '6', '', '', 'offline', 'active', NULL),
('P0019', 'ryan.lee@example.com', 'P0019', 'Ryan Lee', 'Ryan', 'Lee', '', 'RA5100019', '2013-06-05', 'Male', 'SINGAPOREAN', '6591000019', '2026-10-09 04:00:33', '', '80000019', '2', '5', '', 'offline', 'active', NULL),
('P0020', 'arjun.nair@example.com', 'P0020', 'Arjun Nair', 'Arjun', 'Nair', '', 'RA5100020', '2009-11-17', 'Male', 'INDIAN', '9191000020', '2026-10-09 04:00:33', '', '80000020', '7', '8', '', 'offline', 'active', NULL),
('P0021', 'kenji.sato@example.com', 'P0021', 'Kenji Sato', 'Kenji', 'Sato', '', 'RA5100021', '2011-02-03', 'Male', 'JAPANESE', '8191000021', '2026-10-09 04:00:33', '', '80000021', '', '7', '', 'offline', 'active', NULL),
('P0022', 'miguel.santos@example.com', 'P0022', 'Miguel Santos', 'Miguel', 'Santos', '', 'RA5100022', '2008-09-14', 'Male', 'FILIPINO', '6391000022', '2026-10-09 04:00:33', '', '80000022', '1', '10', '', 'offline', 'active', NULL),
('P0023', 'danish.azman@example.com', 'P0023', 'Danish Azman', 'Danish', 'Azman', '', 'RA5100023', '2015-03-21', 'Male', 'MALAYSIAN', '0121000023', '2026-10-09 04:00:33', '', '80000023', '9', '', '', 'offline', 'active', NULL),
('P0024', 'wei.jie.ong@example.com', 'P0024', 'Wei Jie Ong', 'Wei Jie', 'Ong', '', 'RA5100024', '2014-12-09', 'Male', 'MALAYSIAN', '0121000024', '2026-10-09 04:00:33', '', '80000024', '', '', '', 'offline', 'active', NULL),
('P0025', 'putra.wijaya@example.com', 'P0025', 'Putra Wijaya', 'Putra', 'Wijaya', '', 'RA5100025', '2012-07-19', 'Male', 'INDONESIAN', '6281000025', '2026-10-09 04:00:33', '', '80000025', '', '9', '', 'offline', 'active', NULL),
('P0026', 'aisyah.rahman@example.com', 'P0026', 'Aisyah Rahman', 'Aisyah', 'Rahman', '', 'RA5100026', '2011-05-08', 'Female', 'MALAYSIAN', '0121000026', '2026-10-09 04:00:33', '', '80000026', '1', '2', '', 'offline', 'active', NULL),
('P0027', 'chloe.lim@example.com', 'P0027', 'Chloe Lim', 'Chloe', 'Lim', '', 'RA5100027', '2012-10-22', 'Female', 'MALAYSIAN', '0121000027', '2026-10-09 04:00:33', '', '80000027', '2', '3', '', 'offline', 'active', NULL),
('P0028', 'sofia.chen@example.com', 'P0028', 'Sofia Chen', 'Sofia', 'Chen', '', 'RA5100028', '2010-03-15', 'Female', 'SINGAPOREAN', '6591000028', '2026-10-09 04:00:33', '', '80000028', '1', '1', '', 'offline', 'active', NULL),
('P0029', 'nurul.izzah@example.com', 'P0029', 'Nurul Izzah', 'Nurul', 'Izzah', '', 'RA5100029', '2013-01-30', 'Female', 'MALAYSIAN', '0121000029', '2026-10-09 04:00:33', '', '80000029', '5', '', '', 'offline', 'active', NULL),
('P0030', 'priya.menon@example.com', 'P0030', 'Priya Menon', 'Priya', 'Menon', '', 'RA5100030', '2009-08-11', 'Female', 'INDIAN', '9191000030', '2026-10-09 04:00:33', '', '80000030', '3', '6', '', 'offline', 'active', NULL),
('P0031', 'hana.kim@example.com', 'P0031', 'Hana Kim', 'Hana', 'Kim', '', 'RA5100031', '2014-04-27', 'Female', 'KOREAN', '8291000031', '2026-10-09 04:00:33', '', '80000031', '', '5', '', 'offline', 'active', NULL),
('P0032', 'ploy.srisuk@example.com', 'P0032', 'Ploy Srisuk', 'Ploy', 'Srisuk', '', 'RA5100032', '2011-12-02', 'Female', 'THAI', '6691000032', '2026-10-09 04:00:33', '', '80000032', '2', '7', '', 'offline', 'active', NULL),
('P0033', 'emily.wong@example.com', 'P0033', 'Emily Wong', 'Emily', 'Wong', '', 'RA5100033', '2015-09-18', 'Female', 'MALAYSIAN', '0121000033', '2026-10-09 04:00:33', '', '80000033', '6', '', '', 'offline', 'active', NULL),
('P0034', 'zara.ismail@example.com', 'P0034', 'Zara Ismail', 'Zara', 'Ismail', '', 'RA5100034', '2008-06-06', 'Female', 'MALAYSIAN', '0121000034', '2026-10-09 04:00:33', '', '80000034', '4', '8', '', 'offline', 'active', NULL),
('P0035', 'li.na.zhang@example.com', 'P0035', 'Li Na Zhang', 'Li Na', 'Zhang', '', 'RA5100035', '2012-02-14', 'Female', 'CHINESE', '8691000035', '2026-10-09 04:00:33', '', '80000035', '', '', '', 'offline', 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tournament`
--

CREATE TABLE `tournament` (
  `tournamentID` int(11) NOT NULL,
  `tournament_startdate` datetime NOT NULL,
  `tournament_enddate` datetime NOT NULL,
  `tournament_name` varchar(255) NOT NULL,
  `tournament_description` mediumtext NOT NULL,
  `tournament_detail_link` varchar(255) NOT NULL,
  `tournament_location` varchar(255) NOT NULL,
  `tournament_country` varchar(100) DEFAULT NULL,
  `tournament_fee` varchar(255) NOT NULL,
  `tournament_deadline` datetime NOT NULL,
  `tournament_age_cutoff` date DEFAULT NULL,
  `tournament_type` varchar(50) NOT NULL,
  `tournament_tshirt_size` varchar(255) DEFAULT NULL,
  `tournament_picture` varchar(255) DEFAULT NULL,
  `registration_field` varchar(10000) NOT NULL,
  `creatorID` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tournament`
--

INSERT INTO `tournament` (`tournamentID`, `tournament_startdate`, `tournament_enddate`, `tournament_name`, `tournament_description`, `tournament_detail_link`, `tournament_location`, `tournament_country`, `tournament_fee`, `tournament_deadline`, `tournament_age_cutoff`, `tournament_type`, `tournament_tshirt_size`, `tournament_picture`, `registration_field`, `creatorID`) VALUES
(2, '2024-12-24 09:00:00', '2024-12-29 21:00:00', 'HKFC EVISU International Junior Squash Open 2024', '-', 'https://www.asiansquash.org/resources/docs/HKFC%20EVISU%20Int\'%20Jr%20Squash%20Open%202024_Tournament%20Information%20for%20Overseas%20Entries.pdf', 'Hong Kong, China', NULL, 'USD$80', '2024-11-18 12:00:00', '2024-12-29', 'International', 'KID-L (HALF CHEST 40.5CM);KID-XL (HALF CHEST 43CM);XS (HALF CHEST 44CM);S (HALF CHEST 47CM);M (HALF CHEST 50CM);L (HALF CHEST 53CM)', '', 'player_first_name;player_last_name;player_passport;player_dob;asf_member_no;national_ranking;ajss_ranking;category_registered;player_chinese_name;player_tshirt;player_latest_results;player_attachments', 'AKL0002'),
(3, '2025-01-16 00:00:00', '2025-01-19 23:55:00', 'NICOL DAVID JUNIOR OPEN 2025', '-', 'https://docs.google.com/document/d/1R9ZCZLRT7ZOHqNz8NsYREQUaNOdcDnWe/edit', 'MBPJ Astaka Sports Complex, Petaling Jaya, Malaysia', NULL, 'RM100.00', '2024-12-16 12:00:00', '2025-01-01', 'Local', 'XX-SMALL (32);X-SMALL (34);SMALL (36);MEDIUM (38);LARGE (40);X-LARGE (42);XX-LARGE (44)', '../images/tournament/unnamed.png', 'player_full_name;player_ic;player_passport;ajss_ranking;category_registered;player_tshirt;player_attachments', 'AKL0002'),
(4, '2025-02-05 08:00:00', '2025-02-09 21:00:00', 'CIMB RISING STAR NATIONAL JUNIOR CIRCUITS (RSNJC) 1st LEG 2025', 'PLEASE REFER to Tournament Info.\r\n** All Players MUST register to RANKED IN as well : https://rankedin.com/en/tournament/48352/cimb-rising-star-national-junior-circuit-1st-leg-2025 \r\n\r\nEntry fees for this competition as below:\r\na) RM 100.00 per person (local)\r\nb) USD 100 per person (foreign); \r\n\r\nAll payment must be settled by or before the closing date (21 January 2025).', 'https://drive.google.com/file/d/1eH12ZzwvwV93CEDCytvGKg5HKUF41sMH/view?usp=sharing', 'S2 Sports Complex, Sports Complex Tan Sri Dato Hj Mohd Said, Seremban, 70300, Malaysia', NULL, 'RM 100.00 Payment should be made to SRAFTKL CIMB 8000928820', '2025-01-28 00:00:00', '2025-01-01', 'National', 'XS;S;M;L;XL', '../images/tournament/CIMB-Logo.png', 'player_full_name;player_ic;player_passport;ajss_ranking;category_registered;player_tshirt;player_attachments;rankedin_id', 'AKL0002'),
(5, '2025-02-20 09:00:00', '2025-02-23 17:00:00', 'WCE SRAM NATIONAL JUNIOR CHAMPIONSHIPS 2025', 'WCE SRAM NATIONAL JUNIOR CHAMPIONSHIPS 2025 is open for MALAYSIAN players.\r\n*Players are required to register in RANKEDIN : \r\nhttps://rankedin.com/en/tournament/48584/wce-national-junior-championships-2025/info\r\n\r\nSRAFTKL will close this registration on 6th February 2025, 4pm', 'INFO: https://drive.google.com/file/d/13h5S0ZO-t_Jes7B7JIGdcXqch0sCSqgr/view?usp=sharing', 'Kompleks Astaka, Petaling Jaya & Jalan Duta Squash Centre', NULL, 'RM 100.00 Payment should be made to SRAFTKL CIMB 8000928820', '2025-02-09 17:00:00', '2025-02-23', 'NATIONAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/west-coast-expressway-sdn-bhd-logo-7AF7F30C0A-seeklogo.com.png', 'player_full_name;player_ic;player_passport;ajss_ranking;category_registered;player_tshirt;player_attachments;rankedin_id', 'Akl0004'),
(6, '2025-04-09 08:00:00', '2025-04-13 17:00:00', 'CIMB NATIONAL JUNIOR CIRCUITS (NJC) 1st LEG 2025', 'CIMB NJC LEG 1 - Is Open for all U15, U17, U19 players only.\r\nAll Players are required to register in RANKEDIN as well.\r\nSRAFT Registration will close 3 days before SRAM DEADLINE--- 13th March 2025', 'https://drive.google.com/file/d/1Q_XaqDt4d1qIgW7UGHg8jsY-POhadlCr/view?usp=sharing', 'Nicol David International Squash Centre, 36, Lebuhraya Gelugor, 11600 Jelutong, Penang', NULL, 'Local: RM100.00 Foreign: USD100.00', '2025-03-16 12:00:00', '2025-01-01', 'National', '32;34;36;38;40;42;44;46', '../images/tournament/CIMB-Logo.png', 'player_full_name;player_ic;player_passport;ajss_ranking;category_registered;player_tshirt;rankedin_id;accommodation;player_attachments', 'AKL0002'),
(7, '2025-07-15 08:00:00', '2025-07-20 17:00:00', '8th Borneo Junior Open 2025', '8Th Borneo Junior Open 2025 is open to all U11,U13, U15, U17 and U19 players.\nPlayers are required to Register in RANKEDIN as follows:\nhttps://rankedin.com/en/tournament/49795/8th-borneo-junior-open-2025/info\n\nSRAFT Registration will close 3 days before BORNEO DEADLINE--- 23rd May 2025', 'https://www.asiansquash.org/resources/docs/8th%20Borneo%20Junior%20Open%202025%20Handbook%20updated.pdf', 'Sarawak Squash Centre, Jalan Diplomatik off Jalan Stadium, Petra Jaya, Kuching, Sarawak, East Malaysia', NULL, 'Local: RM120.00, Foreign: USD 60.00', '2025-05-23 12:00:00', '2025-07-20', 'International', '32;34;36;38;40;42;44;46', '../images/tournament/images.png', 'player_first_name;player_last_name;player_dob;player_nationality;rankedin;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'AKL0002'),
(8, '2025-05-14 09:00:00', '2025-05-18 17:00:00', 'CIMB RISING STAR NATIONAL JUNIOR CIRCUITS (RSNJC) 2nd LEG 2025-PERLIS', 'CIMB RSNJC LEG 2 - Is Open for all U9, U11, U13 players only.\r\nAll Players are required to register in RANKEDIN at following : https://rankedin.com/en/tournament/50454/cimb-rising-star-national-junior-circuit-2nd-leg-2025-perlis/info\r\nSRAFT Registration will close 3 days before SRAM DEADLINE', 'https://drive.google.com/file/d/1q0IVq8_EkPRRPNeuNswxD6n2_UCv1i-I/view?usp=sharing', 'Gelanggang Skuasy Kompleks Sukan Tuanku Syed Putra, PERLIS', NULL, 'RM 100.00 per person (local)  & USD 100 per person (foreign); must be paid to  SRAFTKL CIMB 8000928820', '2025-04-13 12:33:00', '2025-01-01', 'LOCAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/CIMB-Logo.png', 'player_full_name;player_ic;player_passport;ajss_ranking;category_registered;player_tshirt;rankedin_id;accommodation;player_attachments', 'Akl0004'),
(9, '2025-07-08 08:00:00', '2025-07-13 17:00:00', 'PBA 21ST PENANG (MALAYSIAN) JUNIOR OPEN 2025', 'PBA 21ST PENANG (MALAYSIAN) JUNIOR OPEN 2025 is open to all U11,U13, U15, U17 and U19 players.\r\n\r\nSRAFT Registration will close 3 days before PJO DEADLINE--- 17th May 2025\r\nALL PAYMENT TO BE PAID TO SRAFTKL CIMB ACCOUNT', 'https://drive.google.com/file/d/1KrUbVxUk4UFkSWALs23eWCUKLCxNYuz9/view?usp=sharing', 'Nicol David International Squash Centre, Bukit Dumbar, Penang', NULL, 'Foreign Players - USD100.00  & Local Players - RM140.00', '2025-05-17 22:00:00', '2025-07-13', 'INTERNATIONAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/PBA logo.png', 'player_first_name;player_last_name;player_ic;player_dob;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;accommodation;player_latest_results;player_attachments', 'Akl0004'),
(10, '2025-05-31 09:00:00', '2025-06-04 17:00:00', 'HENRI CHARPENTIER Lion City Junior Open 2025', 'HENRI CHARPENTIER Lion City Junior Open 2025 is open to all U11,U13, U15, U17 and U19 players.\r\n\r\nSRAFT Registration will close 3 days before TOURNAMENT DEADLINE--- 9th April 2025', 'https://drive.google.com/file/d/1Gsv_-JzFZydQ9OTiPWln8tdFag36Tmsp/view?usp=sharing', 'Kallang Squash Centre (Public Facility) Address: 8 Stadium Boulevard. Singapore 397804', NULL, 'SGD$130 , Payment can be made via https://www.lioncityopen.com/ or online bank transfer (REFER TOURNAMENT INFO)', '2025-04-12 13:59:00', '2025-06-04', 'INTERNATIONAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/henri carpenter.jpg', 'player_first_name;player_last_name;player_dob;player_nationality;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'Akl0004'),
(11, '2025-06-12 08:00:00', '2025-06-15 17:00:00', 'NICOL DAVID RISING STARS 2025', 'THE TOURNAMENT IS SRAM Level 1 ranking points - Boys and Girls under 9,11&13.\r\nSRAFTKL CLOSING DATE : 13 MAY 2025\r\n\r\nREGISTER AT RANKED IN : https://rankedin.com/en/tournament/51512/nicol- david-rising-star-2025', 'https://drive.google.com/file/d/1Ijfl9hpcK6GCthHuPc9E3cr1iSW2SUTp/view?usp=sharing', 'MBPJ Astaka Sports Complex', NULL, 'RM 100.00 per person (local)  & USD 100 per person (foreign); must be paid to  SRAFTKL CIMB 8000928820', '2025-05-16 17:00:00', '2025-01-01', 'LOCAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/ndo.png', 'player_full_name;player_ic;player_passport;player_dob;player_nationality;rankedin;spin_number;national_ranking;category_registered;player_tshirt;player_latest_results', 'Akl0004'),
(12, '2025-08-05 09:00:00', '2025-08-10 16:00:00', 'THE JESSICA COMPANY HONG KONG JUNIOR SQUASH OPEN 2025(PLATINUM EVENT)', 'VENUES: \r\nVenue #1: Cornwall Street Squash & Table Tennis Centre, Kowloon Tong, Kowloon\r\nVenue #2: Hong Kong Squash Centre, 23 Cotton Tree Drive, Hong Kong\r\n\r\nENSURE ALL REGISTRATION IS ENCLOSED WITH PAYMENT SLIP & PASSPORT COPY\r\n\r\n\r\nCLOSING DATE : 3 JUNE 2025', 'https://drive.google.com/file/d/1MyEZ0wxI3mr1s6mKK3qgK_TfZGVRXHYH/view?usp=sharing', 'HONG KONG, CHINA', NULL, 'USD 80.00 (PAYABLE TO Squash Association of Hong Kong) . USD 25 additional required for banking charges', '2025-06-03 17:00:00', '2025-08-10', 'INTERNATIONAL', 'KID-L (HALF CHEST 40.5CM);KID-XL (HALF CHEST 43CM);XS (HALF CHEST 44CM);S (HALF CHEST 47CM);M (HALF CHEST 50CM);L (HALF CHEST 53CM)', '../images/tournament/G.jpg', 'player_first_name;player_last_name;player_passport;player_dob;player_gender;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_chinese_name;player_tshirt;player_latest_results;player_attachments', 'Akl0004'),
(13, '2025-08-13 09:00:00', '2025-08-16 17:00:00', '36th JAPAN JUNIOR OPEN 2025 (AJSS Gold Event)', 'VENUES : Yokohama, Saitama & Ebina\r\n\r\n*SELF PAYMENT - FOLLOW GUIDELINE IN TOURNAMENT INFO. player is required to create Sport Entry account and complete registration.\r\nLetter of endorsement would be processed by SRAFT KL\r\n\r\nEnsure register before 12th June for endorsement process.', 'https://drive.google.com/file/d/1Sqy4V93u4-PAQle4iInNm-xYb5Iz2VWO/view?usp=sharing', 'YOKOHAMA, JAPAN', NULL, 'JPN YEN 20 000', '2025-06-04 12:00:00', '2025-08-16', 'INTERNATIONAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/logo.png', 'player_first_name;player_last_name;player_dob;player_gender;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_latest_results;player_attachments', 'Akl0004'),
(14, '2025-09-03 09:00:00', '2025-09-07 17:00:00', 'CIMB NATIONAL JUNIOR CIRCUIT 2ND LEG 2025 (PERLIS)', 'CIMB NJC LEG 2 - Is Open for all U15, U17, U19 players only.\r\nAll Players are required to register in RANKEDIN as well.\r\nSRAFT Registration will close 3 days before SRAM DEADLINE--- 31st JULY 2025', 'https://drive.google.com/file/d/1q0IVq8_EkPRRPNeuNswxD6n2_UCv1i-I/view?usp=sharing', 'Kompleks Sukan Negeri Perlis', NULL, 'Local: RM100.00 Foreign: USD100.00 **  Payment should be made to SRAFTKL CIMB 8000928820', '2025-07-31 17:00:00', '2025-01-01', 'LOCAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/CIMB-Logo.png', 'player_full_name;player_ic;player_passport;rankedin;ajss_ranking;category_registered;player_tshirt;accommodation;player_attachments', 'Akl0004'),
(15, '2025-08-28 09:00:00', '2025-08-31 17:00:00', 'MACAU JUNIOR OPEN 2025- AJSS SILVER EVENT', 'PAYMENT PAYABLE TO: Associacao de Squash de Macau', 'https://drive.google.com/file/d/1bUfkq5ryMuE5wVNyySnSlBuVtRIuuKBb/view?usp=sharing', 'MACAU', NULL, 'USD80 ; ( TSHIRT: USD15 - OPTIONAL)', '2025-07-11 12:00:00', '2025-08-31', 'INTERNATIONAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/MACAU.png', 'player_first_name;player_last_name;player_dob;player_nationality;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'Akl0004'),
(16, '2025-08-18 09:00:00', '2025-08-21 17:00:00', '18th Korea Junior Squash Open 2025 (AJSS Gold Event)', 'VENUE: Incheon Yeorumul Squash Stadium (164, Yeorumul-ro, Bupyeong-gu, Incheon, Republic of Korea)\r\nPAYMENT : USD 80.00 (PAY CASH UPON ARRIVAL)\r\n**Attach Passport copy during registration', 'https://drive.google.com/file/d/1DhMNyfpwZMOrl9H8N6zQmWyDR0pzKkkE/view?usp=sharing', 'Seoul, Korea', NULL, 'USD 80.00 (PAYABLE TO KSF - CASH)', '2025-07-11 12:00:00', '2025-08-20', 'INTERNATIONAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/korea.png', 'player_first_name;player_last_name;player_dob;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'Akl0004'),
(17, '2025-12-02 08:00:00', '2025-12-07 14:00:00', 'REDtone 17th KL International Junior Open Squash Championships 2025 (AJSS Platinum Event)', 'Venue: Refer tournament Info\r\n\r\nAll Payment MUST be made to:\r\nPersatuan Squash WP Kuala Lumpur\r\nAccount Number: 562106969953\r\nBank: MAYBANK\r\n\r\nSRAFTKL Players Do NOT NEED to Register ONLINE ( Google Form)', 'https://api.asiansquash.org/uploads/REDTONE_17th_KL_JUNIORS_OPEN_2025_CHAMPIONSHIP_INFO_updated20250713_2_c6f9f80bdf.pdf', 'refer Turnament Info', NULL, 'Foreign Players - USD120.00  & Local Players - RM70.00', '2025-10-01 15:00:00', '2025-12-07', 'INTERNATIONAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/redtone.jpg', 'player_first_name;player_last_name;player_ic;player_passport;player_gender;player_nationality;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'Akl0004'),
(18, '2025-09-17 08:00:00', '2025-09-21 14:00:00', 'CIMB RISING STAR NATIONAL JUNIOR CIRCUITS (RSNJC) 3rd LEG 2025-KEDAH', 'CIMB RSNJC LEG 2 - Is Open for all U9, U11, U13 players only.\r\n\r\nSRAFT Registration will close 3 days before SRAM DEADLINE\r\n\r\nALL PLAYERS REQUIRED TO REGISTER AT RANKED IN : Registration link:\r\nhttps://rankedin.com/en/tournament/56270/cimb-rising-star-national-juniorcircuit-\r\n3rd-leg-2025-kedah/info', 'https://drive.google.com/file/d/1GVsG3t634DgJ16BbeAue1fIkkKuMehMb/view?usp=sharing', 'Pusat Skuasy Negeri Kedah', NULL, 'RM 100.00 per person (local)  & USD 100 per person (foreign); must be paid to  SRAFTKL CIMB 8000928820', '2025-08-15 17:00:00', '2025-01-01', 'LOCAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/CIMB-Logo.png', 'player_full_name;player_ic;player_passport;rankedin;ajss_ranking;category_registered;player_tshirt;player_attachments', 'Akl0001'),
(19, '2025-10-01 08:00:00', '2025-10-05 17:00:00', 'CIMB NATIONAL JUNIOR CIRCUITS (NJC) 3rd LEG 2025', 'Register RANKED IN at : https://rankedin.com/en/tournament/56741/cimb-national-junior-circuit-3rd-leg-2025-johor \r\nVenue:\r\nGelanggang Skuasy Arena Emas, Taman Impian Emas, 81300 Skudai, Johor\r\n\r\nEvents:\r\nBoys and Girls under 15,17&19', 'https://drive.google.com/file/d/1UscIOfO3RlUHrt55jrEXs3xwS77YAGYY/view?usp=sharing', 'JOHOR BAHRU', NULL, 'Local: RM100.00 Foreign: USD100.00 **  Payment should be made to SRAFTKL CIMB 8000928820', '2025-09-05 17:00:00', '2025-01-01', 'LOCAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/CIMB-Logo.png', 'player_full_name;player_ic;player_passport;rankedin;ajss_ranking;category_registered;player_tshirt;accommodation;player_attachments', 'Akl0004'),
(20, '2025-10-10 09:00:00', '2025-10-12 17:00:00', 'Dunlop SRAFTKL FT Junior Closed Squash Championship 2025', 'DUNLOP SRAFTKL FT Junior Closed Squash Championship 2025\r\n10th October  –   12th  October 2025\r\nJalan Duta', '-', 'Pusat Skuasy Jalan Duta', NULL, 'RM 60.00 per person (SRAFTKL members  & RM 100 per person (NON SRAFTKL); must be paid to  SRAFTKL CIMB 8000928820', '2025-09-28 23:00:00', '2025-01-01', 'LOCAL', 'KID-L (HALF CHEST 40.5CM);KID-XL (HALF CHEST 43CM);XS (HALF CHEST 44CM);S (HALF CHEST 47CM);M (HALF CHEST 50CM);L (HALF CHEST 53CM)', '../images/tournament/dunlop.png', 'player_full_name;player_ic;player_dob;player_gender;category_registered;player_tshirt;player_attachments', 'Akl0004'),
(21, '2025-12-09 08:00:00', '2025-12-14 15:00:00', 'ONCOCARE SINGAPORE JUNIOR SQUASH OPEN 2025', 'The ONCOCARE Singapore Junior Squash Open 2025 is an ASF Platinum event pre-\r\nsented by Singapore Squash Rackets Association. All participants will receive ASF\r\n\r\nranking points. The event has also been registered as a WSF & PSA National Junior\r\nOpen.', 'https://drive.google.com/file/d/1XXB8j94E1aTX0lCYQ14234xZawjddiLe/view?usp=sharing', 'Kallang Squash Centre Address: 8 Stadium Boulevard, Singapore 397804', NULL, 'SGD 160 (USD 120) ** Payment details are defined in tournament handbook in link provided.', '2025-10-16 17:00:00', '2025-12-14', 'INTERNATIONAL', 'KID-L (HALF CHEST 40.5CM);KID-XL (HALF CHEST 43CM);XS (HALF CHEST 44CM);S (HALF CHEST 47CM);M (HALF CHEST 50CM);L (HALF CHEST 53CM)', '../images/tournament/onco.png', 'player_first_name;player_last_name;player_dob;player_nationality;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results', 'Akl0004'),
(22, '2025-12-19 09:00:00', '2025-12-24 21:00:00', 'HKFC International Junior Squash Open 2025', 'The “HKFC International Junior Squash Open 2025” is an ASF Asian Junior Super Series “PLATINUM” event and organized by Squash Association of Hong Kong, China. Participants will earn ASF Asian Junior ranking points (all categories except under 9).\r\n\r\nPayment should be paid directly to:\r\nSquash Association of Hong Kong China Bank Account Details\r\nName of Bank Account (Beneficiary Name): Squash Association of Hong Kong China\r\nBank Account Number: 741-106223-001\r\nName of Bank: THE HONGKONG AND SHANGHAI BANKING CORPORATION LTD\r\nBank Address: HONG KONG MAIN OFFICE, 1 QUEEN’S ROAD, CENTRAL, HONG KONG\r\nSwift Code: HSBCHKHHHKH', 'https://drive.google.com/file/d/1KbENunLYAeNJ6gJQe5Og--GWX5x24YCa/view?usp=sharing', 'Hong Kong Football Club, 3 Sports Road, Happy Valley & Hong Kong Squash Centre, 23 Cotton Tree Drive, Hong Kong', NULL, 'USD 80 + USD 25 (Bank Charges)', '2025-11-06 17:00:00', '2025-12-24', 'International', 'KID-L (HALF CHEST 40.5CM);KID-XL (HALF CHEST 43CM);XS (HALF CHEST 44CM);S (HALF CHEST 47CM);M (HALF CHEST 50CM);L (HALF CHEST 53CM)', '../images/tournament/logo.jpg', 'player_first_name;player_last_name;player_passport;player_dob;player_gender;asf_member_no;national_ranking;ajss_ranking;category_registered;player_chinese_name;player_tshirt;player_latest_results;player_attachments', 'AKL0002'),
(23, '2026-01-21 08:00:00', '2026-01-25 17:00:00', 'SRAM National Junior Championships 2026', 'Players are required to register themselves into the tournament via \r\nthe RankedIn tournament system\r\n\r\nfor more details, you may download the tournament info: https://drive.google.com/file/d/184mx0bCnUiK_Z4zMtqlrqOvNl2On2cN_/view?usp=drivesdk', 'https://www.rankedin.com/en/tournament/61829/sram-national-junior-championships-2026/info', 'NSC SQUASH CENTRE, Persiaran Tuanku Syed Sirajuddin (Persiaran Duta), Kuala Lumpur.', NULL, 'RM100.00 , Payment to be paid to SRAFTKL CIMB 8000928820', '2026-01-01 17:00:00', '2026-01-25', 'Local', 'XS;S;M;L;XL', '../images/tournament/images (3).png', 'player_full_name;player_ic;player_passport;rankedin;category_registered;player_tshirt;player_attachments', 'AKL0002'),
(24, '2026-02-11 08:00:00', '2026-02-14 18:00:00', 'Spark Education Tanglin Junior Open 2026 (SILVER EVENT)', 'Tournament Venue: Tanglin Club, 5 Stevens Rd, Singapore 257814 (10-minute walk distance from Orchard MRT)\r\n\r\nAll entries MUST fill in online: https://forms.gle/NrdsACFXQoqcFKxn7 . \r\n\r\nPAYMENT to be paid to \r\nBeneficiary Name: Ignite Squash Pte. Ltd.  \r\nBank Name: United Overseas Bank Limited  \r\nAccount Number: 3743215778 \r\nBranch: 057 \r\nSwift Code: UOVBSGSG \r\n*indicate Player name, Category', 'https://docs.google.com/document/d/1yz3RLSKpAuvwfuMJIv4L7U_6xNFrM2eE-5NScKnGQfQ/edit?usp=sharing', 'Tanglin Club, 5 Stevens Rd, Singapore 257814', NULL, 'USD 100', '2026-01-08 17:00:00', '2026-02-14', 'INTERNATIONAL', '32;34;36;38;40;42;44', '../images/tournament/download.png', 'player_first_name;player_last_name;player_ic;player_passport;player_dob;player_gender;player_nationality;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'akl0001'),
(25, '2026-04-07 08:00:00', '2026-04-11 17:00:00', 'Negeri Sembilan International Junior Squash Open 2026 (SILVER EVENT)', 'PLAYERS MUST REGISTER IN RANKEDIN\r\n\r\nREGISTRATION CLOSES ON 26 FEBRUARY FOR SRAFTKL VERIFICATIONS.\r\n\r\nTOURNAMENT INFO: https://drive.google.com/file/d/1OJLKFzQFEjpEF2-2r-BUoNLznOhZCsOG/view?usp=sharing', 'https://rankedin.com/en/tournament/60463/1st-negeri-sembilan-international-juniorsquash- open-2026', 'Kompleks Sukan Tan Sri Dato Hj Mohd Said, Squash Centre, Seremban 2, Negeri Sembilan        • Chung Hua High School, Seremban', NULL, 'RM 120.00 Payment to be paid to SRAFTKL CIMB 8000928820', '2026-02-28 17:00:00', '2026-04-11', 'LOCAL', '32;34;36;38;40;42;44;26;28;50', '../images/tournament/61324437_439551590226972_7837461694744887296_n.jpg', 'player_first_name;player_last_name;player_dob;rankedin;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;accommodation;player_latest_results;player_attachments', 'akl0004'),
(26, '2026-04-12 12:00:00', '2026-04-16 12:00:00', '2026 Australian Junior Open (AJSS Silver Event)', 'players are required to register using tournament website. Provide registration confirmation screenshot / email as attachment for endorsement purpose. No evidence of registration will not be endorsed\r\n\r\n\r\nEvent: 2026 Australian Junior Open (AJSS Silver Event)\r\nDate: 12 – 16 April 2026\r\nVenue: Melbourne Sports & Aquatic Centre, Melbourne, Australia\r\nEvent Info & Registration: https://www.squashaus.com.au/events/307188\r\nEntry Deadline: 22 Mar 2026, 23:45 (Australian Time)', 'https://www.squashaus.com.au/events/307188', 'Melbourne Sports & Aquatic Centre, Melbourne, Australia', NULL, 'refer tournament registration page', '2026-03-15 12:00:00', '2026-04-12', 'International', 'XS;S;M;L;XL', '../images/tournament/aust.png', 'player_first_name;player_last_name;player_passport;player_dob;player_gender;player_nationality;asf_member_no;national_ranking;ajss_ranking;category_registered;player_attachments', 'AKL0004'),
(27, '2026-06-03 08:00:00', '2026-06-07 14:00:00', 'Dymon Asia Lion City Junior Open 2026', 'Venue: Kallang Squash Center, SINGAPORE\r\n\r\nCategories: Boys\' & Girls\' Under 11, 13, 15, 17, 19\r\n\r\nTOURNAMENT INDIVIDUAL ENTRY FORM :  https://www.lioncityopen.com \r\n\r\nSRAFT Registration Closing Date: 10 April 2026\r\n\r\n*ALL PAYMENT TO BE MADE TO ORGANIZER DIRECTLY .', 'https://drive.google.com/file/d/11HLbMJDtkBVYjWUELJHGQQ2DTba7Xn1l/view?usp=sharing', 'SINGAPORE', NULL, 'SGD 130.00', '2026-04-04 12:00:00', '2026-06-07', 'INTERNATIONAL', 'XXS; XS; S; M; L; XL; XXL', '', 'player_first_name;player_last_name;player_dob;player_nationality;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results', 'akl0004'),
(28, '2026-04-22 08:00:00', '2026-04-26 14:00:00', 'SRAM NATIONAL JUNIOR CIRCUITS 2026 Leg 1 (TOP 4 ONLY)-U11,U13,U15.17', 'TOP 4 PLAYERS OF BU/GU 11,13,15.17  TO REGISTER HERE FOR ENDORSEMENT.\r\nFOR FURTHER INFORMATION, REFER TO TOURNAMENT INFO: https://drive.google.com/file/d/14n_N060YjqJ-DK_6JbVwU8KAzRTmD3lG/view?usp=sharing \r\nALL PLAYERS MUST REGISTER AT RANKEDIN', 'https://www.rankedin.com/en/tournament/63134/sram-national-junior-circuit-1st-leg- 2026/info', 'NSC, DUTA', NULL, 'RM 120.00 Payment to be paid to SRAFTKL CIMB 8000928820', '2026-03-27 12:00:00', '2026-01-01', 'LOCAL', 'XS;S;M;L;XL', '../images/tournament/OIP.jpg', 'player_full_name;player_ic;player_gender;rankedin;category_registered;player_tshirt;player_attachments', 'akl0004'),
(29, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'SRAM NATIONAL JUNIOR CIRCUITS 2026 Leg 1 (TOP 4 ONLY)', 'TOP 4 PLAYERS OF BU/GU 11,13,15.17  TO REGISTER HERE FOR ENDORSEMENT.\r\nFOR FURTHER INFORMATION, REFER TO TOURNAMENT INFO: https://drive.google.com/file/d/14n_N060YjqJ-DK_6JbVwU8KAzRTmD3lG/view?usp=sharing \r\nALL PLAYERS MUST REGISTER AT RANKEDIN', 'https://www.rankedin.com/en/tournament/63134/sram-national-junior-circuit-1st-leg- 2026/info', 'NSC, DUTA', NULL, 'RM 120.00 Payment to be paid to SRAFTKL CIMB 8000928820', '0000-00-00 00:00:00', '2026-01-01', 'LOCAL', 'XS;S;M;L;XL', '../images/tournament/SRAM.png', 'player_full_name;player_ic;player_gender;rankedin;category_registered;player_tshirt;player_attachments', 'AKL0004'),
(30, '2026-04-22 08:00:00', '2026-04-26 14:00:00', 'SRAM NATIONAL JUNIOR CIRCUITS 2026 Leg 1(BU09 & GU09) ONLY', 'ALL PLAYERS REQUIRED TO REGISTER HERE FOR ENDORSEMENT. FOR FURTHER INFORMATION, REFER TO TOURNAMENT INFO: https://drive.google.com/file/d/14n_N060YjqJ-DK_6JbVwU8KAzRTmD3lG/view?usp=sharing.  ALL PLAYERS MUST REGISTER AT RANKEDIN', 'https://www.rankedin.com/en/tournament/63134/sram-national-junior-circuit-1st-leg-%202026/info', 'NSC, DUTA', NULL, 'RM 120.00 Payment to be paid to SRAFTKL CIMB 8000928820', '2026-03-27 12:00:00', '2026-01-01', 'LOCAL', 'XS;S;M;L;XL', '', 'player_full_name;player_ic;player_gender;rankedin;category_registered;player_tshirt;player_attachments', 'AKL0004'),
(31, '2026-06-30 08:00:00', '2026-07-05 17:00:00', 'PBA 22ND PENANG JUNIOR OPEN 2026 (GOLD EVENT)', 'PBA 22ND PENANG JUNIOR OPEN 2026\r\n30TH JUNE TO 5TH JULY 2026\r\nNDISC, BUKIT DUMBAR, PENANG - MALAYSIA', 'https://drive.google.com/file/d/1TBq1YVQAJ8FDgRGWXz4kd5g1KQ3cnbc2/view?usp=sharing', 'BUKIT DUMBAR, PENANG', NULL, 'RM 140.00 Payment to be paid to SRAFTKL CIMB 8000928820', '2026-05-12 12:00:00', '2026-07-05', 'International', '34;36;38;40;42;44', '', 'player_first_name;player_last_name;player_ic;player_dob;player_nationality;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;accommodation;player_latest_results;player_attachments', 'AKL0004'),
(32, '2026-07-16 09:01:00', '2026-07-19 17:00:00', 'NICOL DAVID RISING STARS 2026   ( U09, U11, U13 ONLY)', 'SRAM Level 1 or 2 ranking points - Boys and Girls under 9,11&13\r\n15th - 19th July 2026\r\nRANKEDIN REGISTRATION IS REQUIRED : https://www.rankedin.com/en/tournament/69031/nicol-david-rising-stars-2026', 'https://docs.google.com/document/d/1mvQlUTuvcUeROkBGaeVcMWISTPFC44Cy/edit?usp=sharing&ouid=116399753593983444408&rtpof=true&sd=true', 'NSC JALAN DUTA', NULL, 'RM120 (MALAYSIAN) & USD100 (INTERNATIONAL)  - PAYABLE TO SRAFTKL CIMB 8000928820', '2026-06-05 17:00:00', '2026-01-01', 'LOCAL', 'XXS(32);XS(34);S(36);M(38);L(40);XL(42)', '../images/tournament/NDO.png', 'player_full_name;player_ic;rankedin;ajss_ranking;category_registered;player_tshirt;player_attachments', 'akl0004'),
(33, '2026-08-04 09:00:00', '2026-08-09 17:00:00', 'HONG KONG JUNIOR SQUASH OPEN 2026 (PLATINUM EVENT)', '2 EVENT VENUES INDICATED (REFER TO TOURNAMENT INFO IN LINK GIVEN)\r\nREGISTER AND SUBMIT ALL DETAILS BEFORE 6/6/26 5 PM\r\n* PAYMENT SLIP TO BE ATTACHED\r\n* PASSPORT COPY REQUIRED TO BE SUBMITTED IN LINK GIVEN BY COACH', 'https://drive.google.com/file/d/1puNLHPRql1yeXcEmtVN8MSxLyEea-uXZ/view?usp=sharing', 'HONG KONG', NULL, 'USD 80 + (USD 25 BANK CHARGES ) - PAY DIRECTLY TO HKG SQUASH', '2026-06-06 17:00:00', '2026-08-09', 'INTERNATIONAL', '4XS (L-55, W-40); 3XS (L-57, W-42); 2XS (L-59, W-44); 2XS (L-59, W-44); S (L-63, W-48); M (L-65, W-50); L (L-67, W-52); XL (L-69, W-54)', '../images/tournament/HKG.png', 'player_first_name;player_last_name;player_passport;player_dob;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'akl0004'),
(34, '2026-07-15 09:00:00', '2026-07-19 17:00:00', 'China Squash Junior Open 2026 (AJSS Silver Event)', 'PLEASE REFER TO TOURNAMENT HANDBOOK FOR DETAILS OF REGISTRATION.\r\n** FOR PASSPORT HOLDERS WHO REQUIRE VISA, PROCEED TO PROCESS IT AND SUBMIT TO THE HOST.\r\n**ATTACH PROOF OF PAYMENT FOR REGISTRATION', 'https://docs.google.com/document/d/1IhaawYPbmQQN_PAoCL0crGPdUeVwrZfh/edit?usp=drivesdk&ouid=116399753593983444408&rtpof=true&sd=true', 'SHANDONG, CHINA', NULL, 'USD 100 -- PAY DIRECTLY TO CHINA SQUASH', '2026-06-11 17:00:00', '2026-07-19', 'INTERNATIONAL', 'S;M;L;XL;XXL', '../images/tournament/SHANGDONG.jpg', 'player_first_name;player_last_name;player_dob;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'akl0004'),
(35, '2026-08-12 08:00:00', '2026-08-15 17:00:00', 'The 19th Korea Junior Open 2026(Gold)', 'ALL PLAYERS REQUESTED TO FILL IN REGISTRATION AT https://l1nq.com/1lyms9n\r\nSRAFTKL registration is for endorsement purpose only', 'https://l1nk.dev/y19vxp9', 'Incheon Yeorumul Squash Stadium', NULL, 'USD 80 + USD 25 : PAYABLE TO KOREA SQUASH FEDERATION', '2026-07-03 17:00:00', '2026-08-15', 'INTERNATIONAL', 'S; M; L; XL; XXL', '', 'player_first_name;player_last_name;player_dob;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results', 'akl0004'),
(36, '2026-08-19 08:00:00', '2026-08-23 17:00:00', '37th Japan Junior Open 2026 (AJSS Silver Event)', 'All Players COMPULSORY to REGISTER AND PAY  at https://www.sportsentry.ne.jp/register/en/100525.\r\nSRAFTKL registration is for endorsement ONLY', 'https://l1nk.dev/m9sjmlg', 'Yokohama & Ebina, Japan', NULL, '20,000 yen (tax included)', '2026-06-23 17:00:00', '2026-08-23', 'INTERNATIONAL', '', '', 'player_first_name;player_last_name;player_passport;player_dob;player_gender;player_nationality;asf_member_no;spin_number;ajss_ranking;category_registered', 'akl0004'),
(37, '2026-08-17 10:00:00', '2026-08-22 22:00:00', 'Southern Slam 2026 (AJSS Gold Event)', 'Tournament is scheduled for Junior and Senior (PSA registered only) players.\r\nPlayers may play both category provided they   follow match timing and not allowed to request for change of timing.', 'https://drive.google.com/file/d/1mns1fjJrLGPE8ZWD6NT0VT3pPUBl2o-_/view?usp=sharing', 'CHENNAI, INDIA', NULL, 'INR 2500; payable to Indian Squash', '2026-07-12 17:00:00', '2026-08-22', 'INTERNATIONAL', 'XXS; XS; S; M; L; XL; XXL', '../images/tournament/SRFI.jpg', 'player_first_name;player_last_name;player_dob;player_nationality;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results', 'akl0004'),
(38, '2026-08-26 08:00:00', '2026-08-30 17:00:00', 'SRAM NATIONAL JUNIOR CIRCUITS 2026 Leg 2 U09 & (TOP 4 ONLY)-U11,U13,U15.17', 'TOP 4 PLAYERS OF BU/GU 11,13,15.17  TO REGISTER HERE FOR ENDORSEMENT.\r\nBU/GU 09 IS OPEN TO REGISTER AND PLAY (NO SELECTION REQUIRED)\r\nFOR FURTHER INFORMATION, REFER TO TOURNAMENT INFO: \r\nhttps://drive.google.com/file/d/14n_N060YjqJ-DK_6JbVwU8KAzRTmD3lG/view?usp=sharing \r\nALL PLAYERS MUST REGISTER AT RANKEDIN', 'https://www.rankedin.com/en/tournament/69295/sram-national-junior-circuit-2nd-leg-2026/info', 'PENANG', NULL, 'RM 120.00 Payment to be paid to SRAFTKL CIMB 8000928820', '2026-07-04 17:00:00', '2026-01-01', 'LOCAL', 'XXXXS; XXXS; XXS; XS; S; M; L; XL; XXL', '../images/tournament/21663.png', 'player_full_name;player_ic;rankedin;category_registered;player_tshirt;player_attachments', 'akl0004'),
(39, '2026-08-26 08:00:00', '2026-08-30 17:00:00', 'Macau Junior Open 2026 (GOLD Event)', 'Championship Status: AJSS \"Gold\" Event\r\n• ASF Ranking Points: All participants will receive ASF junior ranking points.\r\n• PSA ranking points: Players in under 17 & 19 category and below will receive PSA ranking points.', 'https://drive.google.com/file/d/1ZulFeqy5t_bZ6FC5hNxEcEDr7IU1cz6v/view?usp=drive_link', 'Macau', NULL, 'USD80 Payable to Associacao de Squash de Macau', '2026-07-27 17:00:00', '2026-08-30', 'INTERNATIONAL', '', '../images/tournament/312145712_197351952664677_3495103198458946528_n.jpg', 'player_first_name;player_last_name;player_dob;player_nationality;spin_number;national_ranking;ajss_ranking;category_registered;player_latest_results', 'akl0004'),
(40, '2026-09-01 09:00:00', '2026-09-06 17:00:00', 'SUPER SQUASH Rising Stars National Championship 2026', 'REGISTER IN RANKEDIN at link given.\r\n*Top 16 players in SRAM National Ranking NOT allowed to participate in the U11, U13,\r\nU15 and U17 categories.\r\n*Top 8 players in SRAM National Ranking NOT allowed to participate in the U9 categories.\r\n*Foreign players are NOT allowed to participate.', 'https://www.rankedin.com/en/tournament/67532/super-squashrising-starsnational- championship-2026/info', 'Kangar, Perlis', NULL, 'RM100', '2026-07-30 17:00:00', '2026-01-01', 'LOCAL', 'XS; S; M; L; XL; 2XL; 3XL; 4XL; 5XL; 6XL', '', 'player_full_name;player_ic;rankedin;national_ranking;category_registered;player_tshirt;player_attachments', 'akl0004'),
(41, '2026-08-25 08:00:00', '2026-08-29 17:00:00', '1 st Chinese Taipei Junior Squash Open 2026 (SILVER EVENT)', 'Refer link below for Tournament Info', 'https://drive.google.com/file/d/1r7_fURtFrcxe0LSYTspf3xq2eF5IeMOh/view?usp=sharing', 'Sports Center of National Taiwan University', NULL, 'USD$130 + USD25 (Bank charge), Payable to Squash Rackets Association of Chinese Taipei', '2026-08-03 17:00:00', '2026-08-29', 'INTERNATIONAL', '', '../images/tournament/taipei.png', 'player_first_name;player_last_name;player_dob;player_gender;player_nationality;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_latest_results;player_attachments', 'akl0001'),
(42, '2026-09-19 08:50:00', '2026-09-24 17:00:00', 'Indian Junior Open 2026, Kolkata', 'Indian Junior Open 2026, Kolkata is a ASF PLATINUM event, 6 Star National Circuit Tournament', 'https://drive.google.com/file/d/1MI4gC-TI7JykLw7jXw3RvauiEq3wf4jS/view?usp=sharing', 'The Calcutta Racket Club, 95 JL Nehru Road, Kolkata 700071', NULL, 'INR 2500 (payable to SRFI)', '2026-08-09 17:00:00', '2026-09-24', 'INTERNATIONAL', '32; 34; 36; 38; 40; 42; 44', '', 'player_first_name;player_last_name;player_nationality;player_latest_results', 'akl0004'),
(43, '2026-10-13 08:00:00', '2026-10-18 18:00:00', 'Milo All Star Malaysia Junior Open 2026 (Diamond Event)', '-', 'https://docs.google.com/document/d/1yv3quTruVtKYFxu9Cc8pUf-xsIyz9nNE/edit?usp=sharing&ouid=116399753593983444408&rtpof=true&sd=true', 'Pusat Skuasy Jalan Duta , Astaka Squash Centre ,  Universiti Malaya (standby)', NULL, 'RM 120.00 Payment to be paid to SRAFTKL CIMB 8000928820', '2026-09-11 17:00:00', '2026-10-18', 'LOCAL', 'XXXS;XXS;XS;S;M;L;XL', '../images/tournament/milo.png', 'player_first_name;player_last_name;player_ic;player_dob;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'AKL0001'),
(44, '2026-10-24 08:00:00', '2026-10-28 18:00:00', 'COLOMBO JUNIOR SQUASH OPEN 2026 (SILVER EVENT)', 'REGISTER PLAYERS IN RANKEDIN AS WELL: https://www.rankedin.com/en/tournament/72615/colombo-junior-squash-open-2026-asian-junior-super-series-ajss/info', 'https://drive.google.com/file/d/19q4H2oKiu7_13f8LMPf3FaLwG4DX4eE6/view?usp=sharing', 'COLOMBO, SRI LANKA', NULL, 'USD100 PAYABLE TO SRI LANKA SQUASH', '2026-09-21 17:00:00', '2026-10-28', 'International', 'XXS;XXS;XS;S;M;L;XL; 2XL; 3XL; 4XL; 5XL; 6XL', '', 'player_first_name;player_last_name;player_dob;player_nationality;rankedin;asf_member_no;spin_number;national_ranking;ajss_ranking;category_registered;player_tshirt;player_latest_results', 'AKL0001'),
(45, '2026-09-21 08:00:00', '2026-09-24 17:00:00', 'SRAM NATIONAL JUNIOR CIRCUITS 2026 Leg 3 U09 ,U11,U13,U15.17', 'ALL MUST REGISTER IN RANKED IN BY 24TH AUGUST 2026\r\nhttps://www.rankedin.com/en/tournament/64577/sram-national-junior-circuit-3rd-leg-2026', '', 'SEREMBAN NEGERI SEMBILAN', NULL, 'RM 120.00 Payment to be paid to SRAFTKL CIMB 8000928820', '2026-08-24 15:00:00', '2026-01-01', 'LOCAL', 'XXXXS; XXXS; XXS; XS; S; M; L; XL; XXL', '', 'player_full_name;player_ic;rankedin;category_registered;player_tshirt;player_attachments', 'AKL0004'),
(46, '2026-10-02 08:00:00', '2026-10-04 14:00:00', 'DUNLOP SRAFTKL FT JUNIOR CLOSED SQUASH CHAMPIONSHIP 2026', 'OPEN FOR ALL SRAFTKL AND NDO PLAYERS.\r\nALL PAYMENT must be paid to  SRAFTKL CIMB 8000928820', '', 'NSC JALAN DUTA', NULL, 'FOR NON SRAFT MEMBER: RM100.00 ,  SRAFT MEMBER :RM70.00', '2026-09-15 12:00:00', '2026-01-01', 'LOCAL', 'KID-L (HALF CHEST 40.5CM);KID-XL (HALF CHEST 43CM);XS (HALF CHEST 44CM);S (HALF CHEST 47CM);M (HALF CHEST 50CM);L (HALF CHEST 53CM)', '../images/tournament/logo4.png', 'player_full_name;player_ic;player_passport;player_dob;national_ranking;category_registered;player_tshirt;player_latest_results;player_attachments', 'akl0004'),
(47, '2026-10-06 10:04:00', '2026-10-13 10:04:00', 'testing', 'testing', '', 'Kangar, Perlis', NULL, 'RM100', '2026-09-29 10:04:00', NULL, 'LOCAL', 'S,M,L', NULL, 'First Name;Last Name;National ID;Date of Birth;National Ranking;(PSA) World Ranking;Email;T-Shirt Size;Latest Results', 'AD0001'),
(48, '2026-09-29 14:50:00', '2026-10-06 14:50:00', 'testing 2', 'Testing the tournaments', '', 'Kangar, Perlis', NULL, 'RM100', '2026-09-23 14:50:00', NULL, 'LOCAL SQUASH', 'S,M,L', NULL, '', 'AD0001'),
(49, '2026-10-05 14:02:00', '2026-10-09 14:02:00', 'Testing again', 'This is just the testing.', '', 'APU', NULL, 'RM100', '2026-09-28 14:02:00', NULL, 'LOCAL SQUASH', 'S,M,L', NULL, '', 'AD0001'),
(50, '2026-10-05 14:09:00', '2026-10-09 14:09:00', 'Testing again 2', 'This is adding registration_options', '', 'APU', 'Malaysia', 'RM100', '2026-09-30 14:09:00', '2025-01-01', 'LOCAL SQUASH', 'S,M,L', NULL, 'player_first_name;player_last_name;player_national_id;player_date_of_birth;player_gender;player_nationality;player_contact_number;player_national_ranking;player_email;player_tshirt_size', 'AD0001'),
(52, '2026-10-01 14:14:00', '2026-10-09 14:14:00', 'testing', 'Testing', '', 'APU', 'Malaysia', 'RM50', '2026-09-25 14:14:00', '2026-01-01', 'LOCAL', 'S,M,L', NULL, 'player_full_name;player_first_name;player_last_name;player_national_id;player_passport;player_date_of_birth;player_gender;player_nationality;player_contact_number;player_national_ranking;player_email;player_tshirt_size', 'AD0001'),
(53, '2027-01-04 00:00:00', '2027-01-15 17:00:00', 'Squash Manila', 'Squash 2027', '', 'Manila', 'Philippines', 'Peso 1000', '2026-11-27 00:00:00', '2020-01-01', 'Local', 'S,M,L', NULL, 'player_full_name;player_first_name;player_last_name;player_national_id;player_date_of_birth;player_gender;player_nationality;player_contact_number;player_ajss_ranking;player_world_ranking;player_email;player_category;player_tshirt_size', 'AD0002'),
(54, '2026-10-12 12:02:00', '2026-10-15 12:03:00', 'testing', 'testing', '', 'Manila', 'Philippines', 'Peso 1000', '2026-10-09 12:03:00', '2021-01-05', 'Local', '32; 34; 36; 38; 40; 42; 44', NULL, 'player_full_name;player_first_name;player_last_name;player_date_of_birth;player_gender;player_nationality;player_contact_number;player_national_ranking;player_email;player_tshirt_size', 'AD0002'),
(57, '2026-10-26 12:13:00', '2026-10-30 12:14:00', 'testing 2', 'Testing', '', 'Manila', 'Philippines', 'Local: RMPeso 1000, Foreign: USD 50', '2026-10-23 12:14:00', '2019-01-01', 'International', '32; 34; 36; 38; 40; 42; 44', NULL, 'player_full_name;player_first_name;player_last_name;player_date_of_birth;player_gender;player_nationality;player_contact_number;player_national_ranking;player_ajss_ranking;player_email;player_tshirt_size', 'AD0002'),
(58, '2026-11-02 00:00:00', '2026-11-06 17:00:00', 'Testing Subscription', 'Just testing a year subscription', '', 'Manila', 'Philippines', 'Peso 1000', '2026-10-25 23:59:00', '2010-01-01', 'Local', 'S,M,L', NULL, 'player_full_name;player_first_name;player_last_name;player_national_id;player_date_of_birth;player_gender;player_contact_number;player_national_ranking;player_ajss_ranking;player_email;player_category;player_tshirt_size', 'AD0002');

-- --------------------------------------------------------

--
-- Table structure for table `tournament_category`
--

CREATE TABLE `tournament_category` (
  `categoryID` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `age` int(11) NOT NULL,
  `gender` varchar(20) NOT NULL,
  `tournamentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tournament_category`
--

INSERT INTO `tournament_category` (`categoryID`, `category_name`, `age`, `gender`, `tournamentID`) VALUES
(425, 'BU19', 19, 'male', 2),
(426, 'BU17', 17, 'male', 2),
(427, 'BU15', 15, 'male', 2),
(428, 'BU13', 13, 'male', 2),
(429, 'BU11', 11, 'male', 2),
(430, 'BU09', 9, 'male', 2),
(431, 'GU19', 19, 'female', 2),
(432, 'GU17', 17, 'female', 2),
(433, 'GU15', 15, 'female', 2),
(434, 'GU13', 13, 'female', 2),
(435, 'GU11', 11, 'female', 2),
(436, 'GU09', 9, 'female', 2),
(479, 'BU13', 13, 'male', 3),
(480, 'BU11', 11, 'male', 3),
(481, 'BU09', 9, 'male', 3),
(482, 'GU13', 13, 'female', 3),
(483, 'GU11', 11, 'female', 3),
(484, 'GU09', 9, 'female', 3),
(555, 'BU13', 13, 'male', 4),
(556, 'BU11', 11, 'male', 4),
(557, 'BU09', 9, 'male', 4),
(558, 'GU13', 13, 'female', 4),
(559, 'GU11', 11, 'female', 4),
(560, 'GU09', 9, 'female', 4),
(561, 'BU19', 19, 'male', 5),
(562, 'BU17', 17, 'male', 5),
(563, 'BU15', 15, 'male', 5),
(564, 'BU13', 13, 'male', 5),
(565, 'BU11', 11, 'male', 5),
(566, 'GU19', 19, 'female', 5),
(567, 'GU17', 17, 'female', 5),
(568, 'GU15', 15, 'female', 5),
(569, 'GU13', 13, 'female', 5),
(570, 'GU11', 11, 'female', 5),
(671, 'BU19', 19, 'male', 6),
(672, 'BU17', 17, 'male', 6),
(673, 'BU15', 15, 'male', 6),
(674, 'GU19', 19, 'female', 6),
(675, 'GU17', 17, 'female', 6),
(676, 'GU15', 15, 'female', 6),
(763, 'BU19', 19, 'male', 10),
(764, 'BU17', 17, 'male', 10),
(765, 'BU15', 15, 'male', 10),
(766, 'BU13', 13, 'male', 10),
(767, 'BU11', 11, 'male', 10),
(768, 'GU19', 19, 'female', 10),
(769, 'GU17', 17, 'female', 10),
(770, 'GU15', 15, 'female', 10),
(771, 'GU13', 13, 'female', 10),
(772, 'GU11', 11, 'female', 10),
(773, 'BU13', 13, 'male', 11),
(774, 'BU11', 11, 'male', 11),
(775, 'BU09', 9, 'male', 11),
(776, 'GU13', 13, 'female', 11),
(777, 'GU11', 11, 'female', 11),
(778, 'GU09', 9, 'female', 11),
(779, 'BU13', 13, 'male', 8),
(780, 'BU11', 11, 'male', 8),
(781, 'BU09', 9, 'male', 8),
(782, 'GU13', 13, 'female', 8),
(783, 'GU11', 11, 'female', 8),
(784, 'GU09', 9, 'female', 8),
(825, 'BU19', 19, 'male', 9),
(826, 'BU17', 17, 'male', 9),
(827, 'BU15', 15, 'male', 9),
(828, 'BU13', 13, 'male', 9),
(829, 'BU11', 11, 'male', 9),
(830, 'GU19', 19, 'female', 9),
(831, 'GU17', 17, 'female', 9),
(832, 'GU15', 15, 'female', 9),
(833, 'GU13', 13, 'female', 9),
(834, 'GU11', 11, 'female', 9),
(895, 'BU19', 19, 'male', 12),
(896, 'BU17', 17, 'male', 12),
(897, 'BU15', 15, 'male', 12),
(898, 'BU13', 13, 'male', 12),
(899, 'BU11', 11, 'male', 12),
(900, 'BU09', 9, 'male', 12),
(901, 'GU19', 19, 'female', 12),
(902, 'GU17', 17, 'female', 12),
(903, 'GU15', 15, 'female', 12),
(904, 'GU13', 13, 'female', 12),
(905, 'GU11', 11, 'female', 12),
(906, 'GU09', 9, 'female', 12),
(917, 'BU19', 19, 'male', 7),
(918, 'BU17', 17, 'male', 7),
(919, 'BU15', 15, 'male', 7),
(920, 'BU13', 13, 'male', 7),
(921, 'BU11', 11, 'male', 7),
(922, 'GU19', 19, 'female', 7),
(923, 'GU17', 17, 'female', 7),
(924, 'GU15', 15, 'female', 7),
(925, 'GU13', 13, 'female', 7),
(926, 'GU11', 11, 'female', 7),
(927, 'BU19', 19, 'male', 13),
(928, 'BU17', 17, 'male', 13),
(929, 'BU15', 15, 'male', 13),
(930, 'BU13', 13, 'male', 13),
(931, 'BU11', 11, 'male', 13),
(932, 'GU19', 19, 'female', 13),
(933, 'GU17', 17, 'female', 13),
(934, 'GU15', 15, 'female', 13),
(935, 'GU13', 13, 'female', 13),
(936, 'GU11', 11, 'female', 13),
(943, 'BU19', 19, 'male', 14),
(944, 'BU17', 17, 'male', 14),
(945, 'BU15', 15, 'male', 14),
(946, 'GU19', 19, 'female', 14),
(947, 'GU17', 17, 'female', 14),
(948, 'GU15', 15, 'female', 14),
(959, 'BU19', 19, 'male', 16),
(960, 'BU17', 17, 'male', 16),
(961, 'BU15', 15, 'male', 16),
(962, 'BU13', 13, 'male', 16),
(963, 'BU11', 11, 'male', 16),
(964, 'GU19', 19, 'female', 16),
(965, 'GU17', 17, 'female', 16),
(966, 'GU15', 15, 'female', 16),
(967, 'GU13', 13, 'female', 16),
(968, 'GU11', 11, 'female', 16),
(969, 'BU19', 19, 'male', 15),
(970, 'BU17', 17, 'male', 15),
(971, 'BU15', 15, 'male', 15),
(972, 'BU13', 13, 'male', 15),
(973, 'BU11', 11, 'male', 15),
(974, 'GU19', 19, 'female', 15),
(975, 'GU17', 17, 'female', 15),
(976, 'GU15', 15, 'female', 15),
(977, 'GU13', 13, 'female', 15),
(978, 'GU11', 11, 'female', 15),
(1028, 'BU13', 13, 'male', 18),
(1029, 'BU11', 11, 'male', 18),
(1030, 'BU09', 9, 'male', 18),
(1031, 'GU13', 13, 'female', 18),
(1032, 'GU11', 11, 'female', 18),
(1033, 'GU09', 9, 'female', 18),
(1040, 'BU19', 19, 'male', 19),
(1041, 'BU17', 17, 'male', 19),
(1042, 'BU15', 15, 'male', 19),
(1043, 'GU19', 19, 'female', 19),
(1044, 'GU17', 17, 'female', 19),
(1045, 'GU15', 15, 'female', 19),
(1058, 'BU19', 19, 'male', 21),
(1059, 'BU17', 17, 'male', 21),
(1060, 'BU15', 15, 'male', 21),
(1061, 'BU13', 13, 'male', 21),
(1062, 'BU11', 11, 'male', 21),
(1063, 'GU19', 19, 'female', 21),
(1064, 'GU17', 17, 'female', 21),
(1065, 'GU15', 15, 'female', 21),
(1066, 'GU13', 13, 'female', 21),
(1067, 'GU11', 11, 'female', 21),
(1068, 'BU19', 19, 'male', 17),
(1069, 'BU17', 17, 'male', 17),
(1070, 'BU15', 15, 'male', 17),
(1071, 'BU13', 13, 'male', 17),
(1072, 'BU11', 11, 'male', 17),
(1073, 'BU09', 9, 'male', 17),
(1074, 'GU19', 19, 'female', 17),
(1075, 'GU17', 17, 'female', 17),
(1076, 'GU15', 15, 'female', 17),
(1077, 'GU13', 13, 'female', 17),
(1078, 'GU11', 11, 'female', 17),
(1079, 'GU09', 9, 'female', 17),
(1152, 'BU19', 19, 'male', 22),
(1153, 'BU17', 17, 'male', 22),
(1154, 'BU15', 15, 'male', 22),
(1155, 'BU13', 13, 'male', 22),
(1156, 'BU11', 11, 'male', 22),
(1157, 'BU09', 9, 'male', 22),
(1158, 'GU19', 19, 'female', 22),
(1159, 'GU17', 17, 'female', 22),
(1160, 'GU15', 15, 'female', 22),
(1161, 'GU13', 13, 'female', 22),
(1162, 'GU11', 11, 'female', 22),
(1163, 'GU09', 9, 'female', 22),
(1204, 'BU19', 19, 'male', 20),
(1205, 'BU17', 17, 'male', 20),
(1206, 'BU15', 15, 'male', 20),
(1207, 'BU13', 13, 'male', 20),
(1208, 'BU11', 11, 'male', 20),
(1209, 'BU09', 9, 'male', 20),
(1210, 'GU19', 19, 'female', 20),
(1211, 'GU17', 17, 'female', 20),
(1212, 'GU15', 15, 'female', 20),
(1213, 'GU13', 13, 'female', 20),
(1214, 'GU11', 11, 'female', 20),
(1215, 'GU09', 9, 'female', 20),
(1226, 'BU19', 19, 'male', 23),
(1227, 'BU17', 17, 'male', 23),
(1228, 'BU15', 15, 'male', 23),
(1229, 'BU13', 13, 'male', 23),
(1230, 'BU11', 11, 'male', 23),
(1231, 'GU19', 19, 'female', 23),
(1232, 'GU17', 17, 'female', 23),
(1233, 'GU15', 15, 'female', 23),
(1234, 'GU13', 13, 'female', 23),
(1235, 'GU11', 11, 'female', 23),
(1336, 'BU19', 19, 'male', 24),
(1337, 'BU17', 17, 'male', 24),
(1338, 'BU15', 15, 'male', 24),
(1339, 'BU13', 13, 'male', 24),
(1340, 'BU11', 11, 'male', 24),
(1341, 'GU19', 19, 'female', 24),
(1342, 'GU17', 17, 'female', 24),
(1343, 'GU15', 15, 'female', 24),
(1344, 'GU13', 13, 'female', 24),
(1345, 'GU11', 11, 'female', 24),
(1502, 'BU19', 19, 'male', 26),
(1503, 'BU17', 17, 'male', 26),
(1504, 'BU15', 15, 'male', 26),
(1505, 'BU13', 13, 'male', 26),
(1506, 'BU11', 11, 'male', 26),
(1507, 'GU19', 19, 'female', 26),
(1508, 'GU17', 17, 'female', 26),
(1509, 'GU15', 15, 'female', 26),
(1510, 'GU13', 13, 'female', 26),
(1511, 'GU11', 11, 'female', 26),
(1512, 'BU19', 19, 'male', 25),
(1513, 'BU17', 17, 'male', 25),
(1514, 'BU15', 15, 'male', 25),
(1515, 'BU13', 13, 'male', 25),
(1516, 'BU11', 11, 'male', 25),
(1517, 'BU09', 9, 'male', 25),
(1518, 'GU19', 19, 'female', 25),
(1519, 'GU17', 17, 'female', 25),
(1520, 'GU15', 15, 'female', 25),
(1521, 'GU13', 13, 'female', 25),
(1522, 'GU11', 11, 'female', 25),
(1523, 'GU09', 9, 'female', 25),
(1552, 'BU17', 17, 'male', 29),
(1553, 'BU15', 15, 'male', 29),
(1554, 'BU13', 13, 'male', 29),
(1555, 'BU11', 11, 'male', 29),
(1556, 'GU17', 17, 'female', 29),
(1557, 'GU15', 15, 'female', 29),
(1558, 'GU13', 13, 'female', 29),
(1559, 'GU11', 11, 'female', 29),
(1600, 'BU17', 17, 'male', 28),
(1601, 'BU15', 15, 'male', 28),
(1602, 'BU13', 13, 'male', 28),
(1603, 'BU11', 11, 'male', 28),
(1604, 'GU17', 17, 'female', 28),
(1605, 'GU15', 15, 'female', 28),
(1606, 'GU13', 13, 'female', 28),
(1607, 'GU11', 11, 'female', 28),
(1608, 'BU09', 9, 'male', 30),
(1609, 'GU09', 9, 'female', 30),
(1620, 'BU19', 19, 'male', 27),
(1621, 'BU17', 17, 'male', 27),
(1622, 'BU15', 15, 'male', 27),
(1623, 'BU13', 13, 'male', 27),
(1624, 'BU11', 11, 'male', 27),
(1625, 'GU19', 19, 'female', 27),
(1626, 'GU17', 17, 'female', 27),
(1627, 'GU15', 15, 'female', 27),
(1628, 'GU13', 13, 'female', 27),
(1629, 'GU11', 11, 'female', 27),
(1640, 'BU19', 19, 'male', 31),
(1641, 'BU17', 17, 'male', 31),
(1642, 'BU15', 15, 'male', 31),
(1643, 'BU13', 13, 'male', 31),
(1644, 'BU11', 11, 'male', 31),
(1645, 'GU19', 19, 'female', 31),
(1646, 'GU17', 17, 'female', 31),
(1647, 'GU15', 15, 'female', 31),
(1648, 'GU13', 13, 'female', 31),
(1649, 'GU11', 11, 'female', 31),
(1668, 'BU19', 19, 'male', 33),
(1669, 'BU17', 17, 'male', 33),
(1670, 'BU15', 15, 'male', 33),
(1671, 'BU13', 13, 'male', 33),
(1672, 'BU11', 11, 'male', 33),
(1673, 'BU09', 9, 'male', 33),
(1674, 'GU19', 19, 'female', 33),
(1675, 'GU17', 17, 'female', 33),
(1676, 'GU15', 15, 'female', 33),
(1677, 'GU13', 13, 'female', 33),
(1678, 'GU11', 11, 'female', 33),
(1679, 'GU09', 9, 'female', 33),
(1704, 'BU13', 13, 'male', 32),
(1705, 'BU11', 11, 'male', 32),
(1706, 'BU09', 9, 'male', 32),
(1707, 'GU13', 13, 'female', 32),
(1708, 'GU11', 11, 'female', 32),
(1709, 'GU09', 9, 'female', 32),
(1720, 'BU19', 19, 'male', 36),
(1721, 'BU17', 17, 'male', 36),
(1722, 'BU15', 15, 'male', 36),
(1723, 'BU13', 13, 'male', 36),
(1724, 'BU11', 11, 'male', 36),
(1725, 'GU19', 19, 'female', 36),
(1726, 'GU17', 17, 'female', 36),
(1727, 'GU15', 15, 'female', 36),
(1728, 'GU13', 13, 'female', 36),
(1729, 'GU11', 11, 'female', 36),
(1730, 'BU19', 19, 'male', 34),
(1731, 'BU17', 17, 'male', 34),
(1732, 'BU15', 15, 'male', 34),
(1733, 'BU13', 13, 'male', 34),
(1734, 'BU11', 11, 'male', 34),
(1735, 'BU09', 9, 'male', 34),
(1736, 'GU19', 19, 'female', 34),
(1737, 'GU17', 17, 'female', 34),
(1738, 'GU15', 15, 'female', 34),
(1739, 'GU13', 13, 'female', 34),
(1740, 'GU11', 11, 'female', 34),
(1741, 'GU09', 9, 'female', 34),
(1752, 'BU19', 19, 'male', 37),
(1753, 'BU17', 17, 'male', 37),
(1754, 'BU15', 15, 'male', 37),
(1755, 'BU13', 13, 'male', 37),
(1756, 'BU11', 11, 'male', 37),
(1757, 'GU19', 19, 'female', 37),
(1758, 'GU17', 17, 'female', 37),
(1759, 'GU15', 15, 'female', 37),
(1760, 'GU13', 13, 'female', 37),
(1761, 'GU11', 11, 'female', 37),
(1802, 'BU17', 17, 'male', 38),
(1803, 'BU15', 15, 'male', 38),
(1804, 'BU13', 13, 'male', 38),
(1805, 'BU11', 11, 'male', 38),
(1806, 'BU09', 9, 'male', 38),
(1807, 'GU17', 17, 'female', 38),
(1808, 'GU15', 15, 'female', 38),
(1809, 'GU13', 13, 'female', 38),
(1810, 'GU11', 11, 'female', 38),
(1811, 'GU09', 9, 'female', 38),
(1812, 'BU19', 19, 'male', 39),
(1813, 'BU17', 17, 'male', 39),
(1814, 'BU15', 15, 'male', 39),
(1815, 'BU13', 13, 'male', 39),
(1816, 'BU11', 11, 'male', 39),
(1817, 'GU19', 19, 'female', 39),
(1818, 'GU17', 17, 'female', 39),
(1819, 'GU15', 15, 'female', 39),
(1820, 'GU13', 13, 'female', 39),
(1821, 'GU11', 11, 'female', 39),
(1822, 'BU19', 19, 'male', 35),
(1823, 'BU17', 17, 'male', 35),
(1824, 'BU15', 15, 'male', 35),
(1825, 'BU13', 13, 'male', 35),
(1826, 'BU11', 11, 'male', 35),
(1827, 'GU19', 19, 'female', 35),
(1828, 'GU17', 17, 'female', 35),
(1829, 'GU15', 15, 'female', 35),
(1830, 'GU13', 13, 'female', 35),
(1831, 'GU11', 11, 'female', 35),
(1832, 'BU17', 17, 'male', 40),
(1833, 'BU15', 15, 'male', 40),
(1834, 'BU13', 13, 'male', 40),
(1835, 'BU11', 11, 'male', 40),
(1836, 'BU09', 9, 'male', 40),
(1837, 'GU17', 17, 'female', 40),
(1838, 'GU15', 15, 'female', 40),
(1839, 'GU13', 13, 'female', 40),
(1840, 'GU11', 11, 'female', 40),
(1841, 'GU09', 9, 'female', 40),
(1842, 'BU19', 19, 'male', 41),
(1843, 'BU17', 17, 'male', 41),
(1844, 'BU15', 15, 'male', 41),
(1845, 'BU13', 13, 'male', 41),
(1846, 'BU11', 11, 'male', 41),
(1847, 'GU19', 19, 'female', 41),
(1848, 'GU17', 17, 'female', 41),
(1849, 'GU15', 15, 'female', 41),
(1850, 'GU13', 13, 'female', 41),
(1851, 'GU11', 11, 'female', 41),
(1872, 'BU19', 19, 'male', 44),
(1873, 'BU17', 17, 'male', 44),
(1874, 'BU15', 15, 'male', 44),
(1875, 'BU13', 13, 'male', 44),
(1876, 'BU11', 11, 'male', 44),
(1877, 'GU19', 19, 'female', 44),
(1878, 'GU17', 17, 'female', 44),
(1879, 'GU15', 15, 'female', 44),
(1880, 'GU13', 13, 'female', 44),
(1881, 'GU11', 11, 'female', 44),
(1882, 'BU19', 19, 'male', 43),
(1883, 'BU17', 17, 'male', 43),
(1884, 'BU15', 15, 'male', 43),
(1885, 'BU13', 13, 'male', 43),
(1886, 'BU11', 11, 'male', 43),
(1887, 'GU19', 19, 'female', 43),
(1888, 'GU17', 17, 'female', 43),
(1889, 'GU15', 15, 'female', 43),
(1890, 'GU13', 13, 'female', 43),
(1891, 'GU11', 11, 'female', 43),
(1892, 'Boys Under 19 Open Championship', 19, 'Boys', 47),
(1893, 'Boys Under 17 Open Championship', 17, 'Boys', 47),
(1894, 'Boys Under 15 Open Championship', 15, 'Boys', 47),
(1895, 'Boys Under 13 Open Championship', 13, 'Boys', 47),
(1896, 'Boys Under 11 Open Championship', 11, 'Boys', 47),
(1897, 'Boys Under 09 Open Championship', 9, 'Boys', 47),
(1898, 'Boys Under 19 Open Championship', 19, 'Boys', 48),
(1899, 'Girls Under 19 Open Championship', 19, 'Girls', 48),
(1900, 'Boys Under 17 Open Championship', 17, 'Boys', 48),
(1901, 'Girls Under 17 Open Championship', 17, 'Girls', 48),
(1902, 'Boys Under 15 Open Championship', 15, 'Boys', 48),
(1903, 'Girls Under 15 Open Championship', 15, 'Girls', 48),
(1904, 'Boys Under 13 Open Championship', 13, 'Boys', 48),
(1905, 'Girls Under 13 Open Championship', 13, 'Girls', 48),
(1906, 'Boys Under 11 Open Championship', 11, 'Boys', 48),
(1907, 'Girls Under 11 Open Championship', 11, 'Girls', 48),
(1908, 'Boys Under 09 Open Championship', 9, 'Boys', 48),
(1909, 'Girls Under 09 Open Championship', 9, 'Girls', 48),
(1910, 'Boys Under 19 Open Championship', 19, 'Boys', 49),
(1911, 'Girls Under 19 Open Championship', 19, 'Girls', 49),
(1912, 'Boys Under 17 Open Championship', 17, 'Boys', 49),
(1913, 'Girls Under 17 Open Championship', 17, 'Girls', 49),
(1914, 'Boys Under 15 Open Championship', 15, 'Boys', 49),
(1915, 'Girls Under 15 Open Championship', 15, 'Girls', 49),
(1916, 'Boys Under 13 Open Championship', 13, 'Boys', 49),
(1917, 'Girls Under 13 Open Championship', 13, 'Girls', 49),
(1918, 'Boys Under 11 Open Championship', 11, 'Boys', 49),
(1919, 'Girls Under 11 Open Championship', 11, 'Girls', 49),
(1920, 'Boys Under 09 Open Championship', 9, 'Boys', 49),
(1921, 'Girls Under 09 Open Championship', 9, 'Girls', 49),
(1922, 'Boys Under 19 Open Championship', 19, 'Boys', 50),
(1923, 'Girls Under 19 Open Championship', 19, 'Girls', 50),
(1924, 'Boys Under 17 Open Championship', 17, 'Boys', 50),
(1925, 'Girls Under 17 Open Championship', 17, 'Girls', 50),
(1926, 'Boys Under 15 Open Championship', 15, 'Boys', 50),
(1927, 'Girls Under 15 Open Championship', 15, 'Girls', 50),
(1928, 'Boys Under 13 Open Championship', 13, 'Boys', 50),
(1929, 'Girls Under 13 Open Championship', 13, 'Girls', 50),
(1930, 'Boys Under 11 Open Championship', 11, 'Boys', 50),
(1931, 'Girls Under 11 Open Championship', 11, 'Girls', 50),
(1932, 'Boys Under 09 Open Championship', 9, 'Boys', 50),
(1933, 'Girls Under 09 Open Championship', 9, 'Girls', 50),
(1946, 'Boys Under 19 Open Championship', 0, '', 51),
(1947, 'Girls Under 19 Open Championship', 0, '', 51),
(1948, 'Boys Under 17 Open Championship', 0, '', 51),
(1949, 'Girls Under 17 Open Championship', 0, '', 51),
(1950, 'Boys Under 15 Open Championship', 0, '', 51),
(1951, 'Girls Under 15 Open Championship', 0, '', 51),
(1952, 'Girls Under 19 Open Championship', 0, '', 42),
(1991, 'Boys Under 19 Open Championship', 19, 'Boys', 52),
(1992, 'Girls Under 19 Open Championship', 19, 'Girls', 52),
(1993, 'Boys Under 17 Open Championship', 17, 'Boys', 52),
(1994, 'Girls Under 17 Open Championship', 17, 'Girls', 52),
(1995, 'Boys Under 15 Open Championship', 15, 'Boys', 52),
(1996, 'Girls Under 15 Open Championship', 15, 'Girls', 52),
(1997, 'Boys Under 13 Open Championship', 13, 'Boys', 52),
(1998, 'Girls Under 13 Open Championship', 13, 'Girls', 52),
(2014, 'Boys Under 19 Open Championship', 19, 'Boys', 53),
(2015, 'Girls Under 19 Open Championship', 19, 'Girls', 53),
(2016, 'Boys Under 17 Open Championship', 17, 'Boys', 53),
(2017, 'Girls Under 17 Open Championship', 17, 'Girls', 53),
(2018, 'Boys Under 15 Open Championship', 15, 'Boys', 53),
(2019, 'Girls Under 15 Open Championship', 15, 'Girls', 53),
(2020, 'Boys Under 13 Open Championship', 13, 'Boys', 53),
(2021, 'Girls Under 13 Open Championship', 13, 'Girls', 53),
(2022, 'Boys Under 11 Open Championship', 11, 'Boys', 53),
(2023, 'Girls Under 11 Open Championship', 11, 'Girls', 53),
(2024, 'Boys Under 09 Open Championship', 9, 'Boys', 53),
(2025, 'Girls Under 09 Open Championship', 9, 'Girls', 53),
(2028, 'Men', 0, 'Men', 54),
(2029, 'Women', 0, 'Women', 54),
(2032, 'Men', 0, 'Men', 57),
(2033, 'Women', 0, 'Women', 57),
(2034, 'Men', 0, 'Men', 58),
(2035, 'Women', 0, 'Women', 58);

-- --------------------------------------------------------

--
-- Table structure for table `tournament_platform_payments`
--

CREATE TABLE `tournament_platform_payments` (
  `paymentID` int(11) NOT NULL,
  `tournamentID` int(11) NOT NULL,
  `adminID` varchar(255) NOT NULL,
  `plan` enum('per_tournament','annual') NOT NULL DEFAULT 'per_tournament',
  `subscriptionID` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL,
  `status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `gateway` varchar(20) DEFAULT NULL,
  `gateway_reference` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tournament_platform_payments`
--

INSERT INTO `tournament_platform_payments` (`paymentID`, `tournamentID`, `adminID`, `plan`, `subscriptionID`, `amount`, `currency`, `status`, `gateway`, `gateway_reference`, `paid_at`, `created_at`) VALUES
(3, 54, 'AD0002', 'per_tournament', NULL, 50.00, 'USD', 'paid', 'test', 'TEST-20261005120432', '2026-10-05 12:04:32', '2026-10-05 12:04:17'),
(6, 57, 'AD0002', 'annual', 1, 50.00, 'USD', 'paid', 'card (test)', 'Visa **** 4242 / TEST-20261007122025', '2026-10-07 12:20:25', '2026-10-05 12:15:15'),
(10, 58, 'AD0002', 'annual', 1, 0.00, 'MYR', 'paid', 'annual', 'Annual plan until 2027-10-06', '2026-10-07 12:22:58', '2026-10-07 12:22:58');

-- --------------------------------------------------------

--
-- Table structure for table `tournament_rankings`
--

CREATE TABLE `tournament_rankings` (
  `rankingID` int(11) NOT NULL,
  `registrationID` int(11) NOT NULL,
  `tournamentID` int(11) NOT NULL,
  `playerID` varchar(255) NOT NULL,
  `category_registered` varchar(255) NOT NULL,
  `seed_number` int(11) DEFAULT NULL,
  `national_ranking` int(10) UNSIGNED DEFAULT NULL,
  `ajss_ranking` int(10) UNSIGNED DEFAULT NULL,
  `world_ranking` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tournament_rankings`
--

INSERT INTO `tournament_rankings` (`rankingID`, `registrationID`, `tournamentID`, `playerID`, `category_registered`, `seed_number`, `national_ranking`, `ajss_ranking`, `world_ranking`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'P0001', 'BU13', 3, 2, 3, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(2, 2, 2, 'P0002', 'BU13', 2, 5, 2, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(3, 3, 2, 'P0008', 'BU13', 4, 22, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(4, 4, 2, 'P0009', 'BU13', 1, NULL, 1, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(5, 5, 2, 'P0010', 'BU13', 5, NULL, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(6, 6, 52, 'P0001', 'BU15', 1, 2, 3, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(7, 7, 52, 'P0002', 'BU13', 1, 5, 2, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(8, 8, 52, 'P0003', 'BU17', 2, 5, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(9, 9, 52, 'P0004', 'BU15', 2, 13, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(10, 10, 52, 'P0005', 'BU17', 1, 1, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(11, 11, 52, 'P0006', 'BU19', 1, 9, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(12, 12, 42, 'P0007', 'BU13', 4, 6, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(13, 13, 42, 'P0008', 'BU15', 1, 22, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(14, 16, 42, 'P0008', 'BU13', 7, 22, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(15, 19, 49, 'P0001', 'Boys Under 17 Open Championship', NULL, 2, 3, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(16, 21, 47, 'P0001', 'BU19', NULL, 2, 3, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(17, 22, 50, 'P0011', 'GU17', 1, NULL, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(18, 23, 50, 'P0001', 'BU19', 1, 2, 3, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(19, 24, 50, 'P0008', 'BU15', 6, 22, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:21:07'),
(20, 25, 50, 'P0004', 'BU15', 5, 13, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:14:36'),
(21, 26, 50, 'P0003', 'BU17', 1, 5, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(22, 27, 50, 'P0001', 'BU15', 2, 2, 3, NULL, '2026-10-01 12:09:11', '2026-10-01 12:21:07'),
(23, 28, 50, 'P0002', 'BU15', 3, 1, 1, NULL, '2026-10-01 12:09:11', '2026-10-02 10:50:09'),
(24, 29, 50, 'P0005', 'BU15', 1, 1, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(25, 30, 50, 'P0009', 'BU15', 7, NULL, 1, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(26, 31, 50, 'P0010', 'BU15', 8, NULL, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(27, 32, 50, 'P0013', 'BU15', 9, NULL, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:09:11'),
(28, 35, 50, 'P0014', 'BU15', 4, 8, NULL, NULL, '2026-10-01 12:09:11', '2026-10-01 12:14:36'),
(29, 36, 57, 'P0001', 'Men', 3, 2, 3, NULL, '2026-10-09 11:28:38', '2026-10-09 12:02:47'),
(30, 37, 57, 'P0013', 'Men', 17, NULL, NULL, NULL, '2026-10-09 11:28:50', '2026-10-09 12:02:47'),
(31, 38, 57, 'P0002', 'Men', 7, 5, 2, NULL, '2026-10-09 11:29:05', '2026-10-09 12:02:47'),
(32, 39, 57, 'P0014', 'Men', 11, 8, NULL, NULL, '2026-10-09 11:29:18', '2026-10-09 12:02:47'),
(33, 40, 57, 'P0003', 'Men', 8, 5, NULL, NULL, '2026-10-09 11:29:29', '2026-10-09 12:02:47'),
(34, 41, 57, 'P0010', 'Men', 18, NULL, NULL, NULL, '2026-10-09 11:29:58', '2026-10-09 12:02:47'),
(35, 42, 57, 'P0011', 'Women', 12, NULL, NULL, NULL, '2026-10-09 11:30:10', '2026-10-09 12:04:01'),
(36, 43, 57, 'P0009', 'Men', 14, NULL, 1, NULL, '2026-10-09 11:30:21', '2026-10-09 12:02:47'),
(37, 44, 57, 'P0006', 'Women', 10, 9, NULL, NULL, '2026-10-09 11:30:43', '2026-10-09 12:04:01'),
(38, 45, 57, 'P0007', 'Women', 8, 6, NULL, NULL, '2026-10-09 11:31:01', '2026-10-09 12:04:01'),
(39, 46, 57, 'P0004', 'Men', 13, 13, NULL, NULL, '2026-10-09 11:31:34', '2026-10-09 12:02:47'),
(40, 47, 57, 'P0005', 'Men', 2, 1, NULL, NULL, '2026-10-09 11:32:30', '2026-10-09 12:02:47'),
(41, 48, 57, 'P0016', 'Men', 5, 3, 4, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(42, 49, 57, 'P0017', 'Men', 6, 4, 6, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(43, 50, 57, 'P0018', 'Men', 9, 6, NULL, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(44, 51, 57, 'P0019', 'Men', 4, 2, 5, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(45, 52, 57, 'P0020', 'Men', 10, 7, 8, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(46, 53, 57, 'P0021', 'Men', 15, NULL, 7, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(47, 54, 57, 'P0022', 'Men', 1, 1, 10, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(48, 55, 57, 'P0023', 'Men', 12, 9, NULL, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(49, 56, 57, 'P0024', 'Men', 19, NULL, NULL, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(50, 57, 57, 'P0025', 'Men', 16, NULL, 9, NULL, '2026-10-09 12:02:23', '2026-10-09 12:02:47'),
(51, 58, 57, 'P0026', 'Women', 2, 1, 2, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01'),
(52, 59, 57, 'P0027', 'Women', 3, 2, 3, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01'),
(53, 60, 57, 'P0028', 'Women', 1, 1, 1, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01'),
(54, 61, 57, 'P0029', 'Women', 7, 5, NULL, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01'),
(55, 62, 57, 'P0030', 'Women', 5, 3, 6, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01'),
(56, 63, 57, 'P0031', 'Women', 11, NULL, 5, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01'),
(57, 64, 57, 'P0032', 'Women', 4, 2, 7, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01'),
(58, 65, 57, 'P0033', 'Women', 9, 6, NULL, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01'),
(59, 66, 57, 'P0034', 'Women', 6, 4, 8, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01'),
(60, 67, 57, 'P0035', 'Women', 13, NULL, NULL, NULL, '2026-10-09 12:02:23', '2026-10-09 12:04:01');

-- --------------------------------------------------------

--
-- Table structure for table `tournament_register`
--

CREATE TABLE `tournament_register` (
  `registrationID` int(11) NOT NULL,
  `category_registered` varchar(255) NOT NULL,
  `category_remarks` varchar(255) DEFAULT NULL,
  `player_chinese_name` varchar(255) DEFAULT NULL,
  `player_tshirt` varchar(255) DEFAULT NULL,
  `accommodation` varchar(255) DEFAULT NULL,
  `player_latest_results` varchar(1000) DEFAULT NULL,
  `player_attachments` varchar(255) DEFAULT NULL,
  `amount_paid` varchar(255) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `payment_status` varchar(20) NOT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `endorsement` varchar(255) NOT NULL,
  `endorsement_date` date DEFAULT NULL,
  `admin_remark` varchar(255) DEFAULT NULL,
  `tournamentID` int(11) DEFAULT NULL,
  `playerID` varchar(255) DEFAULT NULL,
  `seed_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tournament_register`
--

INSERT INTO `tournament_register` (`registrationID`, `category_registered`, `category_remarks`, `player_chinese_name`, `player_tshirt`, `accommodation`, `player_latest_results`, `player_attachments`, `amount_paid`, `payment_date`, `payment_status`, `payment_proof`, `endorsement`, `endorsement_date`, `admin_remark`, `tournamentID`, `playerID`, `seed_number`) VALUES
(1, 'BU13', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'PAID', NULL, 'ENDORSED', '2024-11-15', NULL, 2, 'P0001', 3),
(2, 'BU13', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'NOT PAID', NULL, 'ENDORSED', '2024-11-15', NULL, 2, 'P0002', 2),
(3, 'BU13', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'NOT PAID', NULL, 'ENDORSED', '2024-11-15', NULL, 2, 'P0008', 4),
(4, 'BU13', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'NOT PAID', NULL, 'ENDORSED', '2024-11-15', NULL, 2, 'P0009', 1),
(5, 'BU13', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'NOT PAID', NULL, 'ENDORSED', '2024-11-15', NULL, 2, 'P0010', 5),
(6, 'BU15', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', '0', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2024-11-15', '', 52, 'P0001', 1),
(7, 'BU13', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'PAID', NULL, 'ENDORSED', '2024-11-15', NULL, 52, 'P0002', 1),
(8, 'BU17', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2024-11-15', '', 52, 'P0003', 2),
(9, 'BU15', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2024-11-15', '', 52, 'P0004', 2),
(10, 'BU17', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', '0', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2024-11-15', '', 52, 'P0005', 1),
(11, 'BU19', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2024-11-15', '', 52, 'P0006', 1),
(12, 'BU13', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'PAID', NULL, 'ENDORSED', '2024-11-15', NULL, 42, 'P0007', 4),
(13, 'BU15', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2026-09-21', '', 42, 'P0008', 1),
(16, 'BU13', '', NULL, 'KID-L (HALF CHEST 40.5CM)', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2026-09-21', '', 42, 'P0008', 7),
(19, 'Boys Under 17 Open Championship', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Not Paid', NULL, 'NOT ENDORSED', NULL, NULL, 49, 'P0001', NULL),
(21, 'BU19', NULL, NULL, 'S', NULL, '1', NULL, NULL, NULL, 'Not Paid', NULL, 'NOT ENDORSED', NULL, NULL, 47, 'P0001', NULL),
(22, 'GU17', NULL, NULL, 'S', NULL, NULL, NULL, NULL, NULL, 'PAID', NULL, 'ENDORSED', NULL, '', 50, 'P0011', 1),
(23, 'BU19', NULL, NULL, 'S', NULL, '1', NULL, NULL, NULL, 'PAID', NULL, 'ENDORSED', NULL, '', 50, 'P0001', 1),
(24, 'BU15', '', NULL, 'S', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2026-09-21', '', 50, 'P0008', 6),
(25, 'BU15', '', NULL, 'S', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2024-11-15', '', 50, 'P0004', 5),
(26, 'BU17', '', NULL, 'S', NULL, '', '', 'USD$80', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2024-11-15', '', 50, 'P0003', 1),
(27, 'BU15', '', NULL, 'S', NULL, '', '', '0', '2024-11-15 16:55:12', 'Paid', NULL, 'ENDORSED', '2024-11-15', '', 50, 'P0001', 2),
(28, 'BU15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAID', NULL, 'ENDORSED', '2026-09-30', NULL, 50, 'P0002', 3),
(29, 'BU15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAID', NULL, 'ENDORSED', '2026-09-30', NULL, 50, 'P0005', 1),
(30, 'BU15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAID', NULL, 'ENDORSED', '2026-09-30', NULL, 50, 'P0009', 7),
(31, 'BU15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAID', NULL, 'ENDORSED', '2026-09-30', NULL, 50, 'P0010', 8),
(32, 'BU15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAID', NULL, 'ENDORSED', '2026-09-30', NULL, 50, 'P0013', 9),
(35, 'BU15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PAID', NULL, 'ENDORSED', '2026-09-30', NULL, 50, 'P0014', 4),
(36, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 11:28:38', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0001', 3),
(37, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 11:28:50', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0013', 17),
(38, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 11:29:05', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0002', 7),
(39, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 11:29:18', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0014', 11),
(40, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 11:29:29', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0003', 8),
(41, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 11:29:58', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0010', 18),
(42, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 11:30:10', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0011', 12),
(43, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 11:30:21', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0009', 14),
(44, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 11:30:43', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0006', 10),
(45, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 11:31:01', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0007', 8),
(46, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 11:31:34', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0004', 13),
(47, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 11:32:30', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Registered by AD0002', 57, 'P0005', 2),
(48, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0016', 5),
(49, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0017', 6),
(50, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0018', 9),
(51, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0019', 4),
(52, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0020', 10),
(53, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0021', 15),
(54, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0022', 1),
(55, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0023', 12),
(56, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0024', 19),
(57, 'Men', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0025', 16),
(58, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0026', 2),
(59, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0027', 3),
(60, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0028', 1),
(61, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0029', 7),
(62, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0030', 5),
(63, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0031', 11),
(64, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0032', 4),
(65, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0033', 9),
(66, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0034', 6),
(67, 'Women', NULL, NULL, NULL, NULL, NULL, NULL, 'USD 50', '2026-10-09 12:02:23', 'PAID', NULL, 'ENDORSED', '2026-10-09', 'Test data', 57, 'P0035', 13);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`adminID`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`matchID`),
  ADD KEY `idx_tournament_event` (`tournamentID`,`category_registered`),
  ADD KEY `idx_player1` (`player1ID`),
  ADD KEY `idx_player2` (`player2ID`),
  ADD KEY `idx_winner` (`winnerID`);

--
-- Indexes for table `organizer_subscriptions`
--
ALTER TABLE `organizer_subscriptions`
  ADD PRIMARY KEY (`subscriptionID`),
  ADD KEY `idx_subscription_admin` (`adminID`,`end_date`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`playerID`);

--
-- Indexes for table `tournament`
--
ALTER TABLE `tournament`
  ADD PRIMARY KEY (`tournamentID`);

--
-- Indexes for table `tournament_category`
--
ALTER TABLE `tournament_category`
  ADD PRIMARY KEY (`categoryID`),
  ADD KEY `tournamentID` (`tournamentID`);

--
-- Indexes for table `tournament_platform_payments`
--
ALTER TABLE `tournament_platform_payments`
  ADD PRIMARY KEY (`paymentID`),
  ADD UNIQUE KEY `uq_platform_payment_tournament` (`tournamentID`),
  ADD KEY `idx_platform_payment_admin` (`adminID`);

--
-- Indexes for table `tournament_rankings`
--
ALTER TABLE `tournament_rankings`
  ADD PRIMARY KEY (`rankingID`),
  ADD UNIQUE KEY `uq_ranking_registration` (`registrationID`),
  ADD KEY `idx_ranking_tournament` (`tournamentID`,`category_registered`),
  ADD KEY `idx_ranking_player` (`playerID`);

--
-- Indexes for table `tournament_register`
--
ALTER TABLE `tournament_register`
  ADD PRIMARY KEY (`registrationID`),
  ADD KEY `fk_register_tournament` (`tournamentID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `matchID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=382;

--
-- AUTO_INCREMENT for table `organizer_subscriptions`
--
ALTER TABLE `organizer_subscriptions`
  MODIFY `subscriptionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tournament`
--
ALTER TABLE `tournament`
  MODIFY `tournamentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `tournament_category`
--
ALTER TABLE `tournament_category`
  MODIFY `categoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2036;

--
-- AUTO_INCREMENT for table `tournament_platform_payments`
--
ALTER TABLE `tournament_platform_payments`
  MODIFY `paymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tournament_rankings`
--
ALTER TABLE `tournament_rankings`
  MODIFY `rankingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `tournament_register`
--
ALTER TABLE `tournament_register`
  MODIFY `registrationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `fk_match_tournament` FOREIGN KEY (`tournamentID`) REFERENCES `tournament` (`tournamentID`) ON DELETE CASCADE;

--
-- Constraints for table `tournament_platform_payments`
--
ALTER TABLE `tournament_platform_payments`
  ADD CONSTRAINT `fk_platform_payment_tournament` FOREIGN KEY (`tournamentID`) REFERENCES `tournament` (`tournamentID`) ON DELETE CASCADE;

--
-- Constraints for table `tournament_rankings`
--
ALTER TABLE `tournament_rankings`
  ADD CONSTRAINT `fk_ranking_player` FOREIGN KEY (`playerID`) REFERENCES `players` (`playerID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ranking_registration` FOREIGN KEY (`registrationID`) REFERENCES `tournament_register` (`registrationID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ranking_tournament` FOREIGN KEY (`tournamentID`) REFERENCES `tournament` (`tournamentID`) ON DELETE CASCADE;

--
-- Constraints for table `tournament_register`
--
ALTER TABLE `tournament_register`
  ADD CONSTRAINT `fk_register_tournament` FOREIGN KEY (`tournamentID`) REFERENCES `tournament` (`tournamentID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
