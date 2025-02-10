<?php

// Include composer autoloader for dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Framework core classes
require_once __DIR__ . '/src/Framework/Job/BaseJob.php';
require_once __DIR__ . '/src/Framework/Logger.php';
require_once __DIR__ . '/src/Framework/Curl.php';
require_once __DIR__ . '/src/Framework/Notifier.php';
require_once __DIR__ . '/src/Framework/Scraper.php';

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(sprintf('%s=%s', trim($name), trim($value)));
    }
}

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

