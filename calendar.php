<?php
require_once 'includes/auth.php';
require_once 'includes/booking-rules.php';

// Require user to be logged in
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Get current month and year within the configured reservation window.
$advanceDays = bookingSettingInt($db, 'booking_advance_days', 30, 1, 730);
$calendarMinDate = new DateTime('today');
$calendarMaxDate = (clone $calendarMinDate)->modify('+' . $advanceDays . ' days');
$calendarViewMaxDate = (clone $calendarMinDate)->modify('+12 months');
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$requestedMonth = DateTime::createFromFormat('!Y-n-j', $currentYear . '-' . $currentMonth . '-1');
$minMonth = new DateTime('first day of this month');
$maxMonth = (clone $calendarViewMaxDate)->modify('first day of this month');
if (!$requestedMonth || $requestedMonth < $minMonth || $requestedMonth > $maxMonth) {
    $requestedMonth = clone $minMonth;
}
$currentMonth = (int)$requestedMonth->format('n');
$currentYear = (int)$requestedMonth->format('Y');

// Get dhana types
$dhanaTypes = $db->fetchAll("SELECT * FROM dhana_types WHERE is_active = 1 AND price > 0 ORDER BY price DESC");

// Get reservations for the current month with time slot information (including annual events)
$bookings = $db->fetchAll(
    "SELECT booking_date, dhana_type_id, booking_time_slot, COUNT(*) as booking_count,
            MAX(is_annual_event) as has_annual_event
     FROM bookings
     WHERE booking_date >= ? AND booking_date < ?
     AND status NOT IN ('cancelled')
     GROUP BY booking_date, dhana_type_id, booking_time_slot",
    [$requestedMonth->format('Y-m-01'), (clone $requestedMonth)->modify('+1 month')->format('Y-m-01')]
);

// Annual instances are physical booking rows; do not synthesize ghost dates from metadata.

// Get blocked dates
$blockedDates = $db->fetchAll(
    "SELECT blocked_date 
     FROM blocked_dates 
     WHERE blocked_date >= ? AND blocked_date < ?",
    [$requestedMonth->format('Y-m-01'), (clone $requestedMonth)->modify('+1 month')->format('Y-m-01')]
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
    return ui_month_name((int)$month);
}

