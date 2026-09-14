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
    $timeSlot = $input['time_slot'] ?? '';
    $date = parseBookingDate($dateValue);
    if (!$date) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date']);
        exit;
    }

    $db = getDB();
    $type = getBookableDhanaType($db, $typeId);
    if (!$type || !in_array($timeSlot, ['morning', 'lunch', 'whole_day'], true) || $type['time_slot'] !== $timeSlot) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid dāna type or time slot']);
        exit;
    }
    $dateError = validateBookingDate($db, $date);
    if ($dateError) {
        echo json_encode(['available' => false, 'reason' => $dateError]);
        exit;
    }
    if (findBookingConflicts($db, $dateValue, $timeSlot)) {
        $reason = $timeSlot === 'whole_day'
            ? 'Cannot reserve the whole day because another reservation exists.'
            : 'This time slot is already reserved.';
        echo json_encode(['available' => false, 'reason' => $reason]);
        exit;
    }

    $pricing = getEffectiveBookingPrice($db, $type, $date);
    echo json_encode([
        'available' => true,
        'date' => $dateValue,
        'time_slot' => $timeSlot,
        'dhana_type' => ['id' => (int)$type['id'], 'name' => $type['name']],
        'price' => $pricing['price'],
        'is_tentative' => $pricing['is_tentative'],
        'message' => 'This slot is available for reservation.'
    ]);
} catch (JsonException $error) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
} catch (Throwable $error) {
    error_log('Availability check error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to check availability right now']);
}
