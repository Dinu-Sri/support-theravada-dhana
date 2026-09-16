<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(false);

// Initialize database connection for permission checking
$db = getDB();

// Add permission checking function
function hasPermission($permission) {
    global $db;
    return adminHasPermission($db, $permission);
}

// Check if user has permission to view analytics
if (!hasPermission('view_analytics') && !hasPermission('view_all_bookings')) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Get filter parameters
$timeFilter = $_GET['time_filter'] ?? '30_days';
$statusFilter = $_GET['status_filter'] ?? 'all';
$validTimeFilters = ['7_days', '30_days', '90_days', '1_year', 'all_time'];
$validStatuses = ['all', 'pending', 'receipt_submitted', 'payment_pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($timeFilter, $validTimeFilters, true)) {
    $timeFilter = '30_days';
}
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'all';
}

// Calculate date range based on time filter
$dateCondition = '';
$params = [];

switch ($timeFilter) {
    case '7_days':
        $dateCondition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30_days':
        $dateCondition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90_days':
        $dateCondition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case '1_year':
        $dateCondition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'all_time':
    default:
        // No date condition
        break;
}

// Status filter condition
$statusCondition = '';
if ($statusFilter !== 'all') {
    $statusCondition = "AND status = ?";
    $params[] = $statusFilter;
}

try {
    $bookingDateCondition = str_replace('created_at', 'b.created_at', $dateCondition);
    $bookingStatusCondition = str_replace('status', 'b.status', $statusCondition);
    
    // Get total statistics
    $totalQuery = "SELECT 
        COUNT(*) as total_reservations,
        COALESCE(SUM(b.total_amount), 0) as total_revenue,
        COALESCE(AVG(b.total_amount), 0) as avg_reservation_value,
        COUNT(DISTINCT b.user_id) as unique_donors
        FROM bookings b
        WHERE 1=1 $bookingDateCondition $bookingStatusCondition";
    
    $totalStats = $db->fetchOne($totalQuery, $params);
    
    // Get status distribution
    $statusQuery = "SELECT 
        b.status,
        COUNT(*) as count,
        COALESCE(SUM(b.total_amount), 0) as revenue
        FROM bookings b
        WHERE 1=1 $bookingDateCondition $bookingStatusCondition
        GROUP BY b.status
        ORDER BY count DESC";

    $statusStats = $db->fetchAll($statusQuery, $params);
    
    // Get monthly trends (last 12 months)
    $monthlyQuery = "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as reservations,
        SUM(total_amount) as revenue
        FROM bookings 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC";
    
    $monthlyStats = $db->fetchAll($monthlyQuery);
    
    // Get dhana type distribution
    $dhanaTypeQuery = "SELECT 
        dt.name AS dhana_type,
        COUNT(*) as count,
        COALESCE(SUM(b.total_amount), 0) as revenue
        FROM bookings b
        JOIN dhana_types dt ON dt.id = b.dhana_type_id
        WHERE 1=1 $bookingDateCondition $bookingStatusCondition
        GROUP BY dt.id, dt.name
        ORDER BY count DESC";
    
    $dhanaTypeStats = $db->fetchAll($dhanaTypeQuery, $params);
    
    // Get top donors
    $topDonorsQuery = "SELECT 
        u.first_name,
        u.last_name,
        u.email,
        COUNT(b.id) as reservation_count,
        SUM(b.total_amount) as total_donated
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE 1=1 $bookingDateCondition $bookingStatusCondition
        GROUP BY b.user_id, u.first_name, u.last_name, u.email
        ORDER BY total_donated DESC
        LIMIT 10";
    
    $topDonors = $db->fetchAll($topDonorsQuery, $params);
    
    // Get recent activity
    $recentQuery = "SELECT 
        b.*,
        dt.name AS dhana_type_name,
        u.first_name,
        u.last_name,
        u.email
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN dhana_types dt ON dt.id = b.dhana_type_id
        WHERE 1=1 $bookingDateCondition $bookingStatusCondition
        ORDER BY b.created_at DESC
        LIMIT 10";
    
    $recentActivity = $db->fetchAll($recentQuery, $params);
    
} catch (Exception $e) {
    adminLogException('Analytics page failed', $e);
    $errorMessage = 'Unable to load analytics. Please try again.';
}

