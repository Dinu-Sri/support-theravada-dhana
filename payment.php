<?php
require_once 'includes/auth.php';

// Require user to be logged in
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Get reservation ID from URL
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$bookingId) {
    header('Location: dashboard.php');
    exit;
}

// Get reservation details
$booking = $db->fetchOne(
    "SELECT b.*, dt.name as dhana_type_name, dt.price, u.first_name, u.last_name, u.email, u.contact_number
     FROM bookings b 
     JOIN dhana_types dt ON b.dhana_type_id = dt.id 
     JOIN users u ON b.user_id = u.id 
     WHERE b.id = ? AND b.user_id = ?",
    [$bookingId, $user['id']]
);

if (!$booking) {
    header('Location: dashboard.php');
    exit;
}

// Get bank details from settings
$bankDetails = $db->fetchOne(
    "SELECT setting_value FROM settings WHERE setting_key = 'bank_details'"
);

$timeoutSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'pending_booking_timeout_hours'");
$timeoutHours = $timeoutSetting ? max(0, (int)$timeoutSetting['setting_value']) : 48;
$paymentDeadlineTimestamp = strtotime($booking['created_at'] . ' +' . $timeoutHours . ' hours');
$paymentExpired = $timeoutHours > 0 && time() > $paymentDeadlineTimestamp;
$existingReceipt = $db->fetchOne(
    "SELECT * FROM payment_receipts WHERE booking_id = ? ORDER BY upload_date DESC, id DESC LIMIT 1",
    [$bookingId]
);

// Handle receipt upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt'])) {
    $errors = [];

    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $paymentReference = trim($_POST['payment_reference'] ?? '');
    $allowedPaymentMethods = ['bank_transfer', 'online_banking', 'mobile_banking', 'cash_deposit'];

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    } elseif (!in_array($booking['status'], ['pending', 'payment_pending'], true) || $paymentExpired || $existingReceipt) {
        $errors[] = 'This reservation is no longer eligible for a receipt upload.';
    }
    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        $errors[] = 'Please select a payment method';
    }
    if (empty($paymentReference) || strlen($paymentReference) > 100) {
        $errors[] = 'Please enter payment reference/transaction ID';
    }
    
    // Validate file upload
    $file = $_FILES['receipt'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a receipt file to upload';
    } else {
        $fileSize = $file['size'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($fileTmpName);
        $allowedMimes = [
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'], 'pdf' => ['application/pdf']
        ];
        
        // Check file size (5MB max)
        if ($fileSize > MAX_FILE_SIZE) {
            $errors[] = 'File size must be less than 5MB';
        }
        
        // Check file type
        if (!isset($allowedMimes[$fileType]) || !in_array($mimeType, $allowedMimes[$fileType], true)) {
            $errors[] = 'Only JPG, JPEG, PNG, and PDF files are allowed';
        }
    }
    
    if (empty($errors)) {
        try {
            // Generate unique filename
            $newFileName = 'receipt_' . $bookingId . '_' . bin2hex(random_bytes(16)) . '.' . $fileType;
            $uploadPath = UPLOAD_DIR . $newFileName;
            
            // Move uploaded file
            if (move_uploaded_file($fileTmpName, $uploadPath)) {
                $connection = $db->getConnection();
                try {
                    $connection->beginTransaction();
                    $db->fetchOne(
                        "SELECT id FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE",
                        [$bookingId, $user['id']]
                    );
                    if ($db->fetchOne("SELECT id FROM payment_receipts WHERE booking_id = ? LIMIT 1", [$bookingId])) {
                        throw new RuntimeException('A receipt has already been uploaded for this reservation.');
                    }
                    $db->query(
                        "INSERT INTO payment_receipts (booking_id, receipt_filename, payment_method, payment_reference) VALUES (?, ?, ?, ?)",
                        [$bookingId, $newFileName, $paymentMethod, $paymentReference]
                    );
                    $updated = $db->query(
                        "UPDATE bookings SET status = 'receipt_submitted' WHERE id = ? AND user_id = ? AND status IN ('pending', 'payment_pending')",
                        [$bookingId, $user['id']]
                    );
                    if ($updated->rowCount() !== 1) throw new RuntimeException('Reservation status changed before upload completed.');
                    $connection->commit();
                } catch (Throwable $writeError) {
                    if ($connection->inTransaction()) $connection->rollBack();
                    if (is_file($uploadPath)) unlink($uploadPath);
                    throw $writeError;
                }
                
                $successMessage = 'Receipt uploaded successfully! Your dāna reservation is now pending payment verification.';

                // Refresh reservation data
                $booking = $db->fetchOne(
                    "SELECT b.*, dt.name as dhana_type_name, dt.price, u.first_name, u.last_name, u.email, u.contact_number
                     FROM bookings b
                     JOIN dhana_types dt ON b.dhana_type_id = dt.id
                     JOIN users u ON b.user_id = u.id
                     WHERE b.id = ? AND b.user_id = ?",
                    [$bookingId, $user['id']]
                );

                // Send receipt uploaded email
                try {
                    require_once __DIR__ . '/includes/email.php';
                    $emailService = getEmailService();

                    $bookingData = [
                        'user_email' => $booking['email'],
                        'user_name' => $booking['first_name'] . ' ' . $booking['last_name'],
                        'booking_id' => $bookingId,
                        'dhana_type' => $booking['dhana_type_name'],
                        'booking_date' => $booking['booking_date']
                    ];

                    $emailService->sendReceiptUploadedEmail($bookingData);
                } catch (Exception $e) {
                    // Log email error but don't stop the process
                    error_log("Failed to send receipt uploaded email: " . $e->getMessage());
                }
            } else {
                $errors[] = 'Failed to upload receipt. Please try again.';
            }
        } catch (Throwable $e) {
            error_log("Receipt upload error: " . $e->getMessage());
            $errors[] = 'An error occurred while uploading the receipt. Please try again.';
        }
    }
}

