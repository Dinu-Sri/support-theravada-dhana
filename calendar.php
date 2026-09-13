<?php
require_once 'includes/auth.php';

// Require user to be logged in
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Get current month and year
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Ensure valid month/year
if ($currentMonth < 1 || $currentMonth > 12) $currentMonth = date('n');
if ($currentYear < date('Y') || $currentYear > date('Y') + 2) $currentYear = date('Y');

// Get dhana types
$dhanaTypes = $db->fetchAll("SELECT * FROM dhana_types WHERE is_active = 1 ORDER BY price DESC");

// Get reservations for the current month with time slot information (including annual events)
$bookings = $db->fetchAll(
    "SELECT booking_date, dhana_type_id, booking_time_slot, COUNT(*) as booking_count,
            MAX(is_annual_event) as has_annual_event
     FROM bookings
     WHERE MONTH(booking_date) = ? AND YEAR(booking_date) = ?
     AND status NOT IN ('cancelled')
     GROUP BY booking_date, dhana_type_id, booking_time_slot",
    [$currentMonth, $currentYear]
);

// Get annual reservations that should appear in this month/year
$annualBookings = $db->fetchAll(
    "SELECT b.booking_date, b.dhana_type_id, b.booking_time_slot,
            COUNT(*) as booking_count, 1 as is_annual
     FROM bookings b
     JOIN annual_bookings ab ON (b.id = ab.booking_id OR b.parent_booking_id = ab.booking_id)
     WHERE MONTH(b.booking_date) = ?
     AND ? BETWEEN ab.year_start AND ab.year_end
     AND b.status NOT IN ('cancelled')
     GROUP BY b.booking_date, b.dhana_type_id, b.booking_time_slot",
    [$currentMonth, $currentYear]
);

// Merge annual reservations with regular reservations
foreach ($annualBookings as $annualBooking) {
    $found = false;
    foreach ($bookings as &$booking) {
        if ($booking['booking_date'] === $annualBooking['booking_date'] &&
            $booking['dhana_type_id'] === $annualBooking['dhana_type_id'] &&
            $booking['booking_time_slot'] === $annualBooking['booking_time_slot']) {
            $booking['has_annual_event'] = 1;
            $found = true;
            break;
        }
    }
    if (!$found) {
        // Create a virtual reservation entry for the annual event
        $virtualDate = $currentYear . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . '-' .
                      str_pad(date('d', strtotime($annualBooking['booking_date'])), 2, '0', STR_PAD_LEFT);
        $bookings[] = [
            'booking_date' => $virtualDate,
            'dhana_type_id' => $annualBooking['dhana_type_id'],
            'booking_time_slot' => $annualBooking['booking_time_slot'],
            'booking_count' => $annualBooking['booking_count'],
            'has_annual_event' => 1
        ];
    }
}

// Get blocked dates
$blockedDates = $db->fetchAll(
    "SELECT blocked_date 
     FROM blocked_dates 
     WHERE MONTH(blocked_date) = ? AND YEAR(blocked_date) = ?",
    [$currentMonth, $currentYear]
);

// Convert to arrays for easier access with time slot information
$bookingsByDate = [];
$wholeDayBookings = []; // Track dates with whole day reservations

foreach ($bookings as $booking) {
    $date = $booking['booking_date'];
    $dhanaTypeId = $booking['dhana_type_id'];
    $timeSlot = $booking['booking_time_slot'];

    if (!isset($bookingsByDate[$date])) {
        $bookingsByDate[$date] = [];
    }

    // Store reservation with time slot information
    if (!isset($bookingsByDate[$date][$dhanaTypeId])) {
        $bookingsByDate[$date][$dhanaTypeId] = [];
    }
    $bookingsByDate[$date][$dhanaTypeId][$timeSlot] = $booking['booking_count'];

    // Track whole day reservations
    if ($timeSlot === 'whole_day') {
        $wholeDayBookings[] = $date;
    }
}

// Remove duplicates from whole day reservations
$wholeDayBookings = array_unique($wholeDayBookings);

$blockedDatesArray = array_column($blockedDates, 'blocked_date');

// Calendar helper functions
function getMonthName($month) {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    return $months[$month];
}

