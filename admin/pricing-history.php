<?php
/**
 * Admin Pricing History - View all price changes
 * Shows who changed what price and when
 */

session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$db = getDB();

// Get current admin details
$adminId = $_SESSION['admin_id'];
$admin = $db->fetchOne("SELECT * FROM admin_users WHERE id = ?", [$adminId]);

// Permission checking function
function hasPermission($permission) {
    global $db;
    $role = $_SESSION['admin_role'];

    $check = $db->fetchOne(
        "SELECT COUNT(*) as has_permission FROM role_permissions
         WHERE role_name = ? AND permission_name = ?",
        [$role, $permission]
    );

    return $check['has_permission'] > 0;
}

// Check if user has permission to view pricing history
if (!hasPermission('view_pricing_history')) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$dhanaTypeFilter = isset($_GET['dhana_type']) ? (int)$_GET['dhana_type'] : 0;
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : 0;

// Build query
$whereConditions = [];
$params = [];

if ($dhanaTypeFilter > 0) {
    $whereConditions[] = "ph.dhana_type_id = ?";
    $params[] = $dhanaTypeFilter;
}

if ($yearFilter > 0) {
    $whereConditions[] = "ph.year = ?";
    $params[] = $yearFilter;
}

if ($monthFilter > 0) {
    $whereConditions[] = "ph.month = ?";
    $params[] = $monthFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM pricing_history ph $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get history records
$historyQuery = "
    SELECT 
        ph.*,
        dt.name as dhana_type_name,
        au.username as changed_by_username
    FROM pricing_history ph
    LEFT JOIN dhana_types dt ON ph.dhana_type_id = dt.id
    LEFT JOIN admin_users au ON ph.changed_by = au.id
    $whereClause
    ORDER BY ph.changed_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$historyRecords = $db->fetchAll($historyQuery, $params);

// Get dhana types for filter
$dhanaTypes = $db->fetchAll("SELECT * FROM dhana_types WHERE is_active = 1 ORDER BY name");

// Get available years for filter
$years = $db->fetchAll("SELECT DISTINCT year FROM pricing_history ORDER BY year DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing History - Admin Panel</title>
    <?php include '../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/pricing-table.css?v=<?php echo time(); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .history-container {
            padding: 20px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #d4822a 0%, #b8860b 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(212, 130, 42, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .history-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .history-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th {
            background: linear-gradient(135deg, #d4822a 0%, #b8860b 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .history-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .history-table tr:hover {
            background: #f8f9fa;
        }

        .price-change {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .old-price {
            color: #dc3545;
            text-decoration: line-through;
        }

        .new-price {
            color: #28a745;
            font-weight: 600;
        }

        .change-arrow {
            color: #6c757d;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #d4822a;
            color: white;
            border-color: #d4822a;
        }

        .pagination .current {
            background: #d4822a;
            color: white;
            border-color: #d4822a;
        }

        .no-records {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .no-records i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="admin-panel">
        <!-- Admin Header -->
        <div class="admin-header">
            <div class="admin-nav">
                <h1><i class="fas fa-history"></i> Pricing History</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo htmlspecialchars($admin['username']); ?>
                        <span class="role-badge role-<?php echo $_SESSION['admin_role']; ?>">
                            <?php echo ucfirst($_SESSION['admin_role']); ?>
                        </span>
                    </span>
                    <a href="pricing-table.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Pricing Table
                    </a>
                    <a href="?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content Layout -->
        <div class="admin-layout">
            <div class="admin-main-content">
                <div class="history-container">

            <!-- Filters -->
            <div class="filters">
                <h2 style="margin: 0 0 15px 0; color: #d4822a;">
                    <i class="fas fa-filter"></i> Filter History
                </h2>
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="dhana_type">Dhana Type</label>
                            <select name="dhana_type" id="dhana_type">
                                <option value="0">All Types</option>
                                <?php foreach ($dhanaTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo $dhanaTypeFilter == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="year">Year</label>
                            <select name="year" id="year">
                                <option value="0">All Years</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo $y['year']; ?>" <?php echo $yearFilter == $y['year'] ? 'selected' : ''; ?>>
                                        <?php echo $y['year']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="month">Month</label>
                            <select name="month" id="month">
                                <option value="0">All Months</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $monthFilter == $m ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- History Table -->
            <div class="history-table">
                <?php if (count($historyRecords) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Dhana Type</th>
                                <th>Year</th>
                                <th>Month</th>
                                <th>Price Change</th>
                                <th>Changed By</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyRecords as $record): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($record['changed_at'])); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($record['dhana_type_name']); ?></strong>
                                    </td>
                                    <td><?php echo $record['year']; ?></td>
                                    <td><?php echo date('F', mktime(0, 0, 0, $record['month'], 1)); ?></td>
                                    <td>
                                        <div class="price-change">
                                            <span class="old-price">LKR <?php echo number_format($record['old_price'], 2); ?></span>
                                            <span class="change-arrow"><i class="fas fa-arrow-right"></i></span>
                                            <span class="new-price">LKR <?php echo number_format($record['new_price'], 2); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($record['changed_by_username'] ?? 'Unknown'); ?>
                                    </td>
                                    <td>
                                        <?php if ($record['change_reason']): ?>
                                            <i class="fas fa-comment"></i>
                                            <?php echo htmlspecialchars($record['change_reason']); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&dhana_type=<?php echo $dhanaTypeFilter; ?>&year=<?php echo $yearFilter; ?>&month=<?php echo $monthFilter; ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <span class="current">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&dhana_type=<?php echo $dhanaTypeFilter; ?>&year=<?php echo $yearFilter; ?>&month=<?php echo $monthFilter; ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-inbox"></i>
                        <h3>No History Records Found</h3>
                        <p>There are no pricing changes matching your filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div><!-- .history-container -->
        </div><!-- .admin-main-content -->
        </div><!-- .admin-layout -->
    </div><!-- .admin-panel -->
</body>
</html>

