-- Allow cancelled slots to be booked again. Concurrency is serialized by the
-- application using a per-date MySQL advisory lock inside a transaction.
SET @drop_legacy_index = IF(
  EXISTS(
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'bookings'
      AND index_name = 'unique_booking_date_slot'
  ),
  'ALTER TABLE `bookings` DROP INDEX `unique_booking_date_slot`',
  'SELECT 1'
);
PREPARE booking_migration_stmt FROM @drop_legacy_index;
EXECUTE booking_migration_stmt;
DEALLOCATE PREPARE booking_migration_stmt;

SET @add_conflict_index = IF(
  NOT EXISTS(
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'bookings'
      AND index_name = 'idx_booking_date_type_slot'
  ),
  'ALTER TABLE `bookings` ADD INDEX `idx_booking_date_type_slot` (`booking_date`, `dhana_type_id`, `booking_time_slot`)',
  'SELECT 1'
);
PREPARE booking_migration_stmt FROM @add_conflict_index;
EXECUTE booking_migration_stmt;
DEALLOCATE PREPARE booking_migration_stmt;
