<?php
/**
 * Admin Sidebar Component
 * Common sidebar for all admin pages
 */

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);

// Get basic stats for dashboard cards (only for index page)
$stats = [];
if ($currentPage === 'index.php') {
    try {
        $db = getDB();
        
        // Get booking statistics
        $stats['total_bookings'] = $db->fetchOne("SELECT COUNT(*) as count FROM bookings")['count'];
        $stats['pending_bookings'] = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")['count'];
        $stats['payment_pending'] = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'payment_pending'")['count'];
        $stats['confirmed_bookings'] = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'")['count'];
        $stats['total_revenue'] = $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM bookings WHERE status IN ('confirmed', 'completed')")['revenue'];
    } catch (Exception $e) {
        // Default values if query fails
        $stats = [
            'total_bookings' => 0,
            'pending_bookings' => 0,
            'payment_pending' => 0,
            'confirmed_bookings' => 0,
            'total_revenue' => 0
        ];
    }
}
?>

<!-- Left Sidebar -->
<div class="admin-sidebar">
    <!-- Navigation Menu -->
    <div class="sidebar-section">
        <h3><i class="fas fa-bars"></i> Navigation</h3>
        
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="analytics.php" class="menu-item <?php echo $currentPage === 'analytics.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>

            <a href="pending-approvals.php" class="menu-item <?php echo $currentPage === 'pending-approvals.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>Pending Approvals</span>
                <?php
                // Show pending count badge
                try {
                    $db = getDB();
                    $pendingCount = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")['count'];
                    if ($pendingCount > 0) {
                        echo '<span class="menu-badge">' . $pendingCount . '</span>';
                    }
                } catch (Exception $e) {
                    // Ignore error
                }
                ?>
            </a>

            <a href="pricing-table.php" class="menu-item <?php echo $currentPage === 'pricing-table.php' ? 'active' : ''; ?>">
                <i class="fas fa-dollar-sign"></i>
                <span>Pricing Table</span>
            </a>

            <a href="settings.php" class="menu-item <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>

    <?php if ($currentPage === 'index.php' && !empty($stats)): ?>
    <!-- Dashboard Statistics (only show on dashboard) -->
    <div class="sidebar-section">
        <h3><i class="fas fa-chart-bar"></i> Dashboard Statistics</h3>

        <div class="stat-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'all') || !isset($_GET['status']) ? 'active' : ''; ?>" onclick="filterBookings('all')">
            <div class="stat-icon total">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-info">
                <h4><?php echo $stats['total_bookings']; ?></h4>
                <p>Total Bookings</p>
            </div>
        </div>

        <div class="stat-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'active' : ''; ?>" onclick="filterBookings('pending')">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h4><?php echo $stats['pending_bookings']; ?></h4>
                <p>Pending Bookings</p>
            </div>
        </div>

        <div class="stat-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'payment_pending') ? 'active' : ''; ?>" onclick="filterBookings('payment_pending')">
            <div class="stat-icon payment">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="stat-info">
                <h4><?php echo $stats['payment_pending']; ?></h4>
                <p>Payment Pending</p>
            </div>
        </div>

        <div class="stat-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'confirmed') ? 'active' : ''; ?>" onclick="filterBookings('confirmed')">
            <div class="stat-icon confirmed">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h4><?php echo $stats['confirmed_bookings']; ?></h4>
                <p>Confirmed</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon revenue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <h4>Rs. <?php echo number_format($stats['total_revenue']); ?></h4>
                <p>Total Revenue</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="sidebar-section">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        
        <div class="quick-actions">
            <button class="quick-action-btn" onclick="refreshData()" title="Refresh Data">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
            
            <?php if ($currentPage === 'index.php'): ?>
            <button class="quick-action-btn" onclick="toggleCalendarView()" title="Calendar View">
                <i class="fas fa-calendar-alt"></i>
                <span>Calendar</span>
            </button>
            
            <button class="quick-action-btn" onclick="showReportGenerator()" title="Generate Reports">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </button>
            <?php endif; ?>
            
            <?php if ($currentPage === 'analytics.php'): ?>
            <button class="quick-action-btn" onclick="exportData()" title="Export Data">
                <i class="fas fa-download"></i>
                <span>Export</span>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Sidebar Menu Styles */
.sidebar-menu {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: #666;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    position: relative;
    font-weight: 500;
}

.menu-item:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #d4822a;
    transform: translateX(5px);
}

.menu-item.active {
    background: linear-gradient(135deg, #d4822a 0%, #b8860b 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(212, 130, 42, 0.3);
}

.menu-item i {
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
}

.menu-badge {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: auto;
    font-weight: 600;
    animation: pulse 2s infinite;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    color: #666;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    font-weight: 500;
}

.quick-action-btn:hover {
    border-color: #d4822a;
    color: #d4822a;
    background: #fef9f3;
    transform: translateY(-2px);
}

.quick-action-btn i {
    font-size: 0.9rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .menu-item span,
    .quick-action-btn span {
        display: none;
    }
    
    .menu-item {
        justify-content: center;
        padding: 12px;
    }
    
    .quick-action-btn {
        justify-content: center;
        padding: 10px;
    }
}
</style>

<script>
// Common sidebar functions
function refreshData() {
    window.location.reload();
}

function exportData() {
    if (typeof exportChartData === 'function') {
        // Export analytics data if on analytics page
        const exportMenu = document.createElement('div');
        exportMenu.innerHTML = `
            <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                        background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000;">
                <h4>Export Data</h4>
                <button onclick="exportChartData('status'); this.parentElement.parentElement.remove();">Status Distribution</button><br><br>
                <button onclick="exportChartData('monthly'); this.parentElement.parentElement.remove();">Monthly Trends</button><br><br>
                <button onclick="exportChartData('dhana_type'); this.parentElement.parentElement.remove();">Dhana Types</button><br><br>
                <button onclick="this.parentElement.parentElement.remove();">Cancel</button>
            </div>
        `;
        document.body.appendChild(exportMenu);
    }
}
</script>
