<?php

require_once __DIR__ . '/booking-rules.php';

function processBookingSubmission($db, $user, $post) {
    $errors = [];
    $dhanaTypeId = (int)($post['dhana_type_id'] ?? 0);
    $reservationDate = $post['booking_date'] ?? '';
    $timeSlot = $post['booking_time_slot'] ?? '';
    $specialRequests = trim($post['special_requests'] ?? '');
    $travelSupport = isset($post['travel_support']) ? 1 : 0;
    $isAnnualEvent = isset($post['is_annual_event']) ? 1 : 0;
    $isAgentBooking = (($user['role'] ?? 'donor') === 'agent') && (($post['book_for_other'] ?? '') === '1');
    $beneficiary = [
        'first_name' => trim((string)($post['booked_for_first_name'] ?? '')),
        'last_name' => trim((string)($post['booked_for_last_name'] ?? '')),
        'primary_contact' => trim((string)($post['booked_for_primary_contact'] ?? '')),
        'secondary_contact' => trim((string)($post['booked_for_secondary_contact'] ?? '')),
    ];

    if (!verifyCsrfToken($post['csrf_token'] ?? null)) $errors[] = 'Your session expired. Please refresh and try again.';
    $dhanaType = getBookableDhanaType($db, $dhanaTypeId);
    if (!$dhanaType) $errors[] = 'Please select an available dāna type.';

    $dateObject = parseBookingDate($reservationDate);
    if (!$dateObject) {
        $errors[] = 'Please select a valid reservation date.';
    } else {
        $dateError = validateBookingDate($db, $dateObject);
        if ($dateError) $errors[] = $dateError;
    }
    if (!$dhanaType || !in_array($timeSlot, ['morning', 'lunch', 'whole_day'], true) || $dhanaType['time_slot'] !== $timeSlot) {
        $errors[] = 'The selected time slot does not match the dāna type.';
    }
    if (mb_strlen($specialRequests) > 2000) $errors[] = 'Special requests must be 2,000 characters or fewer.';
    if ($isAgentBooking) {
        if (mb_strlen($beneficiary['first_name']) < 2 || mb_strlen($beneficiary['first_name']) > 50) {
            $errors[] = 'The reservation recipient’s first name must be between 2 and 50 characters.';
        }
        if (mb_strlen($beneficiary['last_name']) < 2 || mb_strlen($beneficiary['last_name']) > 50) {
            $errors[] = 'The reservation recipient’s last name must be between 2 and 50 characters.';
        }
        foreach (['primary_contact' => 'primary', 'secondary_contact' => 'secondary'] as $field => $label) {
            $number = $beneficiary[$field];
            if ($label === 'secondary' && $number === '') continue;
            if (!preg_match('/^[0-9+()\\-\\s]{5,20}$/', $number)) {
                $errors[] = 'Please enter a valid ' . $label . ' mobile number for the reservation recipient.';
            }
        }
    }
    if ($errors) return ['success' => false, 'errors' => array_values(array_unique($errors))];

    $connection = $db->getConnection();
    $lockedDates = [];
    try {
        $annualYears = $isAnnualEvent ? bookingSettingInt($db, 'annual_booking_years', 2, 1, 25) : 1;
        if ($isAnnualEvent && $dateObject->format('m-d') === '02-29' && $annualYears > 1) {
            throw new RuntimeException('Annual reservations cannot start on February 29. Please choose another date.');
        }

        $instances = [];
        for ($offset = 0; $offset < $annualYears; $offset++) {
            $instanceDate = clone $dateObject;
            if ($offset) $instanceDate->modify('+' . $offset . ' year');
            $dateError = validateBookingDate($db, $instanceDate, $offset === 0);
            if ($dateError) throw new RuntimeException($instanceDate->format('F j, Y') . ': ' . $dateError);
            $instances[] = ['date' => $instanceDate->format('Y-m-d'), 'object' => $instanceDate];
        }

        foreach ($instances as $instance) {
            if (!acquireBookingDateLock($db, $instance['date'])) throw new RuntimeException('The selected date is busy. Please try again.');
            $lockedDates[] = $instance['date'];
        }

        $connection->beginTransaction();
        foreach ($instances as $instance) {
            if (findBookingConflicts($db, $instance['date'], $timeSlot)) {
                throw new RuntimeException($instance['object']->format('F j, Y') . ' is no longer available.');
            }
        }

        $parentId = null;
        $firstPricing = null;
        foreach ($instances as $index => $instance) {
            $pricing = getEffectiveBookingPrice($db, $dhanaType, $instance['object']);
            if ($pricing['price'] <= 0) throw new RuntimeException('Pricing is not available for this reservation.');
            $db->query(
                "INSERT INTO bookings (user_id, booked_by_agent_id, booked_for_first_name, booked_for_last_name, booked_for_primary_contact, booked_for_secondary_contact, dhana_type_id, booking_date, booking_time_slot, special_requests, travel_support, is_annual_event, is_monk, total_amount, is_price_tentative, status, parent_booking_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
                [
                    $user['id'],
                    $isAgentBooking ? $user['id'] : null,
                    $isAgentBooking ? $beneficiary['first_name'] : null,
                    $isAgentBooking ? $beneficiary['last_name'] : null,
                    $isAgentBooking ? $beneficiary['primary_contact'] : null,
                    $isAgentBooking && $beneficiary['secondary_contact'] !== '' ? $beneficiary['secondary_contact'] : null,
                    $dhanaTypeId, $instance['date'], $timeSlot, $specialRequests, $travelSupport, $isAnnualEvent,
                    (int)($user['is_monk'] ?? 0), $pricing['price'], $pricing['is_tentative'] ? 1 : 0, $parentId
                ]
            );
            if ($index === 0) {
                $parentId = $db->lastInsertId();
                $firstPricing = $pricing;
            }
        }

        if ($isAnnualEvent) {
            $db->query(
                "INSERT INTO annual_bookings (booking_id, year_start, year_end) VALUES (?, ?, ?)",
                [$parentId, (int)$dateObject->format('Y'), (int)$instances[count($instances) - 1]['object']->format('Y')]
            );
        }
        $connection->commit();
        foreach ($lockedDates as $lockedDate) releaseBookingDateLock($db, $lockedDate);

        try {
            require_once __DIR__ . '/email.php';
            getEmailService()->sendBookingCreatedEmail([
                'user_email' => $user['email'], 'user_name' => $user['first_name'] . ' ' . $user['last_name'],
                'booking_id' => $parentId, 'dhana_type' => $dhanaType['name'], 'booking_date' => $reservationDate,
                'time_slot' => $timeSlot, 'amount' => $firstPricing['price'],
                'is_price_tentative' => $firstPricing['is_tentative'] ? 1 : 0,
                'booked_for_name' => $isAgentBooking ? trim($beneficiary['first_name'] . ' ' . $beneficiary['last_name']) : null
            ]);
        } catch (Throwable $emailError) {
            error_log('Booking email error: ' . $emailError->getMessage());
        }
        return ['success' => true, 'booking_id' => $parentId];
    } catch (Throwable $error) {
        if ($connection->inTransaction()) $connection->rollBack();
        foreach ($lockedDates as $lockedDate) {
            try { releaseBookingDateLock($db, $lockedDate); } catch (Throwable $ignored) {}
        }
        error_log('Reservation error: ' . $error->getMessage());
        $safe = preg_match('/(not available|cannot start|cannot be|only be made|busy|Pricing is not available)/i', $error->getMessage());
        return ['success' => false, 'errors' => [$safe ? $error->getMessage() : 'Unable to create the reservation. Please try again.']];
    }
}
