<?php
/**
 * Receipt Viewer - Secure receipt file viewer for admin panel
 * Checks admin authentication before serving receipt files
 */

session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied. Please log in as admin.');
}

$db = getDB();

// Permission checking function
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

// Check if user has permission to view receipt files
if (!hasPermission('view_receipt_files')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied. You do not have permission to view receipt files.');
}

// Get receipt filename from query parameter
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    header('HTTP/1.0 400 Bad Request');
    exit('No receipt file specified.');
}

// Sanitize filename to prevent directory traversal attacks
$filename = basename($filename);

// Build full file path
$filePath = '../' . UPLOAD_DIR . $filename;

// Check if file exists
if (!file_exists($filePath)) {
    header('HTTP/1.0 404 Not Found');
    exit('Receipt file not found.');
}

// Get file extension
$fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Validate file type
if (!in_array($fileExtension, ALLOWED_FILE_TYPES)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Invalid file type.');
}

// Set appropriate content type
$contentTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf'
];

$contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=3600');

// Output file
readfile($filePath);
exit;