?>
<!DOCTYPE html>
<?php echo ui_html_open(); ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo te('Payment - Dāna Reservation System', 'ගෙවීම - දාන වෙන්කිරීමේ පද්ධතිය'); ?></title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css?v=20260913">
    <link rel="stylesheet" href="assets/css/payment.css?v=20260913">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pro-ui.css?v=20260916">
</head>
<body>
    <div class="dashboard">
        <!-- Header -->
        <div class="dashboard-header">
            <?php ui_lang_switch(); ?>
            <h1><i class="fas fa-credit-card"></i> <?php echo te('Payment & Receipt Upload', 'ගෙවීම සහ රිසිට්පත උඩුගත කිරීම'); ?></h1>
            <p><?php echo te('Complete your dāna reservation by uploading payment receipt', 'ගෙවීම් රිසිට්පත උඩුගත කර දාන වෙන්කිරීම සම්පූර්ණ කරන්න'); ?></p>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo te('Back to Dashboard', 'ප්‍රධාන පුවරුවට'); ?>
            </a>
        </div>

        <!-- Three-Column Payment Container -->
        <div class="payment-container">
            <!-- Column 1: Dāna Reservation Summary -->
            <div class="booking-summary-card" id="printableSummary">
                <?php
                // Calculate payment deadline (72 hours from creation by default)
                $paymentDeadline = ui_date_keep(strtotime($booking['created_at'] . ' +' . $timeoutHours . ' hours'), 'F j, Y h:i A', 'datetime');

                $timeSlotDisplay = isset($booking['booking_time_slot']) ? ui_time_slot($booking['booking_time_slot']) : '';
                ?>

                <!-- Print Header (only visible when printing) -->
                <div class="print-header">
                    <h1><?php echo te('Dāna Reservation Summary', 'දාන වෙන්කිරීමේ සාරාංශය'); ?></h1>
                    <p class="print-subtitle"><?php echo te('Support Theravada - Dāna Reservation System', 'Support Theravada - දාන වෙන්කිරීමේ පද්ධතිය'); ?></p>
                    <p class="print-date"><?php echo te('Generated on:', 'සෑදූ දිනය:'); ?> <span id="printDateTime"></span></p>
                </div>

                <!-- Screen Header (hidden when printing) -->
                <h3 class="no-print"><i class="fas fa-receipt"></i> <?php echo te('Dāna Reservation Summary', 'දාන වෙන්කිරීමේ සාරාංශය'); ?></h3>

                <!-- Main Summary Table -->
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="table-header"><?php echo te('Reservation Information', 'වෙන්කිරීමේ තොරතුරු'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell"><?php echo te('Reservation ID', 'වෙන්කිරීමේ අංකය'); ?></td>
                            <td class="value-cell"><strong>#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Reservation Date Created', 'වෙන්කළ දිනය'); ?></td>
                            <td class="value-cell"><?php echo htmlspecialchars(ui_date_keep($booking['created_at'], 'F j, Y h:i A', 'datetime')); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Current Status', 'වත්මන් තත්ත්වය'); ?></td>
                            <td class="value-cell">
                                <span class="status-badge status-<?php echo $paymentExpired && $booking['status'] === 'pending' ? 'cancelled' : $booking['status']; ?>">
                                    <?php echo htmlspecialchars(ui_status($paymentExpired && $booking['status'] === 'pending' ? 'expired' : $booking['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Donor Information Table -->
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="table-header"><?php echo te('Donor Information', 'දායක තොරතුරු'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell"><?php echo te('Name', 'නම'); ?></td>
                            <td class="value-cell"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Email', 'විද්‍යුත් තැපෑල'); ?></td>
                            <td class="value-cell"><?php echo htmlspecialchars($booking['email']); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Contact Number', 'දුරකථන අංකය'); ?></td>
                            <td class="value-cell"><?php echo htmlspecialchars($booking['contact_number']); ?></td>
                        </tr>
                    </tbody>
                </table>

                <?php if (!empty($booking['booked_by_agent_id']) && !empty($booking['booked_for_first_name'])): ?>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="table-header"><?php echo te('Reservation Recipient', 'වෙන්කිරීම ලබන්නා'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell"><?php echo te('Name', 'නම'); ?></td>
                            <td class="value-cell"><?php echo htmlspecialchars(trim($booking['booked_for_first_name'] . ' ' . $booking['booked_for_last_name'])); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Primary Mobile', 'ප්‍රධාන ජංගම'); ?></td>
                            <td class="value-cell"><?php echo htmlspecialchars($booking['booked_for_primary_contact']); ?></td>
                        </tr>
                        <?php if (!empty($booking['booked_for_secondary_contact'])): ?>
                        <tr>
                            <td class="label-cell"><?php echo te('Second Mobile', 'දෙවන ජංගම'); ?></td>
                            <td class="value-cell"><?php echo htmlspecialchars($booking['booked_for_secondary_contact']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Dāna Details Table -->
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="table-header"><?php echo te('Dāna Details', 'දාන විස්තර'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell"><?php echo te('Dāna Type', 'දාන වර්ගය'); ?></td>
                            <td class="value-cell"><strong><?php echo htmlspecialchars(ui_dhana_name($booking['dhana_type_name'])); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Dāna Date', 'දාන දිනය'); ?></td>
                            <td class="value-cell"><strong><?php echo htmlspecialchars(ui_date_keep($booking['booking_date'], 'l, F j, Y', 'long')); ?></strong></td>
                        </tr>
                        <?php if ($timeSlotDisplay): ?>
                        <tr>
                            <td class="label-cell"><?php echo te('Time Slot', 'වේලාව'); ?></td>
                            <td class="value-cell"><?php echo $timeSlotDisplay; ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="label-cell"><?php echo te('Travel Support Offered', 'ගමන් පහසුකම්'); ?></td>
                            <td class="value-cell"><?php echo $booking['travel_support'] ? te('Yes', 'ඔව්') : te('No', 'නැත'); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Annual Event', 'වාර්ෂික උත්සවය'); ?></td>
                            <td class="value-cell"><?php echo $booking['is_annual_event'] ? te('Yes (Recurring Yearly)', 'ඔව් (වාර්ෂිකව)') : te('No', 'නැත'); ?></td>
                        </tr>
                        <?php if ($booking['special_requests']): ?>
                        <tr>
                            <td class="label-cell"><?php echo te('Special Requests', 'විශේෂ ඉල්ලීම්'); ?></td>
                            <td class="value-cell"><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="label-cell"><?php echo te('Special Requests', 'විශේෂ ඉල්ලීම්'); ?></td>
                            <td class="value-cell"><?php echo te('None', 'නැත'); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Payment Information Table -->
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="table-header"><?php echo te('Payment Information', 'ගෙවීම් තොරතුරු'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell"><?php echo te('Total Amount', 'මුළු මුදල'); ?></td>
                            <td class="value-cell amount-highlight"><strong>Rs. <?php echo number_format($booking['total_amount']); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Payment Receipt Submitted', 'ගෙවීම් රිසිට්පත යවා ඇත'); ?></td>
                            <td class="value-cell">
                                <?php if ($existingReceipt): ?>
                                    <span class="status-success">✓ <?php echo te('Yes', 'ඔව්'); ?></span> - <?php echo te('Uploaded on', 'උඩුගත කළ දිනය'); ?> <?php echo htmlspecialchars(ui_date_keep($existingReceipt['upload_date'], 'F j, Y h:i A', 'datetime')); ?>
                                <?php else: ?>
                                    <span class="status-warning">✗ <?php echo te('No', 'නැත'); ?></span> - <?php echo te('Please upload payment receipt', 'ගෙවීම් රිසිට්පත උඩුගත කරන්න'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($existingReceipt): ?>
                        <tr>
                            <td class="label-cell"><?php echo te('Payment Method', 'ගෙවීමේ ක්‍රමය'); ?></td>
                            <td class="value-cell"><?php echo htmlspecialchars(ui_payment_method($existingReceipt['payment_method'])); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Payment Reference', 'ගෙවීම් යොමුව'); ?></td>
                            <td class="value-cell"><?php echo htmlspecialchars($existingReceipt['payment_reference']); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell"><?php echo te('Receipt Verification Status', 'රිසිට්පත් තහවුරු තත්ත්වය'); ?></td>
                            <td class="value-cell">
                                <?php if ($existingReceipt['verified']): ?>
                                    <span class="status-success">✓ <?php echo te('Verified', 'තහවුරු කළා'); ?></span>
                                <?php else: ?>
                                    <span class="status-warning">⏳ <?php echo te('Pending Verification', 'තහවුරු කිරීම පොරොත්තුවෙන්'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!$existingReceipt && $timeoutHours > 0): ?>
                        <tr>
                            <td class="label-cell"><?php echo te('Payment Deadline', 'ගෙවීම් අවසන් දිනය'); ?></td>
                            <td class="value-cell <?php echo $paymentExpired ? 'status-error' : 'deadline-warning'; ?>">
                                <strong><?php echo htmlspecialchars($paymentDeadline); ?></strong>
                                <br><small><?php echo $paymentExpired ? te('This payment window has expired', 'මෙම ගෙවීම් කාලය ඉකුත් වී ඇත') : te('Receipt must be uploaded before this deadline', 'මෙම අවසන් දිනයට පෙර රිසිට්පත උඩුගත කරන්න'); ?></small>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Important Notice -->
                <div class="important-notice">
                    <h4><i class="fas fa-exclamation-triangle"></i> <?php echo te('Important Notice', 'වැදගත් දැනුම්දීම'); ?></h4>
                    <ul>
                        <li><strong><?php echo te('Temporary Reservation:', 'තාවකාලික වෙන්කිරීම:'); ?></strong> <?php echo te('This is a temporary booking until confirmed by staff.', 'කාර්ය මණ්ඩලය තහවුරු කරන තුරු මෙය තාවකාලික වෙන්කිරීමකි.'); ?></li>
                        <li><strong><?php echo te('Not a Guarantee:', 'සහතිකයක් නොවේ:'); ?></strong> <?php echo te('This receipt does not guarantee that your reservation is confirmed until the status is marked as "Confirmed" by authorized staff members.', 'බලයලත් කාර්ය මණ්ඩලය තත්ත්වය "තහවුරු කළ" ලෙස සලකුණු කරන තුරු මෙම රිසිට්පත වෙන්කිරීම තහවුරු වූ බවට සහතිකයක් නොවේ.'); ?></li>
                        <?php if (!$existingReceipt && $timeoutHours > 0): ?>
                        <li><strong><?php echo te('Payment Deadline:', 'ගෙවීම් අවසන් දිනය:'); ?></strong> <?php echo $paymentExpired ? te('The receipt deadline was', 'රිසිට්පත් අවසන් දිනය වූයේ') : te('Payment receipt must be uploaded before', 'ගෙවීම් රිසිට්පත උඩුගත කළ යුත්තේ'); ?> <strong><?php echo htmlspecialchars($paymentDeadline); ?></strong><?php echo $paymentExpired ? '.' : te('. Failure to upload will result in automatic cancellation of this reservation.', '. උඩුගත නොකළහොත් මෙම වෙන්කිරීම ස්වයංක්‍රීයව අවලංගු වේ.'); ?></li>
                        <?php endif; ?>
                        <li><strong><?php echo te('Verification Required:', 'තහවුරු කිරීම අවශ්‍යයි:'); ?></strong> <?php echo te('All payment receipts are subject to verification by administration.', 'සියලු ගෙවීම් රිසිට්පත් පරිපාලනය විසින් තහවුරු කරනු ලැබේ.'); ?></li>
                        <li><strong><?php echo te('Contact Us:', 'අප අමතන්න:'); ?></strong> <?php echo te('For any questions or concerns, please contact the administration.', 'ප්‍රශ්න හෝ ගැටලු සඳහා පරිපාලනය අමතන්න.'); ?></li>
                    </ul>
                </div>

                <!-- Print Summary Button (visible on screen, hidden when printing) -->
                <button class="btn-print-summary no-print" onclick="printSummary()">
                    <i class="fas fa-print"></i> <?php echo te('Print Summary', 'සාරාංශය මුද්‍රණය කරන්න'); ?>
                </button>
            </div>

            <!-- Right panel: receipt upload followed by clear bank details -->
            <aside class="payment-side-panel" aria-label="<?php echo te('Payment submission', 'ගෙවීම ඉදිරිපත් කිරීම'); ?>">
            <?php if (($booking['status'] === 'pending' || $booking['status'] === 'payment_pending') && !$paymentExpired && !$existingReceipt): ?>
                <div class="receipt-upload-form">
                    <h3><i class="fas fa-upload"></i> <?php echo te('Upload Payment Receipt', 'ගෙවීම් රිසිට්පත උඩුගත කරන්න'); ?></h3>

                    <?php if (isset($successMessage)): ?>
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            <?php echo te_msg($successMessage); ?>
                        </div>
                    <?php endif; ?>

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

                    <form method="POST" enctype="multipart/form-data" class="upload-form">
                        <?php echo csrfInput(); ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="payment_method"><?php echo te('Payment Method', 'ගෙවීමේ ක්‍රමය'); ?></label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value=""><?php echo te('Select payment method', 'ගෙවීමේ ක්‍රමය තෝරන්න'); ?></option>
                                    <option value="bank_transfer"><?php echo te('Bank Transfer', 'බැංකු මාරුව'); ?></option>
                                    <option value="online_banking"><?php echo te('Online Banking', 'ඔන්ලයින් බැංකුකරණය'); ?></option>
                                    <option value="mobile_banking"><?php echo te('Mobile Banking', 'ජංගම බැංකුකරණය'); ?></option>
                                    <option value="cash_deposit"><?php echo te('Cash Deposit', 'මුදල් තැන්පතුව'); ?></option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="payment_reference"><?php echo te('Transaction ID / Reference', 'ගනුදෙනු අංකය / යොමුව'); ?></label>
                                <input type="text" id="payment_reference" name="payment_reference" maxlength="100"
                                       placeholder="<?php echo te('Enter transaction ID or reference number', 'ගනුදෙනු අංකය හෝ යොමු අංකය ඇතුළත් කරන්න'); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="receipt"><?php echo te('Receipt File', 'රිසිට්පත් ගොනුව'); ?></label>
                            <div class="file-upload-area">
                                <input type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required>
                                <div class="file-upload-text">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><?php echo te('Click to select receipt file or drag and drop', 'රිසිට්පත් ගොනුව තේරීමට ඔබන්න හෝ ඇද දමන්න'); ?></p>
                                    <small><?php echo te('Supported formats: JPG, PNG, PDF (Max 5MB)', 'JPG, PNG, PDF (උපරිම 5MB)'); ?></small>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-upload"></i>
                            <?php echo te('Upload Receipt', 'රිසිට්පත උඩුගත කරන්න'); ?>
                        </button>
                    </form>
                </div>
            <?php elseif ($existingReceipt): ?>
                <!-- Show Existing Receipt in Middle Column -->
                <div class="existing-receipt">
                    <h3><i class="fas fa-file-invoice"></i> <?php echo te('Uploaded Receipt', 'උඩුගත කළ රිසිට්පත'); ?></h3>

                    <div class="receipt-info">
                        <div class="receipt-item">
                            <label><?php echo te('Upload Date:', 'උඩුගත කළ දිනය:'); ?></label>
                            <span><?php echo htmlspecialchars(ui_date_keep($existingReceipt['upload_date'], 'F j, Y g:i A', 'datetime')); ?></span>
                        </div>

                        <div class="receipt-item">
                            <label><?php echo te('Payment Method:', 'ගෙවීමේ ක්‍රමය:'); ?></label>
                            <span><?php echo htmlspecialchars(ui_payment_method($existingReceipt['payment_method'])); ?></span>
                        </div>

                        <div class="receipt-item">
                            <label><?php echo te('Reference:', 'යොමුව:'); ?></label>
                            <span><?php echo htmlspecialchars($existingReceipt['payment_reference']); ?></span>
                        </div>

                        <div class="receipt-item">
                            <label><?php echo te('Status:', 'තත්ත්වය:'); ?></label>
                            <span class="verification-status <?php echo $existingReceipt['verified'] ? 'verified' : 'pending'; ?>">
                                <?php if ($existingReceipt['verified']): ?>
                                    <i class="fas fa-check-circle"></i> <?php echo te('Verified', 'තහවුරු කළා'); ?>
                                <?php else: ?>
                                    <i class="fas fa-clock"></i> <?php echo te('Pending Verification', 'තහවුරු කිරීම පොරොත්තුවෙන්'); ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="receipt-item">
                            <label><?php echo te('File:', 'ගොනුව:'); ?></label>
                            <a href="receipt.php?id=<?php echo (int)$existingReceipt['id']; ?>"
                               target="_blank" rel="noopener" class="receipt-link">
                                <i class="fas fa-external-link-alt"></i>
                                <?php echo te('View Receipt', 'රිසිට්පත බලන්න'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif ($paymentExpired): ?>
                <div class="existing-receipt payment-expired-notice" role="status">
                    <h3><i class="fas fa-clock"></i> <?php echo te('Payment Window Expired', 'ගෙවීම් කාලය ඉකුත් විය'); ?></h3>
                    <p><?php echo te('This temporary reservation can no longer accept a receipt. Please create a new reservation or contact the administration if you already made the payment.', 'මෙම තාවකාලික වෙන්කිරීමට තවදුරටත් රිසිට්පතක් භාර ගත නොහැක. නව වෙන්කිරීමක් සාදන්න, නැතහොත් දැනටමත් ගෙවා ඇත්නම් පරිපාලනය අමතන්න.'); ?></p>
                    <a href="booking-new.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> <?php echo te('Make a New Reservation', 'නව වෙන්කිරීමක් කරන්න'); ?></a>
                </div>
            <?php endif; ?>

                <section class="bank-details-card payment-bank-details">
                    <div class="bank-details-header">
                        <h3><i class="fas fa-university"></i> <?php echo te('Bank Transfer Details', 'බැංකු මාරු විස්තර'); ?></h3>
                        <button type="button" class="copy-bank-details" onclick="copyBankDetails()">
                            <i class="fas fa-copy"></i> <?php echo te('Copy Details', 'විස්තර පිටපත් කරන්න'); ?>
                        </button>
                    </div>
                    <p class="bank-details-intro">
                        <?php echo te('Transfer exactly', 'හරියටම මාරු කරන්න'); ?> <strong>Rs. <?php echo number_format($booking['total_amount']); ?></strong><?php echo ui_lang() === 'si' ? ', ඉන්පසු ඉහත පෝරමයට ආපසු ගොස් රිසිට්පත උඩුගත කරන්න.' : ', then return to the form above and upload the receipt.'; ?>
                    </p>
                    <div class="bank-details" id="bankDetailsText" aria-label="<?php echo te('Bank account details', 'බැංකු ගිණුම් විස්තර'); ?>">
                        <?php if ($bankDetails): ?>
                            <?php echo nl2br(htmlspecialchars($bankDetails['setting_value'])); ?>
                        <?php else: ?>
                            <p><?php echo te('Bank details will be provided by the administration.', 'බැංකු විස්තර පරිපාලනය විසින් ලබා දෙනු ඇත.'); ?></p>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </div> <!-- Close payment-container -->
    </div> <!-- Close dashboard -->

    <script src="assets/js/payment.js?v=20261111"></script>
    <script>
        // Copy bank details function
        function copyBankDetails() {
            const bankDetailsText = document.getElementById('bankDetailsText').innerText;

            if (navigator.clipboard && window.isSecureContext) {
                // Use modern clipboard API
                navigator.clipboard.writeText(bankDetailsText).then(() => {
                    showCopySuccess();
                }).catch(() => {
                    fallbackCopy(bankDetailsText);
                });
            } else {
                // Fallback for older browsers
                fallbackCopy(bankDetailsText);
            }
        }

        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                showCopySuccess();
            } catch (err) {
                console.error('Failed to copy text: ', err);
                alert(window.uiText ? window.uiText('Failed to copy bank details. Please copy manually.') : 'Failed to copy bank details. Please copy manually.');
            }

            document.body.removeChild(textArea);
        }

        function showCopySuccess() {
            const button = document.querySelector('.copy-bank-details');
            const originalText = button.innerHTML;

            button.innerHTML = '<i class="fas fa-check"></i> ' + (window.uiText ? window.uiText('Copied!') : 'Copied!');
            button.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';

            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
            }, 2000);
        }

        // Print Summary Function
        function printSummary() {
            // Set print date in the header
            const now = new Date();
            const printDate = window.uiFormatDateTime ? window.uiFormatDateTime(now) : now.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });

            const printDateElement = document.getElementById('printDateTime');
            if (printDateElement) {
                printDateElement.textContent = printDate;
            }

            // Trigger print
            window.print();
        }

        // Set initial print date on page load
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const printDate = window.uiFormatDateTime ? window.uiFormatDateTime(now) : now.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });

            const printDateElement = document.getElementById('printDateTime');
            if (printDateElement) {
                printDateElement.textContent = printDate;
            }
        });
    </script>
</body>
</html>
