<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(true);
$db = getDB();
adminRequirePermission($db, 'view_analytics', true);
header('Content-Type: application/json; charset=UTF-8');

// Get filter parameters
$timeFilter = $_GET['time_filter'] ?? '30_days';
$statusFilter = $_GET['status_filter'] ?? 'all';
$validTimeFilters = ['7_days', '30_days', '90_days', '1_year', 'all_time'];
if (!in_array($timeFilter, $validTimeFilters, true)) {
    adminJsonResponse(['success' => false, 'error' => 'Invalid time filter.'], 400);
}
$validStatuses = ['all', 'pending', 'receipt_submitted', 'payment_pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($statusFilter, $validStatuses, true)) {
    adminJsonResponse(['success' => false, 'error' => 'Invalid status filter.'], 400);
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
    // Get total statistics
    $totalQuery = "SELECT
        COUNT(*) as total_reservations,
        COALESCE(SUM(b.total_amount), 0) as total_revenue,
        COALESCE(AVG(b.total_amount), 0) as avg_reservation_value,
        COUNT(DISTINCT b.user_id) as unique_donors
        FROM bookings b
        WHERE 1=1 " . str_replace('created_at', 'b.created_at', $dateCondition) . " " . str_replace('status', 'b.status', $statusCondition);
    
    $totalStats = $db->fetchOne($totalQuery, $params);
    
    // Get status distribution
    $statusQuery = "SELECT
        b.status,
        COUNT(*) as count,
        COALESCE(SUM(b.total_amount), 0) as revenue
        FROM bookings b
        WHERE 1=1 " . str_replace('created_at', 'b.created_at', $dateCondition) . " " . str_replace('status', 'b.status', $statusCondition) . "
        GROUP BY b.status
        ORDER BY count DESC";

    $statusStats = $db->fetchAll($statusQuery, $params);
    
    // Get monthly trends - use time filter for range, but ensure we have at least some months to show
    $monthlyTimeCondition = $dateCondition;
    if (empty($monthlyTimeCondition)) {
        // If no time filter, show last 12 months
        $monthlyTimeCondition = "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
    }

    $monthlyQuery = "SELECT
        DATE_FORMAT(b.created_at, '%Y-%m') as month,
        COUNT(*) as reservations,
        COALESCE(SUM(b.total_amount), 0) as revenue
        FROM bookings b
        WHERE 1=1 " . $monthlyTimeCondition . " " . str_replace('status', 'b.status', $statusCondition) . "
        GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
        ORDER BY month ASC";

    $monthlyStats = $db->fetchAll($monthlyQuery, $params);
    
    // Get dhana type distribution
    $dhanaTypeQuery = "SELECT
        dt.name as dhana_type,
        COUNT(*) as count,
        COALESCE(SUM(b.total_amount), 0) as revenue
        FROM bookings b
        JOIN dhana_types dt ON b.dhana_type_id = dt.id
        WHERE 1=1 " . str_replace('created_at', 'b.created_at', $dateCondition) . " " . str_replace('status', 'b.status', $statusCondition) . "
        GROUP BY dt.id, dt.name
        ORDER BY count DESC";
    
    $dhanaTypeStats = $db->fetchAll($dhanaTypeQuery, $params);
    
    // Get top donors
    $topDonorsQuery = "SELECT
        u.first_name,
        u.last_name,
        u.email,
        COUNT(b.id) as reservation_count,
        COALESCE(SUM(b.total_amount), 0) as total_donated
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE 1=1 " . str_replace('created_at', 'b.created_at', $dateCondition) . " " . str_replace('status', 'b.status', $statusCondition) . "
        GROUP BY b.user_id, u.first_name, u.last_name, u.email
        ORDER BY total_donated DESC
        LIMIT 10";
    
    $topDonors = $db->fetchAll($topDonorsQuery, $params);
    
    // Get recent activity
    $recentQuery = "SELECT
        b.*,
        dt.name as dhana_type_name,
        u.first_name,
        u.last_name,
        u.email
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN dhana_types dt ON b.dhana_type_id = dt.id
        WHERE 1=1 " . str_replace('created_at', 'b.created_at', $dateCondition) . " " . str_replace('status', 'b.status', $statusCondition) . "
        ORDER BY b.created_at DESC
        LIMIT 10";
    
    $recentActivity = $db->fetchAll($recentQuery, $params);
    
    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'totalStats' => $totalStats,
            'statusStats' => $statusStats,
            'monthlyStats' => $monthlyStats,
            'dhanaTypeStats' => $dhanaTypeStats,
            'topDonors' => $topDonors,
            'recentActivity' => $recentActivity,
            'filters' => [
                'timeFilter' => $timeFilter,
                'statusFilter' => $statusFilter
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    adminLogException('Analytics data failed', $e);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load analytics data. Please try again.'
    ]);
}
?>
