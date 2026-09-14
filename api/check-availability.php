<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/booking-rules.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
$auth = getAuth();
if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout() || !$auth->getCurrentUser()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
    $dateValue = $input['date'] ?? '';
    $typeId = (int)($input['dhana_type_id'] ?? 0);
    $date = parseBookingDate($dateValue);
    $db = getDB();
    $type = getBookableDhanaType($db, $typeId);
    if (!$date || !$type) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date or dāna type']);
        exit;
    }
    $dateError = validateBookingDate($db, $date);
    if ($dateError) {
        echo json_encode(['available' => false, 'reason' => $dateError]);
        exit;
    }
    if (findBookingConflicts($db, $dateValue, $type['time_slot'])) {
        echo json_encode(['available' => false, 'reason' => 'This offering is already reserved for the selected date.']);
        exit;
    }
    echo json_encode(['available' => true, 'date' => $dateValue, 'dhana_type' => ['id' => (int)$type['id'], 'name' => $type['name']]]);
} catch (JsonException $error) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
} catch (Throwable $error) {
    error_log('Legacy availability error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to check availability right now']);
}
