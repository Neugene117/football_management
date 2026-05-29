-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 27, 2026 at 02:35 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: ` football_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `module` varchar(40) DEFAULT NULL,
  `target_type` varchar(60) DEFAULT NULL,
  `target_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System audit log — tracks every important action for accountability';

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_type` enum('lineup','result') NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `submitted_by` bigint(20) UNSIGNED NOT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `review_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Approval workflow for lineups and match results';

-- --------------------------------------------------------

--
-- Table structure for table `competitions`
--

CREATE TABLE `competitions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `federation_id` bigint(20) UNSIGNED NOT NULL,
  `season_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `type` enum('league','cup','friendly','tournament') NOT NULL DEFAULT 'league',
  `logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Competitions (leagues, cups, tournaments) organized by the federation';

-- --------------------------------------------------------

--
-- Table structure for table `competition_teams`
--

CREATE TABLE `competition_teams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teams enrolled in a specific competition';

-- --------------------------------------------------------

--
-- Table structure for table `federations`
--

CREATE TABLE `federations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `country` varchar(80) DEFAULT NULL,
  `founded_year` year(4) DEFAULT NULL,
  `contact_email` varchar(120) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Football federation / governing body';

-- --------------------------------------------------------

--
-- Table structure for table `formations`
--

CREATE TABLE `formations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(10) NOT NULL,
  `display_name` varchar(40) NOT NULL,
  `defenders` tinyint(3) UNSIGNED NOT NULL,
  `midfielders` tinyint(3) UNSIGNED NOT NULL,
  `forwards` tinyint(3) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Football tactical formations';

--
-- Dumping data for table `formations`
--

INSERT INTO `formations` (`id`, `name`, `display_name`, `defenders`, `midfielders`, `forwards`, `is_active`, `created_at`) VALUES
(1, '4-3-3', 'Four-Three-Three', 4, 3, 3, 1, '2026-05-26 13:54:10'),
(2, '4-4-2', 'Four-Four-Two', 4, 4, 2, 1, '2026-05-26 13:54:10'),
(3, '3-5-2', 'Three-Five-Two', 3, 5, 2, 1, '2026-05-26 13:54:10'),
(4, '3-4-3', 'Three-Four-Three', 3, 4, 3, 1, '2026-05-26 13:54:10'),
(5, '5-3-2', 'Five-Three-Two', 5, 3, 2, 1, '2026-05-26 13:54:10'),
(6, '4-2-3-1', 'Four-Two-Three-One', 4, 5, 1, 1, '2026-05-26 13:54:10'),
(7, '5-4-1', 'Five-Four-One', 5, 4, 1, 1, '2026-05-26 13:54:10'),
(8, '4-1-4-1', 'Four-One-Four-One', 4, 5, 1, 1, '2026-05-26 13:54:10');

-- --------------------------------------------------------

--
-- Table structure for table `lineup_players`
--

CREATE TABLE `lineup_players` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lineup_id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `is_starter` tinyint(1) NOT NULL DEFAULT 1,
  `position_slot` varchar(30) DEFAULT NULL,
  `field_x` decimal(5,2) DEFAULT NULL,
  `field_y` decimal(5,2) DEFAULT NULL,
  `jersey_number_override` tinyint(3) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Players listed in a match lineup (starters and bench)';

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `federation_id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `home_team_id` bigint(20) UNSIGNED NOT NULL,
  `away_team_id` bigint(20) UNSIGNED NOT NULL,
  `stadium_id` bigint(20) UNSIGNED DEFAULT NULL,
  `match_date` date NOT NULL,
  `match_time` time DEFAULT NULL,
  `matchday` tinyint(3) UNSIGNED DEFAULT NULL,
  `round` varchar(30) DEFAULT NULL,
  `status` enum('scheduled','lineup_pending','lineup_approved','in_progress','completed','postponed','cancelled') NOT NULL DEFAULT 'scheduled',
  `scheduled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual matches scheduled between two teams';

-- --------------------------------------------------------

--
-- Table structure for table `match_events`
--

CREATE TABLE `match_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `match_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `assist_player_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` enum('goal','own_goal','yellow_card','red_card','second_yellow','injury','penalty_goal','penalty_missed') NOT NULL,
  `minute` tinyint(3) UNSIGNED DEFAULT NULL,
  `extra_time_min` tinyint(3) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Events that happened during a match (goals, cards, injuries)';

-- --------------------------------------------------------

--
-- Table structure for table `match_lineups`
--

CREATE TABLE `match_lineups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `match_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `formation_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_notes` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Team lineup submitted for a specific match';

-- --------------------------------------------------------

--
-- Table structure for table `match_officials`
--

