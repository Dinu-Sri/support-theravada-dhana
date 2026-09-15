<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/password-reset.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

adminEnsureCsrfToken();
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireCsrf(null, false);
    $email = trim((string)($_POST['email'] ?? ''));
    $lastRequest = (int)($_SESSION['admin_password_reset_requested_at'] ?? 0);
    if ($lastRequest > time() - 60) {
        $message = 'If an active administrator account uses that email address, a reset link has been sent.';
    } else {
        $_SESSION['admin_password_reset_requested_at'] = time();
        try {
            $result = adminRequestPasswordReset(getDB(), $email);
            $message = $result['message'];
        } catch (Throwable $exception) {
            adminLogException('Admin password reset request failed', $exception);
            $error = 'Unable to process the reset request right now. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Recovery - Dāna Reservation System</title>
    <?php include '../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="login-page">
    <main class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-key"></i> Reset Admin Password</h1>
                <p>Enter the email address on your administrator account.</p>
            </div>
            <form method="post" class="auth-form active" id="recoveryForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                <?php if ($message): ?><div class="success-message" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="error-message" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <div class="form-group">
                    <label for="email">Admin email</label>
                    <input type="email" id="email" name="email" autocomplete="email" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary" id="recoveryButton"><i class="fas fa-paper-plane"></i> Send reset link</button>
                <p class="auth-switch"><a href="index.php">← Back to Admin Login</a></p>
            </form>
        </div>
    </main>
    <script>
        document.getElementById('recoveryForm').addEventListener('submit', function () {
            const button = document.getElementById('recoveryButton');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
        });
    </script>
</body>
</html>
