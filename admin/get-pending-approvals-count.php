<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json; charset=UTF-8');

// Check if user is logged in and is super admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['is_super_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    $pdo = getDB()->getConnection();
    
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
