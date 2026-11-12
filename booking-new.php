<?php
require_once 'includes/auth.php';
require_once 'includes/process-booking.php';

// Require user to be logged in
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();
$isReservationAgent = (($user['role'] ?? 'donor') === 'agent');
$bookingForOther = $isReservationAgent && (($_GET['for'] ?? '') === 'other');
$totalBookingSteps = $bookingForOther ? 5 : 4;
$additionalInfoStep = $bookingForOther ? 4 : 3;
$reviewStep = $bookingForOther ? 5 : 4;

// Only types that can actually be reserved are presented to donors.
$dhanaTypes = $db->fetchAll("SELECT * FROM dhana_types WHERE is_active = 1 AND price > 0 ORDER BY price DESC");

$bookingAdvanceDays = bookingSettingInt($db, 'booking_advance_days', 30, 1, 730);
$maxBookingDate = (new DateTime('today'))->modify('+' . $bookingAdvanceDays . ' days');

// Accept a calendar deep-link only when it is a real, currently bookable date.
$selectedDate = '';
if (!empty($_GET['date'])) {
    $candidateDate = parseBookingDate((string) $_GET['date']);
    if ($candidateDate && empty(validateBookingDate($db, $candidateDate))) {
        $selectedDate = $candidateDate->format('Y-m-d');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $bookingResult = processBookingSubmission($db, $user, $_POST);
    if ($bookingResult['success']) {
        header('Location: payment.php?booking_id=' . $bookingResult['booking_id']);
        exit;
    }
    $errors = $bookingResult['errors'];
}

?>
<!DOCTYPE html>
<?php echo ui_html_open(); ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo te('New Dāna Reservation - Dāna Reservation System', 'නව දාන වෙන්කිරීම - දාන වෙන්කිරීමේ පද්ධතිය'); ?></title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css?v=20260913">
    <link rel="stylesheet" href="assets/css/booking-steps.css?v=20260913">
    <link rel="stylesheet" href="assets/css/review-enhancements.css?v=20260913">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pro-ui.css?v=20260917">
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

        /* Compact the option step into one desktop screen: choices side-by-side,
           concise notes below, and navigation immediately after the fields. */
        .step-form .form-step.additional-info-step.active {
            display: block !important;
            min-height: 0 !important;
        }

        .step-form .form-step.additional-info-step .additional-info-section {
            width: 100% !important;
            margin: 0 !important;
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px !important;
            align-items: stretch;
        }

        .step-form .form-step.additional-info-step .toggle-group {
            width: 100% !important;
            min-height: 118px !important;
            margin: 0 !important;
            padding: 18px 20px !important;
        }

        .step-form .form-step.additional-info-step .toggle-content h4 {
            margin-bottom: 5px !important;
            font-size: 1.05rem;
        }

        .step-form .form-step.additional-info-step .toggle-content p {
            font-size: .88rem;
            line-height: 1.4;
        }

        .step-form .form-step.additional-info-step .special-requests-group {
            grid-column: 1 / -1;
            width: 100% !important;
            margin: 0 !important;
        }

        .step-form .form-step.additional-info-step .special-requests-group textarea {
            min-height: 78px;
            max-height: 92px;
            resize: vertical;
        }

        .step-form .form-navigation {
            margin-top: 18px !important;
            padding-top: 16px !important;
        }

        .beneficiary-form-card {
            max-width: 850px;
            margin: 0 auto;
            padding: 24px;
            border: 1px solid #ead7a6;
            border-radius: 14px;
            background: #fffaf0;
        }
        .beneficiary-form-card > p { margin: 0 0 18px; color: #5d5144; }
        .beneficiary-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 18px; }
        .beneficiary-fields .form-group { margin: 0; }
        .beneficiary-fields .full-width { grid-column: 1 / -1; }

        @media (max-width: 720px) {
            .step-form .form-step.additional-info-step .additional-info-section,
            .beneficiary-fields { grid-template-columns: 1fr; }
            .step-form .form-step.additional-info-step .special-requests-group,
            .beneficiary-fields .full-width { grid-column: auto; }
            .step-form .form-step.additional-info-step .toggle-group { min-height: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Header -->
        <div class="dashboard-header">
            <?php ui_lang_switch(); ?>
            <h1><i class="fas fa-plus-circle"></i> <?php echo te('Create New Dāna Reservation', 'නව දාන වෙන්කිරීමක් සාදන්න'); ?></h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo te('Back to Dashboard', 'ප්‍රධාන පුවරුවට'); ?>
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
                        <div class="step-label"><?php echo te('Dāna Type', 'දාන වර්ගය'); ?></div>
                    </div>
                    <div class="step-indicator" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label"><?php echo te('Date & Time', 'දිනය සහ වේලාව'); ?></div>
                    </div>
                    <?php if ($bookingForOther): ?>
                    <div class="step-indicator" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label"><?php echo te('Person Details', 'පුද්ගල විස්තර'); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="step-indicator" data-step="<?php echo $additionalInfoStep; ?>">
                        <div class="step-number"><?php echo $additionalInfoStep; ?></div>
                        <div class="step-label"><?php echo te('Additional Info', 'අමතර තොරතුරු'); ?></div>
                    </div>
                    <div class="step-indicator" data-step="<?php echo $reviewStep; ?>">
                        <div class="step-number"><?php echo $reviewStep; ?></div>
                        <div class="step-label"><?php echo te('Review & Submit', 'පරීක්ෂා කර ඉදිරිපත් කරන්න'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo te_msg($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Reservation Form -->
            <form id="bookingForm" method="POST" class="step-form" data-total-steps="<?php echo $totalBookingSteps; ?>" data-booking-mode="<?php echo $bookingForOther ? 'agent-other' : 'self'; ?>">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="submit_booking" value="1">
                <?php if ($bookingForOther): ?>
                    <input type="hidden" name="book_for_other" value="1">
                <?php endif; ?>
                
                <!-- Step 1: Dhana Type Selection -->
                <div class="form-step active" data-step="1">
                    <div class="step-header">
                        <h3><i class="fas fa-hand-holding-heart"></i> <?php echo te('Step 1: Select Dāna Type', 'පියවර 1: දාන වර්ගය තෝරන්න'); ?></h3>
                        <p><?php echo te('Choose the type of dāna offering you would like to make', 'ඔබ කිරීමට කැමති දාන පූජාව තෝරන්න'); ?></p>
                    </div>
                    
                    <div id="dhana-types-container" class="dhana-types-grid">
                        <!-- Dhana types will be loaded by JavaScript -->
                        <div class="loading-message">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p><?php echo te('Loading dāna types...', 'දාන වර්ග පූරණය වෙමින්...'); ?></p>
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
                        <h3><i class="fas fa-calendar-alt"></i> <?php echo te('Step 2: Select Date & Time', 'පියවර 2: දිනය සහ වේලාව තෝරන්න'); ?></h3>
                        <p><?php echo te('Choose your preferred date and confirm your time slot (auto-selected based on your dāna type)', 'ඔබට පහසු දිනය තෝරා වේලාව තහවුරු කරන්න (දාන වර්ගය අනුව ස්වයංක්‍රීයව තෝරයි)'); ?></p>
                    </div>

                    <div class="date-time-selection">
                        <div class="form-group">
                            <label for="booking_date_display"><?php echo te('Reservation Date', 'වෙන්කිරීමේ දිනය'); ?></label>
                            <div class="date-input-wrapper">
                                <input type="text"
                                       id="booking_date_display"
                                       class="date-input-field"
                                       placeholder="<?php echo te('Click to select date', 'දිනය තේරීමට ඔබන්න'); ?>"
                                       readonly
                                       onclick="showDatePicker()"
                                       value="<?php echo $selectedDate ? htmlspecialchars(ui_date_keep($selectedDate, 'F j, Y'), ENT_QUOTES, 'UTF-8') : ''; ?>">
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
                                <?php echo te('Time Slot', 'වේලාව'); ?>
                                <small style="color: #28a745; font-weight: normal;"><i class="fas fa-magic"></i> <?php echo te('Auto-selected based on your dāna type', 'දාන වර්ගය අනුව ස්වයංක්‍රීයව තෝරයි'); ?></small>
                            </label>
                            <select id="booking_time_slot" name="booking_time_slot" required>
                                <option value=""><?php echo te('Select time slot', 'වේලාව තෝරන්න'); ?></option>
                                <option value="morning"><?php echo te('Morning Dāna', 'උදෑසන දානය'); ?></option>
                                <option value="lunch"><?php echo te('Lunch Dāna', 'දිවා දානය'); ?></option>
                                <option value="whole_day"><?php echo te('Whole Day', 'සම්පූර්ණ දිනය'); ?></option>
                            </select>
                        </div>

                        <div class="availability-checker">
                            <div id="availabilityResult" class="availability-result"></div>
                        </div>

                        <div class="calendar-link">
                            <a href="calendar.php" target="_blank" class="btn btn-outline">
                                <i class="fas fa-calendar-check"></i> <?php echo te('View Full Calendar', 'සම්පූර්ණ දින දර්ශනය බලන්න'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($bookingForOther): ?>
                <!-- Step 3: the person receiving the reservation. Agents retain ownership and payment access. -->
                <div class="form-step beneficiary-step" data-step="3">
                    <div class="step-header">
                        <h3><i class="fas fa-user-friends"></i> <?php echo te('Step 3: Reservation for Another Person', 'පියවර 3: වෙනත් අයෙකු වෙනුවෙන් වෙන්කිරීම'); ?></h3>
                        <p><?php echo te('Enter the details of the person for whom you are arranging this dāna reservation.', 'මෙම දාන වෙන්කිරීම සකසන පුද්ගලයාගේ විස්තර ඇතුළත් කරන්න.'); ?></p>
                    </div>
                    <div class="beneficiary-form-card">
                        <p><i class="fas fa-shield-alt"></i> <?php echo te('You remain the booking agent and payment contact. These details identify the reservation recipient for the temple.', 'ඔබ වෙන්කිරීමේ නියෝජිතයා සහ ගෙවීම් සම්බන්ධතාව ලෙසම පවතී. මෙම විස්තර විහාරයට වෙන්කිරීම ලබන්නා හඳුනා ගැනීමට යොදයි.'); ?></p>
                        <div class="beneficiary-fields">
                            <div class="form-group">
                                <label for="booked_for_first_name"><?php echo te('Recipient First Name', 'ලබන්නාගේ මුල් නම'); ?></label>
                                <input type="text" id="booked_for_first_name" name="booked_for_first_name" maxlength="50" autocomplete="off" required>
                            </div>
                            <div class="form-group">
                                <label for="booked_for_last_name"><?php echo te('Recipient Last Name', 'ලබන්නාගේ අවසන් නම'); ?></label>
                                <input type="text" id="booked_for_last_name" name="booked_for_last_name" maxlength="50" autocomplete="off" required>
                            </div>
                            <div class="form-group">
                                <label for="booked_for_primary_contact"><?php echo te('Primary Mobile Number', 'ප්‍රධාන ජංගම අංකය'); ?></label>
                                <input type="tel" id="booked_for_primary_contact" name="booked_for_primary_contact" maxlength="20" inputmode="tel" autocomplete="tel" required>
                            </div>
                            <div class="form-group">
                                <label for="booked_for_secondary_contact"><?php echo te('Second Mobile Number', 'දෙවන ජංගම අංකය'); ?> <small><?php echo te('(optional)', '(අනිවාර්ය නොවේ)'); ?></small></label>
                                <input type="tel" id="booked_for_secondary_contact" name="booked_for_secondary_contact" maxlength="20" inputmode="tel" autocomplete="tel">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Additional Information -->
                <div class="form-step additional-info-step" data-step="<?php echo $additionalInfoStep; ?>">
                    <div class="step-header">
                        <h3><i class="fas fa-info-circle"></i> <?php echo te('Step', 'පියවර'); ?> <?php echo $additionalInfoStep; ?>: <?php echo te('Additional Information', 'අමතර තොරතුරු'); ?></h3>
                        <p><?php echo te('Please provide additional details about your reservation', 'ඔබගේ වෙන්කිරීම පිළිබඳ අමතර විස්තර ලබා දෙන්න'); ?></p>
                    </div>

                    <div class="additional-info-section">
                        <div class="toggle-group">
                            <div class="toggle-content">
                                <h4><i class="fas fa-bus"></i> <?php echo te('Provide Travel Support', 'ගමන් පහසුකම් සපයන්න'); ?></h4>
                                <p><?php echo te('Toggle this if you can provide transportation or travel support for monks attending the dāna ceremony.', 'දානයට පැමිණෙන ස්වාමීන් වහන්සේලාට ගමන් පහසුකම් සැපයිය හැකි නම් මෙය සක්‍රිය කරන්න.'); ?></p>
                            </div>
                            <div class="toggle-switch-container">
                                <input type="checkbox" id="travel_support" name="travel_support" value="1" class="toggle-switch-input">
                                <label for="travel_support" class="toggle-switch-label">
                                    <span class="toggle-switch-slider">
                                        <span class="toggle-switch-button"></span>
                                    </span>
                                    <span class="toggle-switch-text">
                                        <span class="toggle-on"><?php echo te('ON', 'සක්‍රිය'); ?></span>
                                        <span class="toggle-off"><?php echo te('OFF', 'අක්‍රිය'); ?></span>
                                    </span>
                                </label>
                            </div>
                        </div>



                        <div class="toggle-group">
                            <div class="toggle-content">
                                <h4><i class="fas fa-calendar-check"></i> <?php echo te('Annual Event', 'වාර්ෂික උත්සවය'); ?></h4>
                                <p><?php echo te('Reserve this same calendar date once per year for the configured annual period.', 'නියමිත වාර්ෂික කාලය සඳහා මෙම දිනය වසරකට වරක් වෙන්කරන්න.'); ?></p>

                                <!-- Inline Annual Price Summary -->
                                <div id="annual_price_summary" class="annual-price-inline-summary" style="display: none;">
                                    <div class="loading-inline">
                                        <i class="fas fa-spinner fa-spin"></i> <?php echo te('Loading price breakdown...', 'මිල විස්තරය පූරණය වෙමින්...'); ?>
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
                                        <span class="toggle-on"><?php echo te('ON', 'සක්‍රිය'); ?></span>
                                        <span class="toggle-off"><?php echo te('OFF', 'අක්‍රිය'); ?></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group special-requests-group">
                            <label for="special_requests"><?php echo te('Special Requests (Optional)', 'විශේෂ ඉල්ලීම් (අනිවාර්ය නොවේ)'); ?></label>
                            <textarea id="special_requests"
                                      name="special_requests"
                                      rows="4"
                                      maxlength="2000"
                                      placeholder="<?php echo te('Any special requirements, dietary restrictions, or additional information for your dāna reservation...', 'විශේෂ අවශ්‍යතා, ආහාර සීමා, හෝ ඔබගේ දාන වෙන්කිරීම පිළිබඳ අමතර තොරතුරු...'); ?>"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Review & Submit -->
                <div class="form-step review-step" data-step="<?php echo $reviewStep; ?>">
                    <div class="step-header">
                        <h3><i class="fas fa-check-circle"></i> <?php echo te('Step', 'පියවර'); ?> <?php echo $reviewStep; ?>: <?php echo te('Review & Submit', 'පරීක්ෂා කර ඉදිරිපත් කරන්න'); ?></h3>
                        <p><?php echo te('Please review your reservation details before submitting', 'ඉදිරිපත් කිරීමට පෙර වෙන්කිරීමේ විස්තර පරීක්ෂා කරන්න'); ?></p>
                    </div>

                    <div class="booking-review<?php echo $bookingForOther ? ' agent-review' : ''; ?>">
                        <?php if ($bookingForOther): ?>
                        <div class="review-section beneficiary-review-section">
                            <h4><i class="fas fa-user-friends"></i> <?php echo te('Reservation Recipient', 'වෙන්කිරීම ලබන්නා'); ?></h4>
                            <div class="review-item">
                                <span class="label"><?php echo te('Full Name', 'සම්පූර්ණ නම'); ?></span>
                                <span class="value" id="review-beneficiary-name"><?php echo te('Not provided', 'ලබා දී නැත'); ?></span>
                            </div>
                            <div class="review-item">
                                <span class="label"><?php echo te('Primary Mobile', 'ප්‍රධාන ජංගම'); ?></span>
                                <span class="value" id="review-beneficiary-primary-contact"><?php echo te('Not provided', 'ලබා දී නැත'); ?></span>
                            </div>
                            <div class="review-item">
                                <span class="label"><?php echo te('Second Mobile', 'දෙවන ජංගම'); ?></span>
                                <span class="value" id="review-beneficiary-secondary-contact"><?php echo te('Not provided', 'ලබා දී නැත'); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="review-section donor-info-section">
                            <h4><i class="fas fa-user-circle"></i> <?php echo $bookingForOther ? te('Agent Information', 'නියෝජිත තොරතුරු') : te('Donor Information', 'දායක තොරතුරු'); ?></h4>
                            <div class="review-item">
                                <span class="label"><?php echo te('Full Name', 'සම්පූර්ණ නම'); ?></span>
                                <span class="value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                            </div>
                            <div class="review-item">
                                <span class="label"><?php echo te('Email Address', 'විද්‍යුත් තැපෑල'); ?></span>
                                <span class="value"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                        </div>

                        <div class="review-section reservation-details-section">
                            <h4><i class="fas fa-calendar-check"></i> <?php echo te('Reservation Details', 'වෙන්කිරීමේ විස්තර'); ?></h4>
                            <div class="review-item">
                                <span class="label"><?php echo te('Dāna Type', 'දාන වර්ගය'); ?></span>
                                <span class="value" id="review-dhana-type"><?php echo te('Not selected', 'තෝරා නැත'); ?></span>
                            </div>
                            <div class="review-item">
                                <span class="label"><?php echo te('Date', 'දිනය'); ?></span>
                                <span class="value" id="review-date"><?php echo te('Not selected', 'තෝරා නැත'); ?></span>
                            </div>
                            <div class="review-item">
                                <span class="label"><?php echo te('Time Slot', 'වේලාව'); ?></span>
                                <span class="value" id="review-time-slot"><?php echo te('Not selected', 'තෝරා නැත'); ?></span>
                            </div>
                            <div class="review-item">
                                <span class="label"><?php echo te('Travel Support', 'ගමන් පහසුකම්'); ?></span>
                                <span class="value" id="review-travel-support"><?php echo te('No', 'නැත'); ?></span>
                            </div>
                            <div class="review-item">
                                <span class="label"><?php echo te('Annual Event', 'වාර්ෂික උත්සවය'); ?></span>
                                <span class="value" id="review-annual-event"><?php echo te('No', 'නැත'); ?></span>
                            </div>
                        </div>

                        <div class="review-section total-amount-section">
                            <h4><?php echo te('Total Amount', 'මුළු මුදල'); ?></h4>
                            <div class="total-amount">
                                <span id="review-total-amount"><?php echo te('Calculating…', 'ගණනය කරමින්…'); ?></span>
                            </div>

                            <!-- Tentative Price Disclaimer -->
                            <div id="tentative-price-disclaimer" class="tentative-price-warning" style="display: none;">
                                <div class="warning-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="warning-content">
                                    <h5><?php echo te('Price is Tentative', 'මිල තාවකාලිකයි'); ?></h5>
                                    <p><?php echo te('The selected date is beyond our current pricing window. The displayed price is tentative and subject to confirmation later.', 'තෝරාගත් දිනය වත්මන් මිල කවුළුවෙන් ඔබ්බෙහි ය. පෙන්වන මිල තාවකාලික වන අතර පසුව තහවුරු වේ.'); ?></p>
                                </div>
                            </div>

                            <!-- Review Step Navigation Buttons -->
                            <div class="review-navigation" style="display: none;">
                                <button type="button" id="reviewPrevBtn" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> <?php echo te('Previous', 'පෙර'); ?>
                                </button>
                                <button type="submit" id="reviewSubmitBtn" class="btn btn-success">
                                    <i class="fas fa-check"></i> <?php echo te('Submit Dāna Reservation', 'දාන වෙන්කිරීම ඉදිරිපත් කරන්න'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons (for other steps) -->
                <div class="form-navigation">
                    <button type="button" id="prevBtn" class="btn btn-secondary" style="display: none;">
                        <i class="fas fa-arrow-left"></i> <?php echo te('Previous', 'පෙර'); ?>
                    </button>
                    <button type="button" id="nextBtn" class="btn btn-primary">
                        <?php echo te('Next', 'ඊළඟ'); ?> <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" id="submitBtn" class="btn btn-success" style="display: none;">
                        <i class="fas fa-check"></i> <?php echo te('Submit Reservation', 'වෙන්කිරීම ඉදිරිපත් කරන්න'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Essential data for booking-steps.js
        const dhanaTypes = <?php echo json_encode($dhanaTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.BOOKING_MAX_DATE = '<?php echo $maxBookingDate->format('Y-m-d'); ?>';
        window.BOOKING_FLOW = {
            isAgentBooking: <?php echo $bookingForOther ? 'true' : 'false'; ?>,
            totalSteps: <?php echo $totalBookingSteps; ?>,
            additionalInfoStep: <?php echo $additionalInfoStep; ?>,
            reviewStep: <?php echo $reviewStep; ?>
        };
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
        let datePickerReturnFocus = null;

        function showDatePicker() {
            console.log('📅 Date picker called');
            datePickerReturnFocus = document.activeElement;

            // Create date picker modal
            const modal = document.createElement('div');
            modal.className = 'date-picker-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', 'datePickerTitle');
            modal.innerHTML = `
                <div class="date-picker-content" tabindex="-1">
                    <div class="date-picker-header">
                        <h4 id="datePickerTitle">${uiText('Select Reservation Date')}</h4>
                        <button type="button" class="close-date-picker" onclick="closeDatePicker()" aria-label="${uiText('Close date picker')}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="date-picker-body">
                        <div class="date-selector-row">
                            <div class="year-selector">
                                <label for="dateYearSelect">${uiText('Year:')}</label>
                                <select id="dateYearSelect">
                                    ${generateYearOptions()}
                                </select>
                            </div>
                            <div class="month-selector-dropdown">
                                <label for="dateMonthSelect">${uiText('Month:')}</label>
                                <select id="dateMonthSelect">
                                    ${generateMonthOptions()}
                                </select>
                            </div>
                        </div>
                        <div class="day-selector">
                            <span class="day-selector-label">${uiText('Day:')}</span>
                            <div class="day-grid" id="dayGrid">
                                ${generateDayButtons()}
                            </div>
                        </div>
                    </div>
                    <div class="date-picker-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeDatePicker()">${uiText('Cancel')}</button>
                        <button type="button" class="btn btn-primary" onclick="applyDateSelection()">${uiText('Select Date')}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            modal.querySelector('.date-picker-content').focus();

            // Set current values
            const currentDate = document.getElementById('booking_date')?.value;
            if (currentDate) {
                const date = new Date(currentDate);
                selectedMonth = date.getMonth() + 1;
                selectedYear = date.getFullYear();
                selectedDay = date.getDate();
            } else {
                const today = new Date();
                selectedMonth = today.getMonth() + 1;
                selectedYear = today.getFullYear();
                selectedDay = today.getDate();
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
            const endYear = <?php echo (int) $maxBookingDate->format('Y'); ?>;
            let options = '';

            for (let year = startYear; year <= endYear; year++) {
                options += `<option value="${year}">${year}</option>`;
            }

            return options;
        }

        function generateMonthOptions() {
            const months = (window.UI && window.UI.months) || ['January', 'February', 'March', 'April', 'May', 'June',
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
            tomorrow.setHours(0, 0, 0, 0);
            const latestDate = new Date('<?php echo $maxBookingDate->format('Y-m-d'); ?>T00:00:00');

            let dayButtons = '';

            for (let day = 1; day <= daysInMonth; day++) {
                const currentDate = new Date(selectedYear, selectedMonth - 1, day);
                const isDisabled = currentDate < tomorrow || currentDate > latestDate;
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
            if (datePickerReturnFocus && typeof datePickerReturnFocus.focus === 'function') {
                datePickerReturnFocus.focus();
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
                const displayDate = window.uiFormatMonthDate
                    ? window.uiFormatMonthDate(selectedDate)
                    : selectedDate.toLocaleDateString('en-US', {
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
            const existingModal = document.getElementById('priceDisclaimerModal');
            if (existingModal) {
                existingModal.querySelector('#disclaimerCheckbox')?.focus();
                return;
            }

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

        function closePriceDisclaimerModal(resetAcceptance = true) {
            const modal = document.getElementById('priceDisclaimerModal');
            if (modal) {
                modal.remove();
            }
            if (resetAcceptance) disclaimerAccepted = false;
        }

        function acceptDisclaimerAndSubmit() {
            const checkbox = document.getElementById('disclaimerCheckbox');
            if (!checkbox?.checked) return;

            disclaimerAccepted = true;
            closePriceDisclaimerModal(false);

            // Use requestSubmit so normal client-side availability validation
            // still runs. The accepted state remains true for that submission.
            const form = document.getElementById('bookingForm');
            if (form) {
                form.requestSubmit();
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
    <script src="assets/js/booking-steps.js?v=20261112"></script>
</body>
</html>
