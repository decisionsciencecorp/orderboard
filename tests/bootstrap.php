<?php
/**
 * PHPUnit bootstrap - use isolated test DB and logs.
 */
$testBase = dirname(__DIR__) . DIRECTORY_SEPARATOR . '_env';
if (!is_dir($testBase)) {
    mkdir($testBase, 0755, true);
}
$dbDir = $testBase . DIRECTORY_SEPARATOR . 'db';
$logsDir = $testBase . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}
putenv('ORDERBOARD_BASE=' . $testBase);
$_ENV['ORDERBOARD_BASE'] = $testBase;
// Fresh DB file each run
$dbPath = $dbDir . DIRECTORY_SEPARATOR . 'orderboard.db';
if (file_exists($dbPath)) {
    unlink($dbPath);
}
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php';
