<?php
/**
 * Receipt Viewer - Secure receipt file viewer for admin panel
 * Checks admin authentication before serving receipt files
 */

session_start();
require_once '../config/database.php';
require_once 'includes/security.php';

adminRequireLogin(false);
$db = getDB();
adminRequirePermission($db, 'view_receipt_files', false);

// Get receipt filename from query parameter
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    header('HTTP/1.0 400 Bad Request');
    exit('No receipt file specified.');
}

// Sanitize filename to prevent directory traversal attacks
$filename = basename($filename);

// Resolve the configured receipt directory and enforce path containment.
$receiptDirectory = realpath(__DIR__ . '/../' . rtrim(UPLOAD_DIR, '/\\'));
$filePath = $receiptDirectory === false
    ? false
    : realpath($receiptDirectory . DIRECTORY_SEPARATOR . $filename);

if (
    $filePath === false ||
    strpos($filePath, $receiptDirectory . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($filePath)
) {
    header('HTTP/1.0 404 Not Found');
    exit('Receipt file not found.');
}

// Only stream files that are registered as payment receipts.
$receipt = $db->fetchOne(
    'SELECT id FROM payment_receipts WHERE receipt_filename = ? LIMIT 1',
    [$filename]
);
if (!$receipt) {
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
header('Content-Disposition: inline; filename="receipt.' . $fileExtension . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:");

// Output file
readfile($filePath);
exit;
