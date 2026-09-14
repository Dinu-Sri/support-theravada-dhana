<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/booking-rules.php';

$db = getDB();
$legacyIndex = $db->fetchOne("SHOW INDEX FROM bookings WHERE Key_name = 'unique_booking_date_slot'");
if ($legacyIndex) {
    throw new RuntimeException('Legacy booking index is still present; run the booking-integrity migration.');
}
$conflictIndex = $db->fetchOne("SHOW INDEX FROM bookings WHERE Key_name = 'idx_booking_date_type_slot'");
if (!$conflictIndex) {
    throw new RuntimeException('Booking conflict index is missing; run the booking-integrity migration.');
}

$candidate = $db->fetchOne(
    "SELECT b.* FROM bookings b
     WHERE b.status = 'cancelled'
       AND NOT EXISTS (
           SELECT 1 FROM bookings active
           WHERE active.booking_date = b.booking_date
             AND active.dhana_type_id = b.dhana_type_id
             AND active.booking_time_slot = b.booking_time_slot
             AND active.status <> 'cancelled'
       )
     LIMIT 1"
);

if ($candidate) {
    $connection = $db->getConnection();
    $connection->beginTransaction();
    try {
        $db->query(
            "INSERT INTO bookings (user_id, dhana_type_id, booking_date, booking_time_slot, total_amount, status)
             VALUES (?, ?, ?, ?, ?, 'pending')",
            [$candidate['user_id'], $candidate['dhana_type_id'], $candidate['booking_date'], $candidate['booking_time_slot'], $candidate['total_amount']]
        );
    } finally {
        $connection->rollBack();
    }
}

$type = getBookableDhanaType($db, 1);
if (!$type || !isset(getEffectiveBookingPrice($db, $type, new DateTime('today'))['price'])) {
    throw new RuntimeException('Shared pricing resolution failed.');
}

fwrite(STDOUT, 'Booking integrity database checks passed.' . PHP_EOL);
