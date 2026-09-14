-- Run once in phpMyAdmin before deploying the matching PHP update.
-- The held_by value is written from the admin session and therefore belongs to
-- admin_users, not the donor users table.

ALTER TABLE `bookings`
  DROP FOREIGN KEY `bookings_ibfk_4`;

UPDATE `bookings` b
LEFT JOIN `admin_users` au ON au.id = b.held_by
SET b.held_by = NULL
WHERE b.held_by IS NOT NULL
  AND au.id IS NULL;

ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_held_by_admin_fk`
  FOREIGN KEY (`held_by`) REFERENCES `admin_users` (`id`)
  ON DELETE SET NULL;
