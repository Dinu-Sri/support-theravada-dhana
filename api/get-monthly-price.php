<?php
/**
 * API Endpoint: Get Monthly Price
 * 
 * Returns the price for a specific dhana type for a specific month
 * Also indicates if the price is tentative (beyond pricing window)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    // Get parameters
    $dhanaTypeId = isset($_GET['dhana_type_id']) ? (int)$_GET['dhana_type_id'] : 0;
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    
    // Validate inputs
    if ($dhanaTypeId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid dhana type ID'
        ]);
        exit;
    }
    
    if (empty($date)) {
        echo json_encode([
            'success' => false,
            'error' => 'Date is required'
        ]);
        exit;
    }
    
    // Parse date
    $dateObj = new DateTime($date);
    $year = (int)$dateObj->format('Y');
    $month = (int)$dateObj->format('n');
    
    // Get pricing window setting
    $windowSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'pricing_window_months'");
    $pricingWindowMonths = $windowSetting ? (int)$windowSetting['setting_value'] : 24;
    
    // Calculate pricing window end date
    $currentDate = new DateTime();
    $currentDate->modify('first day of this month');
    $windowEndDate = clone $currentDate;
    $windowEndDate->modify("+{$pricingWindowMonths} months");
    
    // Check if date is beyond pricing window
    $isTentative = $dateObj >= $windowEndDate;
    
    // Try to get price from monthly_pricing table
    $monthlyPricing = $db->fetchOne(
        "SELECT mp.*, dt.name as dhana_type_name 
         FROM monthly_pricing mp
         JOIN dhana_types dt ON mp.dhana_type_id = dt.id
         WHERE mp.dhana_type_id = ? AND mp.year = ? AND mp.month = ?",
        [$dhanaTypeId, $year, $month]
    );
    
    if ($monthlyPricing) {
        // Price found in monthly pricing table
        echo json_encode([
            'success' => true,
            'price' => (float)$monthlyPricing['price'],
            'is_tentative' => $isTentative,
            'is_confirmed' => (bool)$monthlyPricing['is_confirmed'],
            'source' => 'monthly_pricing',
            'dhana_type_name' => $monthlyPricing['dhana_type_name'],
            'month' => $month,
            'year' => $year,
            'notes' => $monthlyPricing['notes'] ?? null
        ]);
    } else {
        // Fallback to base price from dhana_types table
        $dhanaType = $db->fetchOne(
            "SELECT * FROM dhana_types WHERE id = ? AND is_active = 1",
            [$dhanaTypeId]
        );
        
        if ($dhanaType) {
            echo json_encode([
                'success' => true,
                'price' => (float)$dhanaType['price'],
                'is_tentative' => true, // Always tentative if not in monthly pricing
                'is_confirmed' => false,
                'source' => 'dhana_types_fallback',
                'dhana_type_name' => $dhanaType['name'],
                'month' => $month,
                'year' => $year,
                'notes' => 'Price not set for this month. Using base price.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Dhana type not found'
            ]);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

