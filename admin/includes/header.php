<?php
$adminPageTitle = $adminPageTitle ?? 'Administration';
$adminPageIcon = $adminPageIcon ?? 'fa-user-shield';
$adminCurrentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$adminRole = $_SESSION['admin_role'] ?? '';
$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';
$adminPendingApprovalCount = 0;

if ($adminRole === 'administrator') {
    try {
        $adminHeaderDb = isset($db) ? $db : getDB();
        $adminPendingApprovalCount = (int)($adminHeaderDb->fetchOne(
            "SELECT COUNT(*) AS count FROM admin_actions WHERE status = 'pending'"
        )['count'] ?? 0);
    } catch (Throwable $e) {
        error_log('Unable to load pending approval count: ' . $e->getMessage());
    }
}

function adminHeaderLinkClass($pages) {
    global $adminCurrentPage;
    return in_array($adminCurrentPage, (array)$pages, true) ? 'admin-primary-link active' : 'admin-primary-link';
}
?>
<header class="admin-header">
    <div class="admin-nav">
        <div class="admin-brand-block">
            <a class="admin-brand" href="index.php" aria-label="Dāna administration dashboard">
                <i class="fas fa-dharmachakra" aria-hidden="true"></i>
                <span>Dāna Admin</span>
            </a>
            <span class="admin-page-context"><i class="fas <?php echo htmlspecialchars($adminPageIcon); ?>" aria-hidden="true"></i><?php echo htmlspecialchars($adminPageTitle); ?></span>
        </div>

        <button class="admin-nav-toggle" type="button" aria-expanded="false" aria-controls="adminPrimaryNav">
            <i class="fas fa-bars" aria-hidden="true"></i><span class="sr-only">Toggle navigation</span>
        </button>

        <nav class="admin-primary-nav" id="adminPrimaryNav" aria-label="Administration">
            <a href="index.php" class="<?php echo adminHeaderLinkClass('index.php'); ?>"><i class="fas fa-list" aria-hidden="true"></i><span>Reservations</span></a>
            <?php if (!function_exists('hasPermission') || hasPermission('view_analytics') || hasPermission('view_all_bookings')): ?>
                <a href="analytics.php" class="<?php echo adminHeaderLinkClass('analytics.php'); ?>"><i class="fas fa-chart-line" aria-hidden="true"></i><span>Analytics</span></a>
            <?php endif; ?>
            <?php if ($adminRole === 'administrator'): ?>
                <?php if ($adminCurrentPage === 'index.php'): ?>
                    <button type="button" id="userManagementBtn" class="admin-primary-link admin-primary-button"><i class="fas fa-users" aria-hidden="true"></i><span>Users</span></button>
                <?php else: ?>
                    <a href="index.php?manage_users=1" class="admin-primary-link"><i class="fas fa-users" aria-hidden="true"></i><span>Users</span></a>
                <?php endif; ?>
                <a href="pending-approvals.php" class="<?php echo adminHeaderLinkClass('pending-approvals.php'); ?>">
                    <i class="fas fa-clipboard-check" aria-hidden="true"></i><span>Approvals</span>
                    <?php if ($adminPendingApprovalCount > 0): ?><span class="admin-nav-badge" aria-label="<?php echo $adminPendingApprovalCount; ?> pending approval actions"><?php echo $adminPendingApprovalCount; ?></span><?php endif; ?>
                </a>
            <?php endif; ?>
            <?php if (!function_exists('hasPermission') || hasPermission('view_pricing_table') || hasPermission('view_pricing_history') || hasPermission('edit_pricing')): ?>
                <a href="pricing-table.php" class="<?php echo adminHeaderLinkClass(['pricing-table.php', 'pricing-history.php']); ?>"><i class="fas fa-tags" aria-hidden="true"></i><span>Pricing</span></a>
            <?php endif; ?>
            <?php if ($adminRole === 'administrator'): ?>
                <a href="role_management.php" class="<?php echo adminHeaderLinkClass('role_management.php'); ?>"><i class="fas fa-user-shield" aria-hidden="true"></i><span>Roles</span></a>
            <?php endif; ?>
            <a href="settings.php" class="<?php echo adminHeaderLinkClass('settings.php'); ?>"><i class="fas fa-cog" aria-hidden="true"></i><span>Settings</span></a>
        </nav>

        <div class="admin-account-menu">
            <span class="admin-account-copy"><strong><?php echo htmlspecialchars($adminUsername); ?></strong><small><?php echo htmlspecialchars(ucfirst($adminRole)); ?></small></span>
            <?php adminRenderLogoutButton(); ?>
        </div>
    </div>
</header>
<script src="../assets/js/admin-ui.js?v=20260915"></script>
<script>
(function () {
    const toggle = document.querySelector('.admin-nav-toggle');
    const nav = document.getElementById('adminPrimaryNav');
    if (!toggle || !nav) return;
    toggle.addEventListener('click', function () {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        nav.classList.toggle('is-open', !open);
    });
})();
</script>
