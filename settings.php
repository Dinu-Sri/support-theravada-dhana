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
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request');
    }
    $auth->logout();
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = t('Your session expired. Please refresh and try again.', 'ඔබගේ සැසිය කල් ඉකුත් විය. පිටුව නැවුම් කර නැවත උත්සාහ කරන්න.');
    } elseif (isset($_POST['update_profile'])) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $contactNumber = trim($_POST['contact_number'] ?? '');
        
        // Validation
        if (empty($firstName) || empty($lastName) || empty($email) || empty($contactNumber)) {
            $errorMessage = t('All fields are required.', 'සියලු ක්ෂේත්‍ර අවශ්‍යයි.');
        } elseif (strlen($firstName) > 50 || strlen($lastName) > 50 || strlen($email) > 100 || strlen($contactNumber) > 20) {
            $errorMessage = t('One or more fields are too long.', 'එක් ක්ෂේත්‍රයක් හෝ කිහිපයක් දිග වැඩිය.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = t('Please enter a valid email address.', 'වලංගු විද්‍යුත් තැපැල් ලිපිනයක් ඇතුළත් කරන්න.');
        } else {
            try {
                // Check if email already exists for other users
                $existingUser = $db->fetchOne(
                    "SELECT id FROM users WHERE email = ? AND id != ?",
                    [$email, $user['id']]
                );
                
                if ($existingUser) {
                    $errorMessage = t('Email address already exists.', 'මෙම විද්‍යුත් තැපෑල දැනටමත් ඇත.');
                } else {
                    // Update profile
                    $db->query(
                        "UPDATE users SET first_name = ?, last_name = ?, email = ?, contact_number = ? WHERE id = ?",
                        [$firstName, $lastName, $email, $contactNumber, $user['id']]
                    );
                    
                    $successMessage = t('Profile updated successfully!', 'පැතිකඩ සාර්ථකව යාවත්කාලීන කළා.');

                    // Refresh user data from database
                    $user = $db->fetchOne(
                        "SELECT * FROM users WHERE id = ?",
                        [$sessionUser['id']]
                    );
                }
            } catch (Exception $e) {
                error_log('Profile update error: ' . $e->getMessage());
                $errorMessage = t('Unable to update your profile right now. Please try again.', 'දැන් පැතිකඩ යාවත්කාලීන කළ නොහැක. කරුණාකර නැවත උත්සාහ කරන්න.');
            }
        }
    }
    
    elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = t('All password fields are required.', 'මුරපද ක්ෂේත්‍ර සියල්ල අවශ්‍යයි.');
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $errorMessage = t('Current password is incorrect.', 'වත්මන් මුරපදය වැරදිය.');
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = t('New passwords do not match.', 'නව මුරපද නොගැලපේ.');
        } elseif (strlen($newPassword) < 6) {
            $errorMessage = t('New password must be at least 6 characters long.', 'නව මුරපදය අවම වශයෙන් අකුරු 6 ක් විය යුතුය.');
        } else {
            try {
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->query(
                    "UPDATE users SET password_hash = ? WHERE id = ?",
                    [$newPasswordHash, $user['id']]
                );
                
                $successMessage = t('Password changed successfully!', 'මුරපදය සාර්ථකව වෙනස් කළා.');
                $_SESSION['password_version'] = $newPasswordHash;
                session_regenerate_id(true);
            } catch (Exception $e) {
                error_log('Password change error: ' . $e->getMessage());
                $errorMessage = t('Unable to change your password right now. Please try again.', 'දැන් මුරපදය වෙනස් කළ නොහැක. කරුණාකර නැවත උත්සාහ කරන්න.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<?php echo ui_html_open(); ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo te('Account Settings - Dāna Booking System', 'ගිණුම් සැකසුම් - දාන වෙන්කිරීමේ පද්ධතිය'); ?></title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pro-ui.css?v=20260915">
</head>
<body>
    <div class="dashboard">
        <!-- Top Welcome Section -->
        <div class="dashboard-header">
            <div class="user-info">
                <p><?php echo te('Hello,', 'ආයුබෝවන්,'); ?> <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></p>
                <p><?php echo te('Email:', 'විද්‍යුත් තැපෑල:'); ?> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="welcome-content">
                <h1><i class="fas fa-cog"></i> <?php echo te('Account Settings', 'ගිණුම් සැකසුම්'); ?></h1>
            </div>
            <div class="user-actions">
                <?php ui_lang_switch(); ?>
                <a href="dashboard.php" class="settings-btn">
                    <i class="fas fa-arrow-left"></i> <?php echo te('Back to Dashboard', 'ප්‍රධාන පුවරුවට'); ?>
                </a>
                <form method="POST" class="logout-form">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> <?php echo te('Logout', 'පිටවීම'); ?>
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
                    <h2><i class="fas fa-info-circle"></i> <?php echo te('Account Information', 'ගිණුම් තොරතුරු'); ?></h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label><?php echo te('Account Created', 'ගිණුම සාදන ලද්දේ'); ?></label>
                            <span><?php echo htmlspecialchars(ui_format_date($user['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php echo te('Account Status', 'ගිණුමේ තත්වය'); ?></label>
                            <span class="status-active">
                                <i class="fas fa-check-circle"></i> <?php echo te('Active', 'සක්‍රිය'); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label><?php echo te('Total Bookings', 'මුළු වෙන්කිරීම්'); ?></label>
                            <span>
                                <?php 
                                $bookingCount = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?", [$user['id']])['count'];
                                echo $bookingCount;
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label><?php echo te('Profile Last Updated', 'පැතිකඩ අවසන් වරට යාවත්කාලීන කළේ'); ?></label>
                            <span><?php echo htmlspecialchars(ui_format_date($user['updated_at'], 'datetime')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Middle Column: Profile Settings -->
                <div class="settings-section">
                    <h2><i class="fas fa-user"></i> <?php echo te('Profile Settings', 'පැතිකඩ සැකසුම්'); ?></h2>
                    <form method="POST" class="settings-form">
                        <?php echo csrfInput(); ?>
                        <div class="form-group">
                            <label for="first_name"><?php echo te('First Name', 'මුල් නම'); ?></label>
                            <input type="text" id="first_name" name="first_name" autocomplete="given-name" maxlength="50"
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name"><?php echo te('Last Name', 'අවසන් නම'); ?></label>
                            <input type="text" id="last_name" name="last_name" autocomplete="family-name" maxlength="50"
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email"><?php echo te('Email Address', 'විද්‍යුත් තැපැල් ලිපිනය'); ?></label>
                            <input type="email" id="email" name="email" autocomplete="email" maxlength="100"
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact_number"><?php echo te('Contact Number', 'දුරකථන අංකය'); ?></label>
                            <input type="tel" id="contact_number" name="contact_number" autocomplete="tel" maxlength="20"
                                   value="<?php echo htmlspecialchars($user['contact_number']); ?>" required>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo te('Update Profile', 'පැතිකඩ යාවත්කාලීන කරන්න'); ?>
                        </button>
                    </form>
                </div>

                <!-- Right Column: Password Change -->
                <div class="settings-section">
                    <h2><i class="fas fa-lock"></i> <?php echo te('Change Password', 'මුරපදය වෙනස් කරන්න'); ?></h2>
                    <form method="POST" class="settings-form">
                        <?php echo csrfInput(); ?>
                        <div class="form-group">
                            <label for="current_password"><?php echo te('Current Password', 'වත්මන් මුරපදය'); ?></label>
                            <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password"><?php echo te('New Password', 'නව මුරපදය'); ?></label>
                            <input type="password" id="new_password" name="new_password" autocomplete="new-password"
                                   minlength="6" required>
                            <small><?php echo te('Minimum 6 characters', 'අවම අකුරු 6'); ?></small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password"><?php echo te('Confirm New Password', 'නව මුරපදය තහවුරු කරන්න'); ?></label>
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                                   minlength="6" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-secondary">
                            <i class="fas fa-key"></i> <?php echo te('Change Password', 'මුරපදය වෙනස් කරන්න'); ?>
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
                this.setCustomValidity(<?php echo json_encode(t('Passwords do not match', 'මුරපද නොගැලපේ'), JSON_UNESCAPED_UNICODE); ?>);
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
