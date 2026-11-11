<?php
require_once 'includes/auth.php';

$auth = getAuth();

// Redirect to dashboard if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$token = isset($_GET['token']) ? $_GET['token'] : '';
$successMessage = '';
$errorMessage = '';
$tokenValid = false;
$userData = null;

// Verify token on page load
if (!empty($token)) {
    $tokenResult = $auth->verifyResetToken($token);
    if ($tokenResult['success']) {
        $tokenValid = true;
        $userData = $tokenResult['data'];
    } else {
        $errorMessage = $tokenResult['error'];
    }
} else {
    $errorMessage = 'Invalid reset link. Please request a new password reset.';
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'], $_POST['password'], $_POST['confirm_password'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Your session expired. Please refresh and try again.';
    } elseif (empty($password) || empty($confirmPassword)) {
        $errorMessage = 'Please fill in all fields.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Passwords do not match.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errorMessage = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } else {
        $result = $auth->resetPassword($token, $password);
        if ($result['success']) {
            $successMessage = $result['message'];
            $tokenValid = false; // Hide form after success
        } else {
            $errorMessage = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<?php echo ui_html_open(); ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo te('Reset Password - Dāna Reservation System', 'මුරපදය යළි පිහිටුවන්න - දාන වෙන්කිරීමේ පද්ධතිය'); ?></title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pro-ui.css?v=20260915">
    <style>
        .login-page {
            background-image: url('uploads/bck.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

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

        .reset-password-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            margin: 20px;
        }

        .reset-password-header {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }

        .reset-password-header h1 {
            margin: 0;
            font-size: 28px;
        }

        .reset-password-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        .reset-password-content {
            padding: 40px;
        }

        .user-info {
            background: #f3e5ab;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }

        .user-info i {
            color: #ff9800;
            margin-right: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ff9800;
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
        }

        .password-requirements {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #ff9800;
            color: white;
        }

        .btn-primary:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #ff9800;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }

        .password-toggle input {
            padding-right: 40px;
        }
    </style>
</head>
<body class="login-page">
    <div class="reset-password-card">
        <div class="reset-password-header">
            <?php ui_lang_switch(); ?>
            <h1><i class="fas fa-lock"></i> <?php echo te('Reset Password', 'මුරපදය යළි පිහිටුවන්න'); ?></h1>
            <p><?php echo te('Create a new password for your account', 'ගිණුම සඳහා නව මුරපදයක් සාදන්න'); ?></p>
        </div>

        <div class="reset-password-content">
            <?php if ($successMessage): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo te_msg($successMessage); ?>
                </div>
                <div class="back-link">
                    <a href="index.php"><i class="fas fa-sign-in-alt"></i> <?php echo te('Go to Login', 'පිවිසීමට යන්න'); ?></a>
                </div>
            <?php elseif (!$tokenValid): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo te_msg($errorMessage); ?>
                </div>
                <div class="back-link">
                    <a href="forgot-password.php"><i class="fas fa-redo"></i> <?php echo te('Request New Reset Link', 'නව සබැඳියක් ඉල්ලන්න'); ?></a>
                </div>
            <?php else: ?>
                <?php if ($userData): ?>
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        <?php echo te('Resetting password for:', 'මුරපදය යළි පිහිටුවන්නේ:'); ?> <strong><?php echo htmlspecialchars($userData['email']); ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo te_msg($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="resetPasswordForm">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> <?php echo te('New Password', 'නව මුරපදය'); ?>
                        </label>
                        <div class="password-toggle">
                            <input type="password" id="password" name="password" autocomplete="new-password" required
                                   placeholder="<?php echo te('Enter new password', 'නව මුරපදය ඇතුළත් කරන්න'); ?>">
                            <i class="fas fa-eye toggle-icon" onclick="togglePassword('password')"></i>
                        </div>
                        <div class="password-requirements">
                            <?php echo te('Minimum ' . PASSWORD_MIN_LENGTH . ' characters required', 'අවම අකුරු ' . PASSWORD_MIN_LENGTH . ' ක් අවශ්‍යයි'); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> <?php echo te('Confirm New Password', 'නව මුරපදය තහවුරු කරන්න'); ?>
                        </label>
                        <div class="password-toggle">
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required
                                   placeholder="<?php echo te('Confirm new password', 'නව මුරපදය තහවුරු කරන්න'); ?>">
                            <i class="fas fa-eye toggle-icon" onclick="togglePassword('confirm_password')"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> <?php echo te('Reset Password', 'මුරපදය යළි පිහිටුවන්න'); ?>
                    </button>
                </form>

                <div class="back-link">
                    <a href="index.php"><i class="fas fa-arrow-left"></i> <?php echo te('Back to Login', 'පිවිසීමට ආපසු'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('.toggle-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                e.preventDefault();
                alert(<?php echo json_encode(t('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters!', 'මුරපදය අවම වශයෙන් අකුරු ' . PASSWORD_MIN_LENGTH . ' ක් විය යුතුය.'), JSON_UNESCAPED_UNICODE); ?>);
                return false;
            }
        });
    </script>
</body>
</html>
