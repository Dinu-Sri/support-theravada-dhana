<?php
/**
 * API endpoint to check dhana booking availability
 */

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
    
    // Validate inputs
    if (empty($date) || $dhanaTypeId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Date and dhana type ID are required']);
        exit;
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format']);
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
    
    // Check if date is not too far in the future
    $maxDate = date('Y-m-d', strtotime('+30 days'));
    if ($date > $maxDate) {
        echo json_encode([
            'available' => false,
            'reason' => 'Bookings can only be made up to 30 days in advance'
        ]);
        exit;
    }
    
    $db = getDB();
    
    // Check if dhana type exists and is active
    $dhanaType = $db->fetchOne(
        "SELECT id, name, price FROM dhana_types WHERE id = ? AND is_active = 1",
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
    
    // Check if there's already a booking for this date and dhana type
    $existingBooking = $db->fetchOne(
        "SELECT id FROM bookings 
         WHERE booking_date = ? AND dhana_type_id = ? AND status NOT IN ('cancelled')",
        [$date, $dhanaTypeId]
    );
    
    if ($existingBooking) {
        echo json_encode([
            'available' => false,
            'reason' => 'This dhana type is already booked for the selected date'
        ]);
        exit;
    }
    
    // If we reach here, the slot is available
    echo json_encode([
        'available' => true,
        'dhana_type' => [
            'id' => $dhanaType['id'],
            'name' => $dhanaType['name'],
            'price' => $dhanaType['price']
        ],
        'date' => $date,
        'formatted_date' => date('F j, Y', strtotime($date))
    ]);
    
} catch (Exception $e) {
    error_log("Availability check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