CREATE TABLE `match_officials` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `match_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `role` enum('referee','assistant_1','assistant_2','fourth_official') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Referees and officials assigned to a match';

-- --------------------------------------------------------

--
-- Table structure for table `match_results`
--

CREATE TABLE `match_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `match_id` bigint(20) UNSIGNED NOT NULL,
  `home_score` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `away_score` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `home_possession_pct` tinyint(3) UNSIGNED DEFAULT NULL,
  `away_possession_pct` tinyint(3) UNSIGNED DEFAULT NULL,
  `home_shots` tinyint(3) UNSIGNED DEFAULT NULL,
  `away_shots` tinyint(3) UNSIGNED DEFAULT NULL,
  `home_shots_on_target` tinyint(3) UNSIGNED DEFAULT NULL,
  `away_shots_on_target` tinyint(3) UNSIGNED DEFAULT NULL,
  `home_corners` tinyint(3) UNSIGNED DEFAULT NULL,
  `away_corners` tinyint(3) UNSIGNED DEFAULT NULL,
  `home_fouls` tinyint(3) UNSIGNED DEFAULT NULL,
  `away_fouls` tinyint(3) UNSIGNED DEFAULT NULL,
  `status` enum('submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_notes` text DEFAULT NULL,
  `ststuss` enum('pending','approved','reject''') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Match final score and team statistics (requires federation approval)';

-- --------------------------------------------------------

--
-- Table structure for table `media_files`
--

CREATE TABLE `media_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `file_type` enum('image','video') NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(80) NOT NULL,
  `file_size_bytes` int(10) UNSIGNED NOT NULL,
  `width_px` smallint(5) UNSIGNED DEFAULT NULL,
  `height_px` smallint(5) UNSIGNED DEFAULT NULL,
  `duration_seconds` smallint(5) UNSIGNED DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Images and videos uploaded (player photos, match highlights, news covers)';

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `author_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `content` longtext NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Football news articles shown on the public homepage';

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(60) NOT NULL,
  `title` varchar(160) NOT NULL,
  `body` text DEFAULT NULL,
  `extra_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_data`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dashboard notifications for users (teams receive match schedule alerts, etc.)';

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `module` varchar(40) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fine-grained permission actions';

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`, `created_at`) VALUES
-- Dashboard
(1, 'Dashboard Access', 'dashboard.view', 'dashboard', 'Access the main dashboard', '2026-05-26 13:52:25'),

-- Teams
(2, 'Manage Teams', 'teams.manage', 'teams', 'Full team management access', '2026-05-26 13:52:25'),
(3, 'Approve Teams', 'teams.approve', 'teams', 'Approve team registrations', '2026-05-26 13:52:25'),
(4, 'View Teams', 'teams.view', 'teams', 'View team information', '2026-05-26 13:52:25'),
(5, 'Create Team', 'teams.create', 'teams', 'Register a new team', '2026-05-26 13:52:25'),
(6, 'Edit Team', 'teams.edit', 'teams', 'Modify team details', '2026-05-26 13:52:25'),
(7, 'Delete Team', 'teams.delete', 'teams', 'Remove a team from the system', '2026-05-26 13:52:25'),
(8, 'Activate Team', 'teams.activate', 'teams', 'Activate a registered team', '2026-05-26 13:52:25'),
(9, 'Deactivate Team', 'teams.deactivate', 'teams', 'Deactivate an active team', '2026-05-26 13:52:25'),

-- Users
(10, 'Manage Users', 'users.manage', 'users', 'Full user management access', '2026-05-26 13:52:25'),
(11, 'View Users', 'users.view', 'users', 'View user accounts', '2026-05-26 13:52:25'),
(12, 'Create User', 'users.create', 'users', 'Create new user accounts', '2026-05-26 13:52:25'),
(13, 'Edit User', 'users.edit', 'users', 'Modify user account details', '2026-05-26 13:52:25'),
(14, 'Delete User', 'users.delete', 'users', 'Remove user accounts', '2026-05-26 13:52:25'),
(15, 'Assign Role', 'users.assign_role', 'users', 'Assign roles to users', '2026-05-26 13:52:25'),

-- Roles & Permissions
(16, 'Manage Roles & Permissions', 'roles.manage', 'roles', 'Full role and permission management', '2026-05-26 13:52:25'),
(17, 'View Roles', 'roles.view', 'roles', 'View available roles', '2026-05-26 13:52:25'),
(18, 'Create Role', 'roles.create', 'roles', 'Create new roles', '2026-05-26 13:52:25'),
(19, 'Edit Role', 'roles.edit', 'roles', 'Modify existing roles', '2026-05-26 13:52:25'),
(20, 'Delete Role', 'roles.delete', 'roles', 'Remove roles from the system', '2026-05-26 13:52:25'),
(21, 'Assign Permissions', 'roles.assign_permissions', 'roles', 'Assign permissions to roles', '2026-05-26 13:52:25'),

-- Players
(22, 'Manage Players', 'players.manage', 'players', 'Full player management access', '2026-05-26 13:52:25'),
(23, 'View Players', 'players.view', 'players', 'View player profiles', '2026-05-26 13:52:25'),
(24, 'Register Player', 'players.create', 'players', 'Register new players', '2026-05-26 13:52:25'),
(25, 'Edit Player', 'players.edit', 'players', 'Modify player details', '2026-05-26 13:52:25'),
(26, 'Delete Player', 'players.delete', 'players', 'Remove player records', '2026-05-26 13:52:25'),
(27, 'Transfer Player', 'players.transfer', 'players', 'Transfer players between teams', '2026-05-26 13:52:25'),

-- Matches
(28, 'Manage Matches', 'matches.manage', 'matches', 'Full match management access', '2026-05-26 13:52:25'),
(29, 'View Matches', 'matches.view', 'matches', 'View match schedules and details', '2026-05-26 13:52:25'),
(30, 'Create Match', 'matches.create', 'matches', 'Schedule a new match', '2026-05-26 13:52:25'),
(31, 'Edit Match', 'matches.edit', 'matches', 'Modify match details', '2026-05-26 13:52:25'),
(32, 'Delete Match', 'matches.delete', 'matches', 'Cancel or remove a match', '2026-05-26 13:52:25'),
(33, 'Schedule Match', 'matches.schedule', 'matches', 'Schedule match dates and times', '2026-05-26 13:52:25'),
(34, 'Assign Match Officials', 'officials.manage', 'matches', 'Assign referees and officials', '2026-05-26 13:52:25'),

-- Match Lineups
(35, 'Manage Lineups', 'lineups.manage', 'lineups', 'Full lineup management access', '2026-05-26 13:52:25'),
(36, 'View Lineups', 'lineups.view', 'lineups', 'View match lineups', '2026-05-26 13:52:25'),
(37, 'Submit Lineup', 'lineups.submit', 'lineups', 'Submit team lineups for matches', '2026-05-26 13:52:25'),
(38, 'Approve Lineups', 'lineups.approve', 'approvals', 'Approve submitted lineups', '2026-05-26 13:52:25'),
(39, 'Reject Lineup', 'lineups.reject', 'lineups', 'Reject submitted lineups', '2026-05-26 13:52:25'),

-- Match Results
(40, 'Manage Results', 'results.manage', 'results', 'Full match result management', '2026-05-26 13:52:25'),
(41, 'View Results', 'results.view', 'results', 'View match results and statistics', '2026-05-26 13:52:25'),
(42, 'Submit Result', 'results.submit', 'results', 'Submit match results', '2026-05-26 13:52:25'),
(43, 'Approve Match Results', 'results.approve', 'approvals', 'Approve submitted match results', '2026-05-26 13:52:25'),
(44, 'Reject Result', 'results.reject', 'results', 'Reject submitted match results', '2026-05-26 13:52:25'),

-- Match Events
(45, 'Manage Match Events', 'match_events.manage', 'match_events', 'Full match event management', '2026-05-26 13:52:25'),
(46, 'View Match Events', 'match_events.view', 'match_events', 'View match events (goals, cards)', '2026-05-26 13:52:25'),
(47, 'Record Match Event', 'match_events.create', 'match_events', 'Record goals, cards, and injuries', '2026-05-26 13:52:25'),
(48, 'Edit Match Event', 'match_events.edit', 'match_events', 'Modify recorded match events', '2026-05-26 13:52:25'),
(49, 'Delete Match Event', 'match_events.delete', 'match_events', 'Remove recorded match events', '2026-05-26 13:52:25'),

-- Competitions
(50, 'Manage Competitions', 'competitions.manage', 'competitions', 'Full competition management', '2026-05-26 13:52:25'),
(51, 'View Competitions', 'competitions.view', 'competitions', 'View competitions and leagues', '2026-05-26 13:52:25'),
(52, 'Create Competition', 'competitions.create', 'competitions', 'Create new competitions', '2026-05-26 13:52:25'),
(53, 'Edit Competition', 'competitions.edit', 'competitions', 'Modify competition details', '2026-05-26 13:52:25'),
(54, 'Delete Competition', 'competitions.delete', 'competitions', 'Remove competitions', '2026-05-26 13:52:25'),
(55, 'Enroll Teams in Competition', 'competitions.enroll_teams', 'competitions', 'Add or remove teams from competitions', '2026-05-26 13:52:25'),

-- Federations
(56, 'Manage Federations', 'federations.manage', 'federations', 'Full federation management', '2026-05-26 13:52:25'),
(57, 'View Federations', 'federations.view', 'federations', 'View federation information', '2026-05-26 13:52:25'),
(58, 'Create Federation', 'federations.create', 'federations', 'Create new federations', '2026-05-26 13:52:25'),
(59, 'Edit Federation', 'federations.edit', 'federations', 'Modify federation details', '2026-05-26 13:52:25'),
(60, 'Delete Federation', 'federations.delete', 'federations', 'Remove federations', '2026-05-26 13:52:25'),

-- Stadiums
(61, 'Manage Stadiums', 'stadiums.manage', 'stadiums', 'Full stadium management', '2026-05-26 13:52:25'),
(62, 'View Stadiums', 'stadiums.view', 'stadiums', 'View stadium information', '2026-05-26 13:52:25'),
(63, 'Create Stadium', 'stadiums.create', 'stadiums', 'Add new stadiums', '2026-05-26 13:52:25'),
(64, 'Edit Stadium', 'stadiums.edit', 'stadiums', 'Modify stadium details', '2026-05-26 13:52:25'),
(65, 'Delete Stadium', 'stadiums.delete', 'stadiums', 'Remove stadiums', '2026-05-26 13:52:25'),

-- Seasons
(66, 'Manage Seasons', 'seasons.manage', 'seasons', 'Full season management', '2026-05-26 13:52:25'),
(67, 'View Seasons', 'seasons.view', 'seasons', 'View season information', '2026-05-26 13:52:25'),
(68, 'Create Season', 'seasons.create', 'seasons', 'Create new seasons', '2026-05-26 13:52:25'),
(69, 'Edit Season', 'seasons.edit', 'seasons', 'Modify season details', '2026-05-26 13:52:25'),
(70, 'Delete Season', 'seasons.delete', 'seasons', 'Remove seasons', '2026-05-26 13:52:25'),

-- News
(71, 'Manage News', 'news.manage', 'news', 'Full news management access', '2026-05-26 13:52:25'),
(72, 'View News', 'news.view', 'news', 'View news articles', '2026-05-26 13:52:25'),
(73, 'Create News', 'news.create', 'news', 'Write new news articles', '2026-05-26 13:52:25'),
(74, 'Edit News', 'news.edit', 'news', 'Modify news articles', '2026-05-26 13:52:25'),
(75, 'Delete News', 'news.delete', 'news', 'Remove news articles', '2026-05-26 13:52:25'),
(76, 'Publish News', 'news.publish', 'news', 'Publish or unpublish news articles', '2026-05-26 13:52:25'),

-- Media Files
(77, 'Manage Media', 'media.manage', 'media', 'Full media management access', '2026-05-26 13:52:25'),
(78, 'Upload Media', 'media.upload', 'media', 'Upload images and videos', '2026-05-26 13:52:25'),
(79, 'View Media', 'media.view', 'media', 'View uploaded media files', '2026-05-26 13:52:25'),
(80, 'Delete Media', 'media.delete', 'media', 'Delete uploaded media files', '2026-05-26 13:52:25'),

-- Reports
(81, 'View Reports', 'reports.view', 'reports', 'View system reports', '2026-05-26 13:52:25'),
(82, 'Export Reports', 'reports.export', 'reports', 'Export reports to PDF/CSV', '2026-05-26 13:52:25'),
(83, 'Generate Reports', 'reports.generate', 'reports', 'Generate custom reports', '2026-05-26 13:52:25'),

-- Activity Logs
(84, 'View Activity Logs', 'activity_logs.view', 'logs', 'View system audit logs', '2026-05-26 13:52:25'),
(85, 'Export Activity Logs', 'activity_logs.export', 'logs', 'Export audit logs', '2026-05-26 13:52:25'),

-- Settings
(86, 'Manage Settings', 'settings.manage', 'settings', 'Full system settings access', '2026-05-26 13:52:25'),
(87, 'View Settings', 'settings.view', 'settings', 'View system settings', '2026-05-26 13:52:25'),

-- Notifications
(88, 'View Notifications', 'notifications.view', 'notifications', 'View system notifications', '2026-05-26 13:52:25'),
(89, 'Send Notifications', 'notifications.send', 'notifications', 'Send notifications to users', '2026-05-26 13:52:25'),
(90, 'Delete Notifications', 'notifications.delete', 'notifications', 'Delete notifications', '2026-05-26 13:52:25'),

-- Approvals
(91, 'Approve Rankings', 'rankings.approve', 'approvals', 'Approve player ranking submissions', '2026-05-26 13:52:25'),
(92, 'Approve Ratings', 'ratings.approve', 'approvals', 'Approve player rating submissions', '2026-05-26 13:52:25'),
(93, 'Approve Statistics', 'statistics.approve', 'approvals', 'Approve player statistics submissions', '2026-05-26 13:52:25'),
(94, 'Approve Player Registrations', 'player_registrations.approve', 'approvals', 'Approve player registration requests', '2026-05-26 13:52:25'),

-- Player Ratings
(95, 'Manage Player Ratings', 'player_ratings.manage', 'player_ratings', 'Full player rating management', '2026-05-26 13:52:25'),
(96, 'Rate Player', 'player_ratings.rate', 'player_ratings', 'Submit player performance ratings', '2026-05-26 13:52:25'),
(97, 'View Player Ratings', 'player_ratings.view', 'player_ratings', 'View player ratings', '2026-05-26 13:52:25'),

-- Player Rankings
(98, 'Manage Player Rankings', 'player_rankings.manage', 'player_rankings', 'Full player ranking management', '2026-05-26 13:52:25'),
(99, 'View Player Rankings', 'player_rankings.view', 'player_rankings', 'View player rankings', '2026-05-26 13:52:25'),

-- Player Statistics
(100, 'Manage Player Statistics', 'player_statistics.manage', 'player_statistics', 'Full player statistics management', '2026-05-26 13:52:25'),
(101, 'View Player Statistics', 'player_statistics.view', 'player_statistics', 'View player statistics', '2026-05-26 13:52:25'),

-- Team Standings
(102, 'View Team Standings', 'standings.view', 'standings', 'View league standings', '2026-05-26 13:52:25'),
(103, 'Manage Team Standings', 'standings.manage', 'standings', 'Manage league standings', '2026-05-26 13:52:25'),

-- Team Rankings
(104, 'View Team Rankings', 'team_rankings.view', 'team_rankings', 'View team rankings', '2026-05-26 13:52:25'),
(105, 'Manage Team Rankings', 'team_rankings.manage', 'team_rankings', 'Manage team rankings', '2026-05-26 13:52:25'),

-- Substitutions
(106, 'Manage Substitutions', 'substitutions.manage', 'substitutions', 'Full substitution management', '2026-05-26 13:52:25'),
(107, 'Record Substitution', 'substitutions.create', 'substitutions', 'Record player substitutions', '2026-05-26 13:52:25'),

-- Formations
(108, 'Manage Formations', 'formations.manage', 'formations', 'Manage tactical formations', '2026-05-26 13:52:25'),
(109, 'View Formations', 'formations.view', 'formations', 'View available formations', '2026-05-26 13:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `players`
--

CREATE TABLE `players` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `last_name` varchar(60) NOT NULL,
  `photo_pl` text DEFAULT NULL,
  `jersey_number` tinyint(3) UNSIGNED DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `nationality` varchar(60) DEFAULT NULL,
  `position` enum('goalkeeper','defender','midfielder','forward') NOT NULL,
  `height_cm` smallint(5) UNSIGNED DEFAULT NULL,
  `weight_kg` tinyint(3) UNSIGNED DEFAULT NULL,
  `preferred_foot` enum('left','right','both') DEFAULT 'right',
  `biography` text DEFAULT NULL,
  `contract_start` date DEFAULT NULL,
  `contract_end` date DEFAULT NULL,
  `market_value` decimal(12,2) DEFAULT NULL,
  `status` enum('active','inactive','injured','suspended','transferred') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Players registered under a team';

-- --------------------------------------------------------

--
-- Table structure for table `player_rankings`
--

CREATE TABLE `player_rankings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `position` enum('goalkeeper','defender','midfielder','forward') NOT NULL,
  `rank_position` tinyint(3) UNSIGNED DEFAULT NULL,
  `total_score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `goals` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `assists` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `average_rating` decimal(4,2) DEFAULT NULL,
  `clean_sheets` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `ststuss` enum('pending','approved','reject''') NOT NULL DEFAULT 'pending',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Best player rankings per competition and position';

-- --------------------------------------------------------

--
-- Table structure for table `player_ratings`
--

CREATE TABLE `player_ratings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `match_id` bigint(20) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `coach_comment` text DEFAULT NULL,
  `performance_summary` text DEFAULT NULL,
  `highlight_video_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `ststuss` enum('pending','approved','reject''') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Player performance rating (0-100) submitted after each match';

-- --------------------------------------------------------

--
-- Table structure for table `player_statistics`
--

CREATE TABLE `player_statistics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `matches_played` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `matches_started` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `minutes_played` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `goals` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `assists` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `yellow_cards` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `red_cards` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `clean_sheets` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `saves` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `average_rating` decimal(4,2) DEFAULT NULL,
  `statuss` enum('pending','approved','reject''') NOT NULL DEFAULT 'pending',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cumulative player statistics per competition';

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(60) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `scope` enum('federation','club','global') NOT NULL DEFAULT 'club',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles that can be assigned to users';

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `scope`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'super_admin', 'global', 'Full system access', '2026-05-26 13:52:25', '2026-05-26 13:52:25'),
(2, 'Federation Admin', 'federation_admin', 'federation', 'Manages federation settings and approvals', '2026-05-26 13:52:25', '2026-05-26 13:52:25'),
(3, 'Match Manager', 'match_manager', 'federation', 'Creates and manages match schedules', '2026-05-26 13:52:25', '2026-05-26 13:52:25'),
(4, 'Result Manager', 'result_manager', 'federation', 'Approves submitted match results', '2026-05-26 13:52:25', '2026-05-26 13:52:25'),
(5, 'Club Admin', 'club_admin', 'club', 'Full access within a single club', '2026-05-26 13:52:25', '2026-05-26 13:52:25'),
(6, 'Team Manager', 'team_manager', 'club', 'Submits lineups and manages squad', '2026-05-26 13:52:25', '2026-05-26 13:52:25'),
(7, 'Player Manager', 'player_manager', 'club', 'Registers and updates player profiles', '2026-05-26 13:52:25', '2026-05-26 13:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permissions assigned to each role';

-- --------------------------------------------------------

--
-- Table structure for table `seasons`
--

CREATE TABLE `seasons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Football seasons shared across competitions';

-- --------------------------------------------------------

--
-- Table structure for table `stadiums`
--

CREATE TABLE `stadiums` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `city` varchar(80) DEFAULT NULL,
  `country` varchar(80) DEFAULT NULL,
  `capacity` int(10) UNSIGNED DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Football stadiums / venues';

-- --------------------------------------------------------

--
-- Table structure for table `substitutions`
--

CREATE TABLE `substitutions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `match_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `player_out_id` bigint(20) UNSIGNED NOT NULL,
  `player_in_id` bigint(20) UNSIGNED NOT NULL,
  `minute` tinyint(3) UNSIGNED DEFAULT NULL,
  `sub_number` tinyint(3) UNSIGNED NOT NULL,
  `reason` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Substitutions made during a match (max 5 per team)';

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `federation_id` bigint(20) UNSIGNED NOT NULL,
  `home_stadium_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `short_name` varchar(10) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `primary_color` char(7) DEFAULT NULL,
  `secondary_color` char(7) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `country` varchar(80) DEFAULT NULL,
  `founded_year` year(4) DEFAULT NULL,
  `coach_name` varchar(120) DEFAULT NULL,
  `contact_email` varchar(120) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `activated_at` timestamp NULL DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Football clubs / teams registered with a federation';

-- --------------------------------------------------------

--
-- Table structure for table `team_rankings`
--

CREATE TABLE `team_rankings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `rank_position` tinyint(3) UNSIGNED DEFAULT NULL,
  `total_score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `average_rating` decimal(4,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Best team rankings per competition';

-- --------------------------------------------------------

--
-- Table structure for table `team_standings`
--

CREATE TABLE `team_standings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `position` tinyint(3) UNSIGNED DEFAULT NULL,
  `matches_played` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `wins` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `draws` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `losses` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `goals_for` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `goals_against` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `goal_difference` smallint(6) NOT NULL DEFAULT 0,
  `points` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `average_rating` decimal(4,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='League table / standings per team per competition';

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `user_type` enum('federation','club','admin') NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='All system login accounts (federation staff, club staff, admins)';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `profile_photo`, `phone`, `user_type`, `entity_id`, `is_active`, `last_login_at`, `email_verified_at`, `created_at`, `updated_at`) VALUES
(1, 'ne', 'ne@gmail.com', '$2y$10$4pOFQaUWT.4EkJE0/PuXfeAVxiZJduzWlCywvMbSWDpHHGtimcmv2', 'M.ne', NULL, '0785750117', 'admin', NULL, 1, NULL, NULL, '2026-05-26 14:32:39', '2026-05-26 14:32:39');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `granted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles assigned to users';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alog_user` (`user_id`),
  ADD KEY `idx_alog_action` (`action`),
  ADD KEY `idx_alog_target` (`target_type`,`target_id`),
  ADD KEY `idx_alog_created` (`created_at`);

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_approvals_item` (`item_type`,`item_id`),
  ADD KEY `idx_approvals_status` (`status`),
  ADD KEY `fk_approvals_submitted` (`submitted_by`),
  ADD KEY `fk_approvals_reviewed` (`reviewed_by`);

--
-- Indexes for table `competitions`
--
ALTER TABLE `competitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_competitions_slug` (`slug`),
  ADD KEY `idx_competitions_federation` (`federation_id`),
  ADD KEY `idx_competitions_season` (`season_id`);

--
-- Indexes for table `competition_teams`
--
ALTER TABLE `competition_teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_comp_team` (`competition_id`,`team_id`),
  ADD KEY `idx_ct_competition` (`competition_id`),
  ADD KEY `idx_ct_team` (`team_id`);

--
-- Indexes for table `federations`
--
ALTER TABLE `federations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_federations_slug` (`slug`);

--
-- Indexes for table `formations`
--
ALTER TABLE `formations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_formations_name` (`name`);

--
-- Indexes for table `lineup_players`
--
ALTER TABLE `lineup_players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lp_lineup_player` (`lineup_id`,`player_id`),
  ADD KEY `idx_lp_lineup` (`lineup_id`),
  ADD KEY `idx_lp_player` (`player_id`),
  ADD KEY `idx_lp_starter` (`is_starter`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_matches_competition` (`competition_id`),
  ADD KEY `idx_matches_home` (`home_team_id`),
  ADD KEY `idx_matches_away` (`away_team_id`),
  ADD KEY `idx_matches_date` (`match_date`),
  ADD KEY `idx_matches_status` (`status`),
  ADD KEY `idx_matches_federation` (`federation_id`),
  ADD KEY `fk_matches_stadium` (`stadium_id`),
  ADD KEY `fk_matches_scheduler` (`scheduled_by`);

--
-- Indexes for table `match_events`
--
ALTER TABLE `match_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_me_match` (`match_id`),
  ADD KEY `idx_me_team` (`team_id`),
  ADD KEY `idx_me_player` (`player_id`),
  ADD KEY `idx_me_type` (`event_type`),
  ADD KEY `fk_me_assist_player` (`assist_player_id`);

--
-- Indexes for table `match_lineups`
--
ALTER TABLE `match_lineups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lineup_match_team` (`match_id`,`team_id`),
  ADD KEY `idx_ml_match` (`match_id`),
  ADD KEY `idx_ml_team` (`team_id`),
  ADD KEY `idx_ml_status` (`status`),
  ADD KEY `fk_ml_formation` (`formation_id`),
  ADD KEY `fk_ml_submitted_by` (`submitted_by`),
  ADD KEY `fk_ml_approved_by` (`approved_by`);

--
-- Indexes for table `match_officials`
--
ALTER TABLE `match_officials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_official_match_role` (`match_id`,`role`),
  ADD KEY `idx_officials_match` (`match_id`);

--
-- Indexes for table `match_results`
--
ALTER TABLE `match_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_result_match` (`match_id`),
  ADD KEY `idx_mr_status` (`status`),
  ADD KEY `fk_mr_submitted_by` (`submitted_by`),
  ADD KEY `fk_mr_approved_by` (`approved_by`);

--
-- Indexes for table `media_files`
--
ALTER TABLE `media_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_media_stored_name` (`stored_name`),
  ADD KEY `idx_media_uploader` (`uploaded_by`),
  ADD KEY `idx_media_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_media_file_type` (`file_type`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_news_slug` (`slug`),
  ADD KEY `idx_news_author` (`author_id`),
  ADD KEY `idx_news_published` (`is_published`,`published_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_unread` (`user_id`,`is_read`),
  ADD KEY `idx_notif_type` (`type`),
  ADD KEY `idx_notif_created` (`created_at`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_slug` (`slug`),
  ADD KEY `idx_permissions_module` (`module`);

--
-- Indexes for table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_players_team_jersey` (`team_id`,`jersey_number`),
  ADD KEY `idx_players_team` (`team_id`),
  ADD KEY `idx_players_position` (`position`),
  ADD KEY `idx_players_nationality` (`nationality`),
  ADD KEY `idx_players_last_name` (`last_name`),
  ADD KEY `idx_players_status` (`status`);

--
-- Indexes for table `player_rankings`
--
ALTER TABLE `player_rankings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prank_player_comp` (`player_id`,`competition_id`),
  ADD KEY `idx_prank_competition` (`competition_id`),
  ADD KEY `idx_prank_position` (`position`),
  ADD KEY `idx_prank_score` (`total_score`);

--
-- Indexes for table `player_ratings`
--
ALTER TABLE `player_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rating_player_match` (`player_id`,`match_id`);

--
-- Indexes for table `player_statistics`
--
ALTER TABLE `player_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pstats_player_comp` (`player_id`,`competition_id`),
  ADD KEY `idx_pstats_player` (`player_id`),
  ADD KEY `idx_pstats_competition` (`competition_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_slug` (`slug`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_permission` (`role_id`,`permission_id`),
  ADD KEY `idx_rp_role` (`role_id`),
  ADD KEY `idx_rp_permission` (`permission_id`);

--
-- Indexes for table `seasons`
--
ALTER TABLE `seasons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seasons_name` (`name`);

--
-- Indexes for table `stadiums`
--
ALTER TABLE `stadiums`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `substitutions`
--
ALTER TABLE `substitutions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subs_match` (`match_id`),
  ADD KEY `idx_subs_team` (`team_id`),
  ADD KEY `fk_subs_player_out` (`player_out_id`),
  ADD KEY `fk_subs_player_in` (`player_in_id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_teams_slug` (`slug`),
  ADD KEY `idx_teams_federation` (`federation_id`),
  ADD KEY `idx_teams_active` (`is_active`),
  ADD KEY `fk_teams_stadium` (`home_stadium_id`);

--
-- Indexes for table `team_rankings`
--
ALTER TABLE `team_rankings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_trank_team_comp` (`team_id`,`competition_id`),
  ADD KEY `idx_trank_competition` (`competition_id`),
  ADD KEY `idx_trank_score` (`total_score`);

--
-- Indexes for table `team_standings`
--
ALTER TABLE `team_standings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_standings_team_comp` (`team_id`,`competition_id`),
  ADD KEY `idx_standings_team` (`team_id`),
  ADD KEY `idx_standings_competition` (`competition_id`),
  ADD KEY `idx_standings_points` (`points`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_type` (`user_type`),
  ADD KEY `idx_users_entity` (`entity_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_role` (`user_id`,`role_id`),
  ADD KEY `idx_ur_user` (`user_id`),
  ADD KEY `idx_ur_role` (`role_id`),
  ADD KEY `fk_ur_granted_by` (`granted_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `competitions`
--
ALTER TABLE `competitions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `competition_teams`
--
ALTER TABLE `competition_teams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `federations`
--
ALTER TABLE `federations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `lineup_players`
--
ALTER TABLE `lineup_players`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_events`
--
ALTER TABLE `match_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_lineups`
--
ALTER TABLE `match_lineups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_officials`
--
ALTER TABLE `match_officials`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_results`
--
ALTER TABLE `match_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media_files`
--
ALTER TABLE `media_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `players`
--
ALTER TABLE `players`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player_rankings`
--
ALTER TABLE `player_rankings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player_ratings`
--
ALTER TABLE `player_ratings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player_statistics`
--
ALTER TABLE `player_statistics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seasons`
--
ALTER TABLE `seasons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stadiums`
--
ALTER TABLE `stadiums`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `substitutions`
--
ALTER TABLE `substitutions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_rankings`
--
ALTER TABLE `team_rankings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_standings`
--
ALTER TABLE `team_standings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_alog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `fk_approvals_reviewed` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_approvals_submitted` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `competitions`
--
ALTER TABLE `competitions`
  ADD CONSTRAINT `fk_competitions_federation` FOREIGN KEY (`federation_id`) REFERENCES `federations` (`id`),
  ADD CONSTRAINT `fk_competitions_season` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`);

--
-- Constraints for table `competition_teams`
--
ALTER TABLE `competition_teams`
  ADD CONSTRAINT `fk_ct_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ct_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lineup_players`
--
ALTER TABLE `lineup_players`
  ADD CONSTRAINT `fk_lp_lineup` FOREIGN KEY (`lineup_id`) REFERENCES `match_lineups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lp_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `fk_matches_away_team` FOREIGN KEY (`away_team_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `fk_matches_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`),
  ADD CONSTRAINT `fk_matches_federation` FOREIGN KEY (`federation_id`) REFERENCES `federations` (`id`),
  ADD CONSTRAINT `fk_matches_home_team` FOREIGN KEY (`home_team_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `fk_matches_scheduler` FOREIGN KEY (`scheduled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_matches_stadium` FOREIGN KEY (`stadium_id`) REFERENCES `stadiums` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `match_events`
--
ALTER TABLE `match_events`
  ADD CONSTRAINT `fk_me_assist_player` FOREIGN KEY (`assist_player_id`) REFERENCES `players` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_me_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_me_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `fk_me_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Constraints for table `match_lineups`
--
ALTER TABLE `match_lineups`
  ADD CONSTRAINT `fk_ml_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ml_formation` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`),
  ADD CONSTRAINT `fk_ml_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ml_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ml_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Constraints for table `match_officials`
--
ALTER TABLE `match_officials`
  ADD CONSTRAINT `fk_officials_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `match_results`
--
ALTER TABLE `match_results`
  ADD CONSTRAINT `fk_mr_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mr_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mr_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `media_files`
--
ALTER TABLE `media_files`
  ADD CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `fk_news_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `players`
--
ALTER TABLE `players`
  ADD CONSTRAINT `fk_players_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Constraints for table `player_rankings`
--
ALTER TABLE `player_rankings`
  ADD CONSTRAINT `fk_prank_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prank_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `player_statistics`
--
ALTER TABLE `player_statistics`
  ADD CONSTRAINT `fk_pstats_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pstats_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `substitutions`
--
ALTER TABLE `substitutions`
  ADD CONSTRAINT `fk_subs_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subs_player_in` FOREIGN KEY (`player_in_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `fk_subs_player_out` FOREIGN KEY (`player_out_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `fk_subs_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `fk_teams_federation` FOREIGN KEY (`federation_id`) REFERENCES `federations` (`id`),
  ADD CONSTRAINT `fk_teams_stadium` FOREIGN KEY (`home_stadium_id`) REFERENCES `stadiums` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `team_rankings`
--
ALTER TABLE `team_rankings`
  ADD CONSTRAINT `fk_trank_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_trank_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_standings`
--
ALTER TABLE `team_standings`
  ADD CONSTRAINT `fk_standings_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_standings_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_ur_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
