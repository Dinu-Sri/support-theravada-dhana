<?php
require_once 'includes/auth.php';

// Require user to be logged in
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();

// Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request');
    }
    $auth->logout();
    header('Location: index.php');
    exit;
}

// Get dhana types for pricing display
$db = getDB();
$dhanaTypes = $db->fetchAll("SELECT * FROM dhana_types WHERE is_active = 1 AND price > 0 ORDER BY price DESC");
?>
<!DOCTYPE html>
<?php echo ui_html_open(); ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo te('Dashboard - Dāna Reservation System', 'ප්‍රධාන පුවරුව - දාන වෙන්කිරීමේ පද්ධතිය'); ?></title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Sinhala Font Support -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pro-ui.css?v=20260916">
</head>
<body>
    <div class="dashboard">
        <!-- Success Message (if just registered) -->
        <?php if (isset($_SESSION['registration_success']) && $_SESSION['registration_success']): ?>
            <div class="success-notification" id="registrationSuccess">
                <div class="success-content">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo te_msg($_SESSION['success_message']); ?></span>
                </div>
                <button onclick="closeSuccessNotification()" class="close-notification">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php
                // Clear the session variables after displaying
                unset($_SESSION['registration_success']);
                unset($_SESSION['success_message']);
            ?>
        <?php endif; ?>

        <!-- Top Welcome Section -->
        <div class="dashboard-header">
            <div class="user-info">
                <p><?php echo te('Hello,', 'ආයුබෝවන්,'); ?> <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></p>
                <p><?php echo te('Email:', 'විද්‍යුත් තැපෑල:'); ?> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="welcome-content">
                <h1><i class="fas fa-lotus"></i> <?php echo te('Welcome to Dāna Reservation', 'දාන වෙන්කිරීමට සාදරයෙන් පිළිගනිමු'); ?></h1>
            </div>
            <div class="user-actions">
                <?php ui_lang_switch(); ?>
                <a href="settings.php" class="settings-btn">
                    <i class="fas fa-cog"></i> <?php echo te('Settings', 'සැකසුම්'); ?>
                </a>
                <form method="POST" class="logout-form">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> <?php echo te('Logout', 'පිටවීම'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="dashboard-content">

            <!-- Left Panel: How to Place Reservation -->
            <div class="dashboard-panel left-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-list-ol"></i> <span class="lang-en">How to Place Your Dāna Reservation</span><span class="lang-si">ඔබගේ දානමය පින්කම වෙන්කරවා ගන්නා ආකාරය</span></h2>
                </div>

                <?php ui_lang_switch('language-tabs ui-lang-switch'); ?>

                <div class="panel-content">
                    <p class="guide-intro lang-en">Follow these simple steps to complete your sacred dāna reservation</p>
                    <p class="guide-intro lang-si">ඔබගේ දානමය කටයුත්ත වෙන්කරවා ගැනීම සඳහා පහත පියවර අනුගමනය කරන්න</p>

                    <!-- English Steps -->
                    <div class="steps-compact lang-en">
                        <div class="step-compact">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4><i class="fas fa-calendar-check"></i> Check Availability</h4>
                                <p>Browse our calendar to find available dates for your preferred dāna offering.</p>
                            </div>
                        </div>

                        <div class="step-compact">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4><i class="fas fa-hand-holding-heart"></i> Select Dāna Type</h4>
                                <p>Choose from our dāna offerings:</p>
                                <ul class="dhana-types-list">
                                    <li><strong>Whole Day</strong> - Complete day dāna offering</li>
                                    <li><strong>Lunch Dāna</strong> - Midday meal offering</li>
                                    <li><strong>Morning Dāna</strong> - Morning meal offering</li>
                                    <li><strong>Extra Item</strong> - Coming Soon</li>
                                </ul>
                                <p class="price-note"><i class="fas fa-info-circle"></i> Prices will be shown after selecting your preferred date</p>
                            </div>
                        </div>

                        <div class="step-compact">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4><i class="fas fa-edit"></i> Fill Reservation Form</h4>
                                <p>Complete the reservation form with your details and special requests.</p>
                            </div>
                        </div>

                        <div class="step-compact">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h4><i class="fas fa-credit-card"></i> Make Payment</h4>
                                <p>Transfer the amount and upload payment receipt.</p>
                            </div>
                        </div>

                        <div class="step-compact">
                            <div class="step-number">5</div>
                            <div class="step-content">
                                <h4><i class="fas fa-check-circle"></i> Receive Confirmation</h4>
                                <p>Get confirmation email with reservation details.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sinhala Steps -->
                    <div class="steps-compact lang-si">
                        <div class="step-compact">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4><i class="fas fa-calendar-check"></i> දින පරීක්ෂා කිරීම</h4>
                                <p>ඔබට පහසු දිනයක් තෝරා ගැනීම සඳහා අපගේ දින දර්ශනය (Calendar) පරීක්ෂා කරන්න.</p>
                            </div>
                        </div>

                        <div class="step-compact">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4><i class="fas fa-hand-holding-heart"></i> දාන වර්ගය තෝරා ගැනීම</h4>
                                <p>අපගේ දානමය සේවාවන් අතරින් ඔබට අවශ්‍ය දේ තෝරා ගන්න:</p>
                                <ul class="dhana-types-list">
                                    <li><strong>සම්පූර්ණ දිනය</strong> - සම්පූර්ණ දින දානමය පිළිගැනීම</li>
                                    <li><strong>දිවා දානය</strong> - මධ්‍යාහ්න ආහාර පිළිගැනීම</li>
                                    <li><strong>උදෑසන දානය</strong> - උදෑසන ආහාර පිළිගැනීම</li>
                                    <li><strong>අමතර අයිතම</strong> - ළඟදීම</li>
                                </ul>
                                <p class="price-note"><i class="fas fa-info-circle"></i> ඔබ තෝරාගත් දිනයෙන් පසුව මිල ගණන් පෙන්වනු ලැබේ</p>
                            </div>
                        </div>

                        <div class="step-compact">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4><i class="fas fa-edit"></i> අයදුම්පත සම්පූර්ණ කරන්න</h4>
                                <p>ඔබගේ විස්තර සහ විශේෂ ඉල්ලීම් ඇතුළත් කර වෙන්කරවා ගැනීමේ අයදුම්පත පුරවන්න.</p>
                            </div>
                        </div>

                        <div class="step-compact">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h4><i class="fas fa-credit-card"></i> ගෙවීම් සිදු කරන්න</h4>
                                <p>අදාළ මුදල ගෙවා, රිසිට්පතේ පිටපතක් මෙහි අප්ලෝඩ් (Upload) කරන්න.</p>
                            </div>
                        </div>

                        <div class="step-compact">
                            <div class="step-number">5</div>
                            <div class="step-content">
                                <h4><i class="fas fa-check-circle"></i> තහවුරු කිරීම ලබාගන්න</h4>
                                <p>වෙන්කරවා ගැනීමේ විස්තර ඇතුළත් තහවුරු කිරීමේ විද්‍යුත් පණිවිඩය (Email) ලබාගන්න.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Panel: Action Buttons -->
            <div class="dashboard-panel middle-panel">
                <div class="panel-content">
                    <div class="action-buttons-vertical">
                        <a href="calendar.php" class="action-btn-large">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo te('Check Available Dates', 'ලබා ගත හැකි දින පරීක්ෂා කරන්න'); ?></span>
                            <small><?php echo te('View calendar and select your preferred date', 'දින දර්ශනය බලා ඔබට පහසු දිනය තෝරන්න'); ?></small>
                        </a>

                        <a href="booking-new.php" class="action-btn-large">
                            <i class="fas fa-plus-circle"></i>
                            <span><?php echo te('Create New Reservation', 'නව වෙන්කිරීමක් සාදන්න'); ?></span>
                            <small><?php echo te('Step-by-step reservation process', 'පියවරෙන් පියවර වෙන්කිරීමේ ක්‍රියාවලිය'); ?></small>
                        </a>

                        <?php if (($user['role'] ?? 'donor') === 'agent'): ?>
                        <a href="booking-new.php?for=other" class="action-btn-large agent-reservation-action">
                            <i class="fas fa-user-friends"></i>
                            <span><?php echo te('Reservation for Others', 'අනෙක් අය වෙනුවෙන් වෙන්කිරීම'); ?></span>
                            <small><?php echo te('Arrange a dāna reservation for someone you are assisting', 'ඔබ උදව් කරන අයෙකු වෙනුවෙන් දාන වෙන්කිරීමක් සකසන්න'); ?></small>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Upcoming Reservations -->
            <div class="dashboard-panel right-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-calendar-check"></i> <?php echo te('Your Upcoming Reservations', 'ඔබගේ ඉදිරි වෙන්කිරීම්'); ?></h2>
                    <a href="my-bookings.php" class="btn btn-secondary btn-small">
                        <i class="fas fa-list"></i> <?php echo te('View All Reservations', 'සියලු වෙන්කිරීම් බලන්න'); ?>
                    </a>
                </div>
                <div class="panel-content">
                    <?php
                    // Show upcoming, actionable reservations rather than old history.
                    $recentBookings = $db->fetchAll(
                        "SELECT b.*, dt.name as dhana_type_name, dt.price,
                                ab.year_start, ab.year_end,
                                CASE
                                    WHEN b.is_annual_event = 1 AND b.parent_booking_id IS NULL THEN 'main_annual'
                                    WHEN b.is_annual_event = 1 AND b.parent_booking_id IS NOT NULL THEN 'annual_instance'
                                    ELSE 'regular'
                                END as booking_type
                         FROM bookings b
                         JOIN dhana_types dt ON b.dhana_type_id = dt.id
                         LEFT JOIN annual_bookings ab ON (b.id = ab.booking_id OR b.parent_booking_id = ab.booking_id)
                         WHERE b.user_id = ? AND b.booking_date >= CURDATE() AND b.status <> 'cancelled'
                         ORDER BY b.booking_date ASC, b.created_at DESC
                         LIMIT 6",
                        [$user['id']]
                    );
                    ?>

                    <?php if (empty($recentBookings)): ?>
                        <div class="no-bookings">
                            <i class="fas fa-calendar-times"></i>
                            <p><?php echo te("You haven't made any reservations yet.", 'ඔබ තවම වෙන්කිරීම් කර නැත.'); ?></p>
                            <p><?php echo te('Click "Create New Reservation" to get started!', 'ආරම්භ කිරීමට "නව වෙන්කිරීමක් සාදන්න" ඔබන්න.'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="bookings-list-compact">
                            <?php foreach ($recentBookings as $booking): ?>
                                <div class="booking-item-compact">
                                    <div class="booking-info">
                                        <h4>
                                            <?php echo htmlspecialchars(ui_dhana_name($booking['dhana_type_name'])); ?>
                                            <?php if ($booking['is_annual_event']): ?>
                                                <span class="annual-badge">
                                                    <i class="fas fa-calendar-check"></i> <?php echo te('Annual', 'වාර්ෂික'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        <p><i class="fas fa-calendar"></i> <?php echo htmlspecialchars(ui_format_date($booking['booking_date'])); ?></p>
                                        <p><i class="fas fa-money-bill"></i> Rs. <?php echo number_format($booking['total_amount']); ?></p>
                                        <?php if ($booking['is_annual_event'] && $booking['year_start'] && $booking['year_end']): ?>
                                            <p><i class="fas fa-repeat"></i> <?php echo $booking['year_start']; ?> - <?php echo $booking['year_end']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="booking-status">
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo htmlspecialchars(ui_status($booking['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/dashboard.js?v=20261111"></script>

    <style>
        /* Language Tabs Styles */
        .language-tabs {
            display: flex;
            gap: 10px;
            padding: 15px 20px 0 20px;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 0;
        }

        .lang-tab {
            padding: 10px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: #666;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            bottom: -2px;
        }

        .lang-tab:hover {
            color: #d4822a;
            background: rgba(212, 130, 42, 0.05);
        }

        .lang-tab.active {
            color: #d4822a;
            border-bottom-color: #d4822a;
            background: rgba(212, 130, 42, 0.08);
        }

        .lang-tab i {
            font-size: 1rem;
        }

        .lang-si {
            font-family: 'Noto Sans Sinhala', 'Iskoola Pota', sans-serif;
        }

        /* Success Notification Styles */
        .success-notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDown 0.5s ease, fadeIn 0.5s ease;
            max-width: 90%;
            width: auto;
        }

        .success-content {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            font-weight: 500;
        }

        .success-content i {
            font-size: 1.5rem;
            animation: scaleIn 0.6s ease;
        }

        .close-notification {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .close-notification:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        @keyframes slideDown {
            from {
                top: -100px;
                opacity: 0;
            }
            to {
                top: 20px;
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scaleIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* Price Note Styling */
        .price-note {
            margin-top: 12px;
            padding: 10px 12px;
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
            border-radius: 4px;
            color: #0d47a1;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .price-note i {
            color: #2196f3;
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .success-notification {
                top: 10px;
                padding: 15px 20px;
                max-width: 95%;
            }

            .success-content {
                font-size: 0.9rem;
            }

            .success-content i {
                font-size: 1.2rem;
            }
        }
    </style>

    <script>
        // Auto-hide success notification after 5 seconds
        setTimeout(function() {
            const notification = document.getElementById('registrationSuccess');
            if (notification) {
                notification.style.animation = 'slideDown 0.5s ease reverse';
                setTimeout(function() {
                    notification.remove();
                }, 500);
            }
        }, 5000);

        // Close notification manually
        function closeSuccessNotification() {
            const notification = document.getElementById('registrationSuccess');
            if (notification) {
                notification.style.animation = 'slideDown 0.5s ease reverse';
                setTimeout(function() {
                    notification.remove();
                }, 500);
            }
        }

    </script>
</body>
</html>
