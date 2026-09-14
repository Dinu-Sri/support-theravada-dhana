<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json; charset=UTF-8');

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'donor', 'agent', 'supervisor', 'editor', 'administrator'];
if (!in_array($filter, $allowedFilters, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user filter']);
    exit;
}

try {
    $db = getDB();
    $pdo = $db->getConnection();
    
    // Check if role_permissions table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'role_permissions'");
    $tableExists = $checkTable->rowCount() > 0;
    
    // Build query based on filter - need to check both users and admin_users tables
    $whereClause = '';
    $params = [];
    
    switch ($filter) {
        case 'donor':
            // For donor filter, we need to check both tables
            // - users table: role = 'donor' or role = ''
            // - admin_users table: role = 'donor' (demoted admins)
            $donorQuery = "SELECT id, first_name, last_name, email, contact_number,
                                 CASE
                                     WHEN role = '' OR role IS NULL THEN 'donor'
                                     ELSE role
                                 END as role,
                                 is_active, created_at, 'users' as source_table
                          FROM users
                          WHERE (role = 'donor' OR role = '' OR role IS NULL) AND is_active = 1
                          UNION ALL
                          SELECT id, username as first_name, '' as last_name, email, '' as contact_number,
                                 role, is_active, created_at, 'admin_users' as source_table
                          FROM admin_users
                          WHERE role = 'donor' AND is_active = 1
                          ORDER BY created_at DESC";

            $stmt = $pdo->prepare($donorQuery);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Set default permissions for donors
            foreach ($users as &$user) {
                $user['permissions'] = ['view_availability'];
            }

            echo json_encode([
                'success' => true,
                'users' => $users
            ]);
            exit; // Exit here for donor filter

        case 'agent':
            $agentQuery = "SELECT id, first_name, last_name, email, contact_number, role, is_active, created_at,
                                  'users' as source_table
                           FROM users
                           WHERE role = 'agent' AND is_active = 1
                           ORDER BY created_at DESC";
            $stmt = $pdo->prepare($agentQuery);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as &$user) {
                $user['permissions'] = ['check_availability', 'create_booking', 'create_booking_for_others', 'upload_payment', 'view_own_bookings'];
            }

            echo json_encode([
                'success' => true,
                'users' => $users
            ]);
            exit;

        case 'supervisor':
        case 'editor':
        case 'administrator':
            // For admin roles, we need to query admin_users table
            $adminQuery = "SELECT id, username as first_name, '' as last_name, email, '' as contact_number,
                                 role, is_active, created_at, 'admin_users' as source_table
                          FROM admin_users
                          WHERE role = ? AND is_active = 1
                          ORDER BY created_at DESC";
            
            $stmt = $pdo->prepare($adminQuery);
            $stmt->execute([$filter]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get permissions for admin users
            if ($tableExists) {
                $permissionsQuery = "SELECT permission_name FROM role_permissions WHERE role_name = ?";
                $permStmt = $pdo->prepare($permissionsQuery);
                
                foreach ($users as &$user) {
                    $permStmt->execute([$user['role']]);
                    $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
                    $user['permissions'] = $permissions;
                }
            } else {
                // Set default permissions for admin users
                foreach ($users as &$user) {
                    switch ($user['role']) {
                        case 'administrator':
                            $user['permissions'] = ['manage_users', 'view_bookings', 'manage_bookings'];
                            break;
                        case 'editor':
                            $user['permissions'] = ['view_bookings', 'edit_bookings'];
                            break;
                        case 'supervisor':
                            $user['permissions'] = ['view_bookings'];
                            break;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'users' => $users
            ]);
            exit; // Exit here for admin role filters
            
        case 'all':
        default:
            // For 'all', we need to combine both tables
            break;
    }
    
    // For 'all' or 'donor' filter, query the users table
    if ($filter === 'all') {
        // Combine users from both tables
        $usersQuery = "SELECT id, first_name, last_name, email, contact_number, 
                             CASE 
                                 WHEN role = '' OR role IS NULL THEN 'donor'
                                 ELSE role 
                             END as role, 
                             is_active, created_at, 'users' as source_table
                      FROM users 
                      WHERE is_active = 1
                      UNION ALL
                      SELECT id, username as first_name, '' as last_name, email, '' as contact_number,
                             role, is_active, created_at, 'admin_users' as source_table
                      FROM admin_users 
                      WHERE is_active = 1
                      ORDER BY 
                        CASE 
                            WHEN role = 'administrator' THEN 1
                            WHEN role = 'editor' THEN 2  
                            WHEN role = 'supervisor' THEN 3
                            WHEN role = 'agent' THEN 4
                            WHEN role = 'donor' OR role = '' OR role IS NULL THEN 5
                        END, created_at DESC";
    } else {
        // For donor filter, only query users table
        $usersQuery = "SELECT id, first_name, last_name, email, contact_number, 
                             CASE 
                                 WHEN role = '' OR role IS NULL THEN 'donor'
                                 ELSE role 
                             END as role, 
                             is_active, created_at, 'users' as source_table
                      FROM users 
                      $whereClause 
                      ORDER BY created_at DESC";
    }
    
    $stmt = $pdo->prepare($usersQuery);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get permissions for each user role (only if table exists)
    if ($tableExists) {
        $permissionsQuery = "SELECT permission_name FROM role_permissions WHERE role_name = ?";
        $permStmt = $pdo->prepare($permissionsQuery);
        
        foreach ($users as &$user) {
            $permStmt->execute([$user['role']]);
            $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
            $user['permissions'] = $permissions;
        }
    } else {
        // If role_permissions table doesn't exist, set default permissions
        foreach ($users as &$user) {
            switch ($user['role']) {
                case 'administrator':
                    $user['permissions'] = ['manage_users', 'view_bookings', 'manage_bookings'];
                    break;
                case 'editor':
                    $user['permissions'] = ['view_bookings', 'edit_bookings'];
                    break;
                case 'supervisor':
                    $user['permissions'] = ['view_bookings'];
                    break;
                case 'agent':
                    $user['permissions'] = ['check_availability', 'create_booking', 'create_booking_for_others', 'upload_payment', 'view_own_bookings'];
                    break;
                case 'donor':
                default:
                    $user['permissions'] = ['view_availability'];
                    break;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Unable to load admin users: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load users. Please try again.']);
}
?>
