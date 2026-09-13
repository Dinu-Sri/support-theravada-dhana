<?php
/**
 * Enhanced API endpoint to check dhana booking availability with time slots
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
$auth = getAuth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }
    
    $date = $input['date'] ?? '';
    $dhanaTypeId = (int)($input['dhana_type_id'] ?? 0);
    $timeSlot = $input['time_slot'] ?? '';
    
    // Validate inputs
    if (empty($date) || $dhanaTypeId <= 0 || empty($timeSlot)) {
        http_response_code(400);
        echo json_encode(['error' => 'Date, dhana type ID, and time slot are required']);
        exit;
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format']);
        exit;
    }
    
    // Validate time slot
    $validTimeSlots = ['morning', 'lunch', 'whole_day'];
    if (!in_array($timeSlot, $validTimeSlots)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid time slot']);
        exit;
    }
    
    // Check if date is not in the past
    if ($date < date('Y-m-d')) {
        echo json_encode([
            'available' => false,
            'reason' => 'Date is in the past'
        ]);
        exit;
    }
    
    $db = getDB();
    
    // Check if dhana type exists and is active
    $dhanaType = $db->fetchOne(
        "SELECT id, name, price, time_slot FROM dhana_types WHERE id = ? AND is_active = 1",
        [$dhanaTypeId]
    );
    
    if (!$dhanaType) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid dhana type']);
        exit;
    }
    
    // Check if dhana type is available (price > 0)
    if ($dhanaType['price'] <= 0) {
        echo json_encode([
            'available' => false,
            'reason' => 'This dhana type is coming soon'
        ]);
        exit;
    }
    
    // Check if the requested time slot is compatible with the dhana type
    if ($dhanaType['time_slot'] !== 'extra' && $dhanaType['time_slot'] !== $timeSlot) {
        // Allow whole_day bookings to override morning/lunch slots
        if (!($timeSlot === 'whole_day' && in_array($dhanaType['time_slot'], ['morning', 'lunch']))) {
            echo json_encode([
                'available' => false,
                'reason' => 'Time slot not compatible with selected dhana type'
            ]);
            exit;
        }
    }
    
    // Check if date is blocked
    $blockedDate = $db->fetchOne(
        "SELECT id FROM blocked_dates WHERE blocked_date = ?",
        [$date]
    );
    
    if ($blockedDate) {
        echo json_encode([
            'available' => false,
            'reason' => 'This date is blocked'
        ]);
        exit;
    }
    
    // Check for conflicting bookings
    $conflictingBookings = [];

    if ($timeSlot === 'whole_day') {
        // Whole day booking conflicts with ANY booking on the same date (regardless of dhana type)
        $conflictingBookings = $db->fetchAll(
            "SELECT id, booking_time_slot, dhana_type_id FROM bookings
             WHERE booking_date = ? AND status NOT IN ('cancelled')",
            [$date]
        );
    } else {
        // Specific time slot conflicts with:
        // 1. Same time slot for same dhana type
        // 2. ANY whole day booking (regardless of dhana type)
        $conflictingBookings = $db->fetchAll(
            "SELECT id, booking_time_slot, dhana_type_id FROM bookings
             WHERE booking_date = ? AND status NOT IN ('cancelled')
             AND (
                 (dhana_type_id = ? AND booking_time_slot = ?)
                 OR booking_time_slot = 'whole_day'
             )",
            [$date, $dhanaTypeId, $timeSlot]
        );
    }
    
    if (!empty($conflictingBookings)) {
        // Determine the specific reason based on the conflict type
        $reason = '';
        $hasWholeDayConflict = false;

        foreach ($conflictingBookings as $booking) {
            if ($booking['booking_time_slot'] === 'whole_day') {
                $hasWholeDayConflict = true;
                break;
            }
        }

        if ($timeSlot === 'whole_day') {
            $reason = 'Cannot book whole day - there are existing bookings for this date';
        } elseif ($hasWholeDayConflict) {
            $reason = 'This date is fully booked (whole day booking exists)';
        } else {
            $reason = 'This time slot is already booked for the selected dhana type';
        }

        echo json_encode([
            'available' => false,
            'reason' => $reason,
            'conflicts' => $conflictingBookings
        ]);
        exit;
    }
    
    // Check for annual events that might conflict
    if ($timeSlot === 'whole_day') {
        // Whole day booking conflicts with ANY annual booking on the same date
        $annualConflicts = $db->fetchAll(
            "SELECT b.id, b.booking_time_slot, b.dhana_type_id, ab.year_start, ab.year_end
             FROM bookings b
             JOIN annual_bookings ab ON b.id = ab.booking_id
             WHERE MONTH(b.booking_date) = MONTH(?)
             AND DAY(b.booking_date) = DAY(?)
             AND ? BETWEEN ab.year_start AND ab.year_end
             AND b.status NOT IN ('cancelled')",
            [$date, $date, date('Y', strtotime($date))]
        );
    } else {
        // Specific time slot conflicts with same time slot or whole day annual bookings
        $annualConflicts = $db->fetchAll(
            "SELECT b.id, b.booking_time_slot, b.dhana_type_id, ab.year_start, ab.year_end
             FROM bookings b
             JOIN annual_bookings ab ON b.id = ab.booking_id
             WHERE MONTH(b.booking_date) = MONTH(?)
             AND DAY(b.booking_date) = DAY(?)
             AND ? BETWEEN ab.year_start AND ab.year_end
             AND b.status NOT IN ('cancelled')
             AND (
                 (b.dhana_type_id = ? AND b.booking_time_slot = ?)
                 OR b.booking_time_slot = 'whole_day'
             )",
            [$date, $date, date('Y', strtotime($date)), $dhanaTypeId, $timeSlot]
        );
    }
    
    if (!empty($annualConflicts)) {
        echo json_encode([
            'available' => false,
            'reason' => 'This date conflicts with an annual booking',
            'annual_conflicts' => $annualConflicts
        ]);
        exit;
    }
    
    // If we reach here, the slot is available
    echo json_encode([
        'available' => true,
        'dhana_type' => [
            'id' => $dhanaType['id'],
            'name' => $dhanaType['name'],
            'price' => $dhanaType['price'],
            'time_slot' => $dhanaType['time_slot']
        ],
        'date' => $date,
        'time_slot' => $timeSlot,
        'formatted_date' => date('F j, Y', strtotime($date)),
        'message' => 'This slot is available for booking!'
    ]);
    
} catch (Exception $e) {
    error_log("Availability check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
