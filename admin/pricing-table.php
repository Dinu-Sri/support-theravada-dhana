<?php
/**
 * Admin Pricing Table - Dynamic Pricing Management
 * Allows admins to view and edit monthly prices for all dhana types
 */

session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(false);

$db = getDB();
$successMessage = '';
$errorMessage = '';

// Get current admin details
$adminId = $_SESSION['admin_id'];
$admin = $db->fetchOne("SELECT * FROM admin_users WHERE id = ?", [$adminId]);

// Permission checking function
function hasPermission($permission) {
    global $db;
    return adminHasPermission($db, $permission);
}

// Check if user has permission to view pricing table
if (!hasPermission('view_pricing_table')) {
    header('Location: index.php?error=access_denied');
    exit;
}

adminEnsureCsrfToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireCsrf(null, false);
}

// Handle price update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_price'])) {
    // Check permission
    if (!hasPermission('edit_pricing')) {
        $errorMessage = "You don't have permission to edit prices.";
    } else {
        $pricingId = (int)($_POST['pricing_id'] ?? 0);
        $rawPrice = $_POST['price'] ?? null;
        $notes = trim($_POST['notes'] ?? '');

        try {
            if ($pricingId < 1 || !is_numeric($rawPrice)) {
                throw new InvalidArgumentException('Select a valid pricing entry and enter a numeric price.');
            }
            $newPrice = (float)$rawPrice;
            if (!is_finite($newPrice) || $newPrice < 0 || $newPrice > 100000000) {
                throw new InvalidArgumentException('Price must be between LKR 0 and LKR 100,000,000.');
            }
            if (strlen($notes) > 255) {
                throw new InvalidArgumentException('Notes cannot exceed 255 characters.');
            }

            $db->getConnection()->beginTransaction();
            // Get old price for history
            $oldPricing = $db->fetchOne("SELECT * FROM monthly_pricing WHERE id = ? FOR UPDATE", [$pricingId]);

            if (!$oldPricing) {
                throw new InvalidArgumentException('The selected pricing entry no longer exists.');
            }

            $db->query(
                "UPDATE monthly_pricing SET price = ?, notes = ?, updated_by = ?, updated_at = NOW() WHERE id = ?",
                [$newPrice, $notes, $adminId, $pricingId]
            );

            $db->query(
                "INSERT INTO pricing_history (monthly_pricing_id, dhana_type_id, year, month, old_price, new_price, changed_by, change_reason, changed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$pricingId, $oldPricing['dhana_type_id'], $oldPricing['year'], $oldPricing['month'], $oldPricing['price'], $newPrice, $adminId, $notes]
            );

            $db->getConnection()->commit();
            $successMessage = 'Price updated successfully.';
        } catch (Throwable $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->getConnection()->rollBack();
            }
            adminLogException('Pricing update failed', $e);
            $errorMessage = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'Unable to update the price. Please try again.';
        }
    }
}

// Bulk update feature removed - not needed

// Handle add new month
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_month'])) {
    // Check permission
    if (!hasPermission('add_pricing_months')) {
        $errorMessage = "You don't have permission to add months.";
    } else {
        $monthsToAdd = (int)($_POST['months_to_add'] ?? 0);

        try {
            if ($monthsToAdd < 1 || $monthsToAdd > 24) {
                throw new InvalidArgumentException('Choose between 1 and 24 months to add.');
            }
            $db->getConnection()->beginTransaction();
            // Get the latest month in the pricing table
            $latestEntry = $db->fetchOne(
                "SELECT year, month FROM monthly_pricing ORDER BY year DESC, month DESC LIMIT 1"
            );

            if ($latestEntry) {
                $lastDate = new DateTime(sprintf('%04d-%02d-01', $latestEntry['year'], $latestEntry['month']));
                $dhanaTypes = $db->fetchAll("SELECT id, price FROM dhana_types WHERE is_active = 1");

                $insertedCount = 0;

                for ($i = 1; $i <= $monthsToAdd; $i++) {
                    $lastDate->modify('+1 month');
                    $year = (int)$lastDate->format('Y');
                    $month = (int)$lastDate->format('n');

                    foreach ($dhanaTypes as $type) {
                        // Check if already exists
                        $exists = $db->fetchOne(
                            "SELECT id FROM monthly_pricing WHERE dhana_type_id = ? AND year = ? AND month = ?",
                            [$type['id'], $year, $month]
                        );

                        if (!$exists) {
                            $db->query(
                                "INSERT INTO monthly_pricing (dhana_type_id, year, month, price, is_confirmed, created_at)
                                 VALUES (?, ?, ?, ?, 1, NOW())",
                                [$type['id'], $year, $month, $type['price']]
                            );
                            $insertedCount++;
                        }
                    }
                }

                // Update pricing window setting to match actual months in table
                updatePricingWindow($db);

                $db->getConnection()->commit();
                $successMessage = "Added {$monthsToAdd} months to the pricing table ({$insertedCount} entries created).";
            } else {
                throw new DomainException('No base pricing month exists. Import the seed data before adding months.');
            }
        } catch (Throwable $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->getConnection()->rollBack();
            }
            adminLogException('Add pricing months failed', $e);
            $errorMessage = $e instanceof InvalidArgumentException || $e instanceof DomainException
                ? $e->getMessage()
                : 'Unable to add pricing months. Please try again.';
        }
    }
}

