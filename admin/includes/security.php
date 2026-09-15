<?php

/**
 * Shared security helpers for the separate admin authentication system.
 * The caller must load config/database.php before using permission helpers.
 */

function adminJsonResponse($payload, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function adminRequireLogin($jsonResponse = false) {
    $timeout = defined('SESSION_TIMEOUT') ? max(300, (int)SESSION_TIMEOUT) : 3600;
    $lastActivity = isset($_SESSION['admin_last_activity']) ? (int)$_SESSION['admin_last_activity'] : 0;
    if ($lastActivity > 0 && time() - $lastActivity > $timeout) {
        adminDestroySession();
        if ($jsonResponse) {
            adminJsonResponse(['success' => false, 'error' => 'Your admin session expired. Sign in again.'], 401);
        }
        header('Location: index.php?expired=1');
        exit;
    }

    if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
        $_SESSION['admin_last_activity'] = time();
        return;
    }

    if ($jsonResponse) {
        adminJsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    header('Location: index.php');
    exit;
}

function adminEstablishSession($admin) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = (string)$admin['username'];
    $_SESSION['admin_role'] = (string)$admin['role'];
    $_SESSION['is_administrator'] = $admin['role'] === 'administrator';
    $_SESSION['is_editor'] = $admin['role'] === 'editor';
    $_SESSION['is_supervisor'] = $admin['role'] === 'supervisor';
    $_SESSION['is_super_admin'] = $admin['role'] === 'administrator';
    $_SESSION['admin_last_activity'] = time();
    unset($_SESSION['admin_csrf_token']);
    adminEnsureCsrfToken();
}

