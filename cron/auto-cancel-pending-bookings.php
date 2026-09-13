<?php
/**
 * Auto-Cancel Pending Bookings Cron Job
 * 
 * This script automatically cancels pending bookings that have not been confirmed
 * within the configured timeout period.
 * 
 * SETUP:
 * 1. Run this script via cron job every hour:
 *    0 * * * * /usr/bin/php /path/to/your/site/cron/auto-cancel-pending-bookings.php
 * 
 * 2. Or run manually from browser (for testing):
 *    http://yourdomain.com/cron/auto-cancel-pending-bookings.php?key=YOUR_SECRET_KEY
 */

// Security: Only allow execution from command line or with secret key
$isCommandLine = (php_sapi_name() === 'cli');
$hasValidKey = isset($_GET['key']) && $_GET['key'] === 'dhana_cron_2024'; // Change this secret key!

if (!$isCommandLine && !$hasValidKey) {
    http_response_code(403);
    die('Access denied. This script can only be run from command line or with valid key.');
}

// Load database configuration
require_once dirname(__DIR__) . '/config/database.php';

try {
    $db = getDB();
    
    // Get timeout setting from database
    $timeoutSetting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'pending_booking_timeout_hours'"
    );
    
    $timeoutHours = $timeoutSetting ? (int)$timeoutSetting['setting_value'] : 0;
    
    // If timeout is 0 or not set, auto-cancel is disabled
    if ($timeoutHours <= 0) {
        echo "Auto-cancel is disabled (timeout = 0)\n";
        exit(0);
    }
    
    // Calculate cutoff time
    $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$timeoutHours} hours"));
    
    echo "=== Auto-Cancel Pending Bookings ===\n";
    echo "Timeout: {$timeoutHours} hours\n";
    echo "Cutoff time: {$cutoffTime}\n";
    echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Find pending bookings that are past the timeout (excluding bookings on hold)
    $expiredBookings = $db->fetchAll(
        "SELECT b.id, b.booking_date, b.created_at, u.first_name, u.last_name, u.email, dt.name as dhana_type
         FROM bookings b
         JOIN users u ON b.user_id = u.id
         JOIN dhana_types dt ON b.dhana_type_id = dt.id
         WHERE b.status = 'pending'
         AND b.created_at < ?
         AND (b.on_hold IS NULL OR b.on_hold = 0)
         ORDER BY b.created_at ASC",
        [$cutoffTime]
    );
    
    $cancelledCount = 0;
    
    if (empty($expiredBookings)) {
        echo "No expired pending bookings found.\n";
    } else {
        echo "Found " . count($expiredBookings) . " expired pending booking(s):\n\n";
        
        foreach ($expiredBookings as $booking) {
            $bookingId = $booking['id'];
            $createdAt = $booking['created_at'];
            $hoursOld = round((strtotime('now') - strtotime($createdAt)) / 3600, 1);
            
            echo "Booking #{$bookingId}:\n";
            echo "  - Donor: {$booking['first_name']} {$booking['last_name']} ({$booking['email']})\n";
            echo "  - Dhana Type: {$booking['dhana_type']}\n";
            echo "  - Booking Date: {$booking['booking_date']}\n";
            echo "  - Created: {$createdAt} ({$hoursOld} hours ago)\n";
            
            // Cancel the booking
            $db->query(
                "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
                [$bookingId]
            );
            
            echo "  - Status: CANCELLED ✓\n";
            $cancelledCount++;

            // Send email notification to user
            try {
                require_once dirname(__DIR__) . '/includes/booking-emails.php';
                $emailService = getBookingEmailService();

                $bookingData = [
                    'user_email' => $booking['email'],
                    'user_name' => $booking['first_name'] . ' ' . $booking['last_name'],
                    'booking_id' => $bookingId,
                    'dhana_type' => $booking['dhana_type'],
                    'booking_date' => $booking['booking_date']
                ];

                $emailService->sendBookingCancelledEmail($bookingData, 'auto');
                echo "  - Email notification sent ✓\n\n";
            } catch (Exception $e) {
                echo "  - Email notification failed: " . $e->getMessage() . "\n\n";
            }
        }
    }
    
    // Update last cleanup timestamp
    $db->query(
        "UPDATE settings SET setting_value = ? WHERE setting_key = 'last_booking_cleanup'",
        [date('Y-m-d H:i:s')]
    );
    
    echo "=== Summary ===\n";
    echo "Total bookings cancelled: {$cancelledCount}\n";
    echo "Last cleanup: " . date('Y-m-d H:i:s') . "\n";
    echo "Next cleanup: Run this script again in 1 hour\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Auto-cancel cron error: " . $e->getMessage());
    exit(1);
}

