<?php
/**
 * Basic Admin Panel for Dāna Reservation System
 */

session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminEnsureCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_logout'])) {
    adminRequireLogin(false);
    adminRequireCsrf(null, false);
    adminDestroySession();
    header('Location: index.php?logged_out=1');
    exit;
}

if (!isset($_SESSION['admin_logged_in'])) {
    if (isset($_GET['expired'])) {
        $loginError = 'Your admin session expired after being inactive. Please sign in again.';
    } elseif (isset($_GET['account'])) {
        $loginError = 'This admin account is no longer active.';
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        adminRequireCsrf(null, false);
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        
        $db = getDB();
        $throttle = adminLoginThrottleStatus($db, $username);
        $admin = null;
        if (!$throttle['blocked']) {
            $admin = $db->fetchOne(
                "SELECT * FROM admin_users WHERE username = ? AND is_active = 1",
                [$username]
            );
        }
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            adminClearLoginFailures($db, $username);
            adminEstablishSession($admin);
            header('Location: index.php');
            exit;
        } else {
            if (!$throttle['blocked']) adminRecordLoginFailure($db, $username);
            $loginError = $throttle['blocked']
                ? 'Too many sign-in attempts. Please wait 15 minutes and try again.'
                : 'Invalid username or password';
        }
    }
    
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - Dāna Reservation System</title>
        <?php include '../includes/favicon.php'; ?>
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>
            .login-page {
                background-image: url('../uploads/bck.webp');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                background-attachment: fixed;
            }

            /* Responsive background for different screen sizes */
            @media (max-width: 768px) {
                .login-page {
                    background-attachment: scroll;
                    background-size: cover;
                }
            }

            @media (max-width: 480px) {
                .login-page {
                    background-size: cover;
                    background-position: center center;
                }
            }

            /* Add overlay for better text readability */
            .login-page::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.3);
                z-index: -1;
            }

            .login-card {
                position: relative;
                z-index: 1;
            }
        </style>
    </head>
    <body class="login-page">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h1><i class="fas fa-user-shield"></i> Admin Login</h1>
                    <p>Access the admin panel</p>
                </div>
                
                <form method="POST" class="auth-form active">
                    <input type="hidden" name="admin_login" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                    
                    <?php if (isset($loginError)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($loginError); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" autocomplete="username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="adminLoginButton">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    
                    <p class="auth-switch">
                        <a href="forgot-password.php">Forgot password?</a><br>
                        <a href="../index.php">← Back to Main Site</a>
                    </p>
                </form>
            </div>
        </div>
        <script>
            document.querySelector('.auth-form').addEventListener('submit', function () {
                const button = document.getElementById('adminLoginButton');
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

$db = getDB();
adminRefreshIdentity($db, false);

// This token protects JSON mutations initiated from the User Management modal.
adminEnsureCsrfToken();

// Add permission checking function
function hasPermission($permission) {
    global $db;
    return adminHasPermission($db, $permission);
}

// Handle reservation status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    adminRequireCsrf(null, false);
    $bookingId = (int)$_POST['booking_id'];
    $newStatus = $_POST['status'];

    $validStatuses = ['pending', 'receipt_submitted', 'payment_pending', 'confirmed', 'completed', 'cancelled'];
    if (!adminHasAnyPermission($db, ['accept_reject_requests', 'update_booking_status'])) {
        $errorMessage = 'You do not have permission to change reservation statuses.';
    } elseif (in_array($newStatus, $validStatuses, true)) {

        // Check if user has permission to accept/reject requests
        if (hasPermission('accept_reject_requests')) {
            $db->query(
                "UPDATE bookings SET status = ? WHERE id = ?",
                [$newStatus, $bookingId]
            );

            // If confirming, also mark receipt as verified
            if ($newStatus === 'confirmed' && hasPermission('verify_payments')) {
                $db->query(
                    "UPDATE payment_receipts SET verified = 1, verified_by = ?, verified_at = NOW()
                     WHERE booking_id = ?",
                    [$_SESSION['admin_id'], $bookingId]
                );
            }

            // Send email notification to customer
            try {
                require_once __DIR__ . '/../includes/booking-emails.php';
                $emailService = getBookingEmailService();

                // Get booking details with user info
                $bookingDetails = $db->fetchOne(
                    "SELECT b.*, dt.name as dhana_type_name, u.first_name, u.last_name, u.email
                     FROM bookings b
                     JOIN dhana_types dt ON b.dhana_type_id = dt.id
                     JOIN users u ON b.user_id = u.id
                     WHERE b.id = ?",
                    [$bookingId]
                );

                if ($bookingDetails) {
                    $bookingData = [
                        'user_email' => $bookingDetails['email'],
                        'user_name' => $bookingDetails['first_name'] . ' ' . $bookingDetails['last_name'],
                        'booking_id' => $bookingId,
                        'dhana_type' => $bookingDetails['dhana_type_name'],
                        'booking_date' => $bookingDetails['booking_date'],
                        'time_slot' => $bookingDetails['booking_time_slot'],
                        'amount' => $bookingDetails['total_amount'],
                        'status' => $newStatus
                    ];

                    // Send appropriate email based on new status
                    if ($newStatus === 'confirmed') {
                        $emailResult = $emailService->sendBookingConfirmedEmail($bookingData);
                        error_log("Booking confirmed email sent for booking #{$bookingId}: " . ($emailResult ? 'SUCCESS' : 'FAILED'));
                    } elseif ($newStatus === 'cancelled') {
                        $emailResult = $emailService->sendBookingCancelledEmail($bookingData, 'manual');
                        error_log("Booking cancelled email sent for booking #{$bookingId}: " . ($emailResult ? 'SUCCESS' : 'FAILED'));
                    }
                } else {
                    error_log("Could not find booking details for booking #{$bookingId}");
                }
            } catch (Exception $e) {
                // Log email error but don't stop the process
                error_log("Failed to send status change email for booking #{$bookingId}: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
            }

            $successMessage = 'Dāna reservation status updated successfully';
        } else {
            // Create approval request for administrator
            try {
                // Get current reservation data
                $currentBooking = $db->fetchOne(
                    "SELECT * FROM bookings WHERE id = ?",
                    [$bookingId]
                );

                if ($currentBooking) {
                    $oldValues = json_encode(['status' => $currentBooking['status']]);
                    $newValues = json_encode(['status' => $newStatus]);

                    $description = "Change dāna reservation #{$bookingId} status from '{$currentBooking['status']}' to '{$newStatus}'";

                    $db->query(
                        "INSERT INTO admin_actions (admin_id, action_type, action_description, target_id, old_values, new_values, status, created_at)
                         VALUES (?, 'booking_update', ?, ?, ?, ?, 'pending', NOW())",
                        [$_SESSION['admin_id'], $description, $bookingId, $oldValues, $newValues]
                    );

                    $successMessage = 'Status change request submitted for super admin approval';
                } else {
                    $errorMessage = 'Dāna reservation not found';
                }
            } catch (Exception $e) {
                adminLogException('Approval request submission failed', $e);
                $errorMessage = 'Unable to submit the approval request. Please try again.';
            }
        }
    }
}

// Get dashboard stats
$stats = [
    'total_bookings' => $db->fetchOne("SELECT COUNT(*) as count FROM bookings")['count'],
    'pending_bookings' => $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")['count'],
    'receipt_submitted' => $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'receipt_submitted'")['count'],
    'payment_pending' => $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'payment_pending'")['count'],
    'confirmed_bookings' => $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'")['count'],
    'total_revenue' => $db->fetchOne("SELECT SUM(total_amount) as total FROM bookings WHERE status IN ('confirmed', 'completed')")['total'] ?? 0
];

// Handle filtering
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = trim((string)($_GET['q'] ?? ''));
if (strlen($searchTerm) > 100) {
    $searchTerm = substr($searchTerm, 0, 100);
}
$validStatuses = ['all', 'pending', 'receipt_submitted', 'payment_pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

// Build WHERE clause for filtering
$conditions = [];
$queryParams = [];

if ($statusFilter !== 'all') {
    $conditions[] = 'b.status = ?';
    $queryParams[] = $statusFilter;
}

if ($searchTerm !== '') {
    $searchLike = '%' . $searchTerm . '%';
    $searchConditions = [
        "CONCAT_WS(' ', u.first_name, u.last_name) LIKE ?",
        'u.email LIKE ?',
        "CONCAT_WS(' ', b.booked_for_first_name, b.booked_for_last_name) LIKE ?",
        'b.booked_for_primary_contact LIKE ?',
        "CONCAT_WS(' ', agent.first_name, agent.last_name) LIKE ?",
        'agent.email LIKE ?'
    ];
    $searchParams = array_fill(0, count($searchConditions), $searchLike);
    if (preg_match('/^#?0*(\d+)$/', $searchTerm, $idMatch)) {
        array_unshift($searchConditions, 'b.id = ?');
        array_unshift($searchParams, (int)$idMatch[1]);
    }
    $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
    $queryParams = array_merge($queryParams, $searchParams);
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Pagination settings
$recordsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Get total count for pagination (with filter)
$totalRecords = $db->fetchOne(
    "SELECT COUNT(*) as count
     FROM bookings b
     JOIN users u ON b.user_id = u.id
     LEFT JOIN users agent ON b.booked_by_agent_id = agent.id
     $whereClause",
    $queryParams
)['count'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Helper function to build pagination URLs
function buildPaginationUrl($page, $status = 'all') {
    global $searchTerm;
    $params = ['page' => $page];
    if ($status !== 'all') {
        $params['status'] = $status;
    }
    if ($searchTerm !== '') {
        $params['q'] = $searchTerm;
    }
    return '?' . http_build_query($params);
}

// Get recent reservations with pagination and filtering
$bookingQueryParams = array_merge($queryParams, [$recordsPerPage, $offset]);
$recentBookings = $db->fetchAll(
    "SELECT b.*, dt.name as dhana_type_name, u.first_name, u.last_name, u.email,
            agent.first_name AS agent_first_name, agent.last_name AS agent_last_name, agent.email AS agent_email,
            pr.receipt_filename, pr.verified as receipt_verified
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
     $whereClause
     ORDER BY b.created_at DESC
     LIMIT ? OFFSET ?",
    $bookingQueryParams
);

// Dāna types are also used by the edit and report dialogs. Calendar bookings
// themselves are loaded only when the administrator opens Calendar View.
$dhanaTypes = $db->fetchAll("SELECT * FROM dhana_types WHERE is_active = 1 ORDER BY price DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Dāna Reservation System</title>
    <?php include '../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/supervisor-styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-dashboard">
    <div class="admin-panel">
        <?php $adminPageTitle = 'Reservations'; $adminPageIcon = 'fa-list'; include 'includes/header.php'; ?>

        <!-- Main Content Layout -->
        <div class="admin-layout">
            <!-- Left Sidebar with Stats -->
            <aside class="admin-sidebar admin-summary-rail" aria-label="Reservation summary filters">
                <div class="sidebar-section">
                    <h3><i class="fas fa-chart-bar"></i> Reservation overview</h3>
                    <div class="admin-summary-grid">

                    <a href="?status=all&amp;page=1" class="stat-card <?php echo ($statusFilter === 'all') ? 'active' : ''; ?>" <?php echo $statusFilter === 'all' ? 'aria-current="page"' : ''; ?>>
                        <div class="stat-icon total">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo $stats['total_bookings']; ?></h4>
                            <p>Total Reservations</p>
                        </div>
                    </a>

                    <a href="?status=pending&amp;page=1" class="stat-card <?php echo ($statusFilter === 'pending') ? 'active' : ''; ?>" <?php echo $statusFilter === 'pending' ? 'aria-current="page"' : ''; ?>>
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo $stats['pending_bookings']; ?></h4>
                            <p>Pending Reservations</p>
                        </div>
                    </a>

                    <a href="?status=receipt_submitted&amp;page=1" class="stat-card <?php echo ($statusFilter === 'receipt_submitted') ? 'active' : ''; ?>" <?php echo $statusFilter === 'receipt_submitted' ? 'aria-current="page"' : ''; ?>>
                        <div class="stat-icon receipt-submitted">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo $stats['receipt_submitted']; ?></h4>
                            <p>Receipt Submitted</p>
                        </div>
                    </a>

                    <a href="?status=payment_pending&amp;page=1" class="stat-card <?php echo ($statusFilter === 'payment_pending') ? 'active' : ''; ?>" <?php echo $statusFilter === 'payment_pending' ? 'aria-current="page"' : ''; ?>>
                        <div class="stat-icon payment">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo $stats['payment_pending']; ?></h4>
                            <p>Payment Pending</p>
                        </div>
                    </a>

                    <a href="?status=confirmed&amp;page=1" class="stat-card <?php echo ($statusFilter === 'confirmed') ? 'active' : ''; ?>" <?php echo $statusFilter === 'confirmed' ? 'aria-current="page"' : ''; ?>>
                        <div class="stat-icon confirmed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo $stats['confirmed_bookings']; ?></h4>
                            <p>Confirmed</p>
                        </div>
                    </a>

                    <!-- Analytics Card -->
                    <?php if (hasPermission('view_analytics') || hasPermission('view_all_bookings')): ?>
                    <a href="analytics.php" class="stat-card analytics-card">
                        <div class="stat-icon analytics">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Analytics</h4>
                            <p>View Analytics Dashboard</p>
                        </div>
                    </a>
                    <?php endif; ?>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area -->
            <div class="admin-main-content">
                <!-- Recent Reservations Section -->
                <div class="admin-section">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> Recent Reservations</h2>
                        <div class="section-actions">
                            <button class="btn btn-info" onclick="toggleCalendarView()" id="calendarViewBtn">
                                <i class="fas fa-calendar-alt"></i> Calendar View
                            </button>
                            <?php if (hasPermission('export_data')): ?>
                            <button class="btn btn-secondary" onclick="showReportGenerator()">
                                <i class="fas fa-chart-line"></i> Generate Reports
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-primary" onclick="refreshBookings()">
                                <i class="fas fa-sync-alt"></i> Auto Refresh
                            </button>
                        </div>
                    </div>

                    <?php if ($statusFilter !== 'all'): ?>
                        <div class="filter-indicator">
                            <span class="filter-label">
                                <i class="fas fa-filter"></i>
                                Filtered by: <strong><?php echo ucfirst(str_replace('_', ' ', $statusFilter)); ?></strong>
                            </span>
                            <a href="?page=1" class="clear-filter">
                                <i class="fas fa-times"></i> Clear Filter
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($successMessage)): ?>
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Data Table -->
                    <div class="bookings-table-container">
                        <table class="bookings-table" id="bookingsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Donor</th>
                                    <th>Dāna Type</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Receipt</th>
                                    <th>Status update</th>
                                    <th>Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentBookings)): ?>
                                    <tr><td colspan="9" class="admin-empty-cell">
                                        <i class="fas fa-search"></i>
                                        <?php echo $searchTerm !== '' ? 'No reservations match this search.' : 'No reservations are available for this filter.'; ?>
                                    </td></tr>
                                <?php endif; ?>
                                <?php foreach ($recentBookings as $booking): ?>
                                    <tr class="booking-row <?php echo ($booking['is_monk'] == 1) ? 'monk-booking' : ''; ?>" data-customer="<?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>" data-email="<?php echo htmlspecialchars($booking['email']); ?>" data-id="<?php echo $booking['id']; ?>">
                                        <td>#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <div class="donor-info">
                                                <?php if (!empty($booking['booked_by_agent_id'])): ?>
                                                    <strong><?php echo htmlspecialchars($booking['booked_for_first_name'] . ' ' . $booking['booked_for_last_name']); ?></strong>
                                                    <small>Recipient · <?php echo htmlspecialchars($booking['booked_for_primary_contact']); ?></small>
                                                    <small>Agent: <?php echo htmlspecialchars(trim(($booking['agent_first_name'] ?? '') . ' ' . ($booking['agent_last_name'] ?? '')) ?: $booking['email']); ?></small>
                                                <?php else: ?>
                                                    <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                                    <small><?php echo htmlspecialchars($booking['email']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['dhana_type_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                                        <td>Rs. <?php echo number_format($booking['total_amount']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                            </span>
                                            <?php if (isset($booking['on_hold']) && $booking['on_hold'] == 1): ?>
                                                <span class="status-badge status-hold" style="background: #ff9800; margin-left: 5px; font-size: 0.75rem;" title="This booking is on hold">
                                                    <i class="fas fa-pause-circle"></i> HOLD
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($booking['receipt_filename']): ?>
                                                <a href="view-receipt.php?file=<?php echo urlencode($booking['receipt_filename']); ?>"
                                                   target="_blank" class="receipt-link">
                                                    <i class="fas fa-file-image"></i>
                                                    <?php echo $booking['receipt_verified'] ? 'Verified' : 'View'; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="no-receipt">No receipt</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (hasPermission('accept_reject_requests') || hasPermission('update_booking_status')): ?>
                                            <form method="POST" class="status-form">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                                                <select name="status" data-original-status="<?php echo htmlspecialchars($booking['status']); ?>" aria-label="New status for reservation <?php echo $booking['id']; ?>">
                                                    <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="receipt_submitted" <?php echo $booking['status'] === 'receipt_submitted' ? 'selected' : ''; ?>>Receipt Submitted</option>
                                                    <option value="payment_pending" <?php echo $booking['status'] === 'payment_pending' ? 'selected' : ''; ?>>Payment Pending</option>
                                                    <option value="confirmed" <?php echo $booking['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <input type="hidden" name="update_booking" value="1">
                                                <button type="submit" class="btn btn-sm btn-primary status-save-btn" hidden aria-label="Save changed reservation status">
                                                    <i class="fas fa-check"></i><span>Save</span>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                                <span class="read-only-label"><i class="fas fa-lock"></i> Read only</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info details-btn" onclick="showBookingDetails(<?php echo $booking['id']; ?>)">
                                                <i class="fas fa-sliders-h"></i><span>Manage</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination Controls -->
                        <div class="pagination">
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination-info">
                                    Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> entries
                                </div>
                                <div class="pagination-controls">
                                    <?php if ($currentPage > 1): ?>
                                        <a href="<?php echo buildPaginationUrl(1, $statusFilter); ?>" class="pagination-link first-page">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                        <a href="<?php echo buildPaginationUrl($currentPage - 1, $statusFilter); ?>" class="pagination-link prev-page">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    // Calculate range of page numbers to show
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $startPage + 4);

                                    // Adjust start page if needed to always show 5 pages when possible
                                    if ($endPage - $startPage < 4 && $totalPages > 4) {
                                        $startPage = max(1, $endPage - 4);
                                    }

                                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <a href="<?php echo buildPaginationUrl($i, $statusFilter); ?>" class="pagination-link <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="<?php echo buildPaginationUrl($currentPage + 1, $statusFilter); ?>" class="pagination-link next-page">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                        <a href="<?php echo buildPaginationUrl($totalPages, $statusFilter); ?>" class="pagination-link last-page">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Admin Calendar View (Hidden by default) -->
                    <div class="admin-calendar-container" id="adminCalendarContainer" style="display: none;">
                        <div class="calendar-header">
                            <div class="calendar-nav">
                                <button class="nav-btn" onclick="changeMonth(-1)" id="prevMonthBtn">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <h3 id="calendarMonthYear" onclick="showMonthYearPicker()" title="Click to select month and year">Loading...</h3>
                                <button class="nav-btn" onclick="changeMonth(1)" id="nextMonthBtn">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            <div class="calendar-legend">
                                <div class="legend-item">
                                    <span class="legend-color available"></span>
                                    <span>Available</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color partially-booked"></span>
                                    <span>Partially Booked</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color fully-booked"></span>
                                    <span>Fully Booked</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color annual-event"></span>
                                    <span>Annual Event</span>
                                </div>
                            </div>
                        </div>

                        <div class="admin-calendar-grid" id="adminCalendarGrid">
                            <!-- Calendar will be loaded here -->
                        </div>

                        <div class="calendar-booking-details" id="calendarBookingDetails" style="display: none;">
                            <h4>Reservation Details for <span id="selectedDate"></span></h4>
                            <div id="bookingDetailsList"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (hasPermission('export_data')): ?>
    <!-- Report Generation Modal -->
    <div class="report-generator-modal" id="reportGeneratorModal" role="dialog" aria-modal="true" aria-labelledby="reportGeneratorTitle" aria-hidden="true" style="display: none;">
        <div class="report-generator-content" tabindex="-1">
            <div class="modal-header">
                <h4 id="reportGeneratorTitle"><i class="fas fa-chart-line"></i> Generate Reports</h4>
                <button type="button" class="close-modal" onclick="closeReportGenerator()" aria-label="Close report dialog">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="reportGeneratorForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                    <div class="report-type-section">
                        <h5>Report Type</h5>
                        <div class="report-type-options">
                            <label class="report-type-option">
                                <input type="radio" name="report_type" value="monthly" checked>
                                <div class="option-content">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Monthly Report</span>
                                    <small>Generate report for a specific month</small>
                                </div>
                            </label>
                            <label class="report-type-option">
                                <input type="radio" name="report_type" value="quarterly">
                                <div class="option-content">
                                    <i class="fas fa-calendar-week"></i>
                                    <span>Quarterly Report</span>
                                    <small>Generate report for a quarter (3 months)</small>
                                </div>
                            </label>
                            <label class="report-type-option">
                                <input type="radio" name="report_type" value="custom">
                                <div class="option-content">
                                    <i class="fas fa-calendar-day"></i>
                                    <span>Custom Range</span>
                                    <small>Generate report for custom date range</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="date-selection-section">
                        <!-- Monthly Selection -->
                        <div class="date-option monthly-option">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="monthly_month">Month:</label>
                                    <select id="monthly_month" name="monthly_month">
                                        <option value="1">January</option>
                                        <option value="2">February</option>
                                        <option value="3">March</option>
                                        <option value="4">April</option>
                                        <option value="5">May</option>
                                        <option value="6">June</option>
                                        <option value="7">July</option>
                                        <option value="8">August</option>
                                        <option value="9">September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="monthly_year">Year:</label>
                                    <select id="monthly_year" name="monthly_year">
                                        <!-- Will be populated by JavaScript -->
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Quarterly Selection -->
                        <div class="date-option quarterly-option" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="quarterly_quarter">Quarter:</label>
                                    <select id="quarterly_quarter" name="quarterly_quarter">
                                        <option value="1">Q1 (Jan - Mar)</option>
                                        <option value="2">Q2 (Apr - Jun)</option>
                                        <option value="3">Q3 (Jul - Sep)</option>
                                        <option value="4">Q4 (Oct - Dec)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="quarterly_year">Year:</label>
                                    <select id="quarterly_year" name="quarterly_year">
                                        <!-- Will be populated by JavaScript -->
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Range Selection -->
                        <div class="date-option custom-option" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="custom_start_date">Start Date:</label>
                                    <input type="date" id="custom_start_date" name="custom_start_date">
                                </div>
                                <div class="form-group">
                                    <label for="custom_end_date">End Date:</label>
                                    <input type="date" id="custom_end_date" name="custom_end_date">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="report-options-section">
                        <h5>Report Options</h5>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="report_status">Include Status:</label>
                                <select id="report_status" name="report_status">
                                    <option value="all">All Bookings</option>
                                    <option value="confirmed">Confirmed Only</option>
                                    <option value="completed">Completed Only</option>
                                    <option value="pending">Pending Only</option>
                                    <option value="receipt_submitted">Receipt Submitted Only</option>
                                    <option value="payment_pending">Payment Pending Only</option>
                                    <option value="cancelled">Cancelled Only</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="report_dhana_type">Dāna Type:</label>
                                <select id="report_dhana_type" name="report_dhana_type">
                                    <option value="all">All Types</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                            </div>
                        </div>

                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="include_summary" value="1" checked>
                                Include Summary Statistics
                            </label>
                            <label>
                                <input type="checkbox" name="include_annual_events" value="1" checked>
                                Include Annual Events
                            </label>
                            <label>
                                <input type="checkbox" name="include_customer_details" value="1" checked>
                                Include Customer Details
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeReportGenerator()">Cancel</button>
                <button type="button" class="btn btn-info" onclick="generateReport('csv', this)">
                    <i class="fas fa-file-csv"></i> Download CSV
                </button>
                <button type="button" class="btn btn-success" onclick="generateReport('print', this)">
                    <i class="fas fa-print"></i> Print Ready Report
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        window.ADMIN_CSRF_TOKEN = <?php echo json_encode($_SESSION['admin_csrf_token']); ?>;
        window.ADMIN_CAPABILITIES = <?php echo json_encode([
            'canEditBookings' => hasPermission('edit_personal_data'),
            'canDeleteBookings' => hasPermission('delete_bookings'),
            'canHoldBookings' => hasPermission('update_booking_status')
        ]); ?>;

        // Pass PHP data to JavaScript for calendar functionality
        window.adminCalendarData = {
            dhanaTypes: <?php echo json_encode($dhanaTypes); ?>
        };
    </script>
    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>

    <?php if ($_SESSION['is_super_admin']): ?>
    <!-- User Management Modal -->
    <div id="userManagementModal" class="user-management-modal" role="dialog" aria-modal="true" aria-labelledby="userManagementTitle" aria-hidden="true">
        <div class="user-management-content" tabindex="-1">
            <div class="user-management-header">
                <div><h3 id="userManagementTitle"><i class="fas fa-users-cog"></i> People &amp; access</h3><p>Assign reservation-agent or staff access.</p></div>
                <button type="button" class="close" id="closeUserManagement" aria-label="Close people and access dialog"><i class="fas fa-times"></i></button>
            </div>
            <div class="user-management-body">
                <div class="user-management-guidance" role="note">
                    <i class="fas fa-circle-info"></i>
                    <span><strong>Reservation Agent</strong> is for a donor who books for other people. Staff roles sign in through the separate admin login.</span>
                </div>
                <div class="user-controls">
                    <div class="user-filters">
                        <button type="button" class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="donor">Donors</button>
                        <button class="filter-btn" data-filter="agent">Reservation Agents</button>
                        <button class="filter-btn" data-filter="staff">Admin staff</button>
                    </div>

                    <div class="user-search-container">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="userSearch" placeholder="Search users by name or email...">
                            <button type="button" id="clearUserSearch" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="usersTableContainer" class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Person</th>
                                <th>Account</th>
                                <th>Current access</th>
                                <th>Change access</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- User data will be loaded here via AJAX -->
                        </tbody>
                    </table>

                    <div class="pagination-container">
                        <div class="pagination-info">
                            <span id="paginationInfo">Showing 0 - 0 of 0 users</span>
                        </div>

                        <div style="display: flex; gap: 20px; align-items: center;">
                            <div class="page-size-selector">
                                <label for="pageSize">Show:</label>
                                <select id="pageSize">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span>per page</span>
                            </div>

                            <div class="pagination" id="pagination">
                                <!-- Pagination buttons will be generated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // User Management Modal
        const userManagementBtn = document.getElementById('userManagementBtn');
        const userManagementModal = document.getElementById('userManagementModal');
        const closeUserManagement = document.getElementById('closeUserManagement');

        function openUserManagement() {
            userManagementModal.style.display = 'block';
            userManagementModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            loadUsers('all');
            requestAnimationFrame(() => userManagementModal.querySelector('.user-management-content')?.focus());
        }

        function closeUserManagementDialog() {
            userManagementModal.style.display = 'none';
            userManagementModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            userManagementBtn?.focus();
        }

        userManagementBtn.addEventListener('click', openUserManagement);

        if (new URLSearchParams(window.location.search).get('manage_users') === '1') {
            openUserManagement();
        }

        closeUserManagement.addEventListener('click', closeUserManagementDialog);

        window.addEventListener('click', function(event) {
            if (event.target === userManagementModal) {
                closeUserManagementDialog();
            }
        });

        userManagementModal.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeUserManagementDialog();
        });

        // Filter buttons
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                loadUsers(this.dataset.filter);
            });
        });

        // User search
        const userSearch = document.getElementById('userSearch');
        const clearUserSearch = document.getElementById('clearUserSearch');

        userSearch.addEventListener('input', function() {
            if (this.value.length > 0) {
                clearUserSearch.style.display = 'block';
            } else {
                clearUserSearch.style.display = 'none';
            }
            filterUsers();
        });

        clearUserSearch.addEventListener('click', function() {
            userSearch.value = '';
            this.style.display = 'none';
            filterUsers();
        });

        function filterUsers() {
            const searchTerm = userSearch.value.toLowerCase();

            if (searchTerm === '') {
                filteredUsers = [...allUsers];
            } else {
                filteredUsers = allUsers.filter(user => {
                    const fullName = `${user.first_name} ${user.last_name}`.toLowerCase();
                    const email = user.email.toLowerCase();
                    return fullName.includes(searchTerm) || email.includes(searchTerm);
                });
            }

            totalUsers = filteredUsers.length;
            currentPage = 1;
            renderCurrentPage();
        }

        // Pagination variables
        let currentPage = 1;
        let pageSize = 25;
        let totalUsers = 0;
        let allUsers = [];
        let filteredUsers = [];
        let currentFilter = 'all';

        // Page size selector
        const pageSizeSelector = document.getElementById('pageSize');
        pageSizeSelector.addEventListener('change', function() {
            pageSize = parseInt(this.value);
            currentPage = 1;
            renderCurrentPage();
        });

        // Load users via AJAX
        function loadUsers(filter = 'all') {
            currentFilter = filter;
            
            fetch('get-users.php?filter=' + encodeURIComponent(filter))
                .then(response => readAdminJsonResponse(response, 'Unable to load users.'))
                .then(data => {
                    allUsers = Array.isArray(data.users) ? data.users : [];
                    filteredUsers = [...allUsers];
                    totalUsers = filteredUsers.length;
                    currentPage = 1;
                    renderCurrentPage();
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showNotification(error.message, 'error');
                });
        }

        function renderCurrentPage() {
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;
            const pageUsers = filteredUsers.slice(startIndex, endIndex);

            renderUsers(pageUsers);
            updatePaginationInfo();
            renderPagination();
        }

        function renderUsers(users) {
            const tableBody = document.getElementById('usersTableBody');
            tableBody.innerHTML = '';

            if (users.length === 0) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td colspan="4" class="admin-empty-cell">
                        <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                        <br>No users found
                    </td>
                `;
                tableBody.appendChild(row);
                return;
            }

            users.forEach((user, index) => {
                const row = document.createElement('tr');
                row.style.animationDelay = `${index * 0.05}s`;
                
                // Create role change dropdown
                const roleOptions = user.source_table === 'users'
                    ? ['donor', 'agent']
                    : ['supervisor', 'editor', 'administrator'];
                const roleLabels = { donor: 'Donor', agent: 'Reservation Agent', supervisor: 'Supervisor', editor: 'Editor', administrator: 'Administrator' };
                const safeRole = Object.prototype.hasOwnProperty.call(roleLabels, user.role) ? user.role : 'donor';
                const roleDropdown = roleOptions.map(role => 
                    `<option value="${role}" ${safeRole === role ? 'selected' : ''}>${roleLabels[role]}</option>`
                ).join('');

                row.innerHTML = `
                    <td>
                        <div class="user-info">
                            <div class="user-name">${escapeUserText(user.first_name)} ${escapeUserText(user.last_name)}</div>
                            <div class="user-email">${escapeUserText(user.email)}</div>
                            ${user.contact_number ? `<div class="user-contact">${escapeUserText(user.contact_number)}</div>` : ''}
                        </div>
                    </td>
                    <td><span class="account-kind">${user.source_table === 'users' ? 'Donor portal' : 'Admin portal'}</span></td>
                    <td>
                        <span class="role-badge role-${safeRole}">${roleLabels[safeRole]}</span>
                    </td>
                    <td class="action-buttons">
                        <select class="role-change-select" data-user-id="${user.id}" data-source-table="${user.source_table}" data-current-role="${user.role}">
                            ${roleDropdown}
                        </select>
                        <button class="btn-change-role" type="button" hidden onclick="changeUserRole(this)">
                            <i class="fas fa-check"></i> Apply
                        </button>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            // Add event listeners for role change selects
            document.querySelectorAll('.role-change-select').forEach(select => {
                select.addEventListener('change', function() {
                    const updateBtn = this.parentElement.querySelector('.btn-change-role');
                    const currentRole = this.dataset.currentRole;
                    const newRole = this.value;
                    updateBtn.hidden = currentRole === newRole;
                    updateBtn.innerHTML = '<i class="fas fa-check"></i> Apply';
                });
            });
        }

        function escapeUserText(value) {
            const element = document.createElement('span');
            element.textContent = value == null ? '' : String(value);
            return element.innerHTML;
        }

        function updatePaginationInfo() {
            const startIndex = (currentPage - 1) * pageSize + 1;
            const endIndex = Math.min(currentPage * pageSize, totalUsers);
            const paginationInfo = document.getElementById('paginationInfo');
            paginationInfo.textContent = `Showing ${startIndex} - ${endIndex} of ${totalUsers} users`;
        }

        function renderPagination() {
            const totalPages = Math.ceil(totalUsers / pageSize);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';

            if (totalPages <= 1) return;

            // Previous button
            const prevBtn = document.createElement('button');
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.disabled = currentPage === 1;
            prevBtn.onclick = () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderCurrentPage();
                }
            };
            pagination.appendChild(prevBtn);

            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) {
                const firstBtn = document.createElement('button');
                firstBtn.textContent = '1';
                firstBtn.onclick = () => {
                    currentPage = 1;
                    renderCurrentPage();
                };
                pagination.appendChild(firstBtn);

                if (startPage > 2) {
                    const dots = document.createElement('span');
                    dots.textContent = '...';
                    dots.style.padding = '8px 4px';
                    dots.style.color = '#6c757d';
                    pagination.appendChild(dots);
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = i === currentPage ? 'active' : '';
                pageBtn.onclick = () => {
                    currentPage = i;
                    renderCurrentPage();
                };
                pagination.appendChild(pageBtn);
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const dots = document.createElement('span');
                    dots.textContent = '...';
                    dots.style.padding = '8px 4px';
                    dots.style.color = '#6c757d';
                    pagination.appendChild(dots);
                }

                const lastBtn = document.createElement('button');
                lastBtn.textContent = totalPages;
                lastBtn.onclick = () => {
                    currentPage = totalPages;
                    renderCurrentPage();
                };
                pagination.appendChild(lastBtn);
            }

            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.onclick = () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    renderCurrentPage();
                }
            };
            pagination.appendChild(nextBtn);
        }

        // Change user role
        async function changeUserRole(button) {
            const select = button.closest('.action-buttons')?.querySelector('.role-change-select');
            if (!select) {
                showNotification('Unable to identify this user row. Please refresh and try again.', 'error');
                return;
            }
            const userId = Number(select.dataset.userId);
            const newRole = select.value;
            const currentRole = select.dataset.currentRole;
            const sourceTable = select.dataset.sourceTable;
            
            if (newRole === currentRole) {
                window.adminNotify('Select a different access level before applying.', 'info');
                return;
            }

            const confirmed = await window.adminConfirm(`Change access from ${currentRole} to ${newRole}?`, {
                title: 'Change account access?',
                confirmText: 'Apply change'
            });
            if (!confirmed) {
                select.value = currentRole; // Reset to original value
                button.hidden = true;
                return;
            }

            // Show loading state
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;
            select.disabled = true;

            fetch('change-user-role.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    new_role: newRole,
                    source_table: sourceTable,
                    csrf_token: window.ADMIN_CSRF_TOKEN
                }),
            })
            .then(async response => {
                const data = await response.json().catch(() => null);
                if (!response.ok || !data) {
                    throw new Error(data?.error || `Request failed (${response.status})`);
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    // Update the current role in dataset
                    select.dataset.currentRole = newRole;
                    
                    // Reset button style
                    button.style.backgroundColor = '#6c757d';
                    button.innerHTML = '<i class="fas fa-check"></i> Apply';
                    button.hidden = true;
                    button.disabled = false;
                    select.disabled = false;

                    // Reload users with current filter to show updated permissions
                    loadUsers(currentFilter);

                    // Show success message
                    showNotification(`User role successfully changed to ${newRole}`, 'success');
                } else {
                    // Reset to original state on error
                    select.value = currentRole;
                    button.innerHTML = originalText;
                    button.disabled = false;
                    select.disabled = false;
                    
                    showNotification(data.error || 'Failed to change user role', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Reset to original state on error
                select.value = currentRole;
                button.innerHTML = originalText;
                button.disabled = false;
                select.disabled = false;
                
                showNotification(error.message || 'An error occurred while changing user role', 'error');
            });
        }

        // Check for pending approvals
        function checkPendingApprovals() {
            fetch('get-pending-approvals-count.php')
                .then(response => response.json())
                .then(data => {
                    const countElement = document.getElementById('pendingApprovalsCount');
                    if (data.count > 0) {
                        countElement.textContent = data.count;
                        countElement.style.display = 'inline-block';
                    } else {
                        countElement.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error checking approvals:', error);
                });
        }

        // Check for pending approvals on page load and every 60 seconds
        checkPendingApprovals();
        setInterval(checkPendingApprovals, 60000);

        // Analytics View Functions
        let analyticsVisible = false;
        let analyticsCharts = {};

        function toggleAnalyticsView() {
            const bookingsContainer = document.querySelector('.bookings-table-container');
            const calendarContainer = document.getElementById('adminCalendarContainer');
            const analyticsContainer = document.getElementById('analyticsViewContainer');
            const calendarBtn = document.getElementById('calendarViewBtn');

            if (!analyticsVisible) {
                // Hide bookings and calendar
                bookingsContainer.style.display = 'none';
                calendarContainer.style.display = 'none';
                analyticsContainer.style.display = 'block';

                // Update button text
                calendarBtn.innerHTML = '<i class="fas fa-list"></i> Back to Bookings';

                // Load analytics data
                loadAnalyticsData();
                analyticsVisible = true;
            } else {
                // Show bookings, hide analytics
                bookingsContainer.style.display = 'block';
                analyticsContainer.style.display = 'none';

                // Reset button text
                calendarBtn.innerHTML = '<i class="fas fa-calendar-alt"></i> Calendar View';

                analyticsVisible = false;
            }
        }

        function loadAnalyticsData() {
            const timeFilter = document.getElementById('timeFilter')?.value || '30_days';
            const statusFilter = document.getElementById('statusFilterAnalytics')?.value || 'all';
            const analyticsContent = document.getElementById('analyticsContent');

            // Show loading state
            analyticsContent.innerHTML = `
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading analytics data...</p>
                </div>
            `;

            fetch(`get-analytics-data.php?time_filter=${timeFilter}&status_filter=${statusFilter}`)
                .then(response => readAdminJsonResponse(response, 'Unable to load analytics data.'))
                .then(data => {
                    renderAnalyticsContent(data.data);
                })
                .catch(error => {
                    console.error('Analytics error:', error);
                    analyticsContent.innerHTML = `
                        <div class="error-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>${escapeAdminHtml(error.message)}</p>
                            <button onclick="loadAnalyticsData()" class="retry-btn">Retry</button>
                        </div>
                    `;
                });
        }

        function renderAnalyticsContent(data) {
            const analyticsContent = document.getElementById('analyticsContent');

            analyticsContent.innerHTML = `
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="card-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="card-content">
                            <h3>${formatNumber(data.totalStats.total_reservations)}</h3>
                            <p>Total Reservations</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="card-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="card-content">
                            <h3>Rs. ${formatNumber(data.totalStats.total_revenue)}</h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="card-content">
                            <h3>Rs. ${formatNumber(Math.round(data.totalStats.avg_reservation_value))}</h3>
                            <p>Average Value</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-content">
                            <h3>${formatNumber(data.totalStats.unique_donors)}</h3>
                            <p>Unique Donors</p>
                        </div>
                    </div>
                </div>

                <!-- Charts Carousel Section -->
                <div class="charts-carousel">
                    <div class="carousel-header">
                        <div class="carousel-title">
                            <h3><i class="fas fa-chart-pie"></i> <span id="currentChartTitle">Status Distribution</span></h3>
                        </div>
                        <div class="carousel-nav">
                            <div class="carousel-indicators">
                                <div class="indicator active" data-slide="0" onclick="goToAnalyticsSlide(0)"></div>
                                <div class="indicator" data-slide="1" onclick="goToAnalyticsSlide(1)"></div>
                                <div class="indicator" data-slide="2" onclick="goToAnalyticsSlide(2)"></div>
                                <div class="indicator" data-slide="3" onclick="goToAnalyticsSlide(3)"></div>
                            </div>
                            <button class="nav-btn" id="prevAnalyticsBtn" onclick="previousAnalyticsSlide()">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="nav-btn" id="nextAnalyticsBtn" onclick="nextAnalyticsSlide()">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="carousel-content">
                        <!-- Slide 1: Status Distribution -->
                        <div class="chart-slide active" data-slide="0">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h4><i class="fas fa-chart-pie"></i> Status Distribution</h4>
                                    <p>Breakdown by reservation status</p>
                                </div>
                                <div class="chart-wrapper">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Slide 2: Monthly Trends -->
                        <div class="chart-slide" data-slide="1">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h4><i class="fas fa-chart-line"></i> Monthly Trends</h4>
                                    <p>Reservations and revenue over time</p>
                                </div>
                                <div class="chart-wrapper">
                                    <canvas id="monthlyChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Slide 3: Top Donors -->
                        <div class="chart-slide" data-slide="2">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h4><i class="fas fa-trophy"></i> Top Donors</h4>
                                    <p>Most active donors and contributors</p>
                                </div>
                                <div class="donors-list" style="max-height: 300px; overflow-y: auto;">
                                    ${(Array.isArray(data.topDonors) ? data.topDonors : []).map((donor, index) => `
                                        <div class="donor-item">
                                            <span class="donor-rank">#${index + 1}</span>
                                            <div class="donor-info">
                                                <strong>${escapeAdminHtml(donor.first_name)} ${escapeAdminHtml(donor.last_name)}</strong>
                                                <small>${escapeAdminHtml(donor.email)}</small>
                                            </div>
                                            <div class="donor-stats">
                                                <span class="donor-count">${donor.reservation_count} reservations</span>
                                                <span class="donor-amount">Rs. ${formatNumber(donor.total_donated)}</span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>

                        <!-- Slide 4: Dhana Type Distribution -->
                        <div class="chart-slide" data-slide="3">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h4><i class="fas fa-chart-doughnut"></i> Dāna Type Distribution</h4>
                                    <p>Distribution by dāna offering types</p>
                                </div>
                                <div class="chart-wrapper">
                                    <canvas id="dhanaTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Initialize charts after content is rendered
            setTimeout(() => {
                initializeAnalyticsCharts(data);
                initializeAnalyticsCarousel();
            }, 100);
        }

        function formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }

        function initializeAnalyticsCharts(data) {
            // Destroy existing charts
            Object.values(analyticsCharts).forEach(chart => {
                if (chart) chart.destroy();
            });
            analyticsCharts = {};

            // Chart colors
            const colors = ['#d4822a', '#b8860b', '#28a745', '#17a2b8', '#ffc107', '#dc3545'];

            // Status Distribution Pie Chart
            if (data.statusStats && data.statusStats.length > 0) {
                const statusCtx = document.getElementById('statusChart');
                if (statusCtx) {
                    analyticsCharts.status = new Chart(statusCtx, {
                        type: 'pie',
                        data: {
                            labels: data.statusStats.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                            datasets: [{
                                data: data.statusStats.map(item => parseInt(item.count)),
                                backgroundColor: colors.slice(0, data.statusStats.length),
                                borderColor: '#fff',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Monthly Trends Line Chart
            if (data.monthlyStats && data.monthlyStats.length > 0) {
                const monthlyCtx = document.getElementById('monthlyChart');
                if (monthlyCtx) {
                    analyticsCharts.monthly = new Chart(monthlyCtx, {
                        type: 'line',
                        data: {
                            labels: data.monthlyStats.map(item => {
                                const date = new Date(item.month + '-01');
                                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                            }),
                            datasets: [
                                {
                                    label: 'Reservations',
                                    data: data.monthlyStats.map(item => parseInt(item.reservations)),
                                    borderColor: '#d4822a',
                                    backgroundColor: '#d4822a20',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4
                                },
                                {
                                    label: 'Revenue (Rs.)',
                                    data: data.monthlyStats.map(item => parseFloat(item.revenue)),
                                    borderColor: '#28a745',
                                    backgroundColor: '#28a74520',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4,
                                    yAxisID: 'y1'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Reservations'
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Revenue (Rs.)'
                                    },
                                    grid: {
                                        drawOnChartArea: false,
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            }
                        }
                    });
                }
            }

            // Dhana Type Distribution Doughnut Chart
            if (data.dhanaTypeStats && data.dhanaTypeStats.length > 0) {
                const dhanaTypeCtx = document.getElementById('dhanaTypeChart');
                if (dhanaTypeCtx) {
                    analyticsCharts.dhanaType = new Chart(dhanaTypeCtx, {
                        type: 'doughnut',
                        data: {
                            labels: data.dhanaTypeStats.map(item => item.dhana_type),
                            datasets: [{
                                data: data.dhanaTypeStats.map(item => parseInt(item.count)),
                                backgroundColor: colors.slice(0, data.dhanaTypeStats.length),
                                borderColor: '#fff',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }

        // Analytics Carousel Functions
        let currentAnalyticsSlide = 0;
        const totalAnalyticsSlides = 4;
        const analyticsSlideData = [
            { title: 'Status Distribution', icon: 'fas fa-chart-pie' },
            { title: 'Monthly Trends', icon: 'fas fa-chart-line' },
            { title: 'Top Donors', icon: 'fas fa-trophy' },
            { title: 'Dāna Type Distribution', icon: 'fas fa-chart-doughnut' }
        ];

        function initializeAnalyticsCarousel() {
            updateAnalyticsCarouselState();
        }

        function goToAnalyticsSlide(slideIndex) {
            if (slideIndex < 0 || slideIndex >= totalAnalyticsSlides) return;

            const slides = document.querySelectorAll('.chart-slide');
            const indicators = document.querySelectorAll('.indicator');

            // Remove active classes
            if (slides[currentAnalyticsSlide]) {
                slides[currentAnalyticsSlide].classList.remove('active');
                slides[currentAnalyticsSlide].classList.add('prev');
            }
            if (indicators[currentAnalyticsSlide]) {
                indicators[currentAnalyticsSlide].classList.remove('active');
            }

            // Update current slide
            currentAnalyticsSlide = slideIndex;

            // Add active classes
            if (slides[currentAnalyticsSlide]) {
                slides[currentAnalyticsSlide].classList.remove('prev');
                slides[currentAnalyticsSlide].classList.add('active');
            }
            if (indicators[currentAnalyticsSlide]) {
                indicators[currentAnalyticsSlide].classList.add('active');
            }

            // Update title and icon
            updateAnalyticsCarouselState();

            // Trigger chart resize for the active slide
            setTimeout(() => {
                const activeSlide = slides[currentAnalyticsSlide];
                if (activeSlide) {
                    const canvas = activeSlide.querySelector('canvas');
                    if (canvas && window.Chart) {
                        const chartInstance = Chart.getChart(canvas);
                        if (chartInstance) {
                            chartInstance.resize();
                        }
                    }
                }
            }, 100);

            // Clean up prev classes after animation
            setTimeout(() => {
                slides.forEach(slide => slide.classList.remove('prev'));
            }, 500);
        }

        function nextAnalyticsSlide() {
            const nextIndex = (currentAnalyticsSlide + 1) % totalAnalyticsSlides;
            goToAnalyticsSlide(nextIndex);
        }

        function previousAnalyticsSlide() {
            const prevIndex = (currentAnalyticsSlide - 1 + totalAnalyticsSlides) % totalAnalyticsSlides;
            goToAnalyticsSlide(prevIndex);
        }

        function updateAnalyticsCarouselState() {
            // Update title and icon
            const titleElement = document.getElementById('currentChartTitle');
            const iconElement = titleElement?.parentElement.querySelector('i');

            if (titleElement && iconElement && analyticsSlideData[currentAnalyticsSlide]) {
                titleElement.textContent = analyticsSlideData[currentAnalyticsSlide].title;
                iconElement.className = analyticsSlideData[currentAnalyticsSlide].icon;
            }
        }
    </script>
    <?php endif; ?>

    <!-- Reservation Details Modal -->
    <div id="bookingDetailsModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="bookingDetailsTitle" aria-hidden="true" style="display: none;">
        <div class="modal-content booking-details-modal" tabindex="-1">
            <div class="modal-header">
                <h3 id="bookingDetailsTitle"><i class="fas fa-info-circle"></i> Dāna Reservation Details</h3>
                <button type="button" class="close" onclick="closeBookingDetailsModal()" aria-label="Close reservation details"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="bookingDetailsContent">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading reservation details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBookingDetailsModal()">Close</button>
                <?php if (hasPermission('update_booking_status')): ?>
                <button type="button" class="btn btn-warning" id="holdBookingBtn" onclick="toggleBookingHold()" style="display: none;">
                    <i class="fas fa-pause-circle"></i> <span id="holdBtnText">Place on Hold</span>
                </button>
                <?php endif; ?>
                <?php if (hasPermission('edit_personal_data')): ?>
                <button type="button" class="btn btn-primary" id="editBookingBtn" onclick="editBookingFromDetails()">
                    <i class="fas fa-edit"></i> Edit Reservation
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Reservation details modal functions
        function showBookingDetails(bookingId) {
            const modal = document.getElementById('bookingDetailsModal');
            const content = document.getElementById('bookingDetailsContent');

            // Show modal with loading state
            modal.classList.add('show');
            modal.style.display = 'block';
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            requestAnimationFrame(() => modal.querySelector('.modal-content')?.focus());
            content.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading reservation details...</p>
                </div>
            `;

            // Fetch reservation details
            fetch(`get-booking-details.php?id=${bookingId}`)
                .then(response => readAdminJsonResponse(response, 'Unable to load reservation details.'))
                .then(data => {
                    displayBookingDetails(data.data);
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>${escapeAdminHtml(error.message)}</p>
                        </div>
                    `;
                });
        }

        function displayBookingDetails(booking) {
            const content = document.getElementById('bookingDetailsContent');
            const editBtn = document.getElementById('editBookingBtn');
            const holdBtn = document.getElementById('holdBookingBtn');
            const holdBtnText = document.getElementById('holdBtnText');

            // Store booking ID for edit function
            const bookingId = safeAdminId(booking.id);
            if (editBtn) editBtn.setAttribute('data-booking-id', bookingId);
            if (holdBtn) holdBtn.setAttribute('data-booking-id', bookingId);

            // Show/hide hold button based on booking status
            // Only show for pending bookings
            if (holdBtn && booking.status === 'pending') {
                holdBtn.style.display = 'inline-block';

                // Update button text and style based on hold status
                if (booking.on_hold) {
                    holdBtnText.textContent = 'Remove Hold';
                    holdBtn.className = 'btn btn-success';
                    holdBtn.innerHTML = '<i class="fas fa-play-circle"></i> <span id="holdBtnText">Remove Hold</span>';
                } else {
                    holdBtnText.textContent = 'Place on Hold';
                    holdBtn.className = 'btn btn-warning';
                    holdBtn.innerHTML = '<i class="fas fa-pause-circle"></i> <span id="holdBtnText">Place on Hold</span>';
                }
            } else if (holdBtn) {
                holdBtn.style.display = 'none';
            }

            const safeStatus = safeAdminStatus(booking.status);
            const statusClass = safeStatus.replace('_', '-');
            const bookingDate = new Date(booking.booking_date).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const escapeDetail = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[character]));
            const agentBookingSection = booking.booked_by_agent_id ? `
                    <div class="details-section">
                        <h4><i class="fas fa-user-friends"></i> Reservation Recipient</h4>
                        <div class="details-row">
                            <span class="label">Name:</span>
                            <span class="value">${escapeDetail(`${booking.booked_for_first_name || ''} ${booking.booked_for_last_name || ''}`.trim() || 'Not provided')}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Primary Mobile:</span>
                            <span class="value">${escapeDetail(booking.booked_for_primary_contact || 'Not provided')}</span>
                        </div>
                        ${booking.booked_for_secondary_contact ? `
                        <div class="details-row">
                            <span class="label">Second Mobile:</span>
                            <span class="value">${escapeDetail(booking.booked_for_secondary_contact)}</span>
                        </div>` : ''}
                        <div class="details-row">
                            <span class="label">Booked by Agent:</span>
                            <span class="value">${escapeDetail(`${booking.agent_first_name || ''} ${booking.agent_last_name || ''}`.trim() || booking.agent_email || 'Not provided')}</span>
                        </div>
                    </div>` : '';

            content.innerHTML = `
                <div class="booking-details-grid">
                    <!-- Basic Information -->
                    <div class="details-section">
                        <h4><i class="fas fa-info-circle"></i> Basic Information</h4>
                        <div class="details-row">
                            <span class="label">Reservation ID:</span>
                            <span class="value">#${String(bookingId).padStart(6, '0')}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Status:</span>
                            <span class="value">
                                <span class="status-badge status-${statusClass}">
                                    ${escapeAdminHtml(safeStatus.replace('_', ' ').toUpperCase())}
                                </span>
                                ${booking.on_hold ? `
                                    <span class="status-badge status-hold" style="background: #ff9800; margin-left: 10px;">
                                        <i class="fas fa-pause-circle"></i> ON HOLD
                                    </span>
                                ` : ''}
                            </span>
                        </div>
                        ${booking.on_hold ? `
                        <div class="details-row">
                            <span class="label">Hold Reason:</span>
                            <span class="value">${escapeAdminHtml(booking.hold_info?.hold_reason || 'No reason provided')}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Held By:</span>
                            <span class="value">${escapeAdminHtml(booking.hold_info?.held_by_name || 'Unknown administrator')}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Held At:</span>
                            <span class="value">${escapeAdminHtml(new Date(booking.hold_info?.held_at).toLocaleString())}</span>
                        </div>
                        ` : ''}
                        <div class="details-row">
                            <span class="label">Created:</span>
                            <span class="value">${new Date(booking.created_at).toLocaleString()}</span>
                        </div>
                    </div>

                    <!-- Donor Information -->
                    <div class="details-section">
                        <h4><i class="fas fa-user"></i> Donor Information</h4>
                        <div class="details-row">
                            <span class="label">Name:</span>
                            <span class="value">${escapeAdminHtml(booking.first_name)} ${escapeAdminHtml(booking.last_name)}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Email:</span>
                            <span class="value">${escapeAdminHtml(booking.email)}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Contact:</span>
                            <span class="value">${escapeAdminHtml(booking.contact_number || 'Not provided')}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Is Monk:</span>
                            <span class="value">
                                ${booking.is_monk ?
                                    '<span class="badge badge-monk"><i class="fas fa-check"></i> Yes</span>' :
                                    '<span class="badge badge-no">No</span>'
                                }
                            </span>
                        </div>
                    </div>

                    ${agentBookingSection}

                    <!-- Reservation Details -->
                    <div class="details-section">
                        <h4><i class="fas fa-calendar-alt"></i> Reservation Information</h4>
                        <div class="details-row">
                            <span class="label">Dāna Type:</span>
                            <span class="value">${escapeAdminHtml(booking.dhana_type_name)}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Date:</span>
                            <span class="value">${bookingDate}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Time Slot:</span>
                            <span class="value">${escapeAdminHtml(String(booking.booking_time_slot || '').replace('_', ' ').toUpperCase())}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Travel Support:</span>
                            <span class="value">
                                ${booking.travel_support ?
                                    '<span class="badge badge-yes"><i class="fas fa-plane"></i> Required</span>' :
                                    '<span class="badge badge-no">Not Required</span>'
                                }
                            </span>
                        </div>
                        <div class="details-row">
                            <span class="label">Annual Event:</span>
                            <span class="value">
                                ${booking.is_annual_event ?
                                    '<span class="badge badge-annual"><i class="fas fa-calendar-check"></i> Yes</span>' :
                                    '<span class="badge badge-no">No</span>'
                                }
                            </span>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="details-section">
                        <h4><i class="fas fa-money-bill-wave"></i> Payment Information</h4>
                        <div class="details-row">
                            <span class="label">Total Amount:</span>
                            <span class="value amount">Rs. ${Number(booking.total_amount).toLocaleString()}</span>
                        </div>
                        <div class="details-row">
                            <span class="label">Receipt:</span>
                            <span class="value">
                                ${booking.receipt_filename ?
                                    `<a href="view-receipt.php?file=${encodeURIComponent(booking.receipt_filename)}" target="_blank" class="receipt-link">
                                        <i class="fas fa-file-image"></i> View Receipt
                                        ${booking.receipt_verified ? ' (Verified)' : ' (Pending)'}
                                    </a>` :
                                    '<span class="no-receipt">No receipt uploaded</span>'
                                }
                            </span>
                        </div>
                    </div>

                    <!-- Special Requests & Additional Information -->
                    <div class="details-section full-width">
                        <h4><i class="fas fa-comment-alt"></i> Special Notes & Additional Information</h4>

                        <!-- Travel Support Details -->
                        ${booking.travel_support ? `
                            <div class="info-subsection">
                                <h5><i class="fas fa-plane"></i> Travel Support Details</h5>
                                <div class="info-content travel-support">
                                    <p><strong>Travel support has been requested for this reservation.</strong></p>
                                    <p>The donor may require assistance with transportation arrangements to attend the dāna ceremony.</p>
                                </div>
                            </div>
                        ` : ''}

                        <!-- Annual Event Details -->
                        ${booking.is_annual_event ? `
                            <div class="info-subsection">
                                <h5><i class="fas fa-calendar-check"></i> Annual Event Information</h5>
                                <div class="info-content annual-event">
                                    <p><strong>This is an annual dāna event reservation.</strong></p>
                                    ${booking.year_start && booking.year_end ?
                                        `<p>Event period: ${escapeAdminHtml(booking.year_start)} - ${escapeAdminHtml(booking.year_end)}</p>` :
                                        '<p>Annual event details will be confirmed separately.</p>'
                                    }
                                </div>
                            </div>
                        ` : ''}

                        <!-- Monk Status Details -->
                        ${booking.is_monk ? `
                            <div class="info-subsection">
                                <h5><i class="fas fa-user-tie"></i> Monk Status Information</h5>
                                <div class="info-content monk-status">
                                    <p><strong>This reservation is made by a monk.</strong></p>
                                    <p>Special considerations and protocols may apply for this dāna offering.</p>
                                </div>
                            </div>
                        ` : ''}

                        <!-- Special Requests -->
                        <div class="info-subsection">
                            <h5><i class="fas fa-edit"></i> Special Requests</h5>
                            <div class="info-content special-requests">
                                ${booking.special_requests ?
                                    `<p>${escapeAdminHtml(booking.special_requests)}</p>` :
                                    '<p class="no-requests">No special requests provided.</p>'
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function closeBookingDetailsModal() {
            const modal = document.getElementById('bookingDetailsModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        function editBookingFromDetails() {
            const editButton = document.getElementById('editBookingBtn');
            if (!editButton) return;
            const bookingId = editButton.getAttribute('data-booking-id');
            closeBookingDetailsModal();
            editBooking(bookingId);
        }

        async function toggleBookingHold() {
            const holdBtn = document.getElementById('holdBookingBtn');
            if (!holdBtn) return;
            const bookingId = holdBtn.getAttribute('data-booking-id');
            const holdBtnText = document.getElementById('holdBtnText');
            const isCurrentlyOnHold = holdBtnText.textContent === 'Remove Hold';

            const action = isCurrentlyOnHold ? 'unhold' : 'hold';
            let holdReason = '';

            // If placing on hold, ask for reason
            if (action === 'hold') {
                holdReason = await window.adminPrompt('Add an optional note explaining why this reservation is on hold.', {
                    title: 'Place reservation on hold',
                    promptLabel: 'Hold reason (optional)',
                    confirmText: 'Place on hold'
                });
                if (holdReason === null) {
                    // User cancelled
                    return;
                }
            } else {
                // Confirm removal
                const confirmed = await window.adminConfirm('The reservation will return to its normal pending workflow.', {
                    title: 'Remove reservation hold?',
                    confirmText: 'Remove hold'
                });
                if (!confirmed) return;
            }

            // Disable button during request
            holdBtn.disabled = true;
            holdBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            // Send request
            fetch('toggle-booking-hold.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    booking_id: bookingId,
                    action: action,
                    hold_reason: holdReason,
                    csrf_token: window.ADMIN_CSRF_TOKEN || ''
                })
            })
            .then(response => readAdminJsonResponse(response, 'Unable to update the reservation hold.'))
            .then(data => {
                showNotification(data.message || 'Reservation hold updated.', 'success');
                showBookingDetails(bookingId);
                if (isCalendarView) loadCalendarData();
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification(error.message, 'error');
                holdBtn.disabled = false;

                // Restore button state
                if (isCurrentlyOnHold) {
                    holdBtn.innerHTML = '<i class="fas fa-play-circle"></i> <span id="holdBtnText">Remove Hold</span>';
                } else {
                    holdBtn.innerHTML = '<i class="fas fa-pause-circle"></i> <span id="holdBtnText">Place on Hold</span>';
                }
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('bookingDetailsModal');
            if (event.target === modal) {
                closeBookingDetailsModal();
            }
        });
        document.getElementById('bookingDetailsModal')?.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeBookingDetailsModal();
        });
    </script>
</body>
</html>