function adminDestroySession() {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function adminRenderLogoutButton($className = 'logout-btn') {
    $token = htmlspecialchars(adminEnsureCsrfToken(), ENT_QUOTES, 'UTF-8');
    $class = htmlspecialchars($className, ENT_QUOTES, 'UTF-8');
    echo '<form method="post" action="index.php" class="admin-logout-form">'
        . '<input type="hidden" name="csrf_token" value="' . $token . '">'
        . '<button type="submit" name="admin_logout" value="1" class="' . $class . '">'
        . '<i class="fas fa-sign-out-alt" aria-hidden="true"></i><span>Logout</span></button></form>';
}

function adminRefreshIdentity($db, $jsonResponse = false) {
    static $refreshed = false;
    if ($refreshed) return;

    $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
    $admin = $adminId > 0
        ? $db->fetchOne("SELECT id, username, role, is_active FROM admin_users WHERE id = ?", [$adminId])
        : null;
    if (!$admin || empty($admin['is_active'])) {
        adminDestroySession();
        if ($jsonResponse) {
            adminJsonResponse(['success' => false, 'error' => 'Your admin account is no longer active.'], 401);
        }
        header('Location: index.php?account=inactive');
        exit;
    }

    $_SESSION['admin_username'] = (string)$admin['username'];
    $_SESSION['admin_role'] = (string)$admin['role'];
    $_SESSION['is_administrator'] = $admin['role'] === 'administrator';
    $_SESSION['is_editor'] = $admin['role'] === 'editor';
    $_SESSION['is_supervisor'] = $admin['role'] === 'supervisor';
    $_SESSION['is_super_admin'] = $admin['role'] === 'administrator';
    $refreshed = true;
}

function adminLoginThrottleKey($username) {
    $address = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : 'cli';
    return hash('sha256', strtolower(trim((string)$username)) . '|' . $address);
}

function adminLoginThrottleStatus($db, $username) {
    $key = adminLoginThrottleKey($username);
    try {
        $attempt = $db->fetchOne(
            "SELECT failure_count, locked_until, updated_at FROM admin_login_attempts WHERE identifier_hash = ?",
            [$key]
        );
        if ($attempt && !empty($attempt['locked_until']) && strtotime($attempt['locked_until']) > time()) {
            return ['blocked' => true, 'retry_after' => max(1, strtotime($attempt['locked_until']) - time())];
        }
        if ($attempt && strtotime($attempt['updated_at']) < time() - 900) {
            $db->query("DELETE FROM admin_login_attempts WHERE identifier_hash = ?", [$key]);
        }
    } catch (Throwable $e) {
        adminLogException('Persistent admin login throttling unavailable', $e);
    }

    $fallback = $_SESSION['admin_login_throttle'][$key] ?? null;
    if ($fallback && (int)$fallback['updated_at'] >= time() - 900 && (int)$fallback['failure_count'] >= 5) {
        return ['blocked' => true, 'retry_after' => max(1, (int)$fallback['updated_at'] + 900 - time())];
    }
    return ['blocked' => false, 'retry_after' => 0];
}

function adminRecordLoginFailure($db, $username) {
    $key = adminLoginThrottleKey($username);
    $connection = $db->getConnection();
    try {
        $connection->beginTransaction();
        $attempt = $db->fetchOne(
            "SELECT failure_count, updated_at FROM admin_login_attempts WHERE identifier_hash = ? FOR UPDATE",
            [$key]
        );
        $failureCount = $attempt && strtotime($attempt['updated_at']) >= time() - 900
            ? (int)$attempt['failure_count'] + 1
            : 1;
        $lockedUntil = $failureCount >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
        if ($attempt) {
            $db->query(
                "UPDATE admin_login_attempts SET failure_count = ?, locked_until = ?, updated_at = NOW() WHERE identifier_hash = ?",
                [$failureCount, $lockedUntil, $key]
            );
        } else {
            $db->query(
                "INSERT INTO admin_login_attempts (identifier_hash, failure_count, locked_until, updated_at) VALUES (?, ?, ?, NOW())",
                [$key, $failureCount, $lockedUntil]
            );
        }
        $connection->commit();
        return;
    } catch (Throwable $e) {
        if ($connection->inTransaction()) $connection->rollBack();
        adminLogException('Persistent admin login failure recording unavailable', $e);
    }

    $fallback = $_SESSION['admin_login_throttle'][$key] ?? ['failure_count' => 0, 'updated_at' => time()];
    if ((int)$fallback['updated_at'] < time() - 900) $fallback['failure_count'] = 0;
    $fallback['failure_count']++;
    $fallback['updated_at'] = time();
    $_SESSION['admin_login_throttle'][$key] = $fallback;
}

function adminClearLoginFailures($db, $username) {
    $key = adminLoginThrottleKey($username);
    try {
        $db->query("DELETE FROM admin_login_attempts WHERE identifier_hash = ?", [$key]);
    } catch (Throwable $e) {
        adminLogException('Persistent admin login failure cleanup unavailable', $e);
    }
    unset($_SESSION['admin_login_throttle'][$key]);
}

function adminEnsureCsrfToken() {
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function adminRequestCsrfToken($jsonInput = null) {
    if (is_array($jsonInput) && isset($jsonInput['csrf_token'])) {
        return (string)$jsonInput['csrf_token'];
    }
    if (isset($_POST['csrf_token'])) {
        return (string)$_POST['csrf_token'];
    }
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    return '';
}

function adminRequireCsrf($jsonInput = null, $jsonResponse = false) {
    $sessionToken = adminEnsureCsrfToken();
    $requestToken = adminRequestCsrfToken($jsonInput);

    if ($requestToken !== '' && hash_equals($sessionToken, $requestToken)) {
        return;
    }

    if ($jsonResponse) {
        adminJsonResponse([
            'success' => false,
            'error' => 'Your admin session has expired. Refresh the page and try again.'
        ], 419);
    }

    http_response_code(419);
    exit('Your admin session has expired. Refresh the page and try again.');
}

function adminHasPermission($db, $permission) {
    adminRefreshIdentity($db, false);
    $role = isset($_SESSION['admin_role']) ? (string)$_SESSION['admin_role'] : '';
    if ($role === '' || $permission === '') {
        return false;
    }

    $check = $db->fetchOne(
        "SELECT COUNT(*) AS has_permission
         FROM role_permissions
         WHERE role_name = ? AND permission_name = ?",
        [$role, $permission]
    );

    return !empty($check['has_permission']);
}

function adminHasAnyPermission($db, $permissions) {
    foreach ((array)$permissions as $permission) {
        if (adminHasPermission($db, $permission)) {
            return true;
        }
    }
    return false;
}

function adminRequirePermission($db, $permissions, $jsonResponse = false) {
    adminRefreshIdentity($db, $jsonResponse);
    if (adminHasAnyPermission($db, $permissions)) {
        return;
    }

    if ($jsonResponse) {
        adminJsonResponse([
            'success' => false,
            'error' => 'You do not have permission to perform this action.'
        ], 403);
    }

    http_response_code(403);
    exit('You do not have permission to perform this action.');
}

function adminLogException($context, $exception) {
    error_log($context . ': ' . $exception->getMessage());
}