// Handle remove months
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_months'])) {
    // Check permission (Administrator only)
    if (!hasPermission('remove_pricing_months')) {
        $errorMessage = "You don't have permission to remove months. This action is restricted to Administrators only.";
    } else {
        $monthsToRemove = (int)($_POST['months_to_remove'] ?? 0);

        try {
            if ($monthsToRemove < 1 || $monthsToRemove > 12) {
                throw new InvalidArgumentException('Choose between 1 and 12 months to remove.');
            }
            $db->getConnection()->beginTransaction();
            // Get the latest months to remove (from the end)
            $latestMonths = $db->fetchAll(
                "SELECT DISTINCT year, month FROM monthly_pricing
                 ORDER BY year DESC, month DESC
                 LIMIT ?",
                [$monthsToRemove]
            );

            if ($latestMonths) {
                $deletedCount = 0;

                foreach ($latestMonths as $monthData) {
                    // Delete all pricing entries for this month
                    $db->query(
                        "DELETE FROM monthly_pricing WHERE year = ? AND month = ?",
                        [$monthData['year'], $monthData['month']]
                    );
                    $deletedCount++;
                }

                // Update pricing window setting to match actual months in table
                updatePricingWindow($db);

                $db->getConnection()->commit();
                $successMessage = "Removed {$deletedCount} months from the pricing table.";
            } else {
                throw new DomainException('There are no pricing months to remove.');
            }
        } catch (Throwable $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->getConnection()->rollBack();
            }
            adminLogException('Remove pricing months failed', $e);
            $errorMessage = $e instanceof InvalidArgumentException || $e instanceof DomainException
                ? $e->getMessage()
                : 'Unable to remove pricing months. Please try again.';
        }
    }
}

// Function to update pricing window setting based on actual months in table
function updatePricingWindow($db) {
    // Get current date
    $currentDate = new DateTime();
    $currentDate->modify('first day of this month');

    // Get the latest month in pricing table
    $latestEntry = $db->fetchOne(
        "SELECT year, month FROM monthly_pricing ORDER BY year DESC, month DESC LIMIT 1"
    );

    if ($latestEntry) {
        $latestDate = new DateTime(sprintf('%04d-%02d-01', $latestEntry['year'], $latestEntry['month']));
        $monthsDiff = (($latestDate->format('Y') - $currentDate->format('Y')) * 12)
            + ($latestDate->format('n') - $currentDate->format('n')) + 1;
        $monthsDiff = max(0, $monthsDiff);

        $db->query(
            "INSERT INTO settings (setting_key, setting_value, description)
             VALUES ('pricing_window_months', ?, 'Number of months with configured pricing')
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$monthsDiff]
        );
    }
}

// Get all dhana types
$dhanaTypes = $db->fetchAll("SELECT * FROM dhana_types WHERE is_active = 1 ORDER BY id");

// Get pricing window setting
$windowSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'pricing_window_months'");
$pricingWindowMonths = $windowSetting ? (int)$windowSetting['setting_value'] : 24;

// Get current date range in pricing table
$dateRange = $db->fetchOne(
    "SELECT 
        MIN(CONCAT(year, '-', LPAD(month, 2, '0'), '-01')) as min_date,
        MAX(CONCAT(year, '-', LPAD(month, 2, '0'), '-01')) as max_date,
        COUNT(DISTINCT CONCAT(year, '-', month)) as total_months
     FROM monthly_pricing"
);

// Determine which months to display (default: next 12 months from current month)
$displayMonths = isset($_GET['display_months']) ? (int)$_GET['display_months'] : 12;
$startOffset = isset($_GET['start_offset']) ? (int)$_GET['start_offset'] : 0;

$currentDate = new DateTime();
$currentDate->modify("+{$startOffset} months");