// Format numbers for display
function formatCurrency($amount) {
    return 'LKR ' . number_format((float)$amount, 2);
}

function formatNumber($number) {
    return number_format($number);
}

// Get time filter label
function getTimeFilterLabel($filter) {
    switch ($filter) {
        case '7_days': return 'Last 7 Days';
        case '30_days': return 'Last 30 Days';
        case '90_days': return 'Last 90 Days';
        case '1_year': return 'Last Year';
        case 'all_time': return 'All Time';
        default: return 'Last 30 Days';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Dāna Reservation System</title>
    <?php include '../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20260916">
    <link rel="stylesheet" href="../assets/css/analytics.css?v=20260916">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
</head>
<body class="admin-analytics-page">
    <div class="admin-panel">
        <?php $adminPageTitle = 'Analytics'; $adminPageIcon = 'fa-chart-line'; include 'includes/header.php'; ?>

        <!-- Main Content Layout -->
        <div class="admin-layout">
            <!-- Main Content Area -->
            <div class="admin-main-content">
                <!-- Analytics Header -->
                <div class="analytics-header">
                <div class="analytics-title">
                    <h1><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
                    <p>Comprehensive insights into dāna reservations and donor activity</p>
                </div>
                
                <!-- Filters -->
                <div class="analytics-filters">
                    <div class="filter-group">
                        <label for="timeFilter">Time Period:</label>
                        <select id="timeFilter" onchange="applyFilters()">
                            <option value="7_days" <?php echo $timeFilter === '7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30_days" <?php echo $timeFilter === '30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90_days" <?php echo $timeFilter === '90_days' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="1_year" <?php echo $timeFilter === '1_year' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="all_time" <?php echo $timeFilter === 'all_time' ? 'selected' : ''; ?>>All Time</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="statusFilter">Status:</label>
                        <select id="statusFilter" onchange="applyFilters()">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="receipt_submitted" <?php echo $statusFilter === 'receipt_submitted' ? 'selected' : ''; ?>>Receipt Submitted</option>
                            <option value="payment_pending" <?php echo $statusFilter === 'payment_pending' ? 'selected' : ''; ?>>Payment Pending</option>
                            <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <button type="button" class="btn btn-secondary refresh-btn" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="analytics-content">
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php else: ?>
                    <!-- Summary Cards -->
                    <div class="summary-cards">
                        <div class="summary-card">
                            <div class="card-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="card-content">
                                <h3><?php echo formatNumber($totalStats['total_reservations'] ?? 0); ?></h3>
                                <p>Total Reservations</p>
                            </div>
                        </div>
                        
                        <div class="summary-card">
                            <div class="card-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="card-content">
                                <h3><?php echo formatCurrency($totalStats['total_revenue'] ?? 0); ?></h3>
                                <p>Total Revenue</p>
                            </div>
                        </div>
                        
                        <div class="summary-card">
                            <div class="card-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="card-content">
                                <h3><?php echo formatCurrency($totalStats['avg_reservation_value'] ?? 0); ?></h3>
                                <p>Average Value</p>
                            </div>
                        </div>
                        
                        <div class="summary-card">
                            <div class="card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="card-content">
                                <h3><?php echo formatNumber($totalStats['unique_donors'] ?? 0); ?></h3>
                                <p>Unique Donors</p>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Carousel Section -->
                    <div class="charts-carousel">
                        <div class="carousel-header">
                            <div class="carousel-title">
                                <h3><i class="fas fa-chart-line"></i> <span id="currentChartTitle">Reservation Status Distribution</span></h3>
                            </div>
                            <div class="carousel-nav">
                                <div class="carousel-indicators">
                                    <div class="indicator active" data-slide="0"></div>
                                    <div class="indicator" data-slide="1"></div>
                                    <div class="indicator" data-slide="2"></div>
                                    <div class="indicator" data-slide="3"></div>
                                </div>
                                <button class="nav-btn" id="prevBtn" onclick="previousSlide()">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="nav-btn" id="nextBtn" onclick="nextSlide()">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>

                        <div class="carousel-content">
                            <!-- Slide 1: Status Distribution -->
                            <div class="chart-slide active" data-slide="0">
                                <div class="chart-container">
                                    <div class="chart-header">
                                        <h4><i class="fas fa-chart-pie"></i> Reservation Status Distribution</h4>
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

                            <!-- Slide 3: Dāna Type Distribution -->
                            <div class="chart-slide" data-slide="2">
                                <div class="chart-container">
                                    <div class="chart-header">
                                        <h4><i class="fas fa-chart-donut"></i> Dāna Type Distribution</h4>
                                        <p>Popular dāna types</p>
                                    </div>
                                    <div class="chart-wrapper">
                                        <canvas id="dhanaTypeChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Slide 4: Revenue by Status -->
                            <div class="chart-slide" data-slide="3">
                                <div class="chart-container">
                                    <div class="chart-header">
                                        <h4><i class="fas fa-chart-bar"></i> Revenue by Status</h4>
                                        <p>Revenue breakdown by reservation status</p>
                                    </div>
                                    <div class="chart-wrapper">
                                        <canvas id="revenueChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                        <!-- Top Donors Table -->
                        <div class="chart-container full-width">
                            <div class="chart-header">
                                <h3><i class="fas fa-trophy"></i> Top Donors</h3>
                                <p>Most generous contributors</p>
                            </div>
                            <div class="table-wrapper">
                                <?php if (!empty($topDonors)): ?>
                                <table class="donors-table">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Donor</th>
                                            <th>Email</th>
                                            <th>Reservations</th>
                                            <th>Total Donated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topDonors as $index => $donor): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge rank-<?php echo min($index + 1, 3); ?>">
                                                    #<?php echo $index + 1; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="donor-info">
                                                    <strong><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($donor['email']); ?></td>
                                            <td>
                                                <span class="count-badge"><?php echo $donor['reservation_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="amount-badge"><?php echo formatCurrency($donor['total_donated']); ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-users"></i>
                                    <p>No donor data available for the selected period</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="chart-container full-width">
                            <div class="chart-header">
                                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                                <p>Latest reservation activity</p>
                            </div>
                            <div class="activity-list">
                                <?php if (!empty($recentActivity)): ?>
                                    <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon status-<?php echo $activity['status']; ?>">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">
                                                <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                                made a <?php echo htmlspecialchars($activity['dhana_type_name']); ?> reservation
                                            </div>
                                            <div class="activity-details">
                                                <span class="activity-amount"><?php echo formatCurrency($activity['total_amount']); ?></span>
                                                <span class="activity-status status-<?php echo $activity['status']; ?>">
                                                    <?php echo ucfirst($activity['status']); ?>
                                                </span>
                                                <span class="activity-date">
                                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-clock"></i>
                                    <p>No recent activity found</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart data from PHP
        const statusData = <?php echo json_encode($statusStats ?? []); ?>;
        const monthlyData = <?php echo json_encode($monthlyStats ?? []); ?>;
        const dhanaTypeData = <?php echo json_encode($dhanaTypeStats ?? []); ?>;

        // Filter functions
        function applyFilters() {
            const timeFilter = document.getElementById('timeFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;

            const url = new URL(window.location);
            url.searchParams.set('time_filter', timeFilter);
            url.searchParams.set('status_filter', statusFilter);

            window.location.href = url.toString();
        }

        function refreshData() {
            window.location.reload();
        }
    </script>
    <script src="../assets/js/analytics.js"></script>
</body>
</html>
