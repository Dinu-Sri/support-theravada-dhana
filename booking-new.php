<?php
require_once 'includes/auth.php';

// Require user to be logged in
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Get dhana types
$dhanaTypes = $db->fetchAll("SELECT * FROM dhana_types WHERE is_active = 1 ORDER BY price DESC");

// Get pre-selected date from URL
$selectedDate = isset($_GET['date']) ? $_GET['date'] : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $dhanaTypeId = (int)$_POST['dhana_type_id'];
    $reservationDate = $_POST['booking_date'];
    $reservationTimeSlot = $_POST['booking_time_slot'];
    $specialRequests = trim($_POST['special_requests']);
    $travelSupport = isset($_POST['travel_support']) ? 1 : 0;
    $isAnnualEvent = isset($_POST['is_annual_event']) ? 1 : 0;
    $isMonk = $user['is_monk'] ?? 0;
    
    // Validate inputs
    $errors = [];
    
    if (empty($dhanaTypeId)) {
        $errors[] = 'Please select a dāna type';
    }
    
    if (empty($reservationDate)) {
        $errors[] = 'Please select a reservation date';
    } else {
        // Check if date is not in the past
        if ($reservationDate < date('Y-m-d')) {
            $errors[] = 'Reservation date cannot be in the past';
        }
    }
    
    if (empty($errors)) {
        try {
            // Check for reservation conflicts based on whole day logic
            $conflictingReservations = [];

            if ($reservationTimeSlot === 'whole_day') {
                // Whole day reservation conflicts with ANY reservation on the same date
                $conflictingReservations = $db->fetchAll(
                    "SELECT id, booking_time_slot, dhana_type_id FROM bookings
                     WHERE booking_date = ? AND status NOT IN ('cancelled')",
                    [$reservationDate]
                );
            } else {
                // Specific time slot conflicts with same time slot for same dhana type OR any whole day reservation
                $conflictingReservations = $db->fetchAll(
                    "SELECT id, booking_time_slot, dhana_type_id FROM bookings
                     WHERE booking_date = ? AND status NOT IN ('cancelled')
                     AND (
                         (dhana_type_id = ? AND booking_time_slot = ?)
                         OR booking_time_slot = 'whole_day'
                     )",
                    [$reservationDate, $dhanaTypeId, $reservationTimeSlot]
                );
            }

            if (!empty($conflictingReservations)) {
                // Determine specific error message
                $hasWholeDayConflict = false;
                foreach ($conflictingReservations as $conflict) {
                    if ($conflict['booking_time_slot'] === 'whole_day') {
                        $hasWholeDayConflict = true;
                        break;
                    }
                }

                if ($reservationTimeSlot === 'whole_day') {
                    $errors[] = 'Cannot reserve whole day - there are existing reservations for this date';
                } elseif ($hasWholeDayConflict) {
                    $errors[] = 'This date is fully reserved (whole day reservation exists)';
                } else {
                    $errors[] = 'This dāna type and time slot is already reserved for the selected date';
                }
            } else {
                // Get dhana type details for pricing
                $dhanaType = $db->fetchOne(
                    "SELECT * FROM dhana_types WHERE id = ? AND is_active = 1",
                    [$dhanaTypeId]
                );

                if (!$dhanaType) {
                    $errors[] = 'Invalid dāna type selected';
                } else {
                    // Get price from monthly pricing table
                    $reservationDateObj = new DateTime($reservationDate);
                    $year = (int)$reservationDateObj->format('Y');
                    $month = (int)$reservationDateObj->format('n');

                    // Try to get monthly price
                    $monthlyPricing = $db->fetchOne(
                        "SELECT price FROM monthly_pricing WHERE dhana_type_id = ? AND year = ? AND month = ?",
                        [$dhanaTypeId, $year, $month]
                    );

                    // Use monthly price if available, otherwise use last available price
                    if ($monthlyPricing && $monthlyPricing['price'] !== null) {
                        $bookingPrice = (float)$monthlyPricing['price'];
                    } else {
                        // Get the most recent price before this date
                        $lastAvailablePrice = $db->fetchOne(
                            "SELECT price FROM monthly_pricing
                             WHERE dhana_type_id = ?
                             AND price IS NOT NULL
                             AND (year < ? OR (year = ? AND month < ?))
                             ORDER BY year DESC, month DESC
                             LIMIT 1",
                            [$dhanaTypeId, $year, $year, $month]
                        );

                        // If still no price found, use base price from dhana_types
                        $bookingPrice = ($lastAvailablePrice && $lastAvailablePrice['price'] !== null)
                            ? (float)$lastAvailablePrice['price']
                            : (float)$dhanaType['price'];
                    }

                    // Ensure we have a valid price
                    if (!$bookingPrice || $bookingPrice <= 0) {
                        $bookingPrice = (float)$dhanaType['price'];
                    }

                    // Check if price is tentative (beyond pricing window)
                    $windowSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'pricing_window_months'");
                    $pricingWindowMonths = $windowSetting ? (int)$windowSetting['setting_value'] : 24;

                    $currentDate = new DateTime();
                    $currentDate->modify('first day of this month');
                    $windowEndDate = clone $currentDate;
                    $windowEndDate->modify("+{$pricingWindowMonths} months");

                    $isPriceTentative = $reservationDateObj >= $windowEndDate ? 1 : 0;

                    // Create the main booking
                    $db->query(
                        "INSERT INTO bookings (user_id, dhana_type_id, booking_date, booking_time_slot, special_requests, travel_support, is_annual_event, is_monk, total_amount, is_price_tentative, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                        [$user['id'], $dhanaTypeId, $reservationDate, $reservationTimeSlot, $specialRequests, $travelSupport, $isAnnualEvent, $isMonk, $bookingPrice, $isPriceTentative]
                    );

                    $bookingId = $db->lastInsertId();

                    // If annual event, create bookings for the configured number of years
                    if ($isAnnualEvent) {
                        // Get annual booking years setting from database
                        $annualYearsSetting = $db->fetchOne(
                            "SELECT setting_value FROM settings WHERE setting_key = 'annual_booking_years'"
                        );
                        $annualBookingYears = $annualYearsSetting ? (int)$annualYearsSetting['setting_value'] : 10;

                        $currentYear = date('Y', strtotime($reservationDate));
                        $baseDate = new DateTime($reservationDate);

                        // Create annual booking record for tracking
                        $db->query(
                            "INSERT INTO annual_bookings (booking_id, year_start, year_end) VALUES (?, ?, ?)",
                            [$bookingId, $currentYear, $currentYear + ($annualBookingYears - 1)]
                        );

                        // Create individual booking records for years 2-N (year 1 is already created above)
                        for ($yearOffset = 1; $yearOffset <= ($annualBookingYears - 1); $yearOffset++) {
                            $nextYearDate = clone $baseDate;
                            $nextYearDate->modify("+{$yearOffset} year");

                            // Check if this date would conflict with existing bookings
                            $futureDate = $nextYearDate->format('Y-m-d');
                            $conflictCheck = $db->fetchAll(
                                "SELECT id FROM bookings
                                 WHERE booking_date = ? AND status NOT IN ('cancelled')
                                 AND ((dhana_type_id = ? AND booking_time_slot = ?) OR booking_time_slot = 'whole_day')",
                                [$futureDate, $dhanaTypeId, $reservationTimeSlot]
                            );

                            // Only create if no conflicts (annual bookings take precedence for future years)
                            if (empty($conflictCheck)) {
                                // Get price for future date
                                $futureDateObj = new DateTime($futureDate);
                                $futureYear = (int)$futureDateObj->format('Y');
                                $futureMonth = (int)$futureDateObj->format('n');

                                $futureMonthlyPricing = $db->fetchOne(
                                    "SELECT price FROM monthly_pricing WHERE dhana_type_id = ? AND year = ? AND month = ?",
                                    [$dhanaTypeId, $futureYear, $futureMonth]
                                );

                                // If no price found for this specific month, use the last available price
                                if ($futureMonthlyPricing && $futureMonthlyPricing['price'] !== null) {
                                    $futureBookingPrice = (float)$futureMonthlyPricing['price'];
                                } else {
                                    // Get the most recent price before this date
                                    $lastAvailablePrice = $db->fetchOne(
                                        "SELECT price FROM monthly_pricing
                                         WHERE dhana_type_id = ?
                                         AND price IS NOT NULL
                                         AND (year < ? OR (year = ? AND month < ?))
                                         ORDER BY year DESC, month DESC
                                         LIMIT 1",
                                        [$dhanaTypeId, $futureYear, $futureYear, $futureMonth]
                                    );

                                    // If still no price found, use base price from dhana_types
                                    $futureBookingPrice = ($lastAvailablePrice && $lastAvailablePrice['price'] !== null)
                                        ? (float)$lastAvailablePrice['price']
                                        : (float)$dhanaType['price'];
                                }

                                // Ensure we have a valid price
                                if (!$futureBookingPrice || $futureBookingPrice <= 0) {
                                    $futureBookingPrice = (float)$dhanaType['price'];
                                }

                                $futurePriceTentative = $futureDateObj >= $windowEndDate ? 1 : 0;

                                // Check if parent_booking_id column exists
                                try {
                                    $db->query(
                                        "INSERT INTO bookings (user_id, dhana_type_id, booking_date, booking_time_slot, special_requests, travel_support, is_annual_event, is_monk, total_amount, is_price_tentative, status, parent_booking_id)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
                                        [$user['id'], $dhanaTypeId, $futureDate, $reservationTimeSlot, $specialRequests, $travelSupport, $isAnnualEvent, $isMonk, $futureBookingPrice, $futurePriceTentative, $bookingId]
                                    );
                                } catch (Exception $e) {
                                    // Fallback if parent_booking_id column doesn't exist
                                    $db->query(
                                        "INSERT INTO bookings (user_id, dhana_type_id, booking_date, booking_time_slot, special_requests, travel_support, is_annual_event, is_monk, total_amount, is_price_tentative, status)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                                        [$user['id'], $dhanaTypeId, $futureDate, $reservationTimeSlot, $specialRequests, $travelSupport, $isAnnualEvent, $isMonk, $futureBookingPrice, $futurePriceTentative]
                                    );
                                }
                            }
                        }
                    }

                    // Send booking confirmation email
                    try {
                        require_once __DIR__ . '/includes/email.php';
                        $emailService = getEmailService();

                        $bookingData = [
                            'user_email' => $user['email'],
                            'user_name' => $user['first_name'] . ' ' . $user['last_name'],
                            'booking_id' => $bookingId,
                            'dhana_type' => $dhanaType['name'],
                            'booking_date' => $reservationDate,
                            'time_slot' => $reservationTimeSlot,
                            'amount' => $bookingPrice,
                            'is_price_tentative' => $isPriceTentative
                        ];

                        $emailService->sendBookingCreatedEmail($bookingData);
                    } catch (Exception $e) {
                        // Log email error but don't stop the booking process
                        error_log("Failed to send booking confirmation email: " . $e->getMessage());
                    }

                    // Redirect to payment page
                    header("Location: payment.php?booking_id=" . $bookingId);
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log("Reservation error: " . $e->getMessage());
            $errors[] = 'An error occurred while processing your reservation. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Dāna Reservation - Dāna Reservation System</title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/booking-steps.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/review-enhancements.css?v=<?php echo time(); ?>"
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .review-navigation .btn:first-child {
            margin-right: auto !important;
        }
        
        /* Prevent validation errors from creating empty space */
        .error-message.js-step-validation-error {
            position: fixed !important;
            top: 20px !important;
            right: 20px !important;
            z-index: 9999 !important;
            background: #f8d7da !important;
            color: #721c24 !important;
            border: 1px solid #f5c6cb !important;
            border-radius: 8px !important;
            padding: 12px 16px !important;
            max-width: 400px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            animation: slideInFromRight 0.3s ease !important;
            margin: 0 !important;
        }
        
        @keyframes slideInFromRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .error-message.js-step-validation-error i {
            margin-right: 8px !important;
        }
        
        .error-message.js-step-validation-error ul {
            margin: 0 !important;
            padding-left: 20px !important;
        }
        
        /* AGGRESSIVE FIX FOR DHANA TYPES CONTAINER SPACE ISSUE */
        /* When not in step 1, completely hide and remove dhana types container from layout */
        .form-step:not([data-step="1"]) #dhana-types-container,
        .form-step[data-step="2"] #dhana-types-container,
        .form-step[data-step="3"] #dhana-types-container,
        .form-step[data-step="4"] #dhana-types-container {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            position: absolute !important;
            left: -9999px !important;
            top: -9999px !important;
            z-index: -1000 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            max-height: 0 !important;
            max-width: 0 !important;
            min-height: 0 !important;
            min-width: 0 !important;
        }
        
        /* Ensure dhana types grid doesn't take space when step is inactive */
        .form-step:not(.active) .dhana-types-grid,
        .form-step[data-step="2"] .dhana-types-grid,
        .form-step[data-step="3"] .dhana-types-grid,
        .form-step[data-step="4"] .dhana-types-grid {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            position: absolute !important;
            left: -9999px !important;
            z-index: -1000 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Fix large empty space between progress indicator and form content */
        .progress-container {
            margin-bottom: 10px !important;
        }
        
        .step-form {
            padding: 15px 30px 50px 30px !important;
        }
        
        /* Force remove any spacing that might cause empty area at top of step 2 */
        .form-step[data-step="2"] {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }
        
        .form-step[data-step="2"] .step-header {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        .form-step[data-step="2"] .step-header h3 {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        /* Ensure no elements before step content can create space */
        .form-step[data-step="2"]:before,
        .form-step[data-step="2"]:after {
            display: none !important;
        }
        
        /* Remove any potential phantom elements */
        .form-step[data-step="2"] > *:first-child {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        /* Aggressive fix for empty space in step 2 */
        .form-step[data-step="2"] {
            min-height: 0 !important;
            position: relative !important;
            top: 0 !important;
            transform: none !important;
        }
        
        .form-step[data-step="2"] .date-time-selection {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        /* Remove any invisible content that might be taking space */
        .form-step[data-step="2"] .error-message,
        .form-step[data-step="2"] .validation-error,
        .form-step[data-step="2"] .js-step-validation-error {
            display: none !important;
            position: fixed !important;
            top: 20px !important;
            right: 20px !important;
        }
        
        /* AGGRESSIVE FIX FOR STEPS 3 AND 4 EMPTY SPACE ISSUES */
        .form-step[data-step="3"],
        .form-step[data-step="4"] {
            padding-top: 0 !important;
            margin-top: 0 !important;
            min-height: 0 !important;
            position: relative !important;
            top: 0 !important;
            transform: none !important;
        }
        
        .form-step[data-step="3"] .step-header,
        .form-step[data-step="4"] .step-header {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        .form-step[data-step="3"] .step-header h3,
        .form-step[data-step="4"] .step-header h3 {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        /* Ensure no elements before step content can create space */
        .form-step[data-step="3"]:before,
        .form-step[data-step="3"]:after,
        .form-step[data-step="4"]:before,
        .form-step[data-step="4"]:after {
            display: none !important;
        }
        
        /* Remove any potential phantom elements */
        .form-step[data-step="3"] > *:first-child,
        .form-step[data-step="4"] > *:first-child {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        .form-step[data-step="3"] .additional-info-section,
        .form-step[data-step="4"] .review-section {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        /* Remove any invisible content that might be taking space in steps 3 and 4 */
        .form-step[data-step="3"] .error-message,
        .form-step[data-step="3"] .validation-error,
        .form-step[data-step="3"] .js-step-validation-error,
        .form-step[data-step="4"] .error-message,
        .form-step[data-step="4"] .validation-error,
        .form-step[data-step="4"] .js-step-validation-error {
            display: none !important;
            position: fixed !important;
            top: 20px !important;
            right: 20px !important;
        }

        .review-navigation .btn:last-child {
            margin-left: auto !important;
        }

        @media (max-width: 768px) {
            .booking-container {
                padding: 20px 20px 40px 20px !important;
            }

            .step-form {
                padding: 15px 20px 50px 20px !important;
            }

            .review-navigation {
                gap: 15px !important;
            }

            .review-navigation .btn {
                min-width: 120px !important;
                padding: 10px 16px !important;
                font-size: 0.9rem !important;
            }
        }

        @media (max-width: 480px) {
            .booking-container {
                padding: 15px 15px 30px 15px !important;
            }

            .step-form {
                padding: 15px 15px 50px 15px !important;
            }

            .review-navigation {
                flex-direction: column !important;
                gap: 12px !important;
            }

            .review-navigation .btn {
                width: 100% !important;
                min-width: auto !important;
                padding: 12px 20px !important;
            }

            .review-navigation .btn:first-child,
            .review-navigation .btn:last-child {
                margin: 0 !important;
            }
        }

        /* Simple Date Input Styles */
        .date-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .date-input-field {
            width: 100%;
            height: 64px;
            padding: 20px 45px 20px 24px;
            border: 2px solid #e1e5e9;
            border-radius: 15px;
            font-size: 1.1rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .date-input-field:hover {
            border-color: #d4822a;
            box-shadow: 0 4px 12px rgba(212, 130, 42, 0.15);
            transform: translateY(-1px);
        }

        .date-input-field:focus {
            outline: none;
            border-color: #d4822a;
            box-shadow: 0 0 0 4px rgba(212, 130, 42, 0.15);
            background: #fafbff;
            transform: translateY(-1px);
        }

        .date-input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #d4822a;
            font-size: 1.1rem;
            pointer-events: none;
            z-index: 1;
        }

        .inline-price-display {
            position: absolute;
            left: 24px;
            top: 50%;
            transform: translateY(-50%);
            margin-left: 180px;
            color: #28a745;
            font-size: 1.1rem;
            font-weight: 700;
            pointer-events: none;
            white-space: nowrap;
        }

        .inline-price-display strong {
            color: #28a745;
            font-weight: 700;
        }

        /* Date Picker Modal (similar to admin calendar) */
        .date-picker-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .date-picker-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        /* Price Disclaimer Modal */
        .price-disclaimer-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .price-disclaimer-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            animation: modalSlideIn 0.3s ease;
        }

        .price-disclaimer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 2px solid #e9ecef;
            background: linear-gradient(135deg, #d4822a 0%, #b8860b 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .price-disclaimer-header h4 {
            margin: 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-disclaimer-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .disclaimer-text {
            margin-bottom: 25px;
            padding: 20px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 6px;
        }

        .disclaimer-text h5 {
            margin: 0 0 10px 0;
            color: #856404;
            font-size: 1.1rem;
        }

        .disclaimer-text p {
            margin: 0;
            color: #856404;
            line-height: 1.6;
        }

        .disclaimer-text.sinhala {
            font-family: 'Noto Sans Sinhala', sans-serif;
            margin-top: 15px;
        }

        .disclaimer-acceptance {
            margin: 25px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 2px solid #dee2e6;
        }

        .disclaimer-acceptance label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
            color: #495057;
        }

        .disclaimer-acceptance input[type="checkbox"] {
            margin-top: 4px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .price-disclaimer-footer {
            padding: 20px 30px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .price-disclaimer-footer .btn {
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .price-disclaimer-footer .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
        }

        .price-disclaimer-footer .btn-cancel:hover {
            background: #5a6268;
        }

        .price-disclaimer-footer .btn-accept {
            background: linear-gradient(135deg, #d4822a 0%, #b8860b 100%);
            color: white;
            border: none;
        }

        .price-disclaimer-footer .btn-accept:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 130, 42, 0.4);
        }

        .price-disclaimer-footer .btn-accept:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .date-picker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #d4822a 0%, #b8860b 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .date-picker-header h4 {
            margin: 0;
            font-size: 1.2rem;
        }

        .close-date-picker {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: white;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .close-date-picker:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .date-picker-body {
            padding: 25px;
        }

        .date-selector-row {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .year-selector, .month-selector-dropdown {
            flex: 1;
        }

        .year-selector label, .month-selector-dropdown label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .year-selector select, .month-selector-dropdown select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
        }

        .year-selector select:focus, .month-selector-dropdown select:focus {
            outline: none;
            border-color: #d4822a;
        }

        .day-selector label {
            display: block;
            margin-bottom: 15px;
            font-weight: 600;
            color: #333;
        }

        .day-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
        }

        .day-btn {
            padding: 10px 8px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            color: #333;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .day-btn:hover {
            border-color: #d4822a;
            background: #fdf5e6;
        }

        .day-btn.selected {
            background: #d4822a;
            border-color: #d4822a;
            color: white;
        }

        .day-btn.disabled {
            background: #f8f9fa;
            color: #ccc;
            cursor: not-allowed;
        }

        .day-btn.disabled:hover {
            border-color: #e9ecef;
            background: #f8f9fa;
        }

        .date-picker-footer {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
        }

        .date-picker-footer .btn {
            /* Equal button sizing using flexbox */
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 180px !important;
            height: 45px !important;
            min-width: 180px !important;
            max-width: 180px !important;
            padding: 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            font-size: 0.9rem !important;
            font-weight: 500 !important;
            transition: all 0.2s ease !important;
            flex: none !important;
            text-align: center !important;
        }

        .date-picker-footer .btn-secondary {
            background: #f8f9fa !important;
            color: #495057 !important;
            border: 1px solid #dee2e6 !important;
        }

        .date-picker-footer .btn-secondary:hover {
            background: #e9ecef !important;
            border-color: #adb5bd !important;
            transform: translateY(-1px) !important;
        }

        .date-picker-footer .btn-primary {
            background: linear-gradient(135deg, #d4822a 0%, #b8860b 100%) !important;
            color: white !important;
            border: none !important;
        }

        .date-picker-footer .btn-primary:hover {
            background: linear-gradient(135deg, #c8761f 0%, #a67c08 100%) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(212, 130, 42, 0.3) !important;
        }

        /* Hide all debug elements and PHP fallbacks */
        #php-fallback,
        .debug-message,
        .php-error,
        .php-warning,
        .php-notice {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            position: absolute !important;
            left: -9999px !important;
            z-index: -1 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
        }

        /* Center align review table values for professional look */
        .review-item {
            justify-content: center !important;
            gap: 30px !important;
        }

        .review-item .value {
            text-align: center !important;
            font-weight: 600 !important;
        }

        /* Keep labels left aligned but closer to values */
        .review-item .label {
            text-align: right !important;
            min-width: 120px !important;
            font-weight: 500 !important;
        }

        /* FINAL OVERRIDE: Force step 3 elements to use full width like step 2 - ONLY WHEN ACTIVE */
        .step-form .form-step.active[data-step="3"] .additional-info-section {
            max-width: none !important;
            width: 100% !important;
            margin: 0 !important;
            display: block !important;
            flex-direction: unset !important;
            justify-content: unset !important;
            align-items: unset !important;
            flex-grow: unset !important;
            gap: unset !important;
        }

        .step-form .form-step.active[data-step="3"] .toggle-group {
            max-width: none !important;
            width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-bottom: 30px !important;
        }

        .step-form .form-step.active[data-step="3"] .special-requests-group {
            max-width: none !important;
            width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .step-form .form-step.active[data-step="3"] {
            display: block !important;
            flex-direction: unset !important;
            justify-content: unset !important;
            min-height: unset !important;
        }

        /* Ensure inactive step 3 remains hidden */
        .step-form .form-step[data-step="3"]:not(.active) {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Header -->
        <div class="dashboard-header">
            <h1><i class="fas fa-plus-circle"></i> Create New Dāna Reservation</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Step-by-Step Reservation Form -->
        <div class="booking-container">
            <!-- Progress Indicator -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="step-indicators">
                    <div class="step-indicator active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-label">Dāna Type</div>
                    </div>
                    <div class="step-indicator" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label">Date & Time</div>
                    </div>
                    <div class="step-indicator" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label">Additional Info</div>
                    </div>
                    <div class="step-indicator" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-label">Review & Submit</div>
                    </div>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Reservation Form -->
            <form id="bookingForm" method="POST" class="step-form">
                <input type="hidden" name="submit_booking" value="1">
                
                <!-- Step 1: Dhana Type Selection -->
                <div class="form-step active" data-step="1">
                    <div class="step-header">
                        <h3><i class="fas fa-hand-holding-heart"></i> Step 1: Select Dāna Type</h3>
                        <p>Choose the type of dāna offering you would like to make</p>
                    </div>
                    
                    <div id="dhana-types-container" class="dhana-types-grid">
                        <!-- Dhana types will be loaded by JavaScript -->
                        <div class="loading-message">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading dāna types...</p>
                        </div>

                        <!-- Emergency fallback - PHP generated dhana types (permanently hidden) -->
                        <div id="php-fallback" style="display: none !important; visibility: hidden !important; opacity: 0 !important; position: absolute !important; left: -9999px !important; z-index: -1 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; overflow: hidden !important;">
                            <h4 style="color: red;">JavaScript Failed - PHP Fallback:</h4>
                            <?php foreach ($dhanaTypes as $type): ?>
                                <div style="border: 2px solid red; background: yellow; padding: 20px; margin: 10px;">
                                    <input type="radio" id="php_dhana_<?php echo $type['id']; ?>"
                                           name="dhana_type_id" value="<?php echo $type['id']; ?>" required>
                                    <label for="php_dhana_<?php echo $type['id']; ?>">
                                        <h4><?php echo htmlspecialchars($type['name']); ?></h4>
                                        <p>Price: Rs. <?php echo number_format($type['price']); ?></p>
                                        <?php if ($type['description']): ?>
                                            <p><?php echo htmlspecialchars($type['description']); ?></p>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Date & Time Selection -->
                <div class="form-step" data-step="2">
                    <div class="step-header">
                        <h3><i class="fas fa-calendar-alt"></i> Step 2: Select Date & Time</h3>
                        <p>Choose your preferred date and confirm your time slot (auto-selected based on your dāna type)</p>
                    </div>

                    <div class="date-time-selection">
                        <div class="form-group">
                            <label for="booking_date">Reservation Date</label>
                            <div class="date-input-wrapper">
                                <input type="text"
                                       id="booking_date_display"
                                       class="date-input-field"
                                       placeholder="Click to select date"
                                       readonly
                                       onclick="showDatePicker()"
                                       value="<?php echo $selectedDate ? date('F j, Y', strtotime($selectedDate)) : ''; ?>">
                                <input type="hidden"
                                       id="booking_date"
                                       name="booking_date"
                                       value="<?php echo htmlspecialchars($selectedDate); ?>"
                                       required>
                                <i class="fas fa-calendar-alt date-input-icon"></i>
                                <span id="inline_price_display" class="inline-price-display" style="display: none;"></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="booking_time_slot">
                                Time Slot
                                <small style="color: #28a745; font-weight: normal;"><i class="fas fa-magic"></i> Auto-selected based on your dāna type</small>
                            </label>
                            <select id="booking_time_slot" name="booking_time_slot" required>
                                <option value="">Select time slot</option>
                                <option value="morning">Morning Dāna</option>
                                <option value="lunch">Lunch Dāna</option>
                                <option value="whole_day">Whole Day</option>
                            </select>
                        </div>

                        <div class="availability-checker">
                            <div id="availabilityResult" class="availability-result"></div>
                        </div>

                        <div class="calendar-link">
                            <a href="calendar.php" target="_blank" class="btn btn-outline">
                                <i class="fas fa-calendar-check"></i> View Full Calendar
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Additional Information -->
                <div class="form-step" data-step="3">
                    <div class="step-header">
                        <h3><i class="fas fa-info-circle"></i> Step 3: Additional Information</h3>
                        <p>Please provide additional details about your reservation</p>
                    </div>

                    <div class="additional-info-section">
                        <div class="toggle-group">
                            <div class="toggle-content">
                                <h4><i class="fas fa-bus"></i> Provide Travel Support</h4>
                                <p>Toggle this if you can provide transportation or travel support for monks attending the dāna ceremony.</p>
                            </div>
                            <div class="toggle-switch-container">
                                <input type="checkbox" id="travel_support" name="travel_support" value="1" class="toggle-switch-input">
                                <label for="travel_support" class="toggle-switch-label">
                                    <span class="toggle-switch-slider">
                                        <span class="toggle-switch-button"></span>
                                    </span>
                                    <span class="toggle-switch-text">
                                        <span class="toggle-on">ON</span>
                                        <span class="toggle-off">OFF</span>
                                    </span>
                                </label>
                            </div>
                        </div>



                        <div class="toggle-group">
                            <div class="toggle-content">
                                <h4><i class="fas fa-calendar-check"></i> Annual Event</h4>
                                <p>Toggle this if this is an annual recurring event (will be booked for the whole year)</p>

                                <!-- Inline Annual Price Summary -->
                                <div id="annual_price_summary" class="annual-price-inline-summary" style="display: none;">
                                    <div class="loading-inline">
                                        <i class="fas fa-spinner fa-spin"></i> Loading price breakdown...
                                    </div>
                                </div>
                            </div>
                            <div class="toggle-switch-container">
                                <input type="checkbox" id="is_annual_event" name="is_annual_event" value="1" class="toggle-switch-input">
                                <label for="is_annual_event" class="toggle-switch-label">
                                    <span class="toggle-switch-slider">
                                        <span class="toggle-switch-button"></span>
                                    </span>
                                    <span class="toggle-switch-text">
                                        <span class="toggle-on">ON</span>
                                        <span class="toggle-off">OFF</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group special-requests-group">
                            <label for="special_requests">Special Requests (Optional)</label>
                            <textarea id="special_requests"
                                      name="special_requests"
                                      rows="4"
                                      placeholder="Any special requirements, dietary restrictions, or additional information for your dāna reservation..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Review & Submit -->
                <div class="form-step" data-step="4">
                    <div class="step-header">
                        <h3><i class="fas fa-check-circle"></i> Step 4: Review & Submit</h3>
                        <p>Please review your reservation details before submitting</p>
                    </div>

                    <div class="booking-review">
                        <div class="review-section donor-info-section">
                            <h4><i class="fas fa-user-circle"></i> Donor Information</h4>
                            <div class="review-item">
                                <span class="label">Full Name</span>
                                <span class="value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                            </div>
                            <div class="review-item">
                                <span class="label">Email Address</span>
                                <span class="value"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                        </div>

                        <div class="review-section reservation-details-section">
                            <h4><i class="fas fa-calendar-check"></i> Reservation Details</h4>
                            <div class="review-item">
                                <span class="label">Dāna Type</span>
                                <span class="value" id="review-dhana-type">Not selected</span>
                            </div>
                            <div class="review-item">
                                <span class="label">Date</span>
                                <span class="value" id="review-date">Not selected</span>
                            </div>
                            <div class="review-item">
                                <span class="label">Time Slot</span>
                                <span class="value" id="review-time-slot">Not selected</span>
                            </div>
                            <div class="review-item">
                                <span class="label">Travel Support</span>
                                <span class="value" id="review-travel-support">No</span>
                            </div>
                            <div class="review-item">
                                <span class="label">Annual Event</span>
                                <span class="value" id="review-annual-event">No</span>
                            </div>
                        </div>

                        <div class="review-section total-amount-section">
                            <h4>Total Amount</h4>
                            <div class="total-amount">
                                <span id="review-total-amount">Rs. 0</span>
                            </div>

                            <!-- Tentative Price Disclaimer -->
                            <div id="tentative-price-disclaimer" class="tentative-price-warning" style="display: none;">
                                <div class="warning-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="warning-content">
                                    <h5>Price is Tentative</h5>
                                    <p>The selected date is beyond our current pricing window. The displayed price is tentative and subject to confirmation later.</p>
                                </div>
                            </div>

                            <!-- Review Step Navigation Buttons -->
                            <div class="review-navigation" style="display: none;">
                                <button type="button" id="reviewPrevBtn" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="submit" id="reviewSubmitBtn" class="btn btn-success">
                                    <i class="fas fa-check"></i> Submit Dāna Reservation
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons (for other steps) -->
                <div class="form-navigation">
                    <button type="button" id="prevBtn" class="btn btn-secondary" style="display: none;">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" id="nextBtn" class="btn btn-primary">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" id="submitBtn" class="btn btn-success" style="display: none;">
                        <i class="fas fa-check"></i> Submit Reservation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Essential data for booking-steps.js
        const dhanaTypes = <?php echo json_encode($dhanaTypes); ?>;
        const userId = <?php echo $user['id']; ?>;
        
        // Forcibly hide any debug or fallback elements
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                // Hide PHP fallback and any debug elements
                const elementsToHide = [
                    '#php-fallback',
                    '.debug-message',
                    '.php-error',
                    '.php-warning',
                    '.php-notice'
                ];
                
                elementsToHide.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(el => {
                        if (el) {
                            el.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; position: absolute !important; left: -9999px !important; z-index: -1 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; overflow: hidden !important;';
                        }
                    });
                });
            }, 100);
        });

        // Function to remove any dollar signs or money emojis
        function removeDollarSigns() {
            // Remove any dollar signs from total amount area
            const totalAmount = document.querySelector('.total-amount');
            if (totalAmount) {
                // Remove any ::after or ::before content by adding inline style
                const style = document.createElement('style');
                style.textContent = `
                    .total-amount::after,
                    .total-amount::before {
                        display: none !important;
                        content: none !important;
                        visibility: hidden !important;
                        opacity: 0 !important;
                    }
                `;
                if (!document.querySelector('#remove-dollar-style')) {
                    style.id = 'remove-dollar-style';
                    document.head.appendChild(style);
                }
            }
            
            // Remove any text nodes that might contain dollar signs or money emojis
            const reviewArea = document.querySelector('.booking-review');
            if (reviewArea) {
                const walker = document.createTreeWalker(
                    reviewArea,
                    NodeFilter.SHOW_TEXT,
                    null,
                    false
                );
                
                let node;
                while (node = walker.nextNode()) {
                    if (node.nodeValue && (node.nodeValue.includes('💰') || node.nodeValue.includes('$'))) {
                        node.nodeValue = node.nodeValue.replace(/💰|\$/g, '');
                    }
                }
            }
        }
        
        // Run the function when page loads
        document.addEventListener('DOMContentLoaded', removeDollarSigns);
        
        // Run it again after a short delay to catch any dynamically added content
        setTimeout(removeDollarSigns, 1000);
        
        // Run it whenever the step changes
        document.addEventListener('click', function(e) {
            if (e.target.matches('.btn, .step-indicator, [data-step]')) {
                setTimeout(removeDollarSigns, 100);
            }
        });

    </script>

    <script>
        // Date picker functionality (kept inline for date selection)
        let selectedMonth = new Date().getMonth() + 1;
        let selectedYear = new Date().getFullYear();
        let selectedDay = new Date().getDate();

        function showDatePicker() {
            console.log('📅 Date picker called');

            // Create date picker modal
            const modal = document.createElement('div');
            modal.className = 'date-picker-modal';
            modal.innerHTML = `
                <div class="date-picker-content">
                    <div class="date-picker-header">
                        <h4>Select Reservation Date</h4>
                        <button class="close-date-picker" onclick="closeDatePicker()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="date-picker-body">
                        <div class="date-selector-row">
                            <div class="year-selector">
                                <label>Year:</label>
                                <select id="dateYearSelect">
                                    ${generateYearOptions()}
                                </select>
                            </div>
                            <div class="month-selector-dropdown">
                                <label>Month:</label>
                                <select id="dateMonthSelect">
                                    ${generateMonthOptions()}
                                </select>
                            </div>
                        </div>
                        <div class="day-selector">
                            <label>Day:</label>
                            <div class="day-grid" id="dayGrid">
                                ${generateDayButtons()}
                            </div>
                        </div>
                    </div>
                    <div class="date-picker-footer">
                        <button class="btn btn-secondary" onclick="closeDatePicker()">Cancel</button>
                        <button class="btn btn-primary" onclick="applyDateSelection()">Select Date</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Set current values
            const currentDate = document.getElementById('booking_date')?.value;
            if (currentDate) {
                const date = new Date(currentDate);
                selectedMonth = date.getMonth() + 1;
                selectedYear = date.getFullYear();
                selectedDay = date.getDate();
            } else {
                // Default to tomorrow
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                selectedMonth = tomorrow.getMonth() + 1;
                selectedYear = tomorrow.getFullYear();
                selectedDay = tomorrow.getDate();
            }

            const yearSelect = document.getElementById('dateYearSelect');
            const monthSelect = document.getElementById('dateMonthSelect');
            
            if (yearSelect && monthSelect) {
                yearSelect.value = selectedYear;
                monthSelect.value = selectedMonth;

                // Add event listeners
                yearSelect.addEventListener('change', function() {
                    selectedYear = parseInt(this.value);
                    updateDayGrid();
                });

                monthSelect.addEventListener('change', function() {
                    selectedMonth = parseInt(this.value);
                    updateDayGrid();
                });

                updateDayGrid();
            }
        }

        function generateYearOptions() {
            const currentYear = new Date().getFullYear();
            const startYear = currentYear;
            const endYear = 2050;
            let options = '';

            for (let year = startYear; year <= endYear; year++) {
                options += `<option value="${year}">${year}</option>`;
            }

            return options;
        }

        function generateMonthOptions() {
            const months = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
            let options = '';

            months.forEach((month, index) => {
                options += `<option value="${index + 1}">${month}</option>`;
            });

            return options;
        }

        function generateDayButtons() {
            // Initial empty grid - will be populated by updateDayGrid()
            return '';
        }

        function updateDayGrid() {
            const dayGrid = document.getElementById('dayGrid');
            if (!dayGrid) return;
            
            const daysInMonth = new Date(selectedYear, selectedMonth, 0).getDate();
            const today = new Date();
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);

            let dayButtons = '';

            for (let day = 1; day <= daysInMonth; day++) {
                const currentDate = new Date(selectedYear, selectedMonth - 1, day);
                const isDisabled = currentDate < tomorrow;
                const isSelected = day === selectedDay;

                let classes = 'day-btn';
                if (isDisabled) classes += ' disabled';
                if (isSelected) classes += ' selected';

                dayButtons += `<button type="button" class="${classes}" data-day="${day}" ${isDisabled ? 'disabled' : ''}>${day}</button>`;
            }

            dayGrid.innerHTML = dayButtons;

            // Add click listeners to enabled day buttons
            document.querySelectorAll('.day-btn:not(.disabled)').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.day-btn').forEach(b => b.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedDay = parseInt(this.dataset.day);
                });
            });
        }

        function closeDatePicker() {
            const modal = document.querySelector('.date-picker-modal');
            if (modal) {
                modal.remove();
            }
        }

        function applyDateSelection() {
            console.log('📅 Apply date selection called');
            console.log('Selected values:', {
                year: selectedYear,
                month: selectedMonth,
                day: selectedDay
            });
            
            try {
                // Create the selected date
                const selectedDate = new Date(selectedYear, selectedMonth - 1, selectedDay);
                console.log('Created date object:', selectedDate);

                // Format for display
                const displayDate = selectedDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                console.log('Display date formatted:', displayDate);

                // Format for form submission (YYYY-MM-DD) - TIMEZONE SAFE
                // Use local date components instead of toISOString() to avoid timezone shift
                const year = selectedDate.getFullYear();
                const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
                const day = String(selectedDate.getDate()).padStart(2, '0');
                const formDate = `${year}-${month}-${day}`;
                console.log('Form date formatted (timezone-safe):', formDate);

                // Update inputs
                const displayInput = document.getElementById('booking_date_display');
                const dateInput = document.getElementById('booking_date');

                console.log('Found elements:', {
                    displayInput: !!displayInput,
                    dateInput: !!dateInput
                });

                if (displayInput) {
                    displayInput.value = displayDate;
                    console.log('✅ Display input updated');
                }
                if (dateInput) {
                    dateInput.value = formDate;
                    // Trigger change event
                    dateInput.dispatchEvent(new Event('change'));
                    console.log('✅ Hidden input updated and change event fired');
                }

                closeDatePicker();
                console.log('✅ Date picker closed');

            } catch (error) {
                console.error('❌ Error in applyDateSelection:', error);
            }
        }
        
        // Comprehensive function to hide dhana types container when not in step 1
        function hideDhanaTypesContainerOnStepChange() {
            console.log('🔧 Hiding dhana types container for non-step-1 views');
            
            try {
                const dhanaContainer = document.getElementById('dhana-types-container');
                const dhanaGrid = document.querySelector('.dhana-types-grid');
                
                if (dhanaContainer) {
                    dhanaContainer.style.cssText = `
                        display: none !important;
                        visibility: hidden !important;
                        opacity: 0 !important;
                        position: absolute !important;
                        left: -9999px !important;
                        top: -9999px !important;
                        z-index: -1000 !important;
                        pointer-events: none !important;
                        height: 0 !important;
                        width: 0 !important;
                        overflow: hidden !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        border: none !important;
                        max-height: 0 !important;
                        max-width: 0 !important;
                        min-height: 0 !important;
                        min-width: 0 !important;
                    `;
                    console.log('✅ Dhana container completely hidden');
                }
                
                if (dhanaGrid) {
                    dhanaGrid.style.cssText = `
                        display: none !important;
                        visibility: hidden !important;
                        opacity: 0 !important;
                        position: absolute !important;
                        left: -9999px !important;
                        z-index: -1000 !important;
                        pointer-events: none !important;
                        height: 0 !important;
                        width: 0 !important;
                        overflow: hidden !important;
                        margin: 0 !important;
                        padding: 0 !important;
                    `;
                    console.log('✅ Dhana grid completely hidden');
                }
                
            } catch (error) {
                console.error('❌ Error hiding dhana types container:', error);
            }
        }
        
        // Call the hide function immediately for step 2
        document.addEventListener('DOMContentLoaded', function() {
            // Small delay to ensure other scripts have loaded
            setTimeout(() => {
                const currentStep = document.querySelector('.form-step.active');
                if (currentStep && currentStep.dataset.step !== '1') {
                    hideDhanaTypesContainerOnStepChange();
                }
            }, 100);
        });

        // Price Disclaimer Modal Functions
        let disclaimerAccepted = false;

        function showPriceDisclaimerModal() {
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'price-disclaimer-modal';
            modal.id = 'priceDisclaimerModal';
            modal.innerHTML = `
                <div class="price-disclaimer-content">
                    <div class="price-disclaimer-header">
                        <h4>
                            <i class="fas fa-exclamation-triangle"></i>
                            Important Notice / වැදගත් දැනුම්දීම
                        </h4>
                    </div>
                    <div class="price-disclaimer-body">
                        <div class="disclaimer-text">
                            <h5><i class="fas fa-info-circle"></i> Price Change Notice (English)</h5>
                            <p>
                                Please be aware that <strong>prices for dāna offerings may change due to uncontrollable circumstances</strong>.
                                If any price changes occur after your booking, our office will contact you directly to inform you of the updates
                                and discuss any necessary adjustments. We appreciate your understanding and flexibility in supporting the monastery.
                            </p>
                        </div>
                        <div class="disclaimer-text sinhala">
                            <h5><i class="fas fa-info-circle"></i> මිල වෙනස්වීම් පිළිබඳ දැනුම්දීම (සිංහල)</h5>
                            <p>
                                කරුණාකර සලකන්න, <strong>පාලනය කළ නොහැකි තත්ත්වයන් හේතුවෙන් දාන පූජා සඳහා මිල වෙනස් විය හැක</strong>.
                                ඔබගේ වෙන්කිරීමෙන් පසු මිල වෙනස්කම් සිදුවුවහොත්, අපගේ කාර්යාලය ඔබව සෘජුවම සම්බන්ධ කර ගනිමින්
                                යාවත්කාලීන තොරතුරු දැනුම් දෙන අතර අවශ්‍ය වෙනස්කම් පිළිබඳව සාකච්ඡා කරනු ඇත.
                                විහාරස්ථානයට සහාය වීමේදී ඔබගේ අවබෝධය සහ නම්‍යශීලී බව අපි අගය කරමු.
                            </p>
                        </div>
                        <div class="disclaimer-acceptance">
                            <label>
                                <input type="checkbox" id="disclaimerCheckbox">
                                <span>
                                    I have read and accept the above terms /
                                    මම ඉහත කොන්දේසි කියවා පිළිගනිමි
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="price-disclaimer-footer">
                        <button type="button" class="btn btn-cancel" onclick="closePriceDisclaimerModal()">
                            <i class="fas fa-times"></i> Cancel / අවලංගු කරන්න
                        </button>
                        <button type="button" class="btn btn-accept" id="acceptDisclaimerBtn" disabled onclick="acceptDisclaimerAndSubmit()">
                            <i class="fas fa-check"></i> Accept & Submit / පිළිගෙන ඉදිරිපත් කරන්න
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Add checkbox listener
            const checkbox = document.getElementById('disclaimerCheckbox');
            const acceptBtn = document.getElementById('acceptDisclaimerBtn');

            checkbox.addEventListener('change', function() {
                acceptBtn.disabled = !this.checked;
            });
        }

        function closePriceDisclaimerModal() {
            const modal = document.getElementById('priceDisclaimerModal');
            if (modal) {
                modal.remove();
            }
            disclaimerAccepted = false;
        }

        function acceptDisclaimerAndSubmit() {
            disclaimerAccepted = true;
            closePriceDisclaimerModal();

            // Now submit the form
            const form = document.getElementById('bookingForm');
            if (form) {
                form.submit();
            }
        }

        // Intercept form submission
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('bookingForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // If disclaimer not yet accepted, prevent submission and show modal
                    if (!disclaimerAccepted) {
                        e.preventDefault();
                        showPriceDisclaimerModal();
                        return false;
                    }
                    // If already accepted, allow normal submission
                    return true;
                });
            }
        });
    </script>
    <script src="assets/js/booking-steps.js?v=<?php echo time(); ?>"></script>
</body>
</html>