// Build pricing data array
$pricingData = [];
for ($i = 0; $i < $displayMonths; $i++) {
    $targetDate = clone $currentDate;
    $targetDate->modify("+{$i} months");
    $year = (int)$targetDate->format('Y');
    $month = (int)$targetDate->format('n');
    $monthKey = "{$year}-{$month}";
    
    $pricingData[$monthKey] = [
        'year' => $year,
        'month' => $month,
        'display' => $targetDate->format('M Y'),
        'full_date' => $targetDate->format('Y-m-d'),
        'prices' => []
    ];
    
    foreach ($dhanaTypes as $type) {
        $pricing = $db->fetchOne(
            "SELECT * FROM monthly_pricing WHERE dhana_type_id = ? AND year = ? AND month = ?",
            [$type['id'], $year, $month]
        );
        
        $pricingData[$monthKey]['prices'][$type['id']] = $pricing ?: null;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Table - Admin Panel</title>
    <?php include '../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/pricing-table.css?v=<?php echo time(); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-panel">
        <!-- Admin Header -->
        <div class="admin-header">
            <div class="admin-nav">
                <h1><i class="fas fa-dollar-sign"></i> Dynamic Pricing Table</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo htmlspecialchars($admin['username']); ?>
                        <span class="role-badge role-<?php echo $_SESSION['admin_role']; ?>">
                            <?php echo ucfirst($_SESSION['admin_role']); ?>
                        </span>
                    </span>
                    <a href="index.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <?php adminRenderLogoutButton(); ?>
                </div>
            </div>
        </div>

        <!-- Main Content Layout -->
        <div class="admin-layout">
            <div class="admin-main-content">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- Pricing Table Info -->
            <div class="pricing-info-cards">
                <div class="info-card">
                    <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="info-content">
                        <h3><?php echo $dateRange['total_months'] ?? 0; ?></h3>
                        <p>Total Months</p>
                        <small style="color: #666; font-size: 11px; display: block; margin-top: 4px;">All months with pricing data (past + future)</small>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="info-content">
                        <h3><?php echo !empty($dateRange['min_date']) ? date('M Y', strtotime($dateRange['min_date'])) : 'No data'; ?></h3>
                        <p>First Month</p>
                        <small style="color: #666; font-size: 11px; display: block; margin-top: 4px;">Earliest month in pricing table</small>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-icon"><i class="fas fa-calendar-plus"></i></div>
                    <div class="info-content">
                        <h3><?php echo !empty($dateRange['max_date']) ? date('M Y', strtotime($dateRange['max_date'])) : 'No data'; ?></h3>
                        <p>Last Month</p>
                        <small style="color: #666; font-size: 11px; display: block; margin-top: 4px;">Latest month customers can book</small>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-icon"><i class="fas fa-cog"></i></div>
                    <div class="info-content">
                        <h3><?php echo $pricingWindowMonths; ?> Months</h3>
                        <p>Pricing Window</p>
                        <small style="color: #666; font-size: 11px; display: block; margin-top: 4px;">Months from today with confirmed prices</small>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="pricing-controls">
                <div class="control-group">
                    <button class="btn btn-success" onclick="showAddMonthModal()">
                        <i class="fas fa-plus"></i> Add Months
                    </button>
                    <button class="btn btn-danger" onclick="showRemoveMonthModal()">
                        <i class="fas fa-minus"></i> Remove Months
                    </button>
                    <button class="btn btn-primary" onclick="showPricingHistory()">
                        <i class="fas fa-history"></i> View History
                    </button>
                </div>

                <div class="control-group">
                    <label>Display:</label>
                    <select onchange="changeDisplayMonths(this.value)" class="form-control" style="width: auto; display: inline-block;">
                        <option value="6" <?php echo $displayMonths == 6 ? 'selected' : ''; ?>>6 Months</option>
                        <option value="12" <?php echo $displayMonths == 12 ? 'selected' : ''; ?>>12 Months</option>
                        <option value="24" <?php echo $displayMonths == 24 ? 'selected' : ''; ?>>24 Months</option>
                        <option value="36" <?php echo $displayMonths == 36 ? 'selected' : ''; ?>>36 Months</option>
                        <option value="48" <?php echo $displayMonths == 48 ? 'selected' : ''; ?>>48 Months</option>
                        <option value="60" <?php echo $displayMonths == 60 ? 'selected' : ''; ?>>60 Months</option>
                        <option value="72" <?php echo $displayMonths == 72 ? 'selected' : ''; ?>>72 Months</option>
                    </select>

                    <button class="btn btn-sm" onclick="navigateMonths(-<?php echo $displayMonths; ?>)" <?php echo $startOffset <= 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="btn btn-sm" onclick="navigateMonths(<?php echo $displayMonths; ?>)">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>

            <!-- Pricing Table -->
            <div class="pricing-table-container">
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th class="sticky-col">Dhana Type</th>
                            <?php foreach ($pricingData as $monthData): ?>
                                <th class="month-header">
                                    <?php echo $monthData['display']; ?>
                                    <br>
                                    <small><?php echo date('M', mktime(0, 0, 0, $monthData['month'], 1)) . ' ' . $monthData['year']; ?></small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dhanaTypes as $type): ?>
                            <tr>
                                <td class="sticky-col dhana-type-name">
                                    <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                    <br>
                                    <small>Base: LKR <?php echo number_format($type['price'], 2); ?></small>
                                </td>
                                <?php foreach ($pricingData as $monthKey => $monthData): ?>
                                    <?php
                                    $pricing = $monthData['prices'][$type['id']];
                                    $isPast = strtotime($monthData['full_date']) < strtotime(date('Y-m-01'));
                                    $isCurrent = date('Y-n') == "{$monthData['year']}-{$monthData['month']}";
                                    ?>
                                    <td class="price-cell <?php echo $isPast ? 'past-month' : ''; ?> <?php echo $isCurrent ? 'current-month' : ''; ?>">
                                        <?php if ($pricing): ?>
                                            <div class="price-display" role="button" tabindex="0"
                                                 onclick="editPrice(<?php echo (int)$pricing['id']; ?>, <?php echo json_encode((float)$pricing['price']); ?>, <?php echo htmlspecialchars(json_encode($pricing['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)"
                                                 onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }">
                                                <div class="price-amount">LKR <?php echo number_format($pricing['price'], 2); ?></div>
                                                <?php if ($pricing['notes']): ?>
                                                    <div class="price-notes" title="<?php echo htmlspecialchars($pricing['notes']); ?>">
                                                        <i class="fas fa-sticky-note"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($pricing['updated_by']): ?>
                                                    <div class="price-updated">
                                                        <i class="fas fa-edit"></i> <?php echo date('M d', strtotime($pricing['updated_at'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="price-missing">
                                                <i class="fas fa-exclamation-triangle"></i> Not Set
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Legend -->
            <div class="pricing-legend">
                <div class="legend-item">
                    <span class="legend-color current-month"></span>
                    <span>Current Month</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color past-month"></span>
                    <span>Past Month</span>
                </div>
                <div class="legend-item">
                    <i class="fas fa-edit"></i>
                    <span>Click to Edit Price</span>
                </div>
            </div>
            </div><!-- .admin-main-content -->
        </div><!-- .admin-layout -->
    </div><!-- .admin-panel -->

    <!-- Edit Price Modal -->
    <div id="editPriceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Price</h2>
                <span class="close" onclick="closeModal('editPriceModal')">&times;</span>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="pricing_id" id="edit_pricing_id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">

                <div class="form-group">
                    <label for="edit_price">Price (LKR)</label>
                    <input type="number" step="0.01" name="price" id="edit_price" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="edit_notes">Notes (Optional)</label>
                    <textarea name="notes" id="edit_notes" rows="3" class="form-control" placeholder="Add notes about this price change..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editPriceModal')">Cancel</button>
                    <button type="submit" name="update_price" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Price
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Month Modal -->
    <div id="addMonthModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Add Months to Pricing Table</h2>
                <span class="close" onclick="closeModal('addMonthModal')">&times;</span>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                <div class="form-group">
                    <label for="months_to_add">Number of Months to Add</label>
                    <input type="number" name="months_to_add" id="months_to_add" min="1" max="24" value="1" required class="form-control">
                    <small>This will add months to the end of the current pricing table</small>
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <p>New months will be populated with the current base prices from dhana_types table.</p>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addMonthModal')">Cancel</button>
                    <button type="submit" name="add_month" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Months
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Remove Month Modal -->
    <div id="removeMonthModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-minus"></i> Remove Months from Pricing Table</h2>
                <span class="close" onclick="closeModal('removeMonthModal')">&times;</span>
            </div>
            <form method="POST" class="modal-form" onsubmit="return confirmRemoveMonths()">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                <div class="form-group">
                    <label for="months_to_remove">Number of Months to Remove</label>
                    <input type="number" name="months_to_remove" id="months_to_remove" min="1" max="12" value="1" required class="form-control">
                    <small>This will remove months from the end of the pricing table</small>
                </div>

                <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
                    <i class="fas fa-exclamation-triangle" style="color: #856404;"></i>
                    <p style="color: #856404;"><strong>Warning:</strong> This action will permanently delete pricing data for the selected months. This cannot be undone!</p>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('removeMonthModal')">Cancel</button>
                    <button type="submit" name="remove_months" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Remove Months
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/pricing-table.js?v=<?php echo time(); ?>"></script>
</body>
</html>
