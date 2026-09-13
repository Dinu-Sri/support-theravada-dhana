<?php
/**
 * Admin Calendar Data API
 * Provides calendar data for the admin calendar view
 */

session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Get parameters
    $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $specificDate = isset($_GET['date']) ? $_GET['date'] : null;
    
    // Validate month and year
    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2030) {
        throw new Exception('Invalid month or year');
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
         LEFT JOIN payment_receipts pr ON b.id = pr.booking_id
         LEFT JOIN annual_bookings ab ON (b.id = ab.booking_id OR b.parent_booking_id = ab.booking_id)
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
         LEFT JOIN payment_receipts pr ON b.id = pr.booking_id
         JOIN annual_bookings ab ON (b.id = ab.booking_id OR b.parent_booking_id = ab.booking_id)
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
            $virtualDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' .
                          str_pad(date('d', strtotime($annualBooking['booking_date'])), 2, '0', STR_PAD_LEFT);
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
