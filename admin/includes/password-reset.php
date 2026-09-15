<?php

function adminRequestPasswordReset($db, $email) {
    $genericMessage = 'If an active administrator account uses that email address, a reset link has been sent.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => true, 'message' => $genericMessage];
    }

    $admin = $db->fetchOne(
        "SELECT id, username, email FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1",
        [$email]
    );
    if (!$admin) return ['success' => true, 'message' => $genericMessage];

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    $connection = $db->getConnection();
    try {
        $connection->beginTransaction();
        $db->query(
            "UPDATE admin_password_reset_tokens SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL",
            [(int)$admin['id']]
        );
        $db->query(
            "INSERT INTO admin_password_reset_tokens (admin_id, token_hash, expires_at) VALUES (?, ?, ?)",
            [(int)$admin['id'], $tokenHash, $expiresAt]
        );
        $connection->commit();
    } catch (Throwable $e) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $e;
    }

    require_once __DIR__ . '/../../includes/email.php';
    $resetLink = rtrim(SITE_URL, '/') . '/admin/reset-password.php?token=' . urlencode($token);
    $safeName = htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $html = '<p>Hello ' . $safeName . ',</p>'
        . '<p>A password reset was requested for your administrator account.</p>'
        . '<p><a href="' . $safeLink . '">Reset administrator password</a></p>'
        . '<p>This single-use link expires in 60 minutes. If you did not request it, you can ignore this email.</p>';
    $plain = "A password reset was requested for your administrator account.\n\n"
        . $resetLink . "\n\nThis single-use link expires in 60 minutes.";
    $result = getEmailService()->sendEmail($admin['email'], 'Administrator password reset - ' . SITE_NAME, $html, $plain);
    if (empty($result['success'])) {
        adminLogException('Admin password reset email failed', new RuntimeException('Email service returned failure.'));
    }

    return ['success' => true, 'message' => $genericMessage];
}

function adminFindPasswordReset($db, $token, $lock = false) {
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $sql = "SELECT aprt.id, aprt.admin_id, au.username
            FROM admin_password_reset_tokens aprt
            JOIN admin_users au ON au.id = aprt.admin_id AND au.is_active = 1
            WHERE aprt.token_hash = ? AND aprt.used_at IS NULL AND aprt.expires_at > NOW()"
        . ($lock ? ' FOR UPDATE' : '');
    return $db->fetchOne($sql, [hash('sha256', $token)]);
}

function adminCompletePasswordReset($db, $token, $newPassword) {
    $minimum = defined('PASSWORD_MIN_LENGTH') ? max(8, (int)PASSWORD_MIN_LENGTH) : 8;
    if (strlen($newPassword) < $minimum || strlen($newPassword) > 128) {
        throw new DomainException('Use a password between ' . $minimum . ' and 128 characters.');
    }

    $connection = $db->getConnection();
    try {
        $connection->beginTransaction();
        $reset = adminFindPasswordReset($db, $token, true);
        if (!$reset) throw new DomainException('This reset link is invalid, expired, or already used.');
        $db->query(
            "UPDATE admin_users SET password_hash = ? WHERE id = ? AND is_active = 1",
            [password_hash($newPassword, PASSWORD_DEFAULT), (int)$reset['admin_id']]
        );
        $db->query(
            "UPDATE admin_password_reset_tokens SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL",
            [(int)$reset['admin_id']]
        );
        $connection->commit();
        return true;
    } catch (Throwable $e) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $e;
    }
}
