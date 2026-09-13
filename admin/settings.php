<?php
/**
 * Admin Settings Page for Dhana Reservation System
 */

session_start();
require_once '../config/database.php';
require_once '../config/backup.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$db = getDB();
$successMessage = '';
$errorMessage = '';

// Get current admin details
$adminId = $_SESSION['admin_id'];
$admin = $db->fetchOne(
    "SELECT * FROM admin_users WHERE id = ?",
    [$adminId]
);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        // Validation
        if (empty($username) || empty($email)) {
            $errorMessage = 'Username and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } else {
            try {
                // Check if username/email already exists for other users
                $existingUser = $db->fetchOne(
                    "SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?",
                    [$username, $email, $adminId]
                );

                if ($existingUser) {
                    $errorMessage = 'Username or email already exists.';
                } else {
                    // Update profile
                    $db->query(
                        "UPDATE admin_users SET username = ?, email = ?, phone = ? WHERE id = ?",
                        [$username, $email, $phone, $adminId]
                    );

                    // Update session
                    $_SESSION['admin_username'] = $username;

                    $successMessage = 'Profile updated successfully!';

                    // Refresh admin data
                    $admin = $db->fetchOne(
                        "SELECT * FROM admin_users WHERE id = ?",
                        [$adminId]
                    );
                }
            } catch (Exception $e) {
                $errorMessage = 'Error updating profile: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Validation
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = 'All password fields are required.';
        } elseif (!password_verify($currentPassword, $admin['password_hash'])) {
            $errorMessage = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $errorMessage = 'New password must be at least 6 characters long.';
        } else {
            try {
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->query(
                    "UPDATE admin_users SET password_hash = ? WHERE id = ?",
                    [$newPasswordHash, $adminId]
                );

                $successMessage = 'Password changed successfully!';
            } catch (Exception $e) {
                $errorMessage = 'Error changing password: ' . $e->getMessage();
            }
        }
    }

    // Handle annual booking years update (Administrator only)
    if (isset($_POST['update_annual_years']) && $admin['role'] === 'administrator') {
        // Check permission
        if (!hasPermission('manage_annual_settings')) {
            $errorMessage = 'You do not have permission to manage annual booking settings.';
        } else {
            $annualBookingYears = (int)$_POST['annual_booking_years'];

            // Validation
            if ($annualBookingYears < 1 || $annualBookingYears > 50) {
                $errorMessage = 'Annual booking years must be between 1 and 50.';
            } else {
                try {
                    // Update annual booking years setting
                    $db->query(
                        "UPDATE settings SET setting_value = ? WHERE setting_key = 'annual_booking_years'",
                        [$annualBookingYears]
                    );

                    $successMessage = 'Annual booking years updated successfully to ' . $annualBookingYears . ' years!';

                    // Reload the page to show updated values
                    header('Location: settings.php?success=annual_years_updated');
                    exit;
                } catch (Exception $e) {
                    $errorMessage = 'Error updating annual booking years: ' . $e->getMessage();
                }
            }
        }
    }

    // Handle auto-cancel days before dhana update (Administrator only)
    if (isset($_POST['update_auto_cancel']) && $admin['role'] === 'administrator') {
        // Check permission
        if (!hasPermission('manage_auto_cancel')) {
            $errorMessage = 'You do not have permission to manage auto-cancel settings.';
        } else {
            $autoCancelDays = (int)$_POST['auto_cancel_days_before'];

            // Validation
            if ($autoCancelDays < 0 || $autoCancelDays > 365) {
                $errorMessage = 'Auto-cancel days must be between 0 and 365 days.';
            } else {
            try {
                // Update or insert auto-cancel days setting
                $existingSetting = $db->fetchOne(
                    "SELECT id FROM settings WHERE setting_key = 'auto_cancel_days_before_dhana'"
                );

                if ($existingSetting) {
                    $db->query(
                        "UPDATE settings SET setting_value = ? WHERE setting_key = 'auto_cancel_days_before_dhana'",
                        [$autoCancelDays]
                    );
                } else {
                    $db->query(
                        "INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)",
                        ['auto_cancel_days_before_dhana', $autoCancelDays, 'Days before dhana date to auto-cancel unconfirmed bookings']
                    );
                }

                if ($autoCancelDays == 0) {
                    $successMessage = 'Auto-cancel feature disabled successfully!';
                } else {
                    $successMessage = 'Auto-cancel updated successfully! Unconfirmed bookings will be cancelled ' . $autoCancelDays . ' days before dhana date.';
                }

                // Reload the page to show updated values
                header('Location: settings.php?success=auto_cancel_updated');
                exit;
            } catch (Exception $e) {
                $errorMessage = 'Error updating auto-cancel setting: ' . $e->getMessage();
            }
            }
        }
    }

    // Handle clear all bookings (Administrator only)
    if (isset($_POST['clear_all_bookings']) && $admin['role'] === 'administrator') {
        // Check permission
        if (!hasPermission('clear_all_bookings')) {
            $errorMessage = 'You do not have permission to clear all bookings. This action is restricted to Administrators only.';
        } else {
            $confirmText = trim($_POST['confirm_text'] ?? '');

            if ($confirmText !== 'DELETE ALL BOOKINGS') {
                $errorMessage = 'Confirmation text does not match. Please type exactly: DELETE ALL BOOKINGS';
            } else {
            $dbSuccess = false;
            $bookingCount = 0;
            $deletedFiles = 0;

            try {
                // Get count before deletion
                $countResult = $db->fetchOne("SELECT COUNT(*) as count FROM bookings");
                $bookingCount = $countResult ? (int)$countResult['count'] : 0;

                // Get all receipt filenames before deletion
                $receipts = $db->fetchAll("SELECT receipt_filename FROM payment_receipts WHERE receipt_filename IS NOT NULL");

                // Start transaction
                $db->getConnection()->beginTransaction();

                // Delete all bookings (cascades to payment_receipts and annual_bookings due to foreign keys)
                $db->query("DELETE FROM bookings");

                // Reset auto-increment counters
                $db->query("ALTER TABLE bookings AUTO_INCREMENT = 1");
                $db->query("ALTER TABLE payment_receipts AUTO_INCREMENT = 1");
                $db->query("ALTER TABLE annual_bookings AUTO_INCREMENT = 1");

                // Commit transaction
                $db->getConnection()->commit();

                // Mark database operations as successful
                $dbSuccess = true;

                // Delete physical receipt files (after transaction is committed)
                foreach ($receipts as $receipt) {
                    try {
                        $filePath = '../' . UPLOAD_DIR . $receipt['receipt_filename'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                            $deletedFiles++;
                        }
                    } catch (Exception $fileError) {
                        // Log file deletion errors but don't stop the process
                        error_log("Warning: Could not delete receipt file {$receipt['receipt_filename']}: " . $fileError->getMessage());
                    }
                }

                $successMessage = "✅ All bookings cleared successfully! Deleted {$bookingCount} bookings and {$deletedFiles} receipt files. Booking ID counter reset to 1.";

                // Log the action
                error_log("CRITICAL: All bookings cleared by admin #{$adminId} ({$admin['username']}) - {$bookingCount} bookings deleted, {$deletedFiles} files removed");

                // Reload the page to show updated values
                header('Location: settings.php?success=bookings_cleared');
                exit;
            } catch (Exception $e) {
                // Rollback only if transaction is still active
                if ($db->getConnection()->inTransaction()) {
                    $db->getConnection()->rollBack();
                }
                $errorMessage = 'Error clearing bookings: ' . $e->getMessage();
                error_log("Clear all bookings failed: " . $e->getMessage());
            }
            }
        }
    }

    // Handle backup retention update (Administrator only)
    if (isset($_POST['update_backup_retention']) && $admin['role'] === 'administrator') {
        $retentionDays = (int)$_POST['backup_retention_days'];

        // Validation
        if ($retentionDays < 1 || $retentionDays > 30) {
            $errorMessage = 'Backup retention must be between 1 and 30 days.';
        } else {
            try {
                $existingSetting = $db->fetchOne(
                    "SELECT id FROM settings WHERE setting_key = 'backup_retention_days'"
                );

                if ($existingSetting) {
                    $db->query(
                        "UPDATE settings SET setting_value = ? WHERE setting_key = 'backup_retention_days'",
                        [$retentionDays]
                    );
                } else {
                    $db->query(
                        "INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)",
                        ['backup_retention_days', $retentionDays, 'Number of days to retain daily backups']
                    );
                }

                $successMessage = "Backup retention updated to {$retentionDays} days!";
                header('Location: settings.php?success=backup_retention_updated');
                exit;
            } catch (Exception $e) {
                $errorMessage = 'Error updating backup retention: ' . $e->getMessage();
            }
        }
    }

    // Handle manual backup creation (Administrator only)
    if (isset($_POST['create_backup']) && $admin['role'] === 'administrator') {
        try {
            $backupManager = new BackupManager();
            $backupType = $_POST['backup_type'] ?? 'daily';

            if ($backupType === 'database') {
                $result = $backupManager->createDatabaseBackup('daily');
            } elseif ($backupType === 'receipts') {
                $result = $backupManager->createReceiptsBackup();
            } elseif ($backupType === 'monthly') {
                $result = $backupManager->createDatabaseBackup('monthly');
            } else {
                $result = ['success' => false, 'message' => 'Invalid backup type'];
            }

            if ($result['success']) {
                $successMessage = $result['message'];
            } else {
                $errorMessage = $result['message'];
            }

        } catch (Exception $e) {
            $errorMessage = 'Backup failed: ' . $e->getMessage();
        }
    }

    // Handle role permissions update (Administrator only)
    if (isset($_POST['update_permissions']) && $admin['role'] === 'administrator') {
        try {
            // Get all submitted permissions
            $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

            // Get all unique permissions (permission_name and description pairs)
            // Group by permission_name and take the first description found
            $allPermissions = $db->fetchAll("
                SELECT permission_name, MIN(permission_description) as permission_description
                FROM role_permissions
                GROUP BY permission_name
                ORDER BY permission_name
            ");

            // Start transaction
            $db->getConnection()->beginTransaction();

            // Clear existing permissions for all roles
            $db->query("DELETE FROM role_permissions");

            // Build a set of unique permission entries to insert
            $permissionsToInsert = [];

            foreach ($allPermissions as $perm) {
                $permName = $perm['permission_name'];
                $permDesc = $perm['permission_description'];

                // Check which roles have this permission enabled
                $roles = ['donor', 'supervisor', 'editor', 'administrator'];
                foreach ($roles as $role) {
                    $key = $role . '_' . $permName;
                    if (isset($permissions[$key])) {
                        // Create unique key to prevent duplicates
                        $uniqueKey = $role . '|' . $permName;
                        if (!isset($permissionsToInsert[$uniqueKey])) {
                            $permissionsToInsert[$uniqueKey] = [
                                'role' => $role,
                                'permission' => $permName,
                                'description' => $permDesc
                            ];
                        }
                    }
                }
            }

            // Insert all unique permissions
            foreach ($permissionsToInsert as $perm) {
                $db->query(
                    "INSERT INTO role_permissions (role_name, permission_name, permission_description) VALUES (?, ?, ?)",
                    [$perm['role'], $perm['permission'], $perm['description']]
                );
            }

            // Commit transaction
            $db->getConnection()->commit();

            $successMessage = 'Role permissions updated successfully!';
            header('Location: settings.php?success=permissions_updated');
            exit;
        } catch (Exception $e) {
            // Rollback on error
            if ($db->getConnection()->inTransaction()) {
                $db->getConnection()->rollBack();
            }
            $errorMessage = 'Error updating permissions: ' . $e->getMessage();
        }
    }
}

