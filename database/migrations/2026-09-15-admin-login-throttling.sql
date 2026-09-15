-- Persistent throttling state for the separate administrator login.
-- Run once in phpMyAdmin before relying on cross-session login throttling.

CREATE TABLE IF NOT EXISTS `admin_login_attempts` (
  `identifier_hash` char(64) NOT NULL,
  `failure_count` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`identifier_hash`),
  KEY `idx_admin_login_attempts_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
