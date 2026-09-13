<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

try {
    $db = getDB();
    
    // Get parameters
    $dhanaTypeId = isset($_GET['dhana_type_id']) ? (int)$_GET['dhana_type_id'] : 0;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    
    if (!$dhanaTypeId || !$startDate) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing required parameters'
        ]);
        exit;
    }
    
    // Get dhana type info
    $dhanaType = $db->fetchOne(
        "SELECT * FROM dhana_types WHERE id = ? AND is_active = 1",
        [$dhanaTypeId]
    );
    
    if (!$dhanaType) {
        echo json_encode([
            'success' => false,
            'error' => 'Dhana type not found'
        ]);
        exit;
    }
    
    // Get annual booking years setting
    $annualYearsSetting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'annual_booking_years'"
    );
    $annualBookingYears = $annualYearsSetting ? (int)$annualYearsSetting['setting_value'] : 10;
    
    // Get pricing window setting
    $windowSetting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'pricing_window_months'"
    );
    $pricingWindowMonths = $windowSetting ? (int)$windowSetting['setting_value'] : 24;
    
    // Calculate pricing window end date
    $currentDate = new DateTime();
    $currentDate->modify('first day of this month');
    $windowEndDate = clone $currentDate;
    $windowEndDate->modify("+{$pricingWindowMonths} months");
    
    // Build annual prices array
    $baseDate = new DateTime($startDate);
    $annualPrices = [];
    $totalConfirmed = 0;
    $totalTentative = 0;
    $confirmedCount = 0;
    $tentativeCount = 0;
    $notSetCount = 0;
    
    for ($yearOffset = 0; $yearOffset < $annualBookingYears; $yearOffset++) {
        $targetDate = clone $baseDate;
        $targetDate->modify("+{$yearOffset} year");
        
        $year = (int)$targetDate->format('Y');
        $month = (int)$targetDate->format('n');
        $dateStr = $targetDate->format('Y-m-d');
        $displayDate = $targetDate->format('F j, Y');
        
        // Check if date is within pricing window
        $isWithinWindow = $targetDate < $windowEndDate;
        
        // Try to get monthly price
        $monthlyPricing = $db->fetchOne(
            "SELECT price, is_confirmed, notes FROM monthly_pricing 
             WHERE dhana_type_id = ? AND year = ? AND month = ?",
            [$dhanaTypeId, $year, $month]
        );
        
        if ($monthlyPricing) {
            // Price is set in monthly pricing table
            $price = (float)$monthlyPricing['price'];
            $status = 'confirmed';
            $totalConfirmed += $price;
            $confirmedCount++;
            
            $annualPrices[] = [
                'year_number' => $yearOffset + 1,
                'date' => $dateStr,
                'display_date' => $displayDate,
                'price' => $price,
                'status' => $status,
                'is_within_window' => $isWithinWindow,
                'notes' => $monthlyPricing['notes']
            ];
        } else {
            // Price not set - don't use fallback
            $status = 'not_set';
            $notSetCount++;
            
            $annualPrices[] = [
                'year_number' => $yearOffset + 1,
                'date' => $dateStr,
                'display_date' => $displayDate,
                'price' => null,
                'status' => $status,
                'is_within_window' => $isWithinWindow,
                'notes' => 'Price not set for this month yet'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'dhana_type_name' => $dhanaType['name'],
        'annual_years' => $annualBookingYears,
        'pricing_window_months' => $pricingWindowMonths,
        'prices' => $annualPrices,
        'summary' => [
            'total_confirmed' => $totalConfirmed,
            'total_tentative' => $totalTentative,
            'confirmed_count' => $confirmedCount,
            'tentative_count' => $tentativeCount,
            'not_set_count' => $notSetCount,
            'grand_total' => $totalConfirmed + $totalTentative
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

