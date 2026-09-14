<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

// Check if user is logged in and has administrator role
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['new_role']) || !isset($input['source_table'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

if (!is_string($input['csrf_token'] ?? null) || empty($_SESSION['admin_csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $input['csrf_token'])) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Your admin session has expired. Refresh the page and try again.']);
    exit;
}

$userId = (int)$input['user_id'];
$newRole = $input['new_role'];
$sourceTable = $input['source_table'];

// Validate role
if (!in_array($sourceTable, ['users', 'admin_users'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid account type']);
    exit;
}

$allowedRoles = $sourceTable === 'users'
    ? ['donor', 'agent']
    : ['supervisor', 'editor', 'administrator'];
if (!in_array($newRole, $allowedRoles, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid role']);
    exit;
}

if ($sourceTable === 'admin_users' && $userId === (int)($_SESSION['admin_id'] ?? 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'You cannot change your own staff role. Ask another administrator to make this change.']);
    exit;
}

try {
    $db = getDB();
    $pdo = $db->getConnection();

    if ($newRole === 'agent') {
        $roleColumn = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'role'");
        if (!$roleColumn || strpos((string)$roleColumn['Type'], "'agent'") === false) {
            throw new RuntimeException('Reservation agents require the database migration file before they can be enabled. Please run database/migrations/2026-09-14-agent-reservations.sql once in phpMyAdmin.');
        }
    }

    $account = null;
    if ($sourceTable === 'users') {
        $stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT id, email, role, username FROM admin_users WHERE id = ?");
        $stmt->execute([$userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$account) {
        throw new RuntimeException('The selected account no longer exists. Refresh the user list and try again.');
    }

    $currentRole = $account['role'] ?: 'donor';

    $pdo->beginTransaction();

    if ($sourceTable === 'admin_users') {
        // Staff accounts remain in their own store. Do not silently create a
        // second donor account or issue a temporary password during a role change.
        $stmt = $pdo->prepare("UPDATE admin_users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);

        $stmt = $pdo->prepare(
            "INSERT INTO admin_actions (admin_id, action_type, action_description, target_id, new_values, status, approved_by, approved_at)
             VALUES (?, 'role_change', ?, ?, ?, 'approved', ?, NOW())"
        );
        $stmt->execute([
            $_SESSION['admin_id'],
            'Changed staff role from ' . $currentRole . ' to ' . $newRole,
            $userId,
            json_encode(['old_role' => $currentRole, 'new_role' => $newRole]),
            $_SESSION['admin_id']
        ]);
    } else {
        // Donor and reservation-agent roles belong only to normal user accounts.
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);

        $stmt = $pdo->prepare(
            "INSERT INTO user_role_changes (user_id, old_role, new_role, changed_by, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $currentRole, $newRole, $_SESSION['admin_id']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'User role updated successfully'
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Role change error: " . $e->getMessage());
    http_response_code(500);
    $message = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Unable to update the user role. Please try again.';
    echo json_encode(['success' => false, 'error' => $message]);
}
?>
