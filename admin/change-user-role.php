<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has administrator role
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['new_role'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$userId = (int)$input['user_id'];
$newRole = $input['new_role'];

// Validate role
if (!in_array($newRole, ['donor', 'supervisor', 'editor', 'administrator'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid role']);
    exit;
}

try {
    // Use direct PDO connection for better error handling
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // First, try to find user in users table
    $stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $regularUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Then try to find user in admin_users table  
    $stmt = $pdo->prepare("SELECT id, email, role, username FROM admin_users WHERE id = ?");
    $stmt->execute([$userId]);
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$regularUser && !$adminUser) {
        throw new Exception('User not found in either users or admin_users table');
    }

    // Determine current user info and location
    $currentUser = $regularUser ?: $adminUser;
    $currentRole = $currentUser['role'] ?? 'donor';
    $isCurrentlyAdmin = !empty($adminUser);
    $userEmail = $currentUser['email'];
    
    // Determine if new role requires admin_users table
    $newRoleRequiresAdminTable = in_array($newRole, ['supervisor', 'editor', 'administrator']);

    $pdo->beginTransaction();

    if ($isCurrentlyAdmin && $newRoleRequiresAdminTable) {
        // User is admin and staying admin - just update role in admin_users
        $stmt = $pdo->prepare("UPDATE admin_users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);
        
    } elseif ($isCurrentlyAdmin && !$newRoleRequiresAdminTable) {
        // Demoting admin to donor
        // IMPORTANT: We keep the admin_users record to preserve audit trail and avoid foreign key violations
        // Just update the role to 'donor' in admin_users table

        $stmt = $pdo->prepare("UPDATE admin_users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);

        // Also check if user exists in users table and update there too
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$userEmail]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // Update existing user record
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE email = ?");
            $stmt->execute([$newRole, $userEmail]);
        } else {
            // Create new user record for donor functionality
            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, contact_number, role, password_hash, created_at)
                VALUES (?, '', ?, '', ?, ?, NOW())
            ");
            $stmt->execute([
                $adminUser['username'], // Use username as first_name
                $userEmail,
                $newRole,
                password_hash('user123', PASSWORD_DEFAULT) // Temporary password
            ]);
        }

        // NOTE: We do NOT delete from admin_users to preserve:
        // 1. Audit trail (admin_actions records)
        // 2. Login history
        // 3. Prevent foreign key constraint violations
        
    } elseif (!$isCurrentlyAdmin && $newRoleRequiresAdminTable) {
        // Promoting regular user to admin role
        
        // Check if admin_users entry already exists
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
        $stmt->execute([$userEmail]);
        $existingAdmin = $stmt->fetch();

        if ($existingAdmin) {
            // Update existing admin record
            $stmt = $pdo->prepare("UPDATE admin_users SET role = ? WHERE email = ?");
            $stmt->execute([$newRole, $userEmail]);
        } else {
            // Create new admin_users entry
            $fullName = trim(($regularUser['first_name'] ?? '') . ' ' . ($regularUser['last_name'] ?? ''));
            $username = $fullName ?: $userEmail;
            
            $stmt = $pdo->prepare("
                INSERT INTO admin_users (username, email, password_hash, role, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $username,
                $userEmail,
                password_hash('admin123', PASSWORD_DEFAULT), // Temporary password
                $newRole
            ]);
        }
        
        // Update or keep user in users table but mark as inactive admin
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute(['', $userId]); // Clear role in users table
        
    } else {
        // Regular user staying as regular user (donor to donor, etc.)
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'User role updated successfully',
        'debug' => [
            'old_role' => $currentRole,
            'new_role' => $newRole,
            'was_admin' => $isCurrentlyAdmin,
            'now_admin' => $newRoleRequiresAdminTable,
            'user_email' => $userEmail
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Role change error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error updating user role: ' . $e->getMessage(),
        'debug' => [
            'user_id' => $userId,
            'new_role' => $newRole,
            'file' => __FILE__,
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>
