<?php
/**
 * Auto-Append Pricing Month Cron Job
 * 
 * This script automatically adds a new month to the pricing table when the current month expires.
 * It maintains the pricing window by ensuring there are always X months of pricing data available.
 * 
 * Run this daily via cron: 0 0 * * * /usr/bin/php /path/to/cron/auto-append-pricing-month.php
 */

require_once __DIR__ . '/../config/database.php';

// Log file
$logFile = __DIR__ . '/logs/pricing-append.log';
$logDir = dirname($logFile);

// Create log directory if it doesn't exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Function to log messages
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry; // Also output to console
}

try {
    logMessage("=== Auto-Append Pricing Month Cron Job Started ===");
    
    $db = getDB();
    
    // Get pricing window setting
    $windowSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'pricing_window_months'");
    $pricingWindowMonths = $windowSetting ? (int)$windowSetting['setting_value'] : 24;
    
    logMessage("Pricing window: {$pricingWindowMonths} months");
    
    // Get the latest month in the pricing table
    $latestEntry = $db->fetchOne(
        "SELECT year, month FROM monthly_pricing ORDER BY year DESC, month DESC LIMIT 1"
    );
    
    if (!$latestEntry) {
        logMessage("ERROR: No pricing data found in monthly_pricing table!");
        exit(1);
    }
    
    $latestDate = new DateTime("{$latestEntry['year']}-{$latestEntry['month']}-01");
    logMessage("Latest month in pricing table: " . $latestDate->format('F Y'));
    
    // Calculate how many months ahead we should have
    $currentDate = new DateTime();
    $currentDate->modify('first day of this month');
    
    $targetDate = clone $currentDate;
    $targetDate->modify("+{$pricingWindowMonths} months");
    
    logMessage("Current month: " . $currentDate->format('F Y'));
    logMessage("Target end month: " . $targetDate->format('F Y'));
    
    // Calculate how many months to add
    $monthsToAdd = 0;
    $checkDate = clone $latestDate;
    
    while ($checkDate < $targetDate) {
        $checkDate->modify('+1 month');
        $monthsToAdd++;
    }
    
    if ($monthsToAdd <= 0) {
        logMessage("No new months needed. Pricing table is up to date.");
        logMessage("=== Cron Job Completed Successfully ===");
        exit(0);
    }
    
    logMessage("Need to add {$monthsToAdd} month(s) to maintain pricing window");
    
    // Get all active dhana types
    $dhanaTypes = $db->fetchAll("SELECT id, name, price FROM dhana_types WHERE is_active = 1 ORDER BY id");
    
    if (empty($dhanaTypes)) {
        logMessage("ERROR: No active dhana types found!");
        exit(1);
    }
    
    logMessage("Found " . count($dhanaTypes) . " active dhana types");
    
    // Add new months
    $insertedCount = 0;
    $skippedCount = 0;
    
    for ($i = 1; $i <= $monthsToAdd; $i++) {
        $newDate = clone $latestDate;
        $newDate->modify("+{$i} month");
        $year = (int)$newDate->format('Y');
        $month = (int)$newDate->format('n');
        
        logMessage("Adding month: " . $newDate->format('F Y'));
        
        foreach ($dhanaTypes as $type) {
            // Check if entry already exists
            $exists = $db->fetchOne(
                "SELECT id FROM monthly_pricing WHERE dhana_type_id = ? AND year = ? AND month = ?",
                [$type['id'], $year, $month]
            );
            
            if ($exists) {
                $skippedCount++;
                logMessage("  - Skipped {$type['name']} (already exists)");
            } else {
                // Insert new pricing entry using base price from dhana_types
                $db->query(
                    "INSERT INTO monthly_pricing (dhana_type_id, year, month, price, is_confirmed, created_at) 
                     VALUES (?, ?, ?, ?, 1, NOW())",
                    [$type['id'], $year, $month, $type['price']]
                );
                $insertedCount++;
                logMessage("  - Added {$type['name']}: LKR " . number_format($type['price'], 2));
            }
        }
    }
    
    // Update last_pricing_append setting
    $db->query(
        "UPDATE settings SET setting_value = NOW() WHERE setting_key = 'last_pricing_append'"
    );
    
    logMessage("Summary: Inserted {$insertedCount} entries, Skipped {$skippedCount} entries");
    logMessage("Updated last_pricing_append setting");
    logMessage("=== Cron Job Completed Successfully ===");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

