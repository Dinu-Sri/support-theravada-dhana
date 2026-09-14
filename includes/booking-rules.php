<?php

function bookingSettingInt($db, $key, $default, $minimum = 0, $maximum = 2147483647) {
    $row = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    $value = $row ? filter_var($row['setting_value'], FILTER_VALIDATE_INT) : false;
    if ($value === false) {
        return $default;
    }
    return max($minimum, min($maximum, (int)$value));
}

function parseBookingDate($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value) {
        return null;
    }
    return $date;
}

function getBookableDhanaType($db, $dhanaTypeId) {
    return $db->fetchOne(
        "SELECT id, name, price, description, time_slot FROM dhana_types WHERE id = ? AND is_active = 1 AND price > 0",
        [(int)$dhanaTypeId]
    );
}

function getEffectiveBookingPrice($db, $dhanaType, DateTime $date) {
    $year = (int)$date->format('Y');
    $month = (int)$date->format('n');
    $monthly = $db->fetchOne(
        "SELECT price, is_confirmed FROM monthly_pricing WHERE dhana_type_id = ? AND year = ? AND month = ?",
        [$dhanaType['id'], $year, $month]
    );

    $source = 'monthly_pricing';
    if (!$monthly) {
        $monthly = $db->fetchOne(
            "SELECT price, is_confirmed FROM monthly_pricing
             WHERE dhana_type_id = ? AND (year < ? OR (year = ? AND month < ?))
             ORDER BY year DESC, month DESC LIMIT 1",
            [$dhanaType['id'], $year, $year, $month]
        );
        $source = $monthly ? 'previous_month' : 'base_price';
    }

    $price = $monthly ? (float)$monthly['price'] : (float)$dhanaType['price'];
    $windowMonths = bookingSettingInt($db, 'pricing_window_months', 21, 1, 60);
    $windowEnd = new DateTime('first day of this month');
    $windowEnd->modify('+' . $windowMonths . ' months');
    $isTentative = !$monthly || !(bool)$monthly['is_confirmed'] || $date >= $windowEnd;

    return [
        'price' => $price,
        'is_tentative' => $isTentative,
        'source' => $source
    ];
}

function validateBookingDate($db, DateTime $date, $enforceAdvanceWindow = true) {
    $today = new DateTime('today');
    if ($date < $today) {
        return 'Reservation date cannot be in the past.';
    }

    if ($enforceAdvanceWindow) {
        $advanceDays = bookingSettingInt($db, 'booking_advance_days', 30, 1, 730);
        $maxDate = (clone $today)->modify('+' . $advanceDays . ' days');
        if ($date > $maxDate) {
            return 'Reservations can only be made up to ' . $advanceDays . ' days in advance.';
        }
    }

    $blocked = $db->fetchOne("SELECT id FROM blocked_dates WHERE blocked_date = ?", [$date->format('Y-m-d')]);
    return $blocked ? 'This date is blocked and cannot be reserved.' : null;
}

function findBookingConflicts($db, $date, $timeSlot) {
    if ($timeSlot === 'whole_day') {
        return $db->fetchAll(
            "SELECT id FROM bookings WHERE booking_date = ? AND status <> 'cancelled'",
            [$date]
        );
    }

    return $db->fetchAll(
        "SELECT id FROM bookings WHERE booking_date = ? AND status <> 'cancelled'
         AND (booking_time_slot = ? OR booking_time_slot = 'whole_day')",
        [$date, $timeSlot]
    );
}

function acquireBookingDateLock($db, $date) {
    $row = $db->fetchOne("SELECT GET_LOCK(?, 10) AS acquired", ['support_theravada_booking_' . $date]);
    return $row && (int)$row['acquired'] === 1;
}

function releaseBookingDateLock($db, $date) {
    $db->fetchOne("SELECT RELEASE_LOCK(?)", ['support_theravada_booking_' . $date]);
}
