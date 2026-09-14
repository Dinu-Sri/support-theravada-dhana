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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Dāna Reservation System</title>
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
            <h1><i class="fas fa-credit-card"></i> Payment & Receipt Upload</h1>
            <p>Complete your dāna reservation by uploading payment receipt</p>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Three-Column Payment Container -->
        <div class="payment-container">
            <!-- Column 1: Dāna Reservation Summary -->
            <div class="booking-summary-card" id="printableSummary">
                <?php
                // Calculate payment deadline (72 hours from creation by default)
                $paymentDeadline = date('F j, Y h:i A', strtotime($booking['created_at'] . ' +' . $timeoutHours . ' hours'));

                // Get time slot display
                $timeSlotDisplay = '';
                if (isset($booking['booking_time_slot'])) {
                    switch($booking['booking_time_slot']) {
                        case 'morning': $timeSlotDisplay = 'Morning Dāna'; break;
                        case 'lunch': $timeSlotDisplay = 'Lunch Dāna'; break;
                        case 'whole_day': $timeSlotDisplay = 'Whole Day'; break;
                        default: $timeSlotDisplay = ucfirst($booking['booking_time_slot']);
                    }
                }
                ?>

                <!-- Print Header (only visible when printing) -->
                <div class="print-header">
                    <h1>Dāna Reservation Summary</h1>
                    <p class="print-subtitle">Support Theravada - Dāna Reservation System</p>
                    <p class="print-date">Generated on: <span id="printDateTime"></span></p>
                </div>

                <!-- Screen Header (hidden when printing) -->
                <h3 class="no-print"><i class="fas fa-receipt"></i> Dāna Reservation Summary</h3>

                <!-- Main Summary Table -->
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="table-header">Reservation Information</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell">Reservation ID</td>
                            <td class="value-cell"><strong>#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Reservation Date Created</td>
                            <td class="value-cell"><?php echo date('F j, Y h:i A', strtotime($booking['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Current Status</td>
                            <td class="value-cell">
                                <span class="status-badge status-<?php echo $paymentExpired && $booking['status'] === 'pending' ? 'cancelled' : $booking['status']; ?>">
                                    <?php echo $paymentExpired && $booking['status'] === 'pending' ? 'Expired' : ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Donor Information Table -->
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="table-header">Donor Information</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell">Name</td>
                            <td class="value-cell"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Email</td>
                            <td class="value-cell"><?php echo htmlspecialchars($booking['email']); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Contact Number</td>
                            <td class="value-cell"><?php echo htmlspecialchars($booking['contact_number']); ?></td>
                        </tr>
                    </tbody>
                </table>

                <?php if (!empty($booking['booked_by_agent_id']) && !empty($booking['booked_for_first_name'])): ?>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="table-header">Reservation Recipient</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell">Name</td>
                            <td class="value-cell"><?php echo htmlspecialchars(trim($booking['booked_for_first_name'] . ' ' . $booking['booked_for_last_name'])); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Primary Mobile</td>
                            <td class="value-cell"><?php echo htmlspecialchars($booking['booked_for_primary_contact']); ?></td>
                        </tr>
                        <?php if (!empty($booking['booked_for_secondary_contact'])): ?>
                        <tr>
                            <td class="label-cell">Second Mobile</td>
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
                            <th colspan="2" class="table-header">Dāna Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell">Dāna Type</td>
                            <td class="value-cell"><strong><?php echo htmlspecialchars($booking['dhana_type_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Dāna Date</td>
                            <td class="value-cell"><strong><?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?></strong></td>
                        </tr>
                        <?php if ($timeSlotDisplay): ?>
                        <tr>
                            <td class="label-cell">Time Slot</td>
                            <td class="value-cell"><?php echo $timeSlotDisplay; ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="label-cell">Travel Support Offered</td>
                            <td class="value-cell"><?php echo $booking['travel_support'] ? 'Yes' : 'No'; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Annual Event</td>
                            <td class="value-cell"><?php echo $booking['is_annual_event'] ? 'Yes (Recurring Yearly)' : 'No'; ?></td>
                        </tr>
                        <?php if ($booking['special_requests']): ?>
                        <tr>
                            <td class="label-cell">Special Requests</td>
                            <td class="value-cell"><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="label-cell">Special Requests</td>
                            <td class="value-cell">None</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Payment Information Table -->
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="table-header">Payment Information</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label-cell">Total Amount</td>
                            <td class="value-cell amount-highlight"><strong>Rs. <?php echo number_format($booking['total_amount']); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Payment Receipt Submitted</td>
                            <td class="value-cell">
                                <?php if ($existingReceipt): ?>
                                    <span class="status-success">✓ Yes</span> - Uploaded on <?php echo date('F j, Y h:i A', strtotime($existingReceipt['upload_date'])); ?>
                                <?php else: ?>
                                    <span class="status-warning">✗ No</span> - Please upload payment receipt
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($existingReceipt): ?>
                        <tr>
                            <td class="label-cell">Payment Method</td>
                            <td class="value-cell"><?php echo ucfirst(str_replace('_', ' ', $existingReceipt['payment_method'])); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Payment Reference</td>
                            <td class="value-cell"><?php echo htmlspecialchars($existingReceipt['payment_reference']); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Receipt Verification Status</td>
                            <td class="value-cell">
                                <?php if ($existingReceipt['verified']): ?>
                                    <span class="status-success">✓ Verified</span>
                                <?php else: ?>
                                    <span class="status-warning">⏳ Pending Verification</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!$existingReceipt && $timeoutHours > 0): ?>
                        <tr>
                            <td class="label-cell">Payment Deadline</td>
                            <td class="value-cell <?php echo $paymentExpired ? 'status-error' : 'deadline-warning'; ?>">
                                <strong><?php echo $paymentDeadline; ?></strong>
                                <br><small><?php echo $paymentExpired ? 'This payment window has expired' : 'Receipt must be uploaded before this deadline'; ?></small>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Important Notice -->
                <div class="important-notice">
                    <h4><i class="fas fa-exclamation-triangle"></i> Important Notice</h4>
                    <ul>
                        <li><strong>Temporary Reservation:</strong> This is a temporary booking until confirmed by staff.</li>
                        <li><strong>Not a Guarantee:</strong> This receipt does not guarantee that your reservation is confirmed until the status is marked as "Confirmed" by authorized staff members.</li>
                        <?php if (!$existingReceipt && $timeoutHours > 0): ?>
                        <li><strong>Payment Deadline:</strong> <?php echo $paymentExpired ? 'The receipt deadline was' : 'Payment receipt must be uploaded before'; ?> <strong><?php echo $paymentDeadline; ?></strong><?php echo $paymentExpired ? '.' : '. Failure to upload will result in automatic cancellation of this reservation.'; ?></li>
                        <?php endif; ?>
                        <li><strong>Verification Required:</strong> All payment receipts are subject to verification by administration.</li>
                        <li><strong>Contact Us:</strong> For any questions or concerns, please contact the administration.</li>
                    </ul>
                </div>

                <!-- Print Summary Button (visible on screen, hidden when printing) -->
                <button class="btn-print-summary no-print" onclick="printSummary()">
                    <i class="fas fa-print"></i> Print Summary
                </button>
            </div>

            <!-- Right panel: receipt upload followed by clear bank details -->
            <aside class="payment-side-panel" aria-label="Payment submission">
            <?php if (($booking['status'] === 'pending' || $booking['status'] === 'payment_pending') && !$paymentExpired && !$existingReceipt): ?>
                <div class="receipt-upload-form">
                    <h3><i class="fas fa-upload"></i> Upload Payment Receipt</h3>

                    <?php if (isset($successMessage)): ?>
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    <?php endif; ?>

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

                    <form method="POST" enctype="multipart/form-data" class="upload-form">
                        <?php echo csrfInput(); ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="payment_method">Payment Method</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="">Select payment method</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="online_banking">Online Banking</option>
                                    <option value="mobile_banking">Mobile Banking</option>
                                    <option value="cash_deposit">Cash Deposit</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="payment_reference">Transaction ID / Reference</label>
                                <input type="text" id="payment_reference" name="payment_reference" maxlength="100"
                                       placeholder="Enter transaction ID or reference number" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="receipt">Receipt File</label>
                            <div class="file-upload-area">
                                <input type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required>
                                <div class="file-upload-text">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to select receipt file or drag and drop</p>
                                    <small>Supported formats: JPG, PNG, PDF (Max 5MB)</small>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-upload"></i>
                            Upload Receipt
                        </button>
                    </form>
                </div>
            <?php elseif ($existingReceipt): ?>
                <!-- Show Existing Receipt in Middle Column -->
                <div class="existing-receipt">
                    <h3><i class="fas fa-file-invoice"></i> Uploaded Receipt</h3>

                    <div class="receipt-info">
                        <div class="receipt-item">
                            <label>Upload Date:</label>
                            <span><?php echo date('F j, Y g:i A', strtotime($existingReceipt['upload_date'])); ?></span>
                        </div>

                        <div class="receipt-item">
                            <label>Payment Method:</label>
                            <span><?php echo ucfirst(str_replace('_', ' ', $existingReceipt['payment_method'])); ?></span>
                        </div>

                        <div class="receipt-item">
                            <label>Reference:</label>
                            <span><?php echo htmlspecialchars($existingReceipt['payment_reference']); ?></span>
                        </div>

                        <div class="receipt-item">
                            <label>Status:</label>
                            <span class="verification-status <?php echo $existingReceipt['verified'] ? 'verified' : 'pending'; ?>">
                                <?php if ($existingReceipt['verified']): ?>
                                    <i class="fas fa-check-circle"></i> Verified
                                <?php else: ?>
                                    <i class="fas fa-clock"></i> Pending Verification
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="receipt-item">
                            <label>File:</label>
                            <a href="receipt.php?id=<?php echo (int)$existingReceipt['id']; ?>"
                               target="_blank" rel="noopener" class="receipt-link">
                                <i class="fas fa-external-link-alt"></i>
                                View Receipt
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif ($paymentExpired): ?>
                <div class="existing-receipt payment-expired-notice" role="status">
                    <h3><i class="fas fa-clock"></i> Payment Window Expired</h3>
                    <p>This temporary reservation can no longer accept a receipt. Please create a new reservation or contact the administration if you already made the payment.</p>
                    <a href="booking-new.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Make a New Reservation</a>
                </div>
            <?php endif; ?>

                <section class="bank-details-card payment-bank-details">
                    <div class="bank-details-header">
                        <h3><i class="fas fa-university"></i> Bank Transfer Details</h3>
                        <button type="button" class="copy-bank-details" onclick="copyBankDetails()">
                            <i class="fas fa-copy"></i> Copy Details
                        </button>
                    </div>
                    <p class="bank-details-intro">
                        Transfer exactly <strong>Rs. <?php echo number_format($booking['total_amount']); ?></strong>, then return to the form above and upload the receipt.
                    </p>
                    <div class="bank-details" id="bankDetailsText" aria-label="Bank account details">
                        <?php if ($bankDetails): ?>
                            <?php echo nl2br(htmlspecialchars($bankDetails['setting_value'])); ?>
                        <?php else: ?>
                            <p>Bank details will be provided by the administration.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </div> <!-- Close payment-container -->
    </div> <!-- Close dashboard -->

    <script src="assets/js/payment.js"></script>
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
                alert('Failed to copy bank details. Please copy manually.');
            }

            document.body.removeChild(textArea);
        }

        function showCopySuccess() {
            const button = document.querySelector('.copy-bank-details');
            const originalText = button.innerHTML;

            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
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
            const printDate = now.toLocaleDateString('en-US', {
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
            const printDate = now.toLocaleDateString('en-US', {
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
