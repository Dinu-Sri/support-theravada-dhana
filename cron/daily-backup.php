<?php
/**
 * Daily Backup Cron Job
 * Run this script daily (recommended: 2 AM) to create automated database backups
 * 
 * Windows Task Scheduler Command:
 * C:\xampp\php\php.exe "C:\xampp\htdocs\support\cron\daily-backup.php"
 * 
 * Schedule: Daily at 2:00 AM
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die('This script can only be run from command line or with manual_run parameter.');
}

require_once __DIR__ . '/../config/backup.php';

echo "===========================================\n";
echo "Daily Backup Script Started\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n\n";

try {
    $backupManager = new BackupManager();
    
    // Create daily database backup
    echo "Creating daily database backup...\n";
    $dbResult = $backupManager->createDatabaseBackup('daily');
    
    if ($dbResult['success']) {
        echo "✓ SUCCESS: " . $dbResult['message'] . "\n";
        echo "  File: " . $dbResult['file'] . "\n";
        echo "  Size: " . BackupManager::formatBytes($dbResult['size']) . "\n";
    } else {
        echo "✗ FAILED: " . $dbResult['message'] . "\n";
    }
    
    echo "\n";
    
    // Create receipts backup (weekly - only on Sundays)
    if (date('w') == 0) { // 0 = Sunday
        echo "Creating weekly receipts backup...\n";
        $receiptsResult = $backupManager->createReceiptsBackup();
        
        if ($receiptsResult['success']) {
            echo "✓ SUCCESS: " . $receiptsResult['message'] . "\n";
            if ($receiptsResult['file']) {
                echo "  File: " . $receiptsResult['file'] . "\n";
                echo "  Size: " . BackupManager::formatBytes($receiptsResult['size']) . "\n";
            }
        } else {
            echo "✗ FAILED: " . $receiptsResult['message'] . "\n";
        }
        
        echo "\n";
    }
    
    // Show backup statistics
    echo "Current Backup Statistics:\n";
    echo "-------------------------------------------\n";
    $stats = $backupManager->getBackupStats();
    
    echo "Daily Backups: " . $stats['daily']['count'] . " files\n";
    echo "  Total Size: " . BackupManager::formatBytes($stats['daily']['total_size']) . "\n";
    echo "  Retention: " . $stats['retention_days'] . " days\n";
    
    echo "\nMonthly Backups: " . $stats['monthly']['count'] . " files\n";
    echo "  Total Size: " . BackupManager::formatBytes($stats['monthly']['total_size']) . "\n";
    
    echo "\nReceipt Backups: " . $stats['receipts']['count'] . " files\n";
    echo "  Total Size: " . BackupManager::formatBytes($stats['receipts']['total_size']) . "\n";
    
    echo "\n===========================================\n";
    echo "Daily Backup Script Completed Successfully\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    error_log("Daily backup failed: " . $e->getMessage());
    exit(1);
}

exit(0);

