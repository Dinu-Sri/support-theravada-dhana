<?php
require_once 'includes/auth.php';
require_once 'includes/booking-rules.php';

// Require user to be logged in
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();
$paymentTimeoutHours = max(0, bookingSettingInt($db, 'pending_booking_timeout_hours', 48));
$activePendingSql = $paymentTimeoutHours === 0
    ? "status = 'pending'"
    : "status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL {$paymentTimeoutHours} HOUR)";
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;
$bookingSummary = $db->fetchOne(
    "SELECT COUNT(*) AS total,
            SUM(status = 'confirmed') AS confirmed,
            SUM(status IN ('receipt_submitted','payment_pending') OR ({$activePendingSql})) AS pending,
            COALESCE(SUM(CASE
                WHEN booking_date >= CURDATE()
                 AND (status IN ('confirmed','receipt_submitted','payment_pending') OR ({$activePendingSql}))
                THEN total_amount ELSE 0 END), 0) AS active_value
     FROM bookings WHERE user_id = ?",
    [$user['id']]
);
$totalPages = max(1, (int)ceil(((int)$bookingSummary['total']) / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Get user's bookings including annual event information
$bookings = $db->fetchAll(
    "SELECT b.*, dt.name as dhana_type_name, dt.price,
            pr.id as receipt_id, pr.receipt_filename, pr.verified as receipt_verified, pr.upload_date,
            ab.year_start, ab.year_end,
            CASE
                WHEN b.is_annual_event = 1 AND b.parent_booking_id IS NULL THEN 'main_annual'
                WHEN b.is_annual_event = 1 AND b.parent_booking_id IS NOT NULL THEN 'annual_instance'
                ELSE 'regular'
            END as booking_type,
            parent.booking_date as parent_booking_date
     FROM bookings b
     JOIN dhana_types dt ON b.dhana_type_id = dt.id
     LEFT JOIN payment_receipts pr ON pr.id = (
         SELECT pr2.id FROM payment_receipts pr2 WHERE pr2.booking_id = b.id
         ORDER BY pr2.upload_date DESC, pr2.id DESC LIMIT 1
     )
     LEFT JOIN annual_bookings ab ON (b.id = ab.booking_id OR b.parent_booking_id = ab.booking_id)
     LEFT JOIN bookings parent ON b.parent_booking_id = parent.id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC, b.booking_date ASC
     LIMIT {$perPage} OFFSET {$offset}",
    [$user['id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dāna Reservations - Dāna Reservation System</title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pro-ui.css?v=20260915">
</head>
<body>
    <div class="dashboard">
        <!-- Header -->
        <div class="dashboard-header">
            <h1><i class="fas fa-list"></i> My Dāna Reservations</h1>
            <p>View all your dāna reservations and their status
                <?php if (!empty($bookings)): ?>
                    (<?php echo (int)$bookingSummary['total']; ?> total reservation<?php echo (int)$bookingSummary['total'] !== 1 ? 's' : ''; ?>)
                <?php endif; ?>
            </p>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Dāna Reservations Summary -->
        <?php if (!empty($bookings)): ?>
            <div class="reservations-summary-container">
                <div class="reservations-summary">
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="summary-info">
                            <h3><?php echo (int)$bookingSummary['total']; ?></h3>
                            <p>Total Dāna Reservations</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon confirmed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="summary-info">
                            <h3><?php echo (int)$bookingSummary['confirmed']; ?></h3>
                            <p>Confirmed</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="summary-info">
                            <h3><?php echo (int)$bookingSummary['pending']; ?></h3>
                            <p>Active Pending</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon amount">
                            <i class="fas fa-money-bill"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Rs. <?php echo number_format($bookingSummary['active_value']); ?></h3>
                            <p>Upcoming Active Value</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dāna Reservations List -->
        <div class="step-guide">
            <?php if (empty($bookings)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #666;">
                    <i class="fas fa-calendar-times" style="font-size: 4rem; margin-bottom: 30px; opacity: 0.3;"></i>
                    <h3>No Dāna Reservations Yet</h3>
                    <p>You haven't made any dāna reservations yet. Start by creating your first dāna reservation!</p>
                    <div style="margin-top: 30px;">
                        <a href="calendar.php" class="btn btn-primary" style="margin-right: 15px;">
                            <i class="fas fa-calendar-alt"></i> Check Availability
                        </a>
                        <a href="booking-new.php" class="btn btn-secondary">
                            <i class="fas fa-plus-circle"></i> New Dāna Reservation
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="reservations-grid">
                    <?php foreach ($bookings as $booking): ?>
                        <?php
                        $paymentDeadlineTs = strtotime($booking['created_at'] . ' +' . $paymentTimeoutHours . ' hours');
                        $paymentWindowExpired = $paymentTimeoutHours > 0 && time() > $paymentDeadlineTs;
                        ?>
                        <div class="reservation-card">
                            <div class="reservation-header">
                                <div class="reservation-id">
                                    <strong>#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                </div>
                                <div class="reservation-status">
                                    <span class="status-badge status-<?php echo $paymentWindowExpired && $booking['status'] === 'pending' ? 'cancelled' : $booking['status']; ?>">
                                        <?php echo $paymentWindowExpired && $booking['status'] === 'pending' ? 'Expired' : ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="reservation-details">
                                <div class="detail-row">
                                    <i class="fas fa-hand-holding-heart"></i>
                                    <span>
                                        <?php echo htmlspecialchars($booking['dhana_type_name']); ?>
                                        <?php if ($booking['is_annual_event']): ?>
                                            <span class="annual-badge">
                                                <i class="fas fa-calendar-check"></i> Annual
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="detail-row">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
                                </div>

                                <?php if (!empty($booking['booked_by_agent_id'])): ?>
                                    <div class="detail-row">
                                        <i class="fas fa-user-friends"></i>
                                        <span>For: <?php echo htmlspecialchars(trim(($booking['booked_for_first_name'] ?? '') . ' ' . ($booking['booked_for_last_name'] ?? ''))); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($booking['is_annual_event'] && $booking['year_start'] && $booking['year_end']): ?>
                                    <div class="detail-row">
                                        <i class="fas fa-repeat"></i>
                                        <span>Annual Event: <?php echo $booking['year_start']; ?> - <?php echo $booking['year_end']; ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="detail-row">
                                    <i class="fas fa-money-bill"></i>
                                    <span>Rs. <?php echo number_format($booking['total_amount']); ?></span>
                                </div>

                                <div class="detail-row">
                                    <i class="fas fa-clock"></i>
                                    <span>Booked on <?php echo date('M j, Y', strtotime($booking['created_at'])); ?></span>
                                </div>
                                
                                <?php if ($booking['special_requests']): ?>
                                    <div class="detail-row">
                                        <i class="fas fa-comment"></i>
                                        <span><?php echo htmlspecialchars($booking['special_requests']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($booking['receipt_filename']): ?>
                                <div class="receipt-info">
                                    <div class="receipt-status">
                                        <?php if ($booking['receipt_verified']): ?>
                                            <i class="fas fa-check-circle" style="color: #28a745;"></i>
                                            <span style="color: #28a745;">Receipt Verified</span>
                                        <?php else: ?>
                                            <i class="fas fa-clock" style="color: #ffc107;"></i>
                                            <span style="color: #ffc107;">Receipt Pending Verification</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="receipt.php?id=<?php echo (int)$booking['receipt_id']; ?>"
                                       target="_blank" rel="noopener" class="receipt-link">
                                        <i class="fas fa-external-link-alt"></i> View Receipt
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="reservation-actions">
                                <?php if ($booking['status'] === 'pending' && !$paymentWindowExpired): ?>
                                    <a href="payment.php?booking_id=<?php echo $booking['id']; ?>"
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-credit-card"></i> Complete Payment
                                    </a>
                                <?php elseif ($booking['status'] === 'pending' && $paymentWindowExpired): ?>
                                    <span class="status-text cancelled">
                                        <i class="fas fa-clock"></i> Payment window expired
                                    </span>
                                <?php elseif ($booking['status'] === 'receipt_submitted' || $booking['status'] === 'payment_pending'): ?>
                                    <span class="status-text">
                                        <i class="fas fa-hourglass-half"></i> Awaiting Payment Verification
                                    </span>
                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                    <span class="status-text confirmed">
                                        <i class="fas fa-check-circle"></i> Dāna Reservation Confirmed
                                    </span>
                                <?php elseif ($booking['status'] === 'completed'): ?>
                                    <span class="status-text completed">
                                        <i class="fas fa-star"></i> Completed
                                    </span>
                                <?php elseif ($booking['status'] === 'cancelled'): ?>
                                    <span class="status-text cancelled">
                                        <i class="fas fa-times-circle"></i> Cancelled
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Reservation pages">
                        <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="?page=<?php echo $page - 1; ?>">Previous</a><?php endif; ?>
                        <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?><a class="btn btn-secondary btn-sm" href="?page=<?php echo $page + 1; ?>">Next</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 40px;">
                    <a href="booking-new.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Make New Dāna Reservation
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        body {
            overflow-y: auto !important;
            height: auto !important;
        }

        .dashboard {
            height: auto !important;
            min-height: 100vh;
            overflow: visible !important;
        }

        .reservations-summary-container {
            padding: 0 30px;
            margin: 30px 0;
        }

        .reservations-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid #d4822a;
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #d4822a;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .summary-icon.confirmed {
            background: #28a745;
        }

        .summary-icon.pending {
            background: #ffc107;
        }

        .summary-icon.amount {
            background: #17a2b8;
        }

        .summary-info h3 {
            margin: 0;
            font-size: 1.8rem;
            color: #333;
            font-weight: 600;
        }

        .summary-info p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 0.9rem;
        }

        .reservations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .reservation-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #d4822a;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .reservation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .reservation-id {
            font-size: 1.1rem;
            color: #333;
        }

        .reservation-details {
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            color: #555;
        }
        
        .detail-row i {
            color: #d4822a;
            width: 16px;
            text-align: center;
        }
        
        .receipt-info {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .receipt-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .receipt-link {
            color: #d4822a;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .receipt-link:hover {
            text-decoration: underline;
        }
        
        .reservation-actions {
            text-align: center;
        }
        
        .status-text {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .status-text.confirmed {
            color: #28a745;
        }
        
        .status-text.completed {
            color: #20c997;
        }
        
        .status-text.cancelled {
            color: #dc3545;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .bookings-summary-container {
                padding: 0 20px;
                margin: 20px 0;
            }

            .bookings-summary {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .summary-card {
                padding: 15px;
                gap: 12px;
            }

            .summary-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .summary-info h3 {
                font-size: 1.5rem;
            }

            .reservations-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .reservation-card {
                padding: 20px;
            }

            .receipt-info {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .reservations-summary-container {
                padding: 0 15px;
            }

            .reservations-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
