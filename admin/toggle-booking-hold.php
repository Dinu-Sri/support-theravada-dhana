<?php
/**
 * Toggle Booking Hold Status API
 * Allows administrators to place bookings on hold or remove hold
 */

session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $bookingId = isset($input['booking_id']) ? (int)$input['booking_id'] : 0;
    $action = isset($input['action']) ? $input['action'] : ''; // 'hold' or 'unhold'
    $holdReason = isset($input['hold_reason']) ? trim($input['hold_reason']) : '';
    
    if (!$bookingId) {
        throw new Exception('Invalid booking ID');
    }
    
    if (!in_array($action, ['hold', 'unhold'])) {
        throw new Exception('Invalid action. Must be "hold" or "unhold"');
    }
    
    // Get current booking
    $booking = $db->fetchOne(
        "SELECT id, status, on_hold FROM bookings WHERE id = ?",
        [$bookingId]
    );
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    // Perform action
    if ($action === 'hold') {
        // Place booking on hold
        if ($booking['on_hold']) {
            throw new Exception('Booking is already on hold');
        }
        
        if (empty($holdReason)) {
            $holdReason = 'Held by administrator for known donor';
        }
        
        $db->query(
            "UPDATE bookings 
             SET on_hold = 1, 
                 hold_reason = ?, 
                 held_by = ?, 
                 held_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?",
            [$holdReason, $_SESSION['admin_id'], $bookingId]
        );
        
        $message = 'Booking placed on hold successfully';
        
    } else {
        // Remove hold
        if (!$booking['on_hold']) {
            throw new Exception('Booking is not on hold');
        }
        
        $db->query(
            "UPDATE bookings 
             SET on_hold = 0, 
                 hold_reason = NULL, 
                 held_by = NULL, 
                 held_at = NULL,
                 updated_at = NOW()
             WHERE id = ?",
            [$bookingId]
        );
        
        $message = 'Hold removed successfully';
    }
    
    // Get updated booking info
    $updatedBooking = $db->fetchOne(
        "SELECT b.on_hold, b.hold_reason, b.held_at,
                u.first_name, u.last_name
         FROM bookings b
         LEFT JOIN users u ON b.held_by = u.id
         WHERE b.id = ?",
        [$bookingId]
    );
    
    $response = [
        'success' => true,
        'message' => $message,
        'data' => [
            'booking_id' => $bookingId,
            'on_hold' => (bool)$updatedBooking['on_hold'],
            'hold_reason' => $updatedBooking['hold_reason'],
            'held_at' => $updatedBooking['held_at'],
            'held_by_name' => $updatedBooking['on_hold'] 
                ? trim($updatedBooking['first_name'] . ' ' . $updatedBooking['last_name'])
                : null
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

