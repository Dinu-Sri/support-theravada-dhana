<?php
/**
 * Get Booking Details API
 * Retrieves detailed booking information for editing
 */

session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(true);
header('Content-Type: application/json; charset=UTF-8');

try {
    $db = getDB();
    adminRequirePermission($db, 'view_booking_details', true);
    
    // Get reservation ID
    $bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$bookingId) {
        adminJsonResponse(['success' => false, 'error' => 'Invalid reservation ID.'], 400);
    }

    // Get reservation details with related information
    $booking = $db->fetchOne(
        "SELECT b.*, dt.name as dhana_type_name, dt.price as dhana_type_price,
                u.first_name, u.last_name, u.email, u.contact_number,
                agent.first_name as agent_first_name, agent.last_name as agent_last_name,
                agent.email as agent_email, agent.contact_number as agent_contact_number,
                pr.receipt_filename, pr.verified as receipt_verified,
                ab.year_start, ab.year_end,
                holder.username AS held_by_name
         FROM bookings b
         JOIN dhana_types dt ON b.dhana_type_id = dt.id
         JOIN users u ON b.user_id = u.id
         LEFT JOIN users agent ON b.booked_by_agent_id = agent.id
         LEFT JOIN payment_receipts pr ON pr.id = (
         SELECT pr_latest.id FROM payment_receipts pr_latest
         WHERE pr_latest.booking_id = b.id
         ORDER BY pr_latest.upload_date DESC, pr_latest.id DESC
         LIMIT 1
     )
         LEFT JOIN annual_bookings ab ON ab.booking_id = COALESCE(b.parent_booking_id, b.id)
         LEFT JOIN admin_users holder ON b.held_by = holder.id
         WHERE b.id = ?",
        [$bookingId]
    );
    
    if (!$booking) {
        adminJsonResponse(['success' => false, 'error' => 'Reservation not found.'], 404);
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
            'held_by_name' => $booking['held_by_name'] ?? '',
            'held_at' => $booking['held_at'] ?? null,
            'hold_reason' => $booking['hold_reason'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $booking
    ]);
    
} catch (Exception $e) {
    adminLogException('Get booking details failed', $e);
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load the reservation details. Please try again.'
    ]);
}
?>