// Check for success messages from redirects
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'annual_years_updated') {
        $successMessage = 'Annual booking years updated successfully!';
    } elseif ($_GET['success'] === 'auto_cancel_updated') {
        $successMessage = 'Auto-cancel setting updated successfully!';
    } elseif ($_GET['success'] === 'bookings_cleared') {
        $successMessage = 'All bookings and receipts cleared successfully!';
    } elseif ($_GET['success'] === 'backup_retention_updated') {
        $successMessage = 'Backup retention setting updated successfully!';
    }
}

// Get system settings (for administrators)
$systemSettings = [];
$rolePermissions = [];
if ($admin['role'] === 'administrator') {
    $annualYearsSetting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'annual_booking_years'"
    );
    $autoCancelDaysSetting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'auto_cancel_days_before_dhana'"
    );
    $lastCleanupSetting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'last_auto_cancel_cleanup'"
    );

    $systemSettings['annual_booking_years'] = $annualYearsSetting ? $annualYearsSetting['setting_value'] : '10';
    $systemSettings['auto_cancel_days_before'] = $autoCancelDaysSetting ? $autoCancelDaysSetting['setting_value'] : '30';
    $systemSettings['last_auto_cancel_cleanup'] = $lastCleanupSetting ? $lastCleanupSetting['setting_value'] : 'Never';

    // Get backup retention setting
    $backupRetentionSetting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'backup_retention_days'"
    );
    $systemSettings['backup_retention_days'] = $backupRetentionSetting ? $backupRetentionSetting['setting_value'] : '30';

    // Get backup statistics
    $backupManager = new BackupManager();
    $backupStats = $backupManager->getBackupStats();

    // Get all permissions grouped by role
    $allPermissions = $db->fetchAll("
        SELECT DISTINCT permission_name, permission_description
        FROM role_permissions
        ORDER BY permission_name
    ");

    // Get current permissions for each role
    $currentPermissions = $db->fetchAll("
        SELECT role_name, permission_name
        FROM role_permissions
    ");

    // Organize permissions by role
    $rolePermissions = [
        'donor' => [],
        'supervisor' => [],
        'editor' => [],
        'administrator' => []
    ];

    foreach ($currentPermissions as $perm) {
        $rolePermissions[$perm['role_name']][] = $perm['permission_name'];
    }
}

// Check if phone column exists, if not add it
try {
    $columns = $db->fetchAll("SHOW COLUMNS FROM admin_users LIKE 'phone'");
    if (empty($columns)) {
        $db->query("ALTER TABLE admin_users ADD COLUMN phone VARCHAR(20) NULL AFTER email");
    }
} catch (Exception $e) {
    // Column might already exist or there's a permission issue
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Dāna Booking System</title>
    <?php include '../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-panel">
        <!-- Admin Header -->
        <div class="admin-header">
            <div class="admin-nav">
                <h1><i class="fas fa-cog"></i> Admin Settings</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="index.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
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
                <div class="admin-section">
                <?php if ($successMessage): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="switchTab('account')">
                        <i class="fas fa-info-circle"></i> Account Info
                    </button>
                    <button class="tab-btn" onclick="switchTab('profile')">
                        <i class="fas fa-user"></i> Profile
                    </button>
                    <button class="tab-btn" onclick="switchTab('password')">
                        <i class="fas fa-lock"></i> Password
                    </button>
                    <?php if ($admin['role'] === 'administrator'): ?>
                    <button class="tab-btn" onclick="switchTab('system')">
                        <i class="fas fa-cogs"></i> System Settings
                    </button>
                    <button class="tab-btn" onclick="switchTab('backup')">
                        <i class="fas fa-database"></i> Backups
                    </button>
                    <button class="tab-btn" onclick="switchTab('permissions')">
                        <i class="fas fa-shield-alt"></i> Permissions
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Tab Content -->
                <div class="tab-content active" id="account-tab">
                <div class="settings-container">
                    <!-- Account Information -->
                    <div class="settings-section">
                        <h2><i class="fas fa-info-circle"></i> Account Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Account Created</label>
                                <span><?php echo date('F j, Y', strtotime($admin['created_at'])); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Role</label>
                                <span><?php echo ucfirst($admin['role']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Status</label>
                                <span class="status-active">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Last Login</label>
                                <span><?php echo date('F j, Y g:i A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Profile Tab -->
                <div class="tab-content" id="profile-tab">
                <div class="settings-container">
                    <!-- Profile Settings -->
                    <div class="settings-section">
                        <h2><i class="fas fa-user"></i> Profile Settings</h2>
                        <form method="POST" class="settings-form">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username"
                                       value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email"
                                       value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>"
                                       placeholder="Enter phone number">
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>
                </div>

                <!-- Password Tab -->
                <div class="tab-content" id="password-tab">
                <div class="settings-container">
                    <!-- Password Change -->
                    <div class="settings-section">
                        <h2><i class="fas fa-lock"></i> Change Password</h2>
                        <form method="POST" class="settings-form">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password"
                                       minlength="6" required>
                                <small>Minimum 6 characters</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                       minlength="6" required>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-secondary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
                </div>

                <?php if ($admin['role'] === 'administrator'): ?>
                <!-- System Settings Tab -->
                <div class="tab-content" id="system-tab">
                <div class="settings-container">
                        <!-- Annual Booking Configuration -->
                        <div class="settings-section">
                            <h3><i class="fas fa-calendar-alt"></i> Annual Booking Configuration</h3>
                            <form method="POST" class="settings-form">
                                <div class="form-group">
                                    <label for="annual_booking_years">
                                        Number of Years for Annual Bookings
                                        <i class="fas fa-info-circle" title="How many years ahead to create when user selects 'Annual Event'"></i>
                                    </label>
                                    <input type="number" id="annual_booking_years" name="annual_booking_years"
                                           value="<?php echo htmlspecialchars($systemSettings['annual_booking_years']); ?>"
                                           min="1" max="50" required>
                                    <small>Current: <?php echo $systemSettings['annual_booking_years']; ?> years (Range: 1-50 years)</small>
                                    <div class="help-text">
                                        <i class="fas fa-lightbulb"></i>
                                        When a donor selects "Annual Event", the system will automatically create bookings for the next
                                        <strong><?php echo $systemSettings['annual_booking_years']; ?> years</strong> on the same date.
                                    </div>
                                </div>

                                <button type="submit" name="update_annual_years" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Annual Booking Settings
                                </button>
                            </form>
                        </div>

                        <!-- Auto-Cancel Unconfirmed Bookings -->
                        <div class="settings-section">
                            <h3><i class="fas fa-clock"></i> Auto-Cancel Unconfirmed Bookings</h3>
                            <form method="POST" class="settings-form">
                                <div class="form-group">
                                    <label for="auto_cancel_days_before">
                                        Days Before Dhana Date to Auto-Cancel
                                        <i class="fas fa-info-circle" title="Unconfirmed bookings will be cancelled X days before the dhana date"></i>
                                    </label>
                                    <input type="number" id="auto_cancel_days_before" name="auto_cancel_days_before"
                                           value="<?php echo htmlspecialchars($systemSettings['auto_cancel_days_before']); ?>"
                                           min="0" max="365" required>
                                    <small>Current: <?php echo $systemSettings['auto_cancel_days_before']; ?> days before dhana date | Set to 0 to disable auto-cancel</small>
                                    <div class="help-text">
                                        <i class="fas fa-lightbulb"></i>
                                        Bookings that are NOT confirmed by admin will be automatically cancelled <strong><?php echo $systemSettings['auto_cancel_days_before']; ?> days before the dhana date</strong>.
                                        This ensures dates don't stay blocked for unconfirmed bookings.
                                    </div>
                                    <div class="help-text" style="background: #fff3cd; padding: 12px; border-radius: 6px; border-left: 4px solid #ffc107; margin-top: 10px;">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Example:</strong> If set to 30 days, a booking for March 15th will be auto-cancelled on February 13th at midnight if not confirmed by admin.
                                    </div>
                                </div>

                                <div class="info-box" style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 15px 0;">
                                    <h4 style="margin: 0 0 10px 0; color: #1976d2;">
                                        <i class="fas fa-robot"></i> Automation Status
                                    </h4>
                                    <p style="margin: 5px 0;">
                                        <strong>Last Auto-Cleanup:</strong>
                                        <?php echo $systemSettings['last_auto_cancel_cleanup'] ?: 'Never'; ?>
                                    </p>
                                    <p style="margin: 5px 0; font-size: 0.9em; color: #666;">
                                        <i class="fas fa-terminal"></i>
                                        To enable automatic cleanup, set up a cron job to run daily at midnight (12:00 AM):
                                        <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">
                                            /cron/auto-cancel-before-dhana.php
                                        </code>
                                    </p>
                                    <p style="margin: 5px 0; font-size: 0.9em; color: #666;">
                                        <i class="fas fa-clock"></i>
                                        Recommended: Run daily at 12:00 AM (0 0 * * *)
                                    </p>
                                </div>

                                <button type="submit" name="update_auto_cancel" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Auto-Cancel Settings
                                </button>
                            </form>
                        </div>
                </div>
                </div>

                <!-- Backup Tab -->
                <div class="tab-content" id="backup-tab">
                <div class="settings-container">
                        <!-- Backup Management -->
                        <div class="settings-section">
                            <h3><i class="fas fa-database"></i> Backup Management</h3>

                            <!-- Backup Statistics -->
                            <div class="info-box" style="background: #e8f5e9; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #4caf50;">
                                <h4 style="margin: 0 0 15px 0; color: #2e7d32;">
                                    <i class="fas fa-chart-bar"></i> Current Backup Status
                                </h4>

                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                                    <!-- Daily Backups -->
                                    <div style="background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <div style="font-size: 0.85em; color: #666; margin-bottom: 5px;">
                                            <i class="fas fa-calendar-day"></i> Daily Backups
                                        </div>
                                        <div style="font-size: 1.8em; font-weight: bold; color: #1976d2;">
                                            <?php echo $backupStats['daily']['count']; ?>
                                        </div>
                                        <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                                            <?php echo BackupManager::formatBytes($backupStats['daily']['total_size']); ?>
                                        </div>
                                        <?php if ($backupStats['daily']['last_backup']): ?>
                                        <div style="font-size: 0.75em; color: #999; margin-top: 5px;">
                                            Last: <?php echo date('M d, H:i', strtotime($backupStats['daily']['last_backup'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Monthly Backups -->
                                    <div style="background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <div style="font-size: 0.85em; color: #666; margin-bottom: 5px;">
                                            <i class="fas fa-calendar-alt"></i> Monthly Backups
                                        </div>
                                        <div style="font-size: 1.8em; font-weight: bold; color: #388e3c;">
                                            <?php echo $backupStats['monthly']['count']; ?>
                                        </div>
                                        <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                                            <?php echo BackupManager::formatBytes($backupStats['monthly']['total_size']); ?>
                                        </div>
                                        <?php if ($backupStats['monthly']['last_backup']): ?>
                                        <div style="font-size: 0.75em; color: #999; margin-top: 5px;">
                                            Last: <?php echo date('M d, H:i', strtotime($backupStats['monthly']['last_backup'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Receipt Backups -->
                                    <div style="background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <div style="font-size: 0.85em; color: #666; margin-bottom: 5px;">
                                            <i class="fas fa-file-archive"></i> Receipt Backups
                                        </div>
                                        <div style="font-size: 1.8em; font-weight: bold; color: #f57c00;">
                                            <?php echo $backupStats['receipts']['count']; ?>
                                        </div>
                                        <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                                            <?php echo BackupManager::formatBytes($backupStats['receipts']['total_size']); ?>
                                        </div>
                                    </div>

                                    <!-- Retention Setting -->
                                    <div style="background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <div style="font-size: 0.85em; color: #666; margin-bottom: 5px;">
                                            <i class="fas fa-history"></i> Retention Period
                                        </div>
                                        <div style="font-size: 1.8em; font-weight: bold; color: #7b1fa2;">
                                            <?php echo $backupStats['retention_days']; ?>
                                        </div>
                                        <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                                            days
                                        </div>
                                        <div style="font-size: 0.75em; color: #999; margin-top: 5px;">
                                            Daily rotation
                                        </div>
                                    </div>
                                </div>

                                <div style="font-size: 0.85em; color: #666; margin-top: 10px;">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Daily backups:</strong> Automatically rotated, keeping last <?php echo $backupStats['retention_days']; ?> days<br>
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Monthly backups:</strong> Permanent archives, never auto-deleted
                                </div>
                            </div>

                            <!-- Backup Retention Settings -->
                            <form method="POST" class="settings-form" style="margin-bottom: 20px;">
                                <div class="form-group">
                                    <label for="backup_retention_days">
                                        Daily Backup Retention (Days)
                                        <i class="fas fa-info-circle" title="Number of daily backups to keep before rotation"></i>
                                    </label>
                                    <input type="number" id="backup_retention_days" name="backup_retention_days"
                                           value="<?php echo htmlspecialchars($systemSettings['backup_retention_days']); ?>"
                                           min="1" max="30" required>
                                    <small>Current: <?php echo $systemSettings['backup_retention_days']; ?> days | Maximum: 30 days</small>
                                    <div class="help-text">
                                        <i class="fas fa-lightbulb"></i>
                                        Daily backups are automatically rotated. When a new backup is created and the limit is reached,
                                        the oldest backup is deleted. <strong>Monthly backups are permanent and never auto-deleted.</strong>
                                    </div>
                                </div>

                                <button type="submit" name="update_backup_retention" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Retention Setting
                                </button>
                            </form>

                            <!-- Manual Backup Creation -->
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
                                <h4 style="margin: 0 0 10px 0; color: #856404;">
                                    <i class="fas fa-hand-pointer"></i> Manual Backup Creation
                                </h4>
                                <p style="margin: 0 0 15px 0; font-size: 0.9em; color: #856404;">
                                    Create backups manually at any time. Automated backups run via cron jobs.
                                </p>

                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="backup_type" value="database">
                                        <button type="submit" name="create_backup" class="btn btn-primary" style="background: #1976d2;">
                                            <i class="fas fa-database"></i> Create Daily DB Backup
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="backup_type" value="monthly">
                                        <button type="submit" name="create_backup" class="btn btn-primary" style="background: #388e3c;">
                                            <i class="fas fa-calendar-alt"></i> Create Monthly DB Backup
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="backup_type" value="receipts">
                                        <button type="submit" name="create_backup" class="btn btn-primary" style="background: #f57c00;">
                                            <i class="fas fa-file-archive"></i> Backup Receipts
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Cron Job Setup Instructions -->
                            <div class="info-box" style="background: #e3f2fd; padding: 15px; border-radius: 8px;">
                                <h4 style="margin: 0 0 10px 0; color: #1976d2;">
                                    <i class="fas fa-terminal"></i> Automated Backup Setup (Windows Task Scheduler)
                                </h4>
                                <p style="margin: 5px 0; font-size: 0.9em; color: #666;">
                                    <strong>Daily Backup (Recommended: 2:00 AM):</strong>
                                </p>
                                <code style="display: block; background: #f5f5f5; padding: 10px; border-radius: 4px; margin: 5px 0; font-size: 0.85em;">
                                    /cron/daily-backup.php
                                </code>

                                <p style="margin: 15px 0 5px 0; font-size: 0.9em; color: #666;">
                                    <strong>Monthly Backup (Recommended: 1st of month at 3:00 AM):</strong>
                                </p>
                                <code style="display: block; background: #f5f5f5; padding: 10px; border-radius: 4px; margin: 5px 0; font-size: 0.85em;">
                                    /cron/monthly-backup.php
                                </code>
                            </div>
                        </div>

                        <!-- Clear All Bookings & Receipts -->
                        <div class="settings-section">
                            <h3><i class="fas fa-trash-alt"></i> Clear All Bookings & Receipts</h3>

                            <?php
                            // Get current booking statistics
                            $countResult = $db->fetchOne("SELECT COUNT(*) as count FROM bookings");
                            $bookingCount = $countResult ? (int)$countResult['count'] : 0;

                            $receiptCountResult = $db->fetchOne("SELECT COUNT(*) as count FROM payment_receipts WHERE receipt_filename IS NOT NULL");
                            $receiptCount = $receiptCountResult ? (int)$receiptCountResult['count'] : 0;
                            ?>

                            <div class="info-box" style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0;">
                                <h4 style="margin: 0 0 10px 0; color: #856404;">
                                    <i class="fas fa-database"></i> Current Database Status
                                </h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                                    <div>
                                        <p style="margin: 5px 0;">
                                            <strong>Total Bookings:</strong> <?php echo $bookingCount; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p style="margin: 5px 0;">
                                            <strong>Total Receipt Files:</strong> <?php echo $receiptCount; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="help-text" style="background: #ffebee; padding: 15px; border-radius: 8px; border-left: 4px solid #f44336; margin-top: 15px;">
                                <h4 style="margin: 0 0 10px 0; color: #c62828;">
                                    <i class="fas fa-exclamation-triangle"></i> ⚠️ DANGER ZONE - PERMANENT DELETION
                                </h4>
                                <p style="margin: 5px 0; font-size: 0.95em; color: #d32f2f;">
                                    <strong>This action will PERMANENTLY delete:</strong>
                                </p>
                                <ul style="margin: 10px 0; padding-left: 20px; font-size: 0.9em; color: #c62828;">
                                    <li>✗ ALL bookings from database (past and future)</li>
                                    <li>✗ ALL payment receipt records</li>
                                    <li>✗ ALL annual booking records</li>
                                    <li>✗ ALL physical receipt files from server</li>
                                    <li>✓ Reset booking ID counter to 1</li>
                                </ul>
                                <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #d32f2f;">
                                    <strong>⚠️ WARNING:</strong> This action CANNOT be undone! All booking data will be lost forever!
                                </p>
                            </div>

                            <div class="help-text" style="background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3; margin-top: 15px;">
                                <h4 style="margin: 0 0 10px 0; color: #1976d2;">
                                    <i class="fas fa-info-circle"></i> When to Use This
                                </h4>
                                <ul style="margin: 10px 0; padding-left: 20px; font-size: 0.9em;">
                                    <li>Starting fresh with a clean database</li>
                                    <li>After testing phase before going live</li>
                                    <li>Clearing all test/demo bookings at once</li>
                                </ul>
                            </div>

                            <form method="POST" class="settings-form" onsubmit="return confirmClearAllBookings();">
                                <div class="form-group" style="margin-top: 20px;">
                                    <label for="confirm_text" style="color: #d32f2f; font-weight: bold;">
                                        Type "DELETE ALL BOOKINGS" to confirm
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </label>
                                    <input type="text" id="confirm_text" name="confirm_text"
                                           placeholder="Type: DELETE ALL BOOKINGS"
                                           style="border: 2px solid #f44336; font-family: monospace;"
                                           required>
                                    <small style="color: #d32f2f;">You must type exactly: <strong>DELETE ALL BOOKINGS</strong> (case-sensitive)</small>
                                </div>

                                <button type="submit" name="clear_all_bookings" class="btn btn-danger" style="margin-top: 15px; background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);">
                                    <i class="fas fa-trash-alt"></i> Clear All Bookings & Receipts
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Permissions Tab -->
                <div class="tab-content" id="permissions-tab">
                <div class="settings-container">
                    <div class="settings-section">
                        <h2><i class="fas fa-user-shield"></i> Role Permissions Management</h2>

                        <div class="help-text" style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Customize permissions for each user role.</strong> Check the boxes to grant permissions.
                            Default settings are pre-configured for optimal security. Changes affect all users with that role immediately.
                        </div>

                        <form method="POST" class="settings-form" onsubmit="return confirm('Are you sure you want to update role permissions? This will affect all users with these roles immediately.');">
                        <div class="permissions-table-container" style="overflow-x: auto; margin: 20px 0;">
                            <table class="permissions-table" style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <tr>
                                        <th style="padding: 15px; text-align: left; font-weight: 600; min-width: 250px;">
                                            <i class="fas fa-key"></i> Permission
                                        </th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; width: 120px;">
                                            <i class="fas fa-user"></i><br>Donor
                                        </th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; width: 120px;">
                                            <i class="fas fa-eye"></i><br>Supervisor
                                        </th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; width: 120px;">
                                            <i class="fas fa-user-edit"></i><br>Editor
                                        </th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; width: 120px;">
                                            <i class="fas fa-user-shield"></i><br>Administrator
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rowIndex = 0;
                                    foreach ($allPermissions as $permission):
                                        $rowIndex++;
                                        $bgColor = $rowIndex % 2 == 0 ? '#f8f9fa' : 'white';
                                    ?>
                                        <tr style="background: <?php echo $bgColor; ?>; border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 12px 15px;">
                                                <strong style="color: #333; display: block; margin-bottom: 5px;">
                                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $permission['permission_name']))); ?>
                                                </strong>
                                                <small style="color: #666; font-size: 0.85em;">
                                                    <?php echo htmlspecialchars($permission['permission_description']); ?>
                                                </small>
                                            </td>

                                            <!-- Donor -->
                                            <td style="padding: 12px; text-align: center;">
                                                <label class="permission-checkbox">
                                                    <input type="checkbox"
                                                           name="permissions[donor_<?php echo $permission['permission_name']; ?>]"
                                                           value="1"
                                                           <?php echo in_array($permission['permission_name'], $rolePermissions['donor']) ? 'checked' : ''; ?>
                                                           style="width: 20px; height: 20px; cursor: pointer;">
                                                </label>
                                            </td>

                                            <!-- Supervisor -->
                                            <td style="padding: 12px; text-align: center;">
                                                <label class="permission-checkbox">
                                                    <input type="checkbox"
                                                           name="permissions[supervisor_<?php echo $permission['permission_name']; ?>]"
                                                           value="1"
                                                           <?php echo in_array($permission['permission_name'], $rolePermissions['supervisor']) ? 'checked' : ''; ?>
                                                           style="width: 20px; height: 20px; cursor: pointer;">
                                                </label>
                                            </td>

                                            <!-- Editor -->
                                            <td style="padding: 12px; text-align: center;">
                                                <label class="permission-checkbox">
                                                    <input type="checkbox"
                                                           name="permissions[editor_<?php echo $permission['permission_name']; ?>]"
                                                           value="1"
                                                           <?php echo in_array($permission['permission_name'], $rolePermissions['editor']) ? 'checked' : ''; ?>
                                                           style="width: 20px; height: 20px; cursor: pointer;">
                                                </label>
                                            </td>

                                            <!-- Administrator -->
                                            <td style="padding: 12px; text-align: center;">
                                                <label class="permission-checkbox">
                                                    <input type="checkbox"
                                                           name="permissions[administrator_<?php echo $permission['permission_name']; ?>]"
                                                           value="1"
                                                           <?php echo in_array($permission['permission_name'], $rolePermissions['administrator']) ? 'checked' : ''; ?>
                                                           style="width: 20px; height: 20px; cursor: pointer;">
                                                </label>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                            <h4 style="margin: 0 0 10px 0; color: #1976d2;">
                                <i class="fas fa-shield-alt"></i> Role Descriptions
                            </h4>
                            <ul style="margin: 0; padding-left: 20px; color: #666;">
                                <li><strong>Donor:</strong> Regular users who can create bookings and check availability</li>
                                <li><strong>Supervisor:</strong> View-only access to all bookings and user data (no editing)</li>
                                <li><strong>Editor:</strong> Can view and edit bookings, manage user data, accept/reject requests</li>
                                <li><strong>Administrator:</strong> Full system access including user management and system settings</li>
                            </ul>
                        </div>

                        <div style="display: flex; gap: 15px; align-items: center;">
                            <button type="submit" name="update_permissions" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Permission Changes
                            </button>
                            <button type="button" onclick="location.reload();" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset to Current
                            </button>
                        </div>
                    </form>
                    </div>
                </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

    <style>
        /* Tab Navigation Styles */
        .settings-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 0.95rem;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: #d4822a;
            background: rgba(212, 130, 42, 0.1);
        }

        .tab-btn.active {
            color: #d4822a;
            border-bottom-color: #d4822a;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

    <style>
        /* Permissions Table Styling */
        .permissions-table-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .permissions-table tr:hover {
            background: #e3f2fd !important;
            transition: background 0.2s ease;
        }

        .permission-checkbox {
            display: inline-block;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .permission-checkbox:hover {
            transform: scale(1.1);
        }

        .permission-checkbox input[type="checkbox"]:checked {
            accent-color: #28a745;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
    </style>

    <script>
        // Tab Switching Function
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');

            // Add active class to clicked button
            event.target.closest('.tab-btn').classList.add('active');
        }

        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const confirmPasswordField = document.getElementById('confirm_password');
            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', function() {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = this.value;

                    if (newPassword !== confirmPassword) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 300);
            });
        }, 5000);

        // Add "Select All" functionality for permissions table
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('.permissions-table');
            if (table) {
                // Add click handler to column headers for "select all in column"
                const headers = table.querySelectorAll('thead th');
                headers.forEach((header, index) => {
                    if (index > 0) { // Skip first column (permission name)
                        header.style.cursor = 'pointer';
                        header.title = 'Double-click to toggle all in this column';

                        header.addEventListener('dblclick', function() {
                            const checkboxes = table.querySelectorAll(`tbody tr td:nth-child(${index + 1}) input[type="checkbox"]`);
                            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

                            checkboxes.forEach(cb => {
                                cb.checked = !allChecked;
                            });
                        });
                    }
                });
            }
        });

        // Clear All Bookings Confirmation
        function confirmClearAllBookings() {
            const confirmText = document.getElementById('confirm_text').value.trim();
            const totalBookings = <?php echo $bookingCount ?? 0; ?>;
            const totalReceipts = <?php echo $receiptCount ?? 0; ?>;

            // Check if confirmation text matches
            if (confirmText !== 'DELETE ALL BOOKINGS') {
                alert('❌ Confirmation text does not match!\n\nYou must type exactly: DELETE ALL BOOKINGS');
                return false;
            }

            let message = '🚨 CRITICAL WARNING - PERMANENT DELETION 🚨\n\n';
            message += '⚠️ You are about to PERMANENTLY DELETE:\n\n';
            message += `   ✗ ${totalBookings} booking(s) from database\n`;
            message += `   ✗ ${totalReceipts} receipt file(s) from server\n`;
            message += '   ✗ ALL annual booking records\n';
            message += '   ✗ ALL payment receipt records\n\n';
            message += '✓ Booking ID counter will be reset to 1\n\n';
            message += '⚠️ THIS ACTION CANNOT BE UNDONE!\n';
            message += '⚠️ ALL DATA WILL BE LOST FOREVER!\n\n';
            message += 'Are you ABSOLUTELY SURE you want to proceed?';

            if (!confirm(message)) {
                return false;
            }

            // Double confirmation
            const doubleConfirm = confirm('⚠️ FINAL CONFIRMATION\n\nThis is your last chance to cancel.\n\nClick OK to DELETE ALL BOOKINGS permanently.\nClick Cancel to abort.');

            return doubleConfirm;
        }
    </script>
</body>
</html>
