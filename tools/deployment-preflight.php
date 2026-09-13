<?php
/**
 * Run from cPanel Terminal before directing traffic to a new checkout:
 * php tools/deployment-preflight.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$errors = [];
$warnings = [];

if (PHP_VERSION_ID < 70400) {
    $errors[] = 'PHP 7.4 or newer is required.';
}

foreach (['pdo_mysql', 'gd', 'zip'] as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = "Missing PHP extension: {$extension}.";
    }
}

$databaseConfig = $projectRoot . '/config/database.php';
$emailConfig = $projectRoot . '/config/email.php';

if (!is_readable($databaseConfig)) {
    $errors[] = 'Missing config/database.php. Copy config/database.example.php and add the production database credentials.';
} else {
    require_once $databaseConfig;

    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'SITE_URL'] as $constant) {
        if (!defined($constant) || constant($constant) === '') {
            $errors[] = "Database configuration constant {$constant} is missing or empty.";
        }
    }

    if (defined('DB_NAME') && DB_NAME === 'your_database_name') {
        $errors[] = 'config/database.php still contains example database credentials.';
    }
}

if (!is_readable($emailConfig)) {
    $errors[] = 'Missing config/email.php. Copy config/email.example.php and add the production mail settings.';
}

foreach (['uploads/receipts', 'backup'] as $relativeDirectory) {
    $directory = $projectRoot . '/' . $relativeDirectory;
    if (!is_dir($directory)) {
        $errors[] = "Missing writable directory: {$relativeDirectory}.";
    } elseif (!is_writable($directory)) {
        $warnings[] = "Directory is not writable by this CLI user: {$relativeDirectory}. Verify the PHP user can write to it.";
    }
}

$htaccess = $projectRoot . '/.htaccess';
if (!is_readable($htaccess)) {
    $errors[] = 'Missing the project .htaccess file.';
} else {
    $htaccessContents = file_get_contents($htaccess);
    if (strpos($htaccessContents, 'BEGIN WordPress') !== false || strpos($htaccessContents, 'speedycache') !== false) {
        $errors[] = 'The root .htaccess is still a WordPress/SpeedyCache file. Replace it with the version tracked by this repository.';
    }
}

foreach ($warnings as $warning) {
    fwrite(STDOUT, "WARNING: {$warning}" . PHP_EOL);
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}" . PHP_EOL);
    }
    fwrite(STDERR, 'Deployment preflight failed.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Deployment preflight passed.' . PHP_EOL);
exit(0);
