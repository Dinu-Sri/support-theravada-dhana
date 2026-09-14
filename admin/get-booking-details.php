<?php
/**
 * Get Booking Details API
 * Retrieves detailed booking information for editing
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
    
    // Get reservation ID
    $bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$bookingId) {
        throw new Exception('Invalid reservation ID');
    }

    // Get reservation details with related information
    $booking = $db->fetchOne(
        "SELECT b.*, dt.name as dhana_type_name, dt.price as dhana_type_price,
                u.first_name, u.last_name, u.email, u.contact_number,
                agent.first_name as agent_first_name, agent.last_name as agent_last_name,
                agent.email as agent_email, agent.contact_number as agent_contact_number,
                pr.receipt_filename, pr.verified as receipt_verified,
                ab.year_start, ab.year_end,
                holder.first_name as held_by_first_name, holder.last_name as held_by_last_name
         FROM bookings b
         JOIN dhana_types dt ON b.dhana_type_id = dt.id
         JOIN users u ON b.user_id = u.id
         LEFT JOIN users agent ON b.booked_by_agent_id = agent.id
         LEFT JOIN payment_receipts pr ON b.id = pr.booking_id
         LEFT JOIN annual_bookings ab ON (b.id = ab.booking_id OR b.parent_booking_id = ab.booking_id)
         LEFT JOIN users holder ON b.held_by = holder.id
         WHERE b.id = ?",
        [$bookingId]
    );
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    // Convert boolean fields to proper boolean values
    $booking['travel_support'] = (bool)$booking['travel_support'];
    $booking['is_annual_event'] = (bool)$booking['is_annual_event'];
    $booking['is_monk'] = (bool)$booking['is_monk'];
    $booking['receipt_verified'] = (bool)$booking['receipt_verified'];
    $booking['on_hold'] = (bool)($booking['on_hold'] ?? 0);

    // Add hold information
    if ($booking['on_hold']) {
        $booking['hold_info'] = [
            'held_by_name' => trim(($booking['held_by_first_name'] ?? '') . ' ' . ($booking['held_by_last_name'] ?? '')),
            'held_at' => $booking['held_at'] ?? null,
            'hold_reason' => $booking['hold_reason'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $booking
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
