<?php
/**
 * Admin Calendar Data API
 * Provides calendar data for the admin calendar view
 */

session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(true);
header('Content-Type: application/json; charset=UTF-8');

try {
    $db = getDB();
    adminRequirePermission($db, ['view_calendar', 'view_booking_dates', 'view_availability'], true);
    
    // Get parameters
    $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $specificDate = isset($_GET['date']) ? $_GET['date'] : null;
    
    $bookingWindow = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'booking_advance_days'");
    $annualWindow = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'annual_booking_years'");
    $minimumYear = (int)date('Y') - 20;
    $maximumYear = (int)date('Y')
        + (int)ceil(max(1, (int)($bookingWindow['setting_value'] ?? 30)) / 365)
        + max(1, (int)($annualWindow['setting_value'] ?? 2));
    if ($month < 1 || $month > 12 || $year < $minimumYear || $year > $maximumYear) {
        adminJsonResponse(['success' => false, 'error' => 'The requested month is outside the configured calendar range.'], 400);
    }
    if ($specificDate !== null) {
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $specificDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $specificDate ||
            (int)$parsedDate->format('n') !== $month || (int)$parsedDate->format('Y') !== $year) {
            adminJsonResponse(['success' => false, 'error' => 'Invalid calendar date.'], 400);
        }
    }
    
    // Get dhana types
    $dhanaTypes = $db->fetchAll("SELECT * FROM dhana_types WHERE is_active = 1 ORDER BY price DESC");
    
    // Get all reservations for the current month
    $bookings = $db->fetchAll(
        "SELECT b.*, dt.name as dhana_type_name, dt.time_slot, u.first_name, u.last_name, u.email,
                pr.receipt_filename, pr.verified as receipt_verified,
                ab.year_start, ab.year_end
         FROM bookings b
         JOIN dhana_types dt ON b.dhana_type_id = dt.id
         JOIN users u ON b.user_id = u.id
         LEFT JOIN payment_receipts pr ON pr.id = (
         SELECT pr_latest.id FROM payment_receipts pr_latest
         WHERE pr_latest.booking_id = b.id
         ORDER BY pr_latest.upload_date DESC, pr_latest.id DESC
         LIMIT 1
     )
         LEFT JOIN annual_bookings ab ON ab.booking_id = COALESCE(b.parent_booking_id, b.id)
         WHERE MONTH(b.booking_date) = ? AND YEAR(b.booking_date) = ?
         AND b.status NOT IN ('cancelled')
         ORDER BY b.booking_date, dt.price DESC",
        [$month, $year]
    );
    
    // Get annual reservations that should appear in this month/year
    $annualBookings = $db->fetchAll(
        "SELECT b.*, dt.name as dhana_type_name, dt.time_slot, u.first_name, u.last_name, u.email,
                pr.receipt_filename, pr.verified as receipt_verified,
                ab.year_start, ab.year_end, 1 as is_annual_event
         FROM bookings b
         JOIN dhana_types dt ON b.dhana_type_id = dt.id
         JOIN users u ON b.user_id = u.id
         LEFT JOIN payment_receipts pr ON pr.id = (
         SELECT pr_latest.id FROM payment_receipts pr_latest
         WHERE pr_latest.booking_id = b.id
         ORDER BY pr_latest.upload_date DESC, pr_latest.id DESC
         LIMIT 1
     )
         JOIN annual_bookings ab ON ab.booking_id = COALESCE(b.parent_booking_id, b.id)
         WHERE MONTH(b.booking_date) = ?
         AND ? BETWEEN ab.year_start AND ab.year_end
         AND b.status NOT IN ('cancelled')
         ORDER BY b.booking_date, dt.price DESC",
        [$month, $year]
    );
    
    // Merge annual bookings with regular bookings for current year
    foreach ($annualBookings as $annualBooking) {
        $found = false;
        foreach ($bookings as $booking) {
            if ($booking['booking_date'] === $annualBooking['booking_date'] &&
                $booking['dhana_type_id'] === $annualBooking['dhana_type_id'] &&
                $booking['booking_time_slot'] === $annualBooking['booking_time_slot']) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            // Create a virtual booking entry for the annual event
            $annualDay = (int)date('d', strtotime($annualBooking['booking_date']));
            if (!checkdate($month, $annualDay, $year)) {
                continue;
            }
            $virtualDate = sprintf('%04d-%02d-%02d', $year, $month, $annualDay);
            $annualBooking['booking_date'] = $virtualDate;
            $bookings[] = $annualBooking;
        }
    }
    
    // Get blocked dates
    $blockedDates = $db->fetchAll(
        "SELECT blocked_date, reason 
         FROM blocked_dates 
         WHERE MONTH(blocked_date) = ? AND YEAR(blocked_date) = ?",
        [$month, $year]
    );
    
    // Calculate calendar info
    $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
    $firstDayOfWeek = date('w', mktime(0, 0, 0, $month, 1, $year));
    
    // Prepare response
    $response = [
        'success' => true,
        'month' => $month,
        'year' => $year,
        'daysInMonth' => $daysInMonth,
        'firstDayOfWeek' => $firstDayOfWeek,
        'bookings' => $bookings,
        'blockedDates' => $blockedDates,
        'dhanaTypes' => $dhanaTypes
    ];
    
    // If specific date requested, filter bookings for that date
    if ($specificDate) {
        $response['bookings'] = array_filter($bookings, function($booking) use ($specificDate) {
            return $booking['booking_date'] === $specificDate;
        });
        $response['bookings'] = array_values($response['bookings']); // Re-index array
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    adminLogException('Admin calendar data failed', $e);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load calendar data. Please try again.'
    ]);
}
?>
