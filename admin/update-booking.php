<?php
/**
 * Update Reservation API
 * Updates reservation information from admin panel
 */

session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(true);
header('Content-Type: application/json; charset=UTF-8');

try {
    $db = getDB();
    adminRequireCsrf(null, true);
    adminRequirePermission($db, 'edit_personal_data', true);
    
    // Validate required fields
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $bookingDate = isset($_POST['booking_date']) ? $_POST['booking_date'] : '';
    $dhanaTypeId = isset($_POST['dhana_type_id']) ? (int)$_POST['dhana_type_id'] : 0;
    $bookingTimeSlot = isset($_POST['booking_time_slot']) ? $_POST['booking_time_slot'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $totalAmount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0;
    $specialRequests = isset($_POST['special_requests']) ? trim($_POST['special_requests']) : '';
    $travelSupport = isset($_POST['travel_support']) ? 1 : 0;
    $isMonk = isset($_POST['is_monk']) ? 1 : 0;
    
    // Validation
    if (!$bookingId) {
        throw new Exception('Invalid reservation ID');
    }

    if (!$bookingDate || !strtotime($bookingDate)) {
        throw new Exception('Invalid reservation date');
    }
    
    if (!$dhanaTypeId) {
        throw new Exception('Please select a dhana type');
    }
    
    if (!in_array($bookingTimeSlot, ['morning', 'lunch', 'whole_day'])) {
        throw new Exception('Invalid time slot');
    }
    
    if (!in_array($status, ['pending', 'receipt_submitted', 'payment_pending', 'confirmed', 'completed', 'cancelled'])) {
        throw new Exception('Invalid status');
    }
    
    if ($totalAmount < 0) {
        throw new Exception('Total amount cannot be negative');
    }
    
    // Check if reservation exists and get full details for email
    $existingBooking = $db->fetchOne(
        "SELECT b.*, dt.name as dhana_type_name, u.first_name, u.last_name, u.email
         FROM bookings b
         JOIN dhana_types dt ON b.dhana_type_id = dt.id
         JOIN users u ON b.user_id = u.id
         WHERE b.id = ?",
        [$bookingId]
    );

    if (!$existingBooking) {
        throw new Exception('Booking not found');
    }
    
    // Check for conflicts if date, dhana type, or time slot changed
    if ($existingBooking['booking_date'] !== $bookingDate ||
        $existingBooking['dhana_type_id'] != $dhanaTypeId ||
        $existingBooking['booking_time_slot'] !== $bookingTimeSlot) {

        // Get dhana type details
        $dhanaType = $db->fetchOne(
            "SELECT id, name, time_slot, price FROM dhana_types WHERE id = ?",
            [$dhanaTypeId]
        );

        if (!$dhanaType) {
            throw new Exception('Invalid dhana type selected');
        }

        // Check if dhana type is available (price > 0)
        if ($dhanaType['price'] <= 0) {
            throw new Exception('This dhana type is not available yet (Coming Soon)');
        }

        // Check if the requested time slot is compatible with the dhana type
        if ($dhanaType['time_slot'] !== 'extra' && $dhanaType['time_slot'] !== $bookingTimeSlot) {
            // Allow whole_day bookings to override morning/lunch slots
            if (!($bookingTimeSlot === 'whole_day' && in_array($dhanaType['time_slot'], ['morning', 'lunch']))) {
                throw new Exception('Time slot "' . ucfirst($bookingTimeSlot) . '" is not compatible with dhana type "' . $dhanaType['name'] . '" (requires "' . ucfirst($dhanaType['time_slot']) . '")');
            }
        }

        // Check if date is blocked
        $blockedDate = $db->fetchOne(
            "SELECT id, reason FROM blocked_dates WHERE blocked_date = ?",
            [$bookingDate]
        );

        if ($blockedDate) {
            $reason = $blockedDate['reason'] ? ': ' . $blockedDate['reason'] : '';
            throw new Exception('This date is blocked and unavailable for bookings' . $reason);
        }

        // Check for booking conflicts based on time slot logic
        $conflictingBookings = [];

        if ($bookingTimeSlot === 'whole_day') {
            // Whole day booking conflicts with ANY booking on the same date (regardless of dhana type)
            $conflictingBookings = $db->fetchAll(
                "SELECT b.id, b.booking_time_slot, b.dhana_type_id, dt.name as dhana_type_name,
                        u.first_name, u.last_name, u.email
                 FROM bookings b
                 JOIN dhana_types dt ON b.dhana_type_id = dt.id
                 JOIN users u ON b.user_id = u.id
                 WHERE b.booking_date = ? AND b.id != ? AND b.status NOT IN ('cancelled')",
                [$bookingDate, $bookingId]
            );
        } else {
            // Specific time slot conflicts with:
            // 1. Same time slot for same dhana type
            // 2. ANY whole day booking (regardless of dhana type)
            $conflictingBookings = $db->fetchAll(
                "SELECT b.id, b.booking_time_slot, b.dhana_type_id, dt.name as dhana_type_name,
                        u.first_name, u.last_name, u.email
                 FROM bookings b
                 JOIN dhana_types dt ON b.dhana_type_id = dt.id
                 JOIN users u ON b.user_id = u.id
                 WHERE b.booking_date = ? AND b.id != ? AND b.status NOT IN ('cancelled')
                 AND (
                     (b.dhana_type_id = ? AND b.booking_time_slot = ?)
                     OR b.booking_time_slot = 'whole_day'
                 )",
                [$bookingDate, $bookingId, $dhanaTypeId, $bookingTimeSlot]
            );
        }

        if (!empty($conflictingBookings)) {
            // Build detailed conflict message
            $conflictDetails = [];
            $hasWholeDayConflict = false;

            foreach ($conflictingBookings as $conflict) {
                if ($conflict['booking_time_slot'] === 'whole_day') {
                    $hasWholeDayConflict = true;
                }

                $conflictDetails[] = sprintf(
                    "Booking #%s - %s (%s) - %s %s",
                    str_pad($conflict['id'], 6, '0', STR_PAD_LEFT),
                    $conflict['dhana_type_name'],
                    ucfirst($conflict['booking_time_slot']),
                    $conflict['first_name'],
                    $conflict['last_name']
                );
            }

            // Determine the specific error message
            if ($bookingTimeSlot === 'whole_day') {
                $errorMsg = 'Cannot book whole day - there are existing bookings for this date';
            } elseif ($hasWholeDayConflict) {
                $errorMsg = 'This date is fully booked (whole day booking exists)';
            } else {
                $errorMsg = 'This time slot is already booked for the selected dhana type';
            }

            $errorMsg .= "\n\nConflicting Bookings:\n" . implode("\n", $conflictDetails);

            throw new Exception($errorMsg);
        }

        // Check for annual event conflicts
        $year = date('Y', strtotime($bookingDate));
        $annualConflicts = $db->fetchAll(
            "SELECT b.id, b.booking_time_slot, b.dhana_type_id, dt.name as dhana_type_name,
                    u.first_name, u.last_name, ab.year_start, ab.year_end
             FROM bookings b
             JOIN annual_bookings ab ON (b.id = ab.booking_id OR b.parent_booking_id = ab.booking_id)
             JOIN dhana_types dt ON b.dhana_type_id = dt.id
             JOIN users u ON b.user_id = u.id
             WHERE MONTH(b.booking_date) = MONTH(?)
             AND DAY(b.booking_date) = DAY(?)
             AND ? BETWEEN ab.year_start AND ab.year_end
             AND b.id != ?
             AND b.status NOT IN ('cancelled')",
            [$bookingDate, $bookingDate, $year, $bookingId]
        );

        if (!empty($annualConflicts)) {
            $conflictDetails = [];
            foreach ($annualConflicts as $conflict) {
                $conflictDetails[] = sprintf(
                    "Annual Booking #%s - %s (%s) - %s %s (Years: %d-%d)",
                    str_pad($conflict['id'], 6, '0', STR_PAD_LEFT),
                    $conflict['dhana_type_name'],
                    ucfirst($conflict['booking_time_slot']),
                    $conflict['first_name'],
                    $conflict['last_name'],
                    $conflict['year_start'],
                    $conflict['year_end']
                );
            }

            $errorMsg = "This date conflicts with annual (recurring) bookings:\n\n" . implode("\n", $conflictDetails);
            throw new Exception($errorMsg);
        }
    }
    
    // Verify dhana type exists and is active
    $dhanaType = $db->fetchOne(
        "SELECT id, name, price FROM dhana_types WHERE id = ? AND is_active = 1",
        [$dhanaTypeId]
    );
    
    if (!$dhanaType) {
        throw new Exception('Invalid or inactive dhana type');
    }
    
    // Start transaction
    $db->getConnection()->beginTransaction();
    
    try {
        // Update reservation
        $db->query(
            "UPDATE bookings SET 
                booking_date = ?, 
                dhana_type_id = ?, 
                booking_time_slot = ?, 
                status = ?, 
                total_amount = ?, 
                special_requests = ?, 
                travel_support = ?, 
                is_monk = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [
                $bookingDate, 
                $dhanaTypeId, 
                $bookingTimeSlot, 
                $status, 
                $totalAmount, 
                $specialRequests, 
                $travelSupport, 
                $isMonk, 
                $bookingId
            ]
        );

        // Track changes for email notification
        $changes = [];

        // Get new dhana type name
        $newDhanaType = $db->fetchOne(
            "SELECT name FROM dhana_types WHERE id = ?",
            [$dhanaTypeId]
        );

        // Compare old vs new values
        if ($existingBooking['booking_date'] !== $bookingDate) {
            $changes['Date'] = [
                'old' => date('F d, Y', strtotime($existingBooking['booking_date'])),
                'new' => date('F d, Y', strtotime($bookingDate))
            ];
        }

        if ($existingBooking['dhana_type_id'] != $dhanaTypeId) {
            $changes['Dhana Type'] = [
                'old' => $existingBooking['dhana_type_name'],
                'new' => $newDhanaType['name']
            ];
        }

        if ($existingBooking['booking_time_slot'] !== $bookingTimeSlot) {
            $changes['Time Slot'] = [
                'old' => ucfirst(str_replace('_', ' ', $existingBooking['booking_time_slot'])),
                'new' => ucfirst(str_replace('_', ' ', $bookingTimeSlot))
            ];
        }

        if ($existingBooking['status'] !== $status) {
            $changes['Status'] = [
                'old' => ucfirst($existingBooking['status']),
                'new' => ucfirst($status)
            ];
        }

        if ($existingBooking['total_amount'] != $totalAmount) {
            $changes['Amount'] = [
                'old' => 'LKR ' . number_format($existingBooking['total_amount'], 2),
                'new' => 'LKR ' . number_format($totalAmount, 2)
            ];
        }

        if ($existingBooking['special_requests'] !== $specialRequests) {
            $changes['Special Requests'] = [
                'old' => $existingBooking['special_requests'] ?: 'None',
                'new' => $specialRequests ?: 'None'
            ];
        }

        if ($existingBooking['travel_support'] != $travelSupport) {
            $changes['Travel Support'] = [
                'old' => $existingBooking['travel_support'] ? 'Yes' : 'No',
                'new' => $travelSupport ? 'Yes' : 'No'
            ];
        }

        if ($existingBooking['is_monk'] != $isMonk) {
            $changes['Monk'] = [
                'old' => $existingBooking['is_monk'] ? 'Yes' : 'No',
                'new' => $isMonk ? 'Yes' : 'No'
            ];
        }

        $db->getConnection()->commit();

        // Notifications happen after the durable database update. A mail failure
        // must not hold locks open or roll back an otherwise valid reservation.
        if (!empty($changes)) {
            try {
                require_once __DIR__ . '/../includes/booking-emails.php';
                $emailService = getBookingEmailService();

                $bookingData = [
                    'user_email' => $existingBooking['email'],
                    'user_name' => $existingBooking['first_name'] . ' ' . $existingBooking['last_name'],
                    'booking_id' => $bookingId,
                    'dhana_type' => $newDhanaType['name'],
                    'booking_date' => $bookingDate,
                    'time_slot' => $bookingTimeSlot,
                    'amount' => $totalAmount,
                    'status' => $status
                ];

                $emailService->sendBookingUpdatedEmail($bookingData, $changes);
            } catch (Exception $e) {
                // Log email error but don't stop the process
                error_log("Failed to send booking update email: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Reservation updated successfully'
        ]);
        
    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->getConnection()->rollback();
        }
        throw $e;
    }
    
} catch (Exception $e) {
    adminLogException('Update booking failed', $e);
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => $e instanceof PDOException
            ? 'Unable to update the reservation. Please try again.'
            : $e->getMessage()
    ]);
}
?>