function getDaysInMonth($month, $year) {
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

function getFirstDayOfWeek($month, $year) {
    return date('w', mktime(0, 0, 0, $month, 1, $year));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Availability - Dāna Reservation System</title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/calendar.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- Header -->
        <div class="dashboard-header">
            <h1><i class="fas fa-calendar-alt"></i> Check Availability</h1>
            <p>Select your preferred date for dāna reservation</p>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Calendar Navigation -->
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <a href="?month=<?php echo $currentMonth == 1 ? 12 : $currentMonth - 1; ?>&year=<?php echo $currentMonth == 1 ? $currentYear - 1 : $currentYear; ?>" class="nav-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <h2 id="monthYearDisplay"><?php echo getMonthName($currentMonth) . ' ' . $currentYear; ?></h2>
                    <a href="?month=<?php echo $currentMonth == 12 ? 1 : $currentMonth + 1; ?>&year=<?php echo $currentMonth == 12 ? $currentYear + 1 : $currentYear; ?>" class="nav-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>

            <!-- Legend -->
            <div class="calendar-legend">
                <div class="legend-item">
                    <div class="legend-color available"></div>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color partially-booked"></div>
                    <span>Partially Booked</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color fully-booked"></div>
                    <span>Fully Booked</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color blocked"></div>
                    <span>Blocked</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color whole-day-blocked"></div>
                    <span>Whole Day Booked</span>
                </div>
                <div class="legend-item">
                    <div class="annual-event-badge" style="position: static; margin-right: 8px;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <span>Annual Event</span>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-grid">
                <!-- Day headers -->
                <div class="day-header">Sun</div>
                <div class="day-header">Mon</div>
                <div class="day-header">Tue</div>
                <div class="day-header">Wed</div>
                <div class="day-header">Thu</div>
                <div class="day-header">Fri</div>
                <div class="day-header">Sat</div>

                <?php
                $daysInMonth = getDaysInMonth($currentMonth, $currentYear);
                $firstDayOfWeek = getFirstDayOfWeek($currentMonth, $currentYear);
                $today = date('Y-m-d');
                
                // Empty cells for days before the first day of the month
                for ($i = 0; $i < $firstDayOfWeek; $i++) {
                    echo '<div class="day-cell empty"></div>';
                }
                
                // Days of the month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                    $isPast = $date < $today;
                    $isBlocked = in_array($date, $blockedDatesArray);
                    
                    // Calculate availability considering whole day reservations
                    $totalDhanaTypes = count($dhanaTypes);
                    $hasWholeDayBooking = in_array($date, $wholeDayBookings);
                    $bookedTypes = isset($bookingsByDate[$date]) ? count($bookingsByDate[$date]) : 0;

                    $cssClass = 'day-cell';
                    if ($isPast) {
                        $cssClass .= ' past';
                    } elseif ($isBlocked) {
                        $cssClass .= ' blocked';
                    } elseif ($hasWholeDayBooking) {
                        // If there's a whole day reservation, the entire day is blocked
                        $cssClass .= ' fully-booked whole-day-blocked';
                    } elseif ($bookedTypes == 0) {
                        $cssClass .= ' available';
                    } elseif ($bookedTypes < $totalDhanaTypes) {
                        $cssClass .= ' partially-booked';
                    } else {
                        $cssClass .= ' fully-booked';
                    }
                    
                    if ($date == $today) {
                        $cssClass .= ' today';
                    }
                    
                    echo '<div class="' . $cssClass . '" data-date="' . $date . '" onclick="showDateDetails(\'' . $date . '\')">';
                    echo '<span class="day-number">' . $day . '</span>';

                    // Check if this date has annual events
                    $hasAnnualEvent = false;
                    if (isset($bookingsByDate[$date])) {
                        foreach ($bookingsByDate[$date] as $dhanaTypeId => $timeSlots) {
                            foreach ($bookings as $booking) {
                                if ($booking['booking_date'] === $date &&
                                    $booking['dhana_type_id'] == $dhanaTypeId &&
                                    isset($booking['has_annual_event']) && $booking['has_annual_event']) {
                                    $hasAnnualEvent = true;
                                    break 2;
                                }
                            }
                        }
                    }

                    // Show annual event badge if applicable
                    if ($hasAnnualEvent) {
                        echo '<div class="annual-event-badge">';
                        echo '<i class="fas fa-calendar-check"></i>';
                        echo '</div>';
                    }

                    // Show availability indicator only if not past, not blocked, and not fully blocked by whole day reservation
                    if (!$isPast && !$isBlocked && !$hasWholeDayBooking && $bookedTypes < $totalDhanaTypes) {
                        echo '<div class="availability-indicator">';
                        echo '<i class="fas fa-plus-circle"></i>';
                        echo '</div>';
                    } elseif (!$isPast && !$isBlocked && $hasWholeDayBooking) {
                        // Show a different indicator for whole day blocked dates
                        echo '<div class="whole-day-indicator">';
                        echo '<i class="fas fa-ban"></i>';
                        echo '</div>';
                    }

                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Date Details Modal -->
        <div id="dateModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalDate"></h3>
                    <span class="close" onclick="closeDateModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="modalContent"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeDateModal()">Close</button>
                    <a id="bookNowBtn" href="#" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-plus-circle"></i> Book Now
                    </a>
                </div>
            </div>
        </div>

        <!-- Month/Year Picker Modal -->
        <div id="monthYearModal" class="modal">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h3><i class="fas fa-calendar-alt"></i> Jump to Month</h3>
                    <span class="close" onclick="closeMonthYearModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div style="padding: 20px;">
                        <div style="margin-bottom: 20px;">
                            <label for="jumpMonth" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                <i class="fas fa-calendar"></i> Select Month:
                            </label>
                            <select id="jumpMonth" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label for="jumpYear" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                <i class="fas fa-calendar-day"></i> Select Year:
                            </label>
                            <select id="jumpYear" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                                <?php
                                $currentYearNow = date('Y');
                                for ($y = $currentYearNow; $y <= $currentYearNow + 2; $y++) {
                                    echo "<option value='$y'>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeMonthYearModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="jumpToMonthYear()">
                        <i class="fas fa-arrow-right"></i> Go to Month
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dhanaTypes = <?php echo json_encode($dhanaTypes); ?>;
        const bookingsByDate = <?php echo json_encode($bookingsByDate); ?>;
        const blockedDates = <?php echo json_encode($blockedDatesArray); ?>;
        const wholeDayBookings = <?php echo json_encode($wholeDayBookings); ?>;
        const today = '<?php echo $today; ?>';
        const currentMonth = <?php echo $currentMonth; ?>;
        const currentYear = <?php echo $currentYear; ?>;
    </script>
    <script src="assets/js/calendar.js"></script>
</body>
</html>
