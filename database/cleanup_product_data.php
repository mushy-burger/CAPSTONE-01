<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This cleanup script can only be run from the command line.\n");
}

require_once __DIR__ . '/../includes/db.php';

if (!in_array('--confirm', $argv, true)) {
    echo "Safety check: rerun with --confirm to create a backup and delete approved product data.\n";
    echo "Example: php " . basename(__FILE__) . " --confirm\n";
    exit(0);
}

$database = DB_NAME;
$timestamp = date('Ymd_His');
$backupDir = __DIR__ . '/backups';
$backupFile = $backupDir . "/{$database}_product_backup_{$timestamp}.sql";

$deleteOrder = [
    'cart_items',
    'order_items',
    'booking_products',
    'service_booking_items',
    'service_products',
    'products',
];

function shellArg(string $value): string
{
    return escapeshellarg($value);
}

function findMysqldump(): string
{
    $candidates = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        'mysqldump',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === 'mysqldump' || is_file($candidate)) {
            return $candidate;
        }
    }

    return 'mysqldump';
}

function createBackup(string $backupDir, string $backupFile): void
{
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
        throw new RuntimeException("Unable to create backup directory: {$backupDir}");
    }

    $mysqldump = findMysqldump();
    $command = shellArg($mysqldump)
        . ' --host=' . shellArg(DB_HOST)
        . ' --user=' . shellArg(DB_USER)
        . (DB_PASS !== '' ? ' --password=' . shellArg(DB_PASS) : '')
        . ' --single-transaction --routines --triggers '
        . shellArg(DB_NAME)
        . ' > '
        . shellArg($backupFile)
        . ' 2>&1';

    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || !is_file($backupFile) || filesize($backupFile) === 0) {
        $message = $output ? implode(PHP_EOL, $output) : 'No output from mysqldump.';
        throw new RuntimeException("Backup failed. Cleanup was not started.\n{$message}");
    }
}

try {
    echo "Creating backup...\n";
    createBackup($backupDir, $backupFile);
    echo "Backup created: {$backupFile}\n\n";

    $pdo = getDB();
    $pdo->beginTransaction();

    echo "Deleting approved product data with DELETE statements...\n";
    foreach ($deleteOrder as $table) {
        $affectedRows = $pdo->exec("DELETE FROM `{$table}`");
        echo str_pad($table, 24) . ': ' . (int)$affectedRows . " rows deleted\n";
    }

    $pdo->commit();
    echo "\nProduct cleanup committed successfully.\n";
    echo "No tables were dropped. No schema changes were made. Auto-increment counters were not reset.\n";
    echo "Categories, services, settings, accounts, motorcycle data, auth data, and uploaded files were not touched.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "\nProduct cleanup failed. Any transaction changes were rolled back.\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
