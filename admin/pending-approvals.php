<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['is_super_admin']) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'])) {
        $errorMessage = 'Your session has expired. Refresh the page and try again.';
    } else {
    $actionId = $_POST['action_id'] ?? null;
    $decision = $_POST['decision'] ?? null; // 'approve' or 'reject'
    
    if ($actionId && in_array($decision, ['approve', 'reject'])) {
        try {
            $pdo = getDB()->getConnection();
            
            $pdo->beginTransaction();
            
            // Get the action details
            $stmt = $pdo->prepare("SELECT * FROM admin_actions WHERE id = ? AND status = 'pending'");
            $stmt->execute([$actionId]);
            $action = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($action) {
                if ($decision === 'approve') {
                    // Apply the changes based on action type
                    switch ($action['action_type']) {
                        case 'booking_update':
                            // Apply booking status change
                            $newValues = json_decode($action['new_values'], true);
                            if (isset($newValues['status'])) {
                                $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                                $stmt->execute([$newValues['status'], $action['target_id']]);
                            }
                            break;
                        // Add more action types as needed
                    }
                    
                    $status = 'approved';
                    $message = 'Action approved and applied successfully';
                } else {
                    $status = 'rejected';
                    $message = 'Action rejected';
                }
                
                // Update action status
                $stmt = $pdo->prepare("
                    UPDATE admin_actions 
                    SET status = ?, approved_by = ?, approved_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $_SESSION['admin_id'], $actionId]);
                
                $pdo->commit();
                $successMessage = $message;
            } else {
                $errorMessage = 'Action not found or already processed';
            }
            
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Pending approval update failed: ' . $e->getMessage());
            $errorMessage = 'Unable to process this approval. Please try again.';
        }
    }
    }
}

// Get pending actions
try {
    $pdo = getDB()->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT aa.*, au.username as admin_username, au.email as admin_email
        FROM admin_actions aa
        JOIN admin_users au ON aa.admin_id = au.id
        WHERE aa.status = 'pending'
        ORDER BY aa.created_at ASC
    ");
    $stmt->execute();
    $pendingActions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Throwable $e) {
    error_log('Unable to load pending approvals: ' . $e->getMessage());
    $errorMessage = 'Unable to load pending approvals. Please try again.';
    $pendingActions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals - Dāna Reservation System</title>
    <?php include '../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .approval-header-banner {
            background: linear-gradient(135deg, #d4822a 0%, #b8860b 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .approval-header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .approval-header-title h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .approval-header-title i {
            font-size: 1.8rem;
        }

        .approval-header-actions {
            display: flex;
            gap: 10px;
        }

        .header-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .header-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .section-header {
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0 0 5px 0;
            font-size: 1.3rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .admin-content {
            padding: 0;
        }

        .main-content {
            padding: 0 0 30px 0;
        }

        .approvals-list {
            padding: 0 30px;
        }

        .alert {
            margin: 20px 30px;
        }
    </style>
</head>
<body>
    <div class="admin-panel">
        <!-- Admin Header -->
        <div class="approval-header-banner">
            <div class="approval-header-title">
                <i class="fas fa-tasks"></i>
                <h1>Pending Approvals</h1>
            </div>
            <div class="approval-header-actions">
                <a href="index.php" class="header-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="?logout=1" class="header-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="admin-content">
            <div class="main-content">
                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <div class="section-header">
                    <h2><i class="fas fa-hourglass-half"></i> Actions Awaiting Approval</h2>
                    <p>Review and approve or reject admin actions • <?php echo count($pendingActions); ?> pending</p>
                </div>

                <?php if (empty($pendingActions)): ?>
                    <div class="empty-state" style="padding: 60px 30px; text-align: center;">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745; margin-bottom: 20px; opacity: 0.7;"></i>
                        <h3 style="color: #28a745; margin-bottom: 10px;">All Clear!</h3>
                        <p style="color: #666; margin: 0;">No pending approvals at this time. All admin actions have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <div class="approvals-list">
                        <?php foreach ($pendingActions as $action): ?>
                            <div class="approval-card">
                                <div class="approval-header">
                                    <div class="action-info">
                                        <h4><?php echo ucfirst(str_replace('_', ' ', $action['action_type'])); ?></h4>
                                        <div class="action-meta">
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($action['admin_username']); ?></span>
                                            <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($action['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="action-id">
                                        #<?php echo str_pad($action['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </div>
                                    <div class="approval-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action_id" value="<?php echo $action['id']; ?>">
                                            <input type="hidden" name="decision" value="approve">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                                            <button type="submit" class="btn-approve" onclick="return confirm('Are you sure you want to approve this action?')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>

                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action_id" value="<?php echo $action['id']; ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                                            <button type="submit" class="btn-reject" onclick="return confirm('Are you sure you want to reject this action?')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="approval-body">
                                    <div class="action-description">
                                        <p><?php echo htmlspecialchars($action['action_description']); ?></p>
                                    </div>

                                    <?php if ($action['old_values'] || $action['new_values']): ?>
                                        <div class="value-changes">
                                            <?php if ($action['old_values']): ?>
                                                <div class="value-box old-values">
                                                    <div class="value-label">
                                                        <i class="fas fa-arrow-left"></i> Previous
                                                    </div>
                                                    <div class="value-content">
                                                        <?php
                                                        $oldData = json_decode($action['old_values'], true);
                                                        if (isset($oldData['status'])) {
                                                            echo htmlspecialchars(ucfirst(str_replace('_', ' ', $oldData['status'])));
                                                        } else {
                                                            echo htmlspecialchars(json_encode($oldData, JSON_PRETTY_PRINT));
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($action['new_values']): ?>
                                                <div class="value-box new-values">
                                                    <div class="value-label">
                                                        <i class="fas fa-arrow-right"></i> New
                                                    </div>
                                                    <div class="value-content">
                                                        <?php
                                                        $newData = json_decode($action['new_values'], true);
                                                        if (isset($newData['status'])) {
                                                            echo htmlspecialchars(ucfirst(str_replace('_', ' ', $newData['status'])));
                                                        } else {
                                                            echo htmlspecialchars(json_encode($newData, JSON_PRETTY_PRINT));
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
