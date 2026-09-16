<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/approvals.php';

adminRequireLogin(false);
$db = getDB();
adminRequirePermission($db, 'super_admin_approval', false);
adminEnsureCsrfToken();

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireCsrf(null, false);
    $actionId = (int)($_POST['action_id'] ?? 0);
    $decision = $_POST['decision'] ?? null; // 'approve' or 'reject'
    
    if ($actionId > 0 && in_array($decision, ['approve', 'reject'], true)) {
        try {
            $successMessage = adminProcessApproval($db, $actionId, $decision, $_SESSION['admin_id']);
        } catch (Throwable $e) {
            adminLogException('Pending approval update failed', $e);
            $errorMessage = $e instanceof InvalidArgumentException || $e instanceof DomainException
                ? $e->getMessage()
                : 'Unable to process this approval. Please try again.';
        }
    } else {
        $errorMessage = 'Invalid approval request.';
    }
}

// Get pending actions
try {
    $pdo = $db->getConnection();
    
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
    adminLogException('Unable to load pending approvals', $e);
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
    <link rel="stylesheet" href="../assets/css/admin.css?v=20260916">
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
<body class="admin-approvals-page">
    <div class="admin-panel">
        <?php $adminPageTitle = 'Approvals'; $adminPageIcon = 'fa-clipboard-check'; include 'includes/header.php'; ?>

        <div class="admin-layout">
            <main class="admin-main-content">
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

                <div class="admin-page-heading">
                    <div>
                        <span class="admin-page-eyebrow">Governance</span>
                        <h1><i class="fas fa-clipboard-check"></i> Approval Queue</h1>
                        <p>Review sensitive changes requested by administrators.</p>
                    </div>
                    <span class="approval-count-summary"><?php echo count($pendingActions); ?> pending</span>
                </div>

                <?php if (empty($pendingActions)): ?>
                    <div class="admin-empty-state">
                        <span class="admin-empty-state-icon"><i class="fas fa-check"></i></span>
                        <h2>Approval queue is clear</h2>
                        <p>There are no sensitive administrator actions waiting for review.</p>
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
                                        <form method="POST" class="approval-decision-form" data-admin-confirm="Apply this approved action to the system?" data-admin-confirm-title="Approve this action?" data-admin-confirm-button="Approve action">
                                            <input type="hidden" name="action_id" value="<?php echo $action['id']; ?>">
                                            <input type="hidden" name="decision" value="approve">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                                            <button type="submit" class="btn btn-success btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>

                                        <form method="POST" class="approval-decision-form" data-admin-confirm="Reject this requested action? The requester will need to submit it again." data-admin-confirm-title="Reject this action?" data-admin-confirm-button="Reject action" data-admin-confirm-danger="1">
                                            <input type="hidden" name="action_id" value="<?php echo $action['id']; ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                                            <button type="submit" class="btn btn-danger btn-reject">
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
            </main>
        </div>
    </div>
</body>
</html>
