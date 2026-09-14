<?php
/**
 * Monthly Permanent Backup Cron Job
 * Run this script on the 1st of each month to create permanent monthly backups
 * 
 * Windows Task Scheduler Command:
 * C:\xampp\php\php.exe "C:\xampp\htdocs\support\cron\monthly-backup.php"
 * 
 * Schedule: Monthly on the 1st at 3:00 AM
 */

// Prevent direct browser access
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This maintenance task is available from the command line only.');
}

require_once __DIR__ . '/../config/backup.php';

echo "===========================================\n";
echo "Monthly Permanent Backup Script Started\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "Month: " . date('F Y') . "\n";
echo "===========================================\n\n";

try {
    $backupManager = new BackupManager();
    
    // Create monthly permanent database backup
    echo "Creating monthly permanent database backup...\n";
    $dbResult = $backupManager->createDatabaseBackup('monthly');
    
    if ($dbResult['success']) {
        echo "✓ SUCCESS: " . $dbResult['message'] . "\n";
        echo "  File: " . $dbResult['file'] . "\n";
        echo "  Size: " . BackupManager::formatBytes($dbResult['size']) . "\n";
    } else {
        echo "✗ FAILED: " . $dbResult['message'] . "\n";
    }
    
    echo "\n";
    
    // Create monthly receipts backup
    echo "Creating monthly receipts backup...\n";
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
    
    // Show backup statistics
    echo "Current Backup Statistics:\n";
    echo "-------------------------------------------\n";
    $stats = $backupManager->getBackupStats();
    
    echo "Monthly Backups: " . $stats['monthly']['count'] . " files\n";
    echo "  Total Size: " . BackupManager::formatBytes($stats['monthly']['total_size']) . "\n";
    
    if (!empty($stats['monthly']['files'])) {
        echo "\n  Recent Monthly Backups:\n";
        foreach (array_slice($stats['monthly']['files'], 0, 5) as $file) {
            echo "  - " . $file['name'] . " (" . BackupManager::formatBytes($file['size']) . ") - " . $file['date'] . "\n";
        }
    }
    
    echo "\n===========================================\n";
    echo "Monthly Backup Script Completed Successfully\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    error_log("Monthly backup failed: " . $e->getMessage());
    exit(1);
}

exit(0);
