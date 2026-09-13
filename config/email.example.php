<?php
/**
 * Email Configuration Example
 * Copy this file to email.php and update with your credentials
 */

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'your-email@gmail.com'); // TODO: Update this
define('SMTP_PASSWORD', 'your-app-password'); // TODO: Update this (16-char App Password)
define('SMTP_FROM_EMAIL', 'your-email@gmail.com'); // TODO: Update this
define('SMTP_FROM_NAME', 'Dhana Reservation System');

// Password reset settings
define('RESET_TOKEN_EXPIRY', 3600); // 1 hour
define('RESET_LINK_BASE_URL', SITE_URL . '/reset-password.php');
?>

