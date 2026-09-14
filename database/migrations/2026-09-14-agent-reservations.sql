-- Reservation-agent support for existing MariaDB 11.4 installations.
-- Run once in the same database as the application, after backing it up.

ALTER TABLE `users`
  MODIFY `role` enum('donor','agent','supervisor','editor','administrator') DEFAULT 'donor';

ALTER TABLE `user_role_changes`
  MODIFY `old_role` enum('donor','agent','supervisor','editor','administrator') NOT NULL,
  MODIFY `new_role` enum('donor','agent','supervisor','editor','administrator') NOT NULL;

ALTER TABLE `role_hierarchy`
  MODIFY `role_name` enum('donor','agent','supervisor','editor','administrator') NOT NULL;

ALTER TABLE `role_permissions`
  MODIFY `role_name` enum('donor','agent','supervisor','editor','administrator') NOT NULL;

ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `booked_by_agent_id` int(11) DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `booked_for_first_name` varchar(50) DEFAULT NULL AFTER `booked_by_agent_id`,
  ADD COLUMN IF NOT EXISTS `booked_for_last_name` varchar(50) DEFAULT NULL AFTER `booked_for_first_name`,
  ADD COLUMN IF NOT EXISTS `booked_for_primary_contact` varchar(20) DEFAULT NULL AFTER `booked_for_last_name`,
  ADD COLUMN IF NOT EXISTS `booked_for_secondary_contact` varchar(20) DEFAULT NULL AFTER `booked_for_primary_contact`,
  ADD KEY IF NOT EXISTS `idx_booked_by_agent_id` (`booked_by_agent_id`),
  ADD CONSTRAINT `bookings_booked_by_agent_fk` FOREIGN KEY (`booked_by_agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

INSERT INTO `role_hierarchy` (`role_name`, `hierarchy_level`, `role_display_name`, `role_description`)
SELECT 'agent', 1, 'Reservation Agent', 'Can make reservations for another person through the normal donor dashboard'
WHERE NOT EXISTS (SELECT 1 FROM `role_hierarchy` WHERE `role_name` = 'agent');

INSERT INTO `role_permissions` (`role_name`, `permission_name`, `permission_description`)
SELECT 'agent', permissions.permission_name, permissions.permission_description
FROM (
  SELECT 'check_availability' AS permission_name, 'Check booking availability' AS permission_description
  UNION ALL SELECT 'create_booking', 'Create booking requests'
  UNION ALL SELECT 'create_booking_for_others', 'Create booking requests on behalf of another person'
  UNION ALL SELECT 'edit_own_profile', 'Edit their own profile information'
  UNION ALL SELECT 'upload_payment', 'Upload payment receipts for their bookings'
  UNION ALL SELECT 'view_own_bookings', 'View booking history they created'
) AS permissions
WHERE NOT EXISTS (
  SELECT 1 FROM `role_permissions` rp
  WHERE rp.role_name = 'agent' AND rp.permission_name = permissions.permission_name
);
