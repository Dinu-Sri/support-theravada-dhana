<?php

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$receiptId = (int)($_GET['id'] ?? 0);
$receipt = getDB()->fetchOne(
    "SELECT pr.receipt_filename FROM payment_receipts pr
     JOIN bookings b ON b.id = pr.booking_id
     WHERE pr.id = ? AND b.user_id = ?",
    [$receiptId, $user['id']]
);
if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found');
}

$filename = basename($receipt['receipt_filename']);
$path = __DIR__ . '/uploads/receipts/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('Receipt file not found');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit('Unsupported receipt type');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="receipt.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
header('Cache-Control: private, no-store');
readfile($path);
exit;
