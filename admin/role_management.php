<?php
/**
 * Role Management System for Dāna Booking Admin Panel
 * This adds the new 4-role system management capabilities
 */

session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/approvals.php';

adminRequireLogin(false);

$db = getDB();

adminEnsureCsrfToken();

// Get current admin info with new role system
$currentAdmin = $db->fetchOne(
    "SELECT * FROM admin_users WHERE id = ?",
    [$_SESSION['admin_id']]
);

if (!$currentAdmin || empty($currentAdmin['is_active'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Update session with new role system
$_SESSION['admin_role'] = $currentAdmin['role'];
$_SESSION['is_administrator'] = ($currentAdmin['role'] === 'administrator');
$_SESSION['is_editor'] = ($currentAdmin['role'] === 'editor');
$_SESSION['is_supervisor'] = ($currentAdmin['role'] === 'supervisor');
$_SESSION['is_super_admin'] = ($currentAdmin['role'] === 'administrator');

// Check permissions
function hasPermission($permission) {
    global $db;
    return adminHasPermission($db, $permission);
}

// Handle role management actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireCsrf(null, false);
    $requestedActions = array_filter([
        'change_role' => isset($_POST['change_role']),
        'action_decision' => isset($_POST['action_decision'])
    ]);
    if (count($requestedActions) !== 1) {
        $message = 'Submit one role-management action at a time.';
        $messageType = 'error';
    } else {

    // Change user role
    if (isset($_POST['change_role'])) {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['new_role'] ?? '';
        $userType = $_POST['user_type'] ?? '';

        $requiredPermission = $userType === 'admin' ? 'manage_admins' : 'manage_users';
        if (!hasPermission($requiredPermission)) {
            $message = 'You do not have permission to change this account type.';
            $messageType = 'error';
            $newRole = null;
        }

        $allowedRoles = $userType === 'admin'
            ? ['supervisor', 'editor', 'administrator']
            : ['donor', 'agent'];
        if ($userId < 1 || !in_array($userType, ['admin', 'regular'], true) || !in_array($newRole, $allowedRoles, true)) {
            $message = 'That role is not available for this account type.';
            $messageType = 'error';
            $newRole = null;
        }
        
        try {
            if ($newRole === null) throw new DomainException($message);
            if ($userType === 'admin' && $userId === (int)$_SESSION['admin_id']) {
                throw new DomainException('You cannot change your own staff role. Ask another administrator to make this change.');
            }
            if ($newRole === 'agent') {
                $roleColumn = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'role'");
                if (!$roleColumn || strpos((string)$roleColumn['Type'], "'agent'") === false) {
                    throw new DomainException('Reservation agents require the database migration. Run database/migrations/2026-09-14-agent-reservations.sql once in phpMyAdmin.');
                }
            }
            $db->getConnection()->beginTransaction();
            if ($userType === 'admin') {
                $existingAdmin = $db->fetchOne("SELECT id, role FROM admin_users WHERE id = ? AND is_active = 1 FOR UPDATE", [$userId]);
                if (!$existingAdmin) {
                    throw new DomainException('The selected staff account no longer exists. Refresh the page and try again.');
                }
                if ($existingAdmin['role'] === 'administrator' && $newRole !== 'administrator') {
                    $activeAdministrators = $db->fetchAll("SELECT id FROM admin_users WHERE role = 'administrator' AND is_active = 1 FOR UPDATE");
                    if (count($activeAdministrators) <= 1) {
                        throw new DomainException('At least one active administrator must remain.');
                    }
                }
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
                $existingUser = $db->fetchOne("SELECT role FROM users WHERE id = ? AND is_active = 1 FOR UPDATE", [$userId]);
                if (!$existingUser) {
                    throw new DomainException('The selected user no longer exists. Refresh the page and try again.');
                }
                $oldRole = $existingUser['role'] ?? 'donor';
                // Update users table
                $db->query(
                    "UPDATE users SET role = ? WHERE id = ?",
                    [$newRole, $userId]
                );
                
                // Log in user_role_changes
                $db->query(
                    "INSERT INTO user_role_changes (user_id, old_role, new_role, changed_by, created_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [$userId, $oldRole, $newRole, $_SESSION['admin_id']]
                );
            }

            $db->getConnection()->commit();
            $message = 'Role updated successfully.';
            $messageType = "success";
            
        } catch (Throwable $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->getConnection()->rollBack();
            }
            error_log('Role management update failed: ' . $e->getMessage());
            $message = $e instanceof InvalidArgumentException || $e instanceof DomainException
                ? $e->getMessage()
                : 'Unable to update the role. Please try again.';
            $messageType = "error";
        }
    }
    
    // Approve/reject pending actions
    if (isset($_POST['action_decision'])) {
        $actionId = (int)($_POST['action_id'] ?? 0);
        $decision = $_POST['decision'] ?? ''; // 'approve' or 'reject'
        
        try {
            if (!hasPermission('super_admin_approval')) {
                throw new DomainException('You do not have permission to process approvals.');
            }
            $message = adminProcessApproval($db, $actionId, $decision, $_SESSION['admin_id']);
            $messageType = "success";
            
        } catch (Throwable $e) {
            error_log('Role management approval failed: ' . $e->getMessage());
            $message = $e instanceof InvalidArgumentException || $e instanceof DomainException
                ? $e->getMessage()
                : 'Unable to process this approval. Please try again.';
            $messageType = "error";
        }
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

// Reservation agents are normal donor accounts that may book on behalf of others.
$elevatedUsers = $db->fetchAll("
    SELECT u.*, rh.role_display_name, rh.hierarchy_level
    FROM users u
    LEFT JOIN role_hierarchy rh ON u.role = rh.role_name
    WHERE u.role = 'agent' AND u.is_active = 1
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
    <link rel="stylesheet" href="../assets/css/admin.css?v=20260915">
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
        .role-agent { border-left: 5px solid #9b59b6; }
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
        .role-badge.agent { background-color: #9b59b6; }
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
    <div class="admin-panel">
        <?php $adminPageTitle = 'Roles & Access'; $adminPageIcon = 'fa-user-shield'; include 'includes/header.php'; ?>
        <main class="role-management">
        <div class="admin-page-heading">
            <div><h1>Roles &amp; access</h1><p>Manage staff permissions and reservation-agent access.</p></div>
        </div>

        <div class="role-card" style="border-left: 4px solid #d4822a;">
            <h3><i class="fas fa-user-friends"></i> Assigning reservation agents</h3>
            <p>Use <strong>User Management</strong> on the dashboard to find a donor, select <strong>Reservation Agent</strong>, and save. Agents are donor-dashboard users who may place reservations for other people; they are not staff administrators.</p>
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
                    combined.role,
                    COUNT(*) as count,
                    rh.role_display_name,
                    rh.hierarchy_level
                FROM (
                    SELECT role FROM admin_users WHERE is_active = 1
                    UNION ALL
                    SELECT role FROM users WHERE role = 'agent' AND is_active = 1
                ) combined
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
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                                        <input type="hidden" name="action_decision" value="1">
                                        <button type="submit" name="decision" value="approve" class="btn btn-success">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="decision" value="reject" class="btn btn-danger">
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
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                                            <select name="new_role" onchange="this.form.submit()">
                                                <option value="">Change Role...</option>
                                                <?php foreach ($roleHierarchy as $role): ?>
                                                    <?php if (!in_array($role['role_name'], ['donor', 'agent'], true) && $role['role_name'] !== $user['role']): ?>
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
                <h3><i class="fas fa-user-friends"></i> Reservation Agents</h3>
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
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                                        <select name="new_role" onchange="this.form.submit()">
                                            <option value="">Change Role...</option>
                                            <option value="donor">Demote to Donor</option>
                                            <?php if ($user['role'] !== 'agent'): ?>
                                                <option value="agent">Make Reservation Agent</option>
                                            <?php endif; ?>
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
        </main>
    </div>
</body>
</html>
