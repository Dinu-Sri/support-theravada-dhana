<?php
/**
 * Backup Utility - Database and Receipt Backup Functions
 * Handles daily rotation, monthly permanent backups, and cleanup
 */

require_once __DIR__ . '/database.php';

class BackupManager {
    private $db;
    private $backupDir;
    private $dailyDir;
    private $monthlyDir;
    private $receiptsDir;
    
    public function __construct() {
        $this->db = getDB();
        $this->backupDir = __DIR__ . '/../backup';
        $this->dailyDir = $this->backupDir . '/daily';
        $this->monthlyDir = $this->backupDir . '/monthly';
        $this->receiptsDir = $this->backupDir . '/receipts';
        
        // Create directories if they don't exist
        $this->ensureDirectoriesExist();
    }
    
    /**
     * Ensure all backup directories exist
     */
    private function ensureDirectoriesExist() {
        $dirs = [$this->backupDir, $this->dailyDir, $this->monthlyDir, $this->receiptsDir];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Backup storage could not be created. Check the directory permissions.');
            }

            // Keep every backup directory protected on Apache 2.2 and 2.4.
            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                $rules = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                    . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
                if (file_put_contents($htaccess, $rules, LOCK_EX) === false) {
                    throw new RuntimeException('Backup storage protection could not be installed. Check the directory permissions.');
                }
            }
        }
    }

    private function commandExecutionAvailable() {
        if (!function_exists('exec')) return false;
        $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
        return !in_array('exec', $disabled, true);
    }

    private function resolveMysqlDumpPath() {
        if (!$this->commandExecutionAvailable()) return null;

        $configured = defined('MYSQLDUMP_PATH') ? trim((string)MYSQLDUMP_PATH) : '';
        $candidates = array_filter(array_unique([
            $configured,
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe'
        ]));

        foreach ($candidates as $candidate) {
            if ((strpos($candidate, '/') !== false || strpos($candidate, '\\') !== false) && is_file($candidate)) {
                return $candidate;
            }
        }

        $commandName = $configured !== '' && strpos($configured, '/') === false && strpos($configured, '\\') === false
            ? $configured
            : 'mysqldump';
        $lookup = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . escapeshellarg($commandName) . ' 2>NUL'
            : 'command -v ' . escapeshellarg($commandName) . ' 2>/dev/null';
        $paths = [];
        exec($lookup, $paths, $returnCode);
        if ($returnCode === 0 && !empty($paths[0]) && is_file(trim($paths[0]))) {
            return trim($paths[0]);
        }
        return null;
    }

    private function escapeDefaultsValue($value) {
        return '"' . str_replace(["\\", "\r", "\n", '"'], ["\\\\", '', '', '\\"'], (string)$value) . '"';
    }

    public function getPreflightStatus() {
        $issues = [];
        if (!$this->commandExecutionAvailable()) {
            $issues[] = 'PHP command execution (exec) is disabled by the hosting provider.';
        }
        $dumpPath = $this->resolveMysqlDumpPath();
        if ($dumpPath === null) {
            $issues[] = 'mysqldump was not found. Set MYSQLDUMP_PATH in config/database.php to the host’s absolute mysqldump path.';
        }
        foreach ([$this->backupDir, $this->dailyDir, $this->monthlyDir, $this->receiptsDir] as $directory) {
            if (!is_dir($directory) || !is_writable($directory)) {
                $issues[] = 'One or more backup directories are not writable by PHP.';
                break;
            }
        }

        return [
            'ready' => empty($issues),
            'issues' => $issues,
            'mysqldump' => $dumpPath === null ? null : basename($dumpPath),
            'zip_available' => class_exists('ZipArchive')
        ];
    }
    
    /**
     * Create database backup
     * @param string $type 'daily' or 'monthly'
     * @return array ['success' => bool, 'message' => string, 'file' => string]
     */
    public function createDatabaseBackup($type = 'daily') {
        try {
            if (!in_array($type, ['daily', 'monthly'], true)) {
                throw new InvalidArgumentException('Invalid database backup type.');
            }
            $timestamp = date('Y-m-d_H-i-s');
            $monthYear = date('Y-m'); // For monthly backups
            
            if ($type === 'monthly') {
                $filename = "db_monthly_{$monthYear}.sql";
                $filepath = $this->monthlyDir . '/' . $filename;
            } else {
                $filename = "db_daily_{$timestamp}.sql";
                $filepath = $this->dailyDir . '/' . $filename;
            }
            
            $preflight = $this->getPreflightStatus();
            if (!$preflight['ready']) {
                throw new RuntimeException(implode(' ', $preflight['issues']));
            }
            $mysqlDumpPath = $this->resolveMysqlDumpPath();
            $credentialsFile = tempnam($this->backupDir, 'mysql-');
            $errorFile = tempnam($this->backupDir, 'dump-error-');
            if ($credentialsFile === false || $errorFile === false) {
                if ($credentialsFile && is_file($credentialsFile)) @unlink($credentialsFile);
                if ($errorFile && is_file($errorFile)) @unlink($errorFile);
                throw new RuntimeException('Could not create temporary backup control files. Check backup directory permissions.');
            }
            $credentials = "[client]\nhost=" . $this->escapeDefaultsValue(DB_HOST)
                . "\nuser=" . $this->escapeDefaultsValue(DB_USER)
                . "\npassword=" . $this->escapeDefaultsValue(DB_PASS)
                . "\ndefault-character-set=utf8mb4\n";
            if (file_put_contents($credentialsFile, $credentials, LOCK_EX) === false) {
                throw new RuntimeException('Could not write the temporary database credentials file.');
            }
            @chmod($credentialsFile, 0600);

            $command = escapeshellarg($mysqlDumpPath)
                . ' ' . escapeshellarg('--defaults-extra-file=' . $credentialsFile)
                . ' --single-transaction --skip-lock-tables --routines --triggers '
                . escapeshellarg(DB_NAME)
                . ' > ' . escapeshellarg($filepath)
                . ' 2> ' . escapeshellarg($errorFile);

            try {
                exec($command, $output, $returnCode);
            } finally {
                if (is_file($credentialsFile)) @unlink($credentialsFile);
            }
            
            if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                // Update last backup time in settings
                $this->updateLastBackupTime($type);
                
                // Rotate daily backups if needed
                if ($type === 'daily') {
                    $this->rotateDailyBackups();
                }
                if (is_file($errorFile)) @unlink($errorFile);
                return [
                    'success' => true,
                    'message' => ucfirst($type) . ' database backup created successfully!',
                    'file' => $filename,
                    'size' => filesize($filepath)
                ];
            } else {
                $errorText = is_file($errorFile) ? (string)file_get_contents($errorFile) : '';
                if (is_file($filepath)) @unlink($filepath);
                if (stripos($errorText, 'access denied') !== false) {
                    throw new RuntimeException('mysqldump could not authenticate. Check the database credentials available to the PHP/cron environment.');
                }
                throw new RuntimeException('mysqldump failed with exit code ' . (int)$returnCode . '. Confirm MYSQLDUMP_PATH and ask the host to enable database export for this account.');
            }

        } catch (Exception $e) {
            if (isset($errorFile) && is_file($errorFile)) @unlink($errorFile);
            if (isset($credentialsFile) && is_file($credentialsFile)) @unlink($credentialsFile);
            error_log("Database backup failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
                'file' => null
            ];
        }
    }
    
    /**
     * Create receipts backup (zip all receipt files)
     * @return array ['success' => bool, 'message' => string, 'file' => string]
     */
    public function createReceiptsBackup() {
        try {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('PHP ZipArchive is unavailable. Ask the hosting provider to enable the zip extension.');
            }
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "receipts_{$timestamp}.zip";
            $filepath = $this->receiptsDir . '/' . $filename;
            
            $uploadsDir = __DIR__ . '/../uploads/receipts';
            
            if (!file_exists($uploadsDir)) {
                return [
                    'success' => false,
                    'message' => 'Receipts directory does not exist',
                    'file' => null
                ];
            }
            
            // Create zip archive
            $zip = new ZipArchive();
            
            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Could not create zip file');
            }
            
            // Add all files from receipts directory
            $files = glob($uploadsDir . '/*');
            $fileCount = 0;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $zip->addFile($file, basename($file));
                    $fileCount++;
                }
            }
            
            $zip->close();
            
            if ($fileCount === 0) {
                unlink($filepath);
                return [
                    'success' => true,
                    'message' => 'No receipt files to backup',
                    'file' => null
                ];
            }
            
            return [
                'success' => true,
                'message' => "Receipts backup created successfully! ({$fileCount} files)",
                'file' => $filename,
                'size' => filesize($filepath)
            ];
            
        } catch (Exception $e) {
            error_log("Receipts backup failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Receipts backup failed: ' . $e->getMessage(),
                'file' => null
            ];
        }
    }

    /**
     * Rotate daily backups based on retention setting
     */
    private function rotateDailyBackups() {
        try {
            // Get retention setting (default 30 days)
            $retention = $this->getBackupRetention();

            // Get all daily backup files
            $files = glob($this->dailyDir . '/db_daily_*.sql');

            // Sort by modification time (oldest first)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Delete oldest files if we exceed retention
            $fileCount = count($files);
            if ($fileCount > $retention) {
                $filesToDelete = $fileCount - $retention;

                for ($i = 0; $i < $filesToDelete; $i++) {
                    if (file_exists($files[$i])) {
                        unlink($files[$i]);
                        error_log("Rotated old backup: " . basename($files[$i]));
                    }
                }
            }

        } catch (Exception $e) {
            error_log("Backup rotation failed: " . $e->getMessage());
        }
    }

    /**
     * Get backup retention setting
     * @return int Number of days to retain daily backups
     */
    private function getBackupRetention() {
        try {
            $setting = $this->db->fetchOne(
                "SELECT setting_value FROM settings WHERE setting_key = 'backup_retention_days'"
            );

            if ($setting) {
                $days = (int)$setting['setting_value'];
                // Ensure it's between 1 and 30
                return max(1, min(30, $days));
            }

            return 30; // Default

        } catch (Exception $e) {
            return 30; // Default on error
        }
    }

    /**
     * Update last backup time in settings
     * @param string $type 'daily' or 'monthly'
     */
    private function updateLastBackupTime($type) {
        try {
            $settingKey = $type === 'monthly' ? 'last_monthly_backup' : 'last_daily_backup';
            $currentTime = date('Y-m-d H:i:s');

            $existing = $this->db->fetchOne(
                "SELECT id FROM settings WHERE setting_key = ?",
                [$settingKey]
            );

            if ($existing) {
                $this->db->query(
                    "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                    [$currentTime, $settingKey]
                );
            } else {
                $this->db->query(
                    "INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)",
                    [$settingKey, $currentTime, "Last {$type} backup timestamp"]
                );
            }

        } catch (Exception $e) {
            error_log("Failed to update last backup time: " . $e->getMessage());
        }
    }

    /**
     * Get backup statistics
     * @return array Backup stats including counts and sizes
     */
    public function getBackupStats() {
        $stats = [
            'daily' => [
                'count' => 0,
                'total_size' => 0,
                'last_backup' => null,
                'files' => []
            ],
            'monthly' => [
                'count' => 0,
                'total_size' => 0,
                'last_backup' => null,
                'files' => []
            ],
            'receipts' => [
                'count' => 0,
                'total_size' => 0,
                'files' => []
            ],
            'retention_days' => $this->getBackupRetention()
        ];

        // Daily backups
        $dailyFiles = glob($this->dailyDir . '/db_daily_*.sql');
        if ($dailyFiles) {
            usort($dailyFiles, function($a, $b) {
                return filemtime($b) - filemtime($a); // Newest first
            });

            foreach ($dailyFiles as $file) {
                $size = filesize($file);
                $stats['daily']['count']++;
                $stats['daily']['total_size'] += $size;
                $stats['daily']['files'][] = [
                    'name' => basename($file),
                    'size' => $size,
                    'date' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }

            if (!empty($dailyFiles)) {
                $stats['daily']['last_backup'] = date('Y-m-d H:i:s', filemtime($dailyFiles[0]));
            }
        }

        // Monthly backups
        $monthlyFiles = glob($this->monthlyDir . '/db_monthly_*.sql');
        if ($monthlyFiles) {
            usort($monthlyFiles, function($a, $b) {
                return filemtime($b) - filemtime($a); // Newest first
            });

            foreach ($monthlyFiles as $file) {
                $size = filesize($file);
                $stats['monthly']['count']++;
                $stats['monthly']['total_size'] += $size;
                $stats['monthly']['files'][] = [
                    'name' => basename($file),
                    'size' => $size,
                    'date' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }

            if (!empty($monthlyFiles)) {
                $stats['monthly']['last_backup'] = date('Y-m-d H:i:s', filemtime($monthlyFiles[0]));
            }
        }

        // Receipt backups
        $receiptFiles = glob($this->receiptsDir . '/receipts_*.zip');
        if ($receiptFiles) {
            foreach ($receiptFiles as $file) {
                $size = filesize($file);
                $stats['receipts']['count']++;
                $stats['receipts']['total_size'] += $size;
                $stats['receipts']['files'][] = [
                    'name' => basename($file),
                    'size' => $size,
                    'date' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
        }

        return $stats;
    }

    /**
     * Format bytes to human readable size
     * @param int $bytes
     * @return string
     */
    public static function formatBytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
