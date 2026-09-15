<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminJsonResponse(['success' => false, 'error' => 'Role changes must be requested with POST.'], 405);
}
header('Content-Type: application/json; charset=UTF-8');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['user_id'], $input['new_role'], $input['source_table'])) {
    adminJsonResponse(['success' => false, 'error' => 'Missing required parameters.'], 400);
}

$db = getDB();
adminRequirePermission($db, 'manage_admins', true);
adminRequireCsrf($input, true);

$userId = (int)$input['user_id'];
$newRole = $input['new_role'];
$sourceTable = $input['source_table'];

// Validate role
if (!in_array($sourceTable, ['users', 'admin_users'], true)) {
    adminJsonResponse(['success' => false, 'error' => 'Invalid account type.'], 400);
}

$allowedRoles = $sourceTable === 'users'
    ? ['donor', 'agent']
    : ['supervisor', 'editor', 'administrator'];
if (!in_array($newRole, $allowedRoles, true)) {
    adminJsonResponse(['success' => false, 'error' => 'Invalid role.'], 400);
}

if ($sourceTable === 'admin_users' && $userId === (int)($_SESSION['admin_id'] ?? 0)) {
    adminJsonResponse(['success' => false, 'error' => 'You cannot change your own staff role. Ask another administrator to make this change.'], 400);
}

try {
    $pdo = $db->getConnection();

    if ($newRole === 'agent') {
        $roleColumn = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'role'");
        if (!$roleColumn || strpos((string)$roleColumn['Type'], "'agent'") === false) {
            throw new DomainException('Reservation agents require the database migration file before they can be enabled. Please run database/migrations/2026-09-14-agent-reservations.sql once in phpMyAdmin.');
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
        throw new DomainException('The selected account no longer exists. Refresh the user list and try again.');
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
    $message = $e instanceof DomainException
        ? $e->getMessage()
        : 'Unable to update the user role. Please try again.';
    adminJsonResponse(['success' => false, 'error' => $message], $e instanceof DomainException ? 400 : 500);
}
?>
