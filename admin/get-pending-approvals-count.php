<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/security.php';

adminRequireLogin(true);
$db = getDB();
adminRequirePermission($db, 'super_admin_approval', true);
header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_actions WHERE status = 'pending'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Unable to load pending approval count: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load pending approvals.']);
}
?>
