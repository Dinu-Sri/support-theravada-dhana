-- Dhana Booking System schema (no personal data)
-- Import this file first, then database/seed.sql
-- Compatible with MySQL 5.7+ / MariaDB 10.4+

--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_actions`
--

CREATE TABLE `admin_actions` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` enum('booking_update','user_status_change','settings_update','dhana_type_update','role_change','user_promotion','permission_change') NOT NULL,
  `action_description` text NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit_log`
--

CREATE TABLE `admin_audit_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_actor_date` (`admin_id`, `created_at`),
  KEY `idx_admin_audit_target` (`target_type`, `target_id`),
  CONSTRAINT `admin_audit_log_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `role` enum('administrator','editor','supervisor') DEFAULT 'editor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_login_attempts`
--

CREATE TABLE `admin_login_attempts` (
  `identifier_hash` char(64) NOT NULL,
  `failure_count` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`identifier_hash`),
  KEY `idx_admin_login_attempts_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `annual_bookings`
--

CREATE TABLE `annual_bookings` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `year_start` int(11) NOT NULL,
  `year_end` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `blocked_dates`
--

CREATE TABLE `blocked_dates` (
  `id` int(11) NOT NULL,
  `blocked_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booked_by_agent_id` int(11) DEFAULT NULL,
  `booked_for_first_name` varchar(50) DEFAULT NULL,
  `booked_for_last_name` varchar(50) DEFAULT NULL,
  `booked_for_primary_contact` varchar(20) DEFAULT NULL,
  `booked_for_secondary_contact` varchar(20) DEFAULT NULL,
  `dhana_type_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time_slot` enum('morning','lunch','whole_day') DEFAULT 'whole_day',
  `booking_time` time DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `travel_support` tinyint(1) DEFAULT 0,
  `is_annual_event` tinyint(1) DEFAULT 0,
  `is_monk` tinyint(1) DEFAULT 0,
  `parent_booking_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `is_price_tentative` tinyint(1) DEFAULT 0,
  `status` enum('pending','receipt_submitted','payment_pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `on_hold` tinyint(1) DEFAULT 0,
  `hold_reason` varchar(255) DEFAULT NULL,
  `held_by` int(11) DEFAULT NULL,
  `held_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `dhana_types`
--

CREATE TABLE `dhana_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `time_slot` enum('morning','lunch','whole_day','extra') DEFAULT 'whole_day',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `monthly_pricing`
--

CREATE TABLE `monthly_pricing` (
  `id` int(11) NOT NULL,
  `dhana_type_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_confirmed` tinyint(1) DEFAULT 1 COMMENT '1 = confirmed price, 0 = tentative/estimated',
  `notes` text DEFAULT NULL COMMENT 'Admin notes about this price',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL COMMENT 'Admin user who last updated this price'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Monthly pricing for dhana types';

--
--


-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `payment_receipts`
--

CREATE TABLE `payment_receipts` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pricing_history`
--

CREATE TABLE `pricing_history` (
  `id` int(11) NOT NULL,
  `monthly_pricing_id` int(11) NOT NULL,
  `dhana_type_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `new_price` decimal(10,2) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `change_reason` varchar(255) DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit trail for price changes';

-- --------------------------------------------------------

--
-- Table structure for table `role_hierarchy`
--

CREATE TABLE `role_hierarchy` (
  `id` int(11) NOT NULL,
  `role_name` enum('donor','agent','supervisor','editor','administrator') NOT NULL,
  `hierarchy_level` int(11) NOT NULL,
  `role_display_name` varchar(50) NOT NULL,
  `role_description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `role_migration_backup`
--

CREATE TABLE `role_migration_backup` (
  `id` int(11) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `original_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `original_role` varchar(50) DEFAULT NULL,
  `backup_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_name` enum('donor','agent','supervisor','editor','administrator') NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `permission_description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `is_monk` tinyint(1) DEFAULT 0,
  `role` enum('donor','agent','supervisor','editor','administrator') DEFAULT 'donor',
  `admin_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_role_changes`
--

CREATE TABLE `user_role_changes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_role` enum('donor','agent','supervisor','editor','administrator') NOT NULL,
  `new_role` enum('donor','agent','supervisor','editor','administrator') NOT NULL,
  `changed_by` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------


--

--
--


--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `annual_bookings`
--
ALTER TABLE `annual_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_date_type_slot` (`booking_date`,`dhana_type_id`,`booking_time_slot`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `booked_by_agent_id` (`booked_by_agent_id`),
  ADD KEY `dhana_type_id` (`dhana_type_id`),
  ADD KEY `parent_booking_id` (`parent_booking_id`),
  ADD KEY `held_by` (`held_by`),
  ADD KEY `idx_on_hold` (`on_hold`);

--
-- Indexes for table `dhana_types`
--
ALTER TABLE `dhana_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `monthly_pricing`
--
ALTER TABLE `monthly_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pricing` (`dhana_type_id`,`year`,`month`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_year_month` (`year`,`month`),
  ADD KEY `idx_dhana_type` (`dhana_type_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `pricing_history`
--
ALTER TABLE `pricing_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dhana_type_id` (`dhana_type_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_monthly_pricing` (`monthly_pricing_id`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- Indexes for table `role_hierarchy`
--
ALTER TABLE `role_hierarchy`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_migration_backup`
--
ALTER TABLE `role_migration_backup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_permission` (`role_name`,`permission_name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `admin_user_id` (`admin_user_id`);

--
-- Indexes for table `user_role_changes`
--
ALTER TABLE `user_role_changes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_actions`
--
ALTER TABLE `admin_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `annual_bookings`
--
ALTER TABLE `annual_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `dhana_types`
--
ALTER TABLE `dhana_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `monthly_pricing`
--
ALTER TABLE `monthly_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pricing_history`
--
ALTER TABLE `pricing_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `role_hierarchy`
--
ALTER TABLE `role_hierarchy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `role_migration_backup`
--
ALTER TABLE `role_migration_backup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `user_role_changes`
--
ALTER TABLE `user_role_changes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

-- --------------------------------------------------------

--

-- Views (no DEFINER, safe to import on any host)
DROP VIEW IF EXISTS `pending_admin_actions`;
CREATE VIEW `pending_admin_actions` AS
SELECT aa.id, aa.admin_id, aa.action_type, aa.action_description, aa.target_id,
  aa.old_values, aa.new_values, aa.status, aa.approved_by, aa.approved_at, aa.created_at,
  au.username AS admin_username, au.email AS admin_email, sau.username AS approver_username
FROM admin_actions aa
JOIN admin_users au ON aa.admin_id = au.id
LEFT JOIN admin_users sau ON aa.approved_by = sau.id
WHERE aa.status = 'pending';

DROP VIEW IF EXISTS `user_permissions`;
CREATE VIEW `user_permissions` AS
SELECT au.id AS admin_id, au.username, au.email, au.role,
  rh.role_display_name, rh.hierarchy_level,
  GROUP_CONCAT(rp.permission_name ORDER BY rp.permission_name ASC SEPARATOR ',') AS permissions
FROM admin_users au
LEFT JOIN role_hierarchy rh ON au.role = rh.role_name
LEFT JOIN role_permissions rp ON au.role = rp.role_name
WHERE au.is_active = 1
GROUP BY au.id, au.username, au.email, au.role, rh.role_display_name, rh.hierarchy_level;

-- Constraints for dumped tables
--

--
-- Constraints for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD CONSTRAINT `admin_actions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`),
  ADD CONSTRAINT `admin_actions_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `annual_bookings`
--
ALTER TABLE `annual_bookings`
  ADD CONSTRAINT `annual_bookings_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  ADD CONSTRAINT `blocked_dates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_5` FOREIGN KEY (`booked_by_agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`dhana_type_id`) REFERENCES `dhana_types` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`parent_booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_held_by_admin_fk` FOREIGN KEY (`held_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `monthly_pricing`
--
ALTER TABLE `monthly_pricing`
  ADD CONSTRAINT `monthly_pricing_ibfk_1` FOREIGN KEY (`dhana_type_id`) REFERENCES `dhana_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `monthly_pricing_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD CONSTRAINT `payment_receipts_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pricing_history`
--
ALTER TABLE `pricing_history`
  ADD CONSTRAINT `pricing_history_ibfk_1` FOREIGN KEY (`monthly_pricing_id`) REFERENCES `monthly_pricing` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pricing_history_ibfk_2` FOREIGN KEY (`dhana_type_id`) REFERENCES `dhana_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pricing_history_ibfk_3` FOREIGN KEY (`changed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `user_role_changes`
--
ALTER TABLE `user_role_changes`
  ADD CONSTRAINT `user_role_changes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_role_changes_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `admin_users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