function getDaysInMonth($month, $year) {
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

function getFirstDayOfWeek($month, $year) {
    return date('w', mktime(0, 0, 0, $month, 1, $year));
}
$previousMonth = (clone $requestedMonth)->modify('-1 month');
$nextMonth = (clone $requestedMonth)->modify('+1 month');
?>
<!DOCTYPE html>
<?php echo ui_html_open(); ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo te('Check Availability - Dāna Reservation System', 'දින පරීක්ෂා කිරීම - දාන වෙන්කිරීමේ පද්ධතිය'); ?></title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/calendar.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pro-ui.css?v=20260915">
</head>
<body>
    <div class="dashboard">
        <!-- Header -->
        <div class="dashboard-header">
            <?php ui_lang_switch(); ?>
            <h1><i class="fas fa-calendar-alt"></i> <?php echo te('Check Availability', 'ලබා ගත හැකි දින පරීක්ෂා කරන්න'); ?></h1>
            <p><?php echo te('Select your preferred date for dāna reservation', 'දාන වෙන්කිරීම සඳහා ඔබට පහසු දිනය තෝරන්න'); ?></p>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo te('Back to Dashboard', 'ප්‍රධාන පුවරුවට'); ?>
            </a>
        </div>

        <!-- Calendar Navigation -->
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <?php if ($previousMonth >= $minMonth): ?>
                        <a aria-label="<?php echo te('Previous month', 'පෙර මාසය'); ?>" href="?month=<?php echo $previousMonth->format('n'); ?>&year=<?php echo $previousMonth->format('Y'); ?>" class="nav-btn"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="nav-btn disabled" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <h2 id="monthYearDisplay"><?php echo getMonthName($currentMonth) . ' ' . $currentYear; ?></h2>
                    <?php if ($nextMonth <= $maxMonth): ?>
                        <a aria-label="<?php echo te('Next month', 'ඊළඟ මාසය'); ?>" href="?month=<?php echo $nextMonth->format('n'); ?>&year=<?php echo $nextMonth->format('Y'); ?>" class="nav-btn"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="nav-btn disabled" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <p class="calendar-window-note">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <?php echo te('You can browse the next 12 months. Reservations are currently open through', 'ඉදිරි මාස 12 බලන්න පුළුවන්. වෙන්කිරීම් දැනට විවෘත වන්නේ'); ?>
                    <strong><?php echo htmlspecialchars(ui_format_date($calendarMaxDate->getTimestamp())); ?></strong><?php echo ui_lang() === 'si' ? ' දක්වා.' : '.'; ?>
                </p>
            </div>

            <!-- Legend -->
            <div class="calendar-legend">
                <div class="legend-item">
                    <div class="legend-color available"></div>
                    <span><?php echo te('Available', 'ලබා ගත හැක'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color partially-booked"></div>
                    <span><?php echo te('Partially Booked', 'අර්ධ වශයෙන් වෙන් වී ඇත'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color fully-booked"></div>
                    <span><?php echo te('Fully Booked', 'සම්පූර්ණයෙන් වෙන් වී ඇත'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color blocked"></div>
                    <span><?php echo te('Blocked', 'අවහිරයි'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color whole-day-blocked"></div>
                    <span><?php echo te('Whole Day Booked', 'සම්පූර්ණ දිනය වෙන් වී ඇත'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="annual-event-badge" style="position: static; margin-right: 8px;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <span><?php echo te('Annual Event', 'වාර්ෂික උත්සවය'); ?></span>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-grid">
                <!-- Day headers -->
                <div class="day-header"><?php echo te('Sun', 'ඉරි'); ?></div>
                <div class="day-header"><?php echo te('Mon', 'සඳු'); ?></div>
                <div class="day-header"><?php echo te('Tue', 'අඟ'); ?></div>
                <div class="day-header"><?php echo te('Wed', 'බදා'); ?></div>
                <div class="day-header"><?php echo te('Thu', 'බ්‍රහ'); ?></div>
                <div class="day-header"><?php echo te('Fri', 'සිකු'); ?></div>
                <div class="day-header"><?php echo te('Sat', 'සෙන'); ?></div>

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
                    $isOutsideWindow = $date > $calendarMaxDate->format('Y-m-d');
                    $isBlocked = in_array($date, $blockedDatesArray);
                    
                    // Calculate availability considering whole day reservations
                    $totalDhanaTypes = count($dhanaTypes);
                    $hasWholeDayBooking = in_array($date, $wholeDayBookings);
                    $bookedTypes = isset($bookingsByDate[$date]) ? count($bookingsByDate[$date]) : 0;

                    $cssClass = 'day-cell';
                    if ($isPast || $isOutsideWindow) {
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
                    
                    $dayLabel = ui_date_keep($date, 'l, F j, Y', 'long');
                    $disabled = ($isPast || $isOutsideWindow) ? ' disabled' : '';
                    echo '<button type="button" class="' . $cssClass . '" data-date="' . $date . '" aria-label="' . htmlspecialchars($dayLabel) . '" onclick="showDateDetails(\'' . $date . '\')"' . $disabled . '>';
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
                    if (!$isPast && !$isOutsideWindow && !$isBlocked && !$hasWholeDayBooking && $bookedTypes < $totalDhanaTypes) {
                        echo '<div class="availability-indicator">';
                        echo '<i class="fas fa-plus-circle"></i>';
                        echo '</div>';
                    } elseif (!$isPast && !$isBlocked && $hasWholeDayBooking) {
                        // Show a different indicator for whole day blocked dates
                        echo '<div class="whole-day-indicator">';
                        echo '<i class="fas fa-ban"></i>';
                        echo '</div>';
                    }

                    echo '</button>';
                }
                ?>
            </div>
        </div>

        <!-- Date Details Modal -->
        <div id="dateModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalDate">
            <div class="modal-content" tabindex="-1">
                <div class="modal-header">
                    <h3 id="modalDate"></h3>
                    <button type="button" class="close" aria-label="<?php echo te('Close date details', 'දින විස්තර වසන්න'); ?>" onclick="closeDateModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="modalContent"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeDateModal()"><?php echo te('Close', 'වසන්න'); ?></button>
                    <a id="bookNowBtn" href="#" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-plus-circle"></i> <?php echo te('Book Now', 'දැන් වෙන්කරන්න'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Month/Year Picker Modal -->
        <div id="monthYearModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="monthPickerTitle">
            <div class="modal-content" style="max-width: 400px;" tabindex="-1">
                <div class="modal-header">
                    <h3 id="monthPickerTitle"><i class="fas fa-calendar-alt"></i> <?php echo te('Jump to Month', 'මාසයකට යන්න'); ?></h3>
                    <button type="button" class="close" aria-label="<?php echo te('Close month picker', 'මාස තේරීම වසන්න'); ?>" onclick="closeMonthYearModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="padding: 20px;">
                        <div style="margin-bottom: 20px;">
                            <label for="jumpMonth" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                <i class="fas fa-calendar"></i> <?php echo te('Select Month:', 'මාසය තෝරන්න:'); ?>
                            </label>
                            <select id="jumpMonth" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                                <?php for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++): ?>
                                    <option value="<?php echo $monthNumber; ?>"><?php echo htmlspecialchars(ui_month_name($monthNumber), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label for="jumpYear" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                <i class="fas fa-calendar-day"></i> <?php echo te('Select Year:', 'වසර තෝරන්න:'); ?>
                            </label>
                            <select id="jumpYear" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                                <?php
                                $currentYearNow = (int)date('Y');
                                for ($y = $currentYearNow; $y <= (int)$maxMonth->format('Y'); $y++) {
                                    echo "<option value='$y'>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeMonthYearModal()"><?php echo te('Cancel', 'අවලංගු කරන්න'); ?></button>
                    <button class="btn btn-primary" onclick="jumpToMonthYear()">
                        <i class="fas fa-arrow-right"></i> <?php echo te('Go to Month', 'මාසයට යන්න'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dhanaTypes = <?php echo json_encode($dhanaTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const bookingsByDate = <?php echo json_encode($bookingsByDate, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const blockedDates = <?php echo json_encode($blockedDatesArray); ?>;
        const wholeDayBookings = <?php echo json_encode($wholeDayBookings); ?>;
        const today = '<?php echo $today; ?>';
        const currentMonth = <?php echo $currentMonth; ?>;
        const currentYear = <?php echo $currentYear; ?>;
    </script>
    <script src="assets/js/calendar.js?v=20261112"></script>
</body>
</html>
