<?php
/**
 * Auto-Cancel Unconfirmed Bookings Before Dhana Date
 * 
 * This cron job should run daily at midnight (12:00 AM)
 * Cron schedule: 0 0 * * *
 * 
 * Purpose: Automatically cancel bookings that are NOT confirmed by admin
 * X days before the dhana date (configurable in settings)
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die('This script can only be run from command line or with manual_run parameter.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Database.php';

// Initialize database
$db = Database::getInstance();

// Log start
$logMessage = "[" . date('Y-m-d H:i:s') . "] Auto-cancel cron job started\n";
echo $logMessage;
error_log($logMessage);

try {
    // Get auto-cancel days setting
    $autoCancelSetting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'auto_cancel_days_before_dhana'"
    );
    
    $autoCancelDays = $autoCancelSetting ? (int)$autoCancelSetting['setting_value'] : 30;
    
    // If set to 0, auto-cancel is disabled
    if ($autoCancelDays === 0) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] Auto-cancel is disabled (set to 0 days)\n";
        echo $logMessage;
        error_log($logMessage);
        exit(0);
    }
    
    // Calculate the cutoff date (today + auto_cancel_days)
    $cutoffDate = date('Y-m-d', strtotime("+{$autoCancelDays} days"));
    
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Auto-cancel days: {$autoCancelDays}, Cutoff date: {$cutoffDate}\n";
    echo $logMessage;
    error_log($logMessage);
    
    // Find all bookings that:
    // 1. Are NOT confirmed (status != 'confirmed')
    // 2. Have dhana date <= cutoff date
    // 3. Are not already cancelled
    $bookingsToCancel = $db->fetchAll(
        "SELECT b.id, b.booking_date, b.status, dt.name as dhana_type_name, 
                u.first_name, u.last_name, u.email,
                DATEDIFF(b.booking_date, CURDATE()) as days_until_dhana
         FROM bookings b
         JOIN dhana_types dt ON b.dhana_type_id = dt.id
         JOIN users u ON b.user_id = u.id
         WHERE b.status != 'confirmed'
         AND b.status != 'cancelled'
         AND b.booking_date <= ?
         ORDER BY b.booking_date ASC",
        [$cutoffDate]
    );
    
    $cancelledCount = 0;
    
    if (empty($bookingsToCancel)) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] No bookings to cancel\n";
        echo $logMessage;
        error_log($logMessage);
    } else {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] Found " . count($bookingsToCancel) . " booking(s) to cancel\n";
        echo $logMessage;
        error_log($logMessage);
        
        foreach ($bookingsToCancel as $booking) {
            try {
                // Cancel the booking
                $db->query(
                    "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
                    [$booking['id']]
                );
                
                $cancelledCount++;
                
                $logMessage = sprintf(
                    "[%s] Cancelled booking #%d - %s %s (%s) - Dhana: %s (%d days away) - Status was: %s\n",
                    date('Y-m-d H:i:s'),
                    $booking['id'],
                    $booking['first_name'],
                    $booking['last_name'],
                    $booking['email'],
                    $booking['booking_date'],
                    $booking['days_until_dhana'],
                    $booking['status']
                );
                echo $logMessage;
                error_log($logMessage);
                
                // TODO: Send email notification to user about cancellation
                // You can implement email notification here if needed
                
            } catch (Exception $e) {
                $errorMessage = "[" . date('Y-m-d H:i:s') . "] Error cancelling booking #{$booking['id']}: " . $e->getMessage() . "\n";
                echo $errorMessage;
                error_log($errorMessage);
            }
        }
    }
    
    // Update last cleanup timestamp
    $existingSetting = $db->fetchOne(
        "SELECT id FROM settings WHERE setting_key = 'last_auto_cancel_cleanup'"
    );
    
    $currentTimestamp = date('Y-m-d H:i:s');
    
    if ($existingSetting) {
        $db->query(
            "UPDATE settings SET setting_value = ? WHERE setting_key = 'last_auto_cancel_cleanup'",
            [$currentTimestamp]
        );
    } else {
        $db->query(
            "INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)",
            ['last_auto_cancel_cleanup', $currentTimestamp, 'Last time auto-cancel cron job ran']
        );
    }
    
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Auto-cancel cron job completed. Cancelled {$cancelledCount} booking(s)\n";
    echo $logMessage;
    error_log($logMessage);
    
} catch (Exception $e) {
    $errorMessage = "[" . date('Y-m-d H:i:s') . "] CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo $errorMessage;
    error_log($errorMessage);
    exit(1);
}

exit(0);

