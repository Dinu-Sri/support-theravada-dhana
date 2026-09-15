<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/password-reset.php';

adminEnsureCsrfToken();
$db = getDB();
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$error = '';
$success = '';

try {
    $validReset = adminFindPasswordReset($db, $token, false);
} catch (Throwable $exception) {
    adminLogException('Admin reset token lookup failed', $exception);
    $validReset = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireCsrf(null, false);
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');
    if ($password !== $confirmation) {
        $error = 'The new passwords do not match.';
    } else {
        try {
            adminCompletePasswordReset($db, $token, $password);
            $success = 'Your administrator password has been reset. You can now sign in.';
            $validReset = null;
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            adminLogException('Admin password reset failed', $exception);
            $error = 'Unable to reset the password right now. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Admin Password - Dāna Reservation System</title>
    <?php include '../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="login-page">
    <main class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-lock"></i> Choose New Password</h1>
                <p>This link is single-use and expires after 60 minutes.</p>
            </div>
            <?php if ($success): ?>
                <div class="auth-form active">
                    <div class="success-message" role="status"><?php echo htmlspecialchars($success); ?></div>
                    <p class="auth-switch"><a href="index.php">Continue to Admin Login</a></p>
                </div>
            <?php elseif (!$validReset): ?>
                <div class="auth-form active">
                    <div class="error-message" role="alert"><?php echo htmlspecialchars($error ?: 'This reset link is invalid, expired, or already used.'); ?></div>
                    <p class="auth-switch"><a href="forgot-password.php">Request a new reset link</a></p>
                </div>
            <?php else: ?>
                <form method="post" class="auth-form active" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <?php if ($error): ?><div class="error-message" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                    <div class="form-group">
                        <label for="password">New password</label>
                        <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="128" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirm new password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="128" required>
                    </div>
                    <button type="submit" class="btn btn-primary" id="resetButton"><i class="fas fa-check"></i> Save new password</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
    <script>
        const form = document.getElementById('resetForm');
        if (form) form.addEventListener('submit', function () {
            const button = document.getElementById('resetButton');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        });
    </script>
</body>
</html>
