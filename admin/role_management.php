<?php
/**
 * Role Management System for Dāna Booking Admin Panel
 * This adds the new 4-role system management capabilities
 */

session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$db = getDB();

// Get current admin info with new role system
$currentAdmin = $db->fetchOne(
    "SELECT * FROM admin_users WHERE id = ?",
    [$_SESSION['admin_id']]
);

// Update session with new role system
$_SESSION['admin_role'] = $currentAdmin['role'];
$_SESSION['is_administrator'] = ($currentAdmin['role'] === 'administrator');
$_SESSION['is_editor'] = ($currentAdmin['role'] === 'editor');
$_SESSION['is_supervisor'] = ($currentAdmin['role'] === 'supervisor');

// Check permissions
function hasPermission($permission) {
    global $db;
    $role = $_SESSION['admin_role'];
    
    $check = $db->fetchOne(
        "SELECT COUNT(*) as has_permission FROM role_permissions 
         WHERE role_name = ? AND permission_name = ?",
        [$role, $permission]
    );
    
    return $check['has_permission'] > 0;
}

// Handle role management actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Change user role
    if (isset($_POST['change_role']) && hasPermission('manage_users')) {
        $userId = $_POST['user_id'];
        $newRole = $_POST['new_role'];
        $userType = $_POST['user_type']; // 'admin' or 'regular'
        
        try {
            if ($userType === 'admin') {
                // Update admin_users table
                $db->query(
                    "UPDATE admin_users SET role = ? WHERE id = ?",
                    [$newRole, $userId]
                );
                
                // Log the change
                $db->query(
                    "INSERT INTO admin_actions (admin_id, action_type, action_description, target_id, new_values, status, approved_by, approved_at)
                     VALUES (?, 'role_change', ?, ?, ?, 'approved', ?, NOW())",
                    [$_SESSION['admin_id'], "Changed admin user role to $newRole", $userId, json_encode(['role' => $newRole]), $_SESSION['admin_id']]
                );
                
            } else {
                // Update users table
                $db->query(
                    "UPDATE users SET role = ? WHERE id = ?",
                    [$newRole, $userId]
                );
                
                // Log in user_role_changes
                $oldRole = $db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId])['role'] ?? 'donor';
                $db->query(
                    "INSERT INTO user_role_changes (user_id, old_role, new_role, changed_by, created_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [$userId, $oldRole, $newRole, $_SESSION['admin_id']]
                );
            }
            
            $message = "Role updated successfully!";
            $messageType = "success";
            
        } catch (Exception $e) {
            $message = "Error updating role: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    // Approve/reject pending actions
    if (isset($_POST['action_decision']) && hasPermission('super_admin_approval')) {
        $actionId = $_POST['action_id'];
        $decision = $_POST['decision']; // 'approve' or 'reject'
        
        try {
            $status = ($decision === 'approve') ? 'approved' : 'rejected';
            
            $db->query(
                "UPDATE admin_actions SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?",
                [$status, $_SESSION['admin_id'], $actionId]
            );
            
            // If approved, execute the action
            if ($decision === 'approve') {
                $action = $db->fetchOne("SELECT * FROM admin_actions WHERE id = ?", [$actionId]);
                
                if ($action['action_type'] === 'booking_update') {
                    $newValues = json_decode($action['new_values'], true);
                    if (isset($newValues['status'])) {
                        $db->query(
                            "UPDATE bookings SET status = ? WHERE id = ?",
                            [$newValues['status'], $action['target_id']]
                        );
                    }
                }
            }
            
            $message = "Action " . $decision . "d successfully!";
            $messageType = "success";
            
        } catch (Exception $e) {
            $message = "Error processing action: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get all admin users
$adminUsers = $db->fetchAll("
    SELECT au.*, rh.role_display_name, rh.hierarchy_level
    FROM admin_users au
    LEFT JOIN role_hierarchy rh ON au.role = rh.role_name
    WHERE au.is_active = 1
    ORDER BY rh.hierarchy_level DESC, au.username
");

// Get regular users with elevated roles
$elevatedUsers = $db->fetchAll("
    SELECT u.*, rh.role_display_name, rh.hierarchy_level
    FROM users u
    LEFT JOIN role_hierarchy rh ON u.role = rh.role_name
    WHERE u.role IS NOT NULL AND u.role != 'donor' AND u.is_active = 1
    ORDER BY rh.hierarchy_level DESC, u.first_name, u.last_name
");

// Get pending admin actions (if administrator)
$pendingActions = [];
if (hasPermission('super_admin_approval')) {
    $pendingActions = $db->fetchAll("
        SELECT aa.*, au.username as admin_username
        FROM admin_actions aa
        JOIN admin_users au ON aa.admin_id = au.id
        WHERE aa.status = 'pending'
        ORDER BY aa.created_at ASC
    ");
}

// Get role hierarchy for dropdowns
$roleHierarchy = $db->fetchAll("
    SELECT * FROM role_hierarchy 
    ORDER BY hierarchy_level DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - Dāna Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .role-management {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .role-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .role-administrator { border-left: 5px solid #e74c3c; }
        .role-editor { border-left: 5px solid #f39c12; }
        .role-supervisor { border-left: 5px solid #27ae60; }
        .role-donor { border-left: 5px solid #3498db; }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .user-table th, .user-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .user-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .role-badge.administrator { background-color: #e74c3c; }
        .role-badge.editor { background-color: #f39c12; }
        .role-badge.supervisor { background-color: #27ae60; }
        .role-badge.donor { background-color: #3498db; }
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-warning { background-color: #ffc107; color: black; }
        .btn:hover { opacity: 0.9; }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .permission-list {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="role-management">
        <div class="header" style="text-align: center; margin-bottom: 30px;">
            <h1><i class="fas fa-users-cog"></i> Role Management System</h1>
            <p>Manage user roles and permissions for the Dāna Booking System</p>
            <a href="index.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Current User Info -->
        <div class="role-card role-<?php echo $currentAdmin['role']; ?>">
            <h3><i class="fas fa-user-circle"></i> Your Role: <?php echo ucfirst($currentAdmin['role']); ?></h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($currentAdmin['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($currentAdmin['email']); ?></p>
            
            <?php
            $userPerms = $db->fetchAll(
                "SELECT permission_name, permission_description FROM role_permissions WHERE role_name = ?",
                [$currentAdmin['role']]
            );
            ?>
            <div class="permission-list">
                <strong>Your Permissions:</strong>
                <?php foreach ($userPerms as $perm): ?>
                    <span title="<?php echo htmlspecialchars($perm['permission_description']); ?>">
                        <?php echo str_replace('_', ' ', ucwords($perm['permission_name'])); ?>
                    </span>
                    <?php if ($perm !== end($userPerms)) echo ' • '; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Role Statistics -->
        <div class="stats-grid">
            <?php
            $roleStats = $db->fetchAll("
                SELECT 
                    COALESCE(au.role, u.role) as role,
                    COUNT(*) as count,
                    rh.role_display_name,
                    rh.hierarchy_level
                FROM (
                    SELECT role FROM admin_users WHERE is_active = 1
                    UNION ALL
                    SELECT role FROM users WHERE role IS NOT NULL AND role != 'donor' AND is_active = 1
                ) combined
                LEFT JOIN admin_users au ON combined.role = au.role
                LEFT JOIN users u ON combined.role = u.role
                LEFT JOIN role_hierarchy rh ON combined.role = rh.role_name
                GROUP BY combined.role, rh.role_display_name, rh.hierarchy_level
                ORDER BY rh.hierarchy_level DESC
            ");
            
            foreach ($roleStats as $stat):
            ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stat['count']; ?></div>
                    <div><?php echo $stat['role_display_name'] ?? ucfirst($stat['role']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pending Actions (Administrator only) -->
        <?php if (hasPermission('super_admin_approval') && !empty($pendingActions)): ?>
            <div class="role-card">
                <h3><i class="fas fa-clock"></i> Pending Approval Requests</h3>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingActions as $action): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($action['admin_username']); ?></td>
                                <td><?php echo str_replace('_', ' ', ucwords($action['action_type'])); ?></td>
                                <td><?php echo htmlspecialchars($action['action_description']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($action['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action_id" value="<?php echo $action['id']; ?>">
                                        <button type="submit" name="action_decision" value="approve" class="btn btn-success">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="action_decision" value="reject" class="btn btn-danger">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Admin Users Management -->
        <?php if (hasPermission('manage_admins')): ?>
            <div class="role-card">
                <h3><i class="fas fa-user-shield"></i> Admin Users</h3>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Current Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo $user['role']; ?>">
                                        <?php echo $user['role_display_name'] ?? ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="user_type" value="admin">
                                            <select name="new_role" onchange="this.form.submit()">
                                                <option value="">Change Role...</option>
                                                <?php foreach ($roleHierarchy as $role): ?>
                                                    <?php if ($role['role_name'] !== 'donor' && $role['role_name'] !== $user['role']): ?>
                                                        <option value="<?php echo $role['role_name']; ?>">
                                                            <?php echo $role['role_display_name']; ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="change_role" value="1">
                                        </form>
                                    <?php else: ?>
                                        <em>Current User</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Elevated Regular Users -->
        <?php if (hasPermission('manage_users') && !empty($elevatedUsers)): ?>
            <div class="role-card">
                <h3><i class="fas fa-user-plus"></i> Elevated Regular Users</h3>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Current Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($elevatedUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo $user['role']; ?>">
                                        <?php echo $user['role_display_name'] ?? ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="user_type" value="regular">
                                        <select name="new_role" onchange="this.form.submit()">
                                            <option value="">Change Role...</option>
                                            <option value="donor">Demote to Donor</option>
                                            <?php foreach ($roleHierarchy as $role): ?>
                                                <?php if ($role['role_name'] !== 'donor' && $role['role_name'] !== $user['role']): ?>
                                                    <option value="<?php echo $role['role_name']; ?>">
                                                        <?php echo $role['role_display_name']; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="change_role" value="1">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Role Descriptions -->
        <div class="role-card">
            <h3><i class="fas fa-info-circle"></i> Role Descriptions</h3>
            <div class="stats-grid">
                <?php foreach ($roleHierarchy as $role): ?>
                    <div class="role-card role-<?php echo $role['role_name']; ?>">
                        <h4><?php echo $role['role_display_name']; ?> (Level <?php echo $role['hierarchy_level']; ?>)</h4>
                        <p><?php echo $role['role_description']; ?></p>
                        
                        <?php
                        $rolePerms = $db->fetchAll(
                            "SELECT permission_name FROM role_permissions WHERE role_name = ?",
                            [$role['role_name']]
                        );
                        ?>
                        <div class="permission-list">
                            <strong>Permissions:</strong><br>
                            <?php foreach ($rolePerms as $perm): ?>
                                • <?php echo str_replace('_', ' ', ucwords($perm['permission_name'])); ?><br>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
