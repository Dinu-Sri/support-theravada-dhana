<?php
/**
 * Delete Booking API
 * Deletes a booking from admin panel
 */

session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(true);
header('Content-Type: application/json; charset=UTF-8');

try {
    $db = getDB();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        adminJsonResponse(['success' => false, 'error' => 'Invalid request body.'], 400);
    }

    adminRequireCsrf($input, true);
    adminRequirePermission($db, 'delete_bookings', true);
    $bookingId = isset($input['booking_id']) ? (int)$input['booking_id'] : 0;
    
    if (!$bookingId) {
        throw new Exception('Invalid booking ID');
    }
    
    // Check if booking exists
    $booking = $db->fetchOne(
        "SELECT b.id, b.user_id, b.booking_date, dt.name as dhana_type_name, 
                u.first_name, u.last_name, u.email
         FROM bookings b
         JOIN dhana_types dt ON b.dhana_type_id = dt.id
         JOIN users u ON b.user_id = u.id
         WHERE b.id = ?",
        [$bookingId]
    );
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    // Start transaction
    $db->getConnection()->beginTransaction();
    
    try {
        // Delete related records first (due to foreign key constraints)
        
        // Delete payment receipts
        $db->query("DELETE FROM payment_receipts WHERE booking_id = ?", [$bookingId]);
        
        // Delete annual booking records
        $db->query("DELETE FROM annual_bookings WHERE booking_id = ?", [$bookingId]);
        
        // Delete child reservations (if this is a parent annual reservation)
        $childBookings = $db->fetchAll(
            "SELECT id FROM bookings WHERE parent_booking_id = ?",
            [$bookingId]
        );

        foreach ($childBookings as $child) {
            // Delete payment receipts for child reservations
            $db->query("DELETE FROM payment_receipts WHERE booking_id = ?", [$child['id']]);
            // Delete annual reservation records for child reservations
            $db->query("DELETE FROM annual_bookings WHERE booking_id = ?", [$child['id']]);
        }

        // Delete child reservations
        $db->query("DELETE FROM bookings WHERE parent_booking_id = ?", [$bookingId]);

        // Finally, delete the main reservation
        $db->query("DELETE FROM bookings WHERE id = ?", [$bookingId]);
        
        // Log the deletion (optional - you could create an admin_logs table)
        // For now, we'll just commit the transaction
        
        $db->getConnection()->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Reservation deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    adminLogException('Delete booking failed', $e);
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => 'Unable to delete the reservation. Please try again.'
    ]);
}
?>
