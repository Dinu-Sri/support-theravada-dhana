-- Separate, hashed reset tokens for administrator accounts.
-- Run once in phpMyAdmin before enabling Admin “Forgot password”.

CREATE TABLE IF NOT EXISTS `admin_password_reset_tokens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_reset_token_hash` (`token_hash`),
  KEY `idx_admin_reset_admin_expiry` (`admin_id`, `expires_at`),
  CONSTRAINT `admin_password_reset_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
