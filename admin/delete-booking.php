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
        adminJsonResponse(['success' => false, 'error' => 'Invalid reservation ID.'], 400);
    }
    
    // Start transaction
    $db->getConnection()->beginTransaction();
    
    try {
        $bookings = $db->fetchAll(
            "SELECT id, user_id, dhana_type_id, booking_date, booking_time_slot,
                    is_annual_event, parent_booking_id, total_amount, status
             FROM bookings
             WHERE id = ? OR parent_booking_id = ?
             ORDER BY id
             FOR UPDATE",
            [$bookingId, $bookingId]
        );
        if (!$bookings || !in_array($bookingId, array_map('intval', array_column($bookings, 'id')), true)) {
            $db->getConnection()->rollback();
            adminJsonResponse(['success' => false, 'error' => 'Reservation not found.'], 404);
        }

        $bookingIds = array_map('intval', array_column($bookings, 'id'));
        $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
        $receipts = $db->fetchAll(
            "SELECT receipt_filename FROM payment_receipts
             WHERE booking_id IN ({$placeholders}) AND receipt_filename IS NOT NULL",
            $bookingIds
        );

        $db->query(
            "INSERT INTO admin_audit_log
                (admin_id, action_type, target_type, target_id, before_data, metadata)
             VALUES (?, 'booking_delete', 'booking', ?, ?, ?)",
            [
                (int)$_SESSION['admin_id'],
                $bookingId,
                json_encode($bookings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode([
                    'series_size' => count($bookings),
                    'receipt_count' => count($receipts)
                ])
            ]
        );

        // Foreign keys cascade to annual rows, child bookings, and receipts.
        $db->query("DELETE FROM bookings WHERE id = ?", [$bookingId]);
        
        $db->getConnection()->commit();

        // Physical files are intentionally removed only after the database commit.
        // Failure is logged for maintenance without making a successful deletion look failed.
        $receiptDirectory = realpath(__DIR__ . '/../' . rtrim(UPLOAD_DIR, '/\\'));
        if ($receiptDirectory !== false) {
            foreach ($receipts as $receipt) {
                $receiptName = basename((string)$receipt['receipt_filename']);
                $receiptPath = realpath($receiptDirectory . DIRECTORY_SEPARATOR . $receiptName);
                if ($receiptPath !== false && strpos($receiptPath, $receiptDirectory . DIRECTORY_SEPARATOR) === 0 && is_file($receiptPath)) {
                    if (!@unlink($receiptPath)) {
                        error_log('Unable to remove one or more receipt files for deleted booking #' . $bookingId);
                    }
                }
            }
        }
        
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
    $migrationMissing = $e instanceof PDOException && strpos($e->getMessage(), 'admin_audit_log') !== false;
    adminJsonResponse([
        'success' => false,
        'error' => $migrationMissing
            ? 'Deletion auditing is not installed. Run the latest database migration, then try again.'
            : 'Unable to delete the reservation. Please try again.'
    ], 500);
}
?>
