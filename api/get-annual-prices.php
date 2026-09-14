<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/booking-rules.php';

try {
    $typeId = (int)($_GET['dhana_type_id'] ?? 0);
    $startDate = parseBookingDate($_GET['start_date'] ?? '');
    if (!$startDate || $typeId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid date or dāna type']);
        exit;
    }
    if ($startDate->format('m-d') === '02-29') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Annual reservations cannot start on February 29']);
        exit;
    }

    $db = getDB();
    $type = getBookableDhanaType($db, $typeId);
    if (!$type) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Dāna type is not available']);
        exit;
    }
    $years = bookingSettingInt($db, 'annual_booking_years', 2, 1, 25);
    $prices = [];
    $confirmedTotal = 0;
    $tentativeTotal = 0;
    $confirmedCount = 0;
    $tentativeCount = 0;
    for ($offset = 0; $offset < $years; $offset++) {
        $date = clone $startDate;
        if ($offset) $date->modify('+' . $offset . ' year');
        $pricing = getEffectiveBookingPrice($db, $type, $date);
        if ($pricing['is_tentative']) {
            $tentativeTotal += $pricing['price'];
            $tentativeCount++;
        } else {
            $confirmedTotal += $pricing['price'];
            $confirmedCount++;
        }
        $prices[] = [
            'year_number' => $offset + 1,
            'date' => $date->format('Y-m-d'),
            'display_date' => $date->format('F j, Y'),
            'price' => $pricing['price'],
            'status' => $pricing['is_tentative'] ? 'tentative' : 'confirmed',
            'is_within_window' => !$pricing['is_tentative'],
            'notes' => $pricing['is_tentative'] ? 'Estimated price; final amount may change.' : null
        ];
    }
    echo json_encode([
        'success' => true,
        'dhana_type_name' => $type['name'],
        'annual_years' => $years,
        'prices' => $prices,
        'summary' => [
            'total_confirmed' => $confirmedTotal,
            'total_tentative' => $tentativeTotal,
            'confirmed_count' => $confirmedCount,
            'tentative_count' => $tentativeCount,
            'not_set_count' => 0,
            'grand_total' => $confirmedTotal + $tentativeTotal
        ]
    ]);
} catch (Throwable $error) {
    error_log('Annual price error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load annual pricing right now']);
}

