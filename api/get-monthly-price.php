<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/booking-rules.php';

try {
    $typeId = (int)($_GET['dhana_type_id'] ?? 0);
    $date = parseBookingDate($_GET['date'] ?? '');
    if (!$date || $typeId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid date or dāna type']);
        exit;
    }
    $db = getDB();
    $type = getBookableDhanaType($db, $typeId);
    if (!$type) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Dāna type is not available']);
        exit;
    }
    $pricing = getEffectiveBookingPrice($db, $type, $date);
    echo json_encode([
        'success' => true,
        'price' => $pricing['price'],
        'is_tentative' => $pricing['is_tentative'],
        'is_confirmed' => !$pricing['is_tentative'],
        'source' => $pricing['source'],
        'dhana_type_name' => $type['name'],
        'month' => (int)$date->format('n'),
        'year' => (int)$date->format('Y')
    ]);
} catch (Throwable $error) {
    error_log('Monthly price error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load pricing right now']);
}

