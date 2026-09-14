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
    if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
        return;
    }

    if ($jsonResponse) {
        adminJsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    header('Location: index.php');
    exit;
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
