<?php
/**
 * User Settings Page for Dhana Booking System
 */

require_once 'includes/auth.php';
requireLogin();

$auth = getAuth();
$sessionUser = $auth->getCurrentUser();
$db = getDB();

// Get complete user data from database
$user = $db->fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$sessionUser['id']]
);

$successMessage = '';
$errorMessage = '';

// Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    $auth->logout();
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $contactNumber = trim($_POST['contact_number']);
        
        // Validation
        if (empty($firstName) || empty($lastName) || empty($email) || empty($contactNumber)) {
            $errorMessage = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } else {
            try {
                // Check if email already exists for other users
                $existingUser = $db->fetchOne(
                    "SELECT id FROM users WHERE email = ? AND id != ?",
                    [$email, $user['id']]
                );
                
                if ($existingUser) {
                    $errorMessage = 'Email address already exists.';
                } else {
                    // Update profile
                    $db->query(
                        "UPDATE users SET first_name = ?, last_name = ?, email = ?, contact_number = ? WHERE id = ?",
                        [$firstName, $lastName, $email, $contactNumber, $user['id']]
                    );
                    
                    $successMessage = 'Profile updated successfully!';

                    // Refresh user data from database
                    $user = $db->fetchOne(
                        "SELECT * FROM users WHERE id = ?",
                        [$sessionUser['id']]
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
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $errorMessage = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $errorMessage = 'New password must be at least 6 characters long.';
        } else {
            try {
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->query(
                    "UPDATE users SET password_hash = ? WHERE id = ?",
                    [$newPasswordHash, $user['id']]
                );
                
                $successMessage = 'Password changed successfully!';
            } catch (Exception $e) {
                $errorMessage = 'Error changing password: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Dāna Booking System</title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- Top Welcome Section -->
        <div class="dashboard-header">
            <div class="user-info">
                <p>Hello, <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></p>
                <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="welcome-content">
                <h1><i class="fas fa-cog"></i> Account Settings</h1>
            </div>
            <div class="user-actions">
                <a href="dashboard.php" class="settings-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <form method="POST" class="logout-form">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="settings-main">
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

            <div class="settings-container">
                <!-- Left Column: Account Information -->
                <div class="settings-section">
                    <h2><i class="fas fa-info-circle"></i> Account Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Account Created</label>
                            <span><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Account Status</label>
                            <span class="status-active">
                                <i class="fas fa-check-circle"></i> Active
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Total Bookings</label>
                            <span>
                                <?php 
                                $bookingCount = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?", [$user['id']])['count'];
                                echo $bookingCount;
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Last Updated</label>
                            <span><?php echo date('F j, Y g:i A', strtotime($user['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Middle Column: Profile Settings -->
                <div class="settings-section">
                    <h2><i class="fas fa-user"></i> Profile Settings</h2>
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" 
                                   value="<?php echo htmlspecialchars($user['contact_number']); ?>" required>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>

                <!-- Right Column: Password Change -->
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
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
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
    </script>
</body>
</html>
