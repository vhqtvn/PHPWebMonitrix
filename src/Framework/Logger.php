<?php

namespace App\Framework;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static ?self $instance = null;
    private MonologLogger $logger;
    private string $scope = '';

    private function __construct()
    {
        $this->logger = $this->createLogger();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function createLogger(): MonologLogger
    {
        $logger = new MonologLogger('monitor');

        // Create a formatter
        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        // Create handlers
        $streamHandler = new StreamHandler('php://stdout', MonologLogger::DEBUG);
        $streamHandler->setFormatter($formatter);

        $fileHandler = new RotatingFileHandler(
            __DIR__ . '/../../storage/logs/monitor.log',
            30,
            MonologLogger::INFO
        );
        $fileHandler->setFormatter($formatter);

        // Add handlers to logger
        $logger->pushHandler($streamHandler);
        $logger->pushHandler($fileHandler);

        return $logger;
    }

    public function scope(string $prefix): self
    {
        $newLogger = new self();
        $newLogger->scope .= "[{$prefix}] ";
        return $newLogger;
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($this->scope . $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($this->scope . $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($this->scope . $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($this->scope . $message, $context);
    }

    // Prevent cloning of the instance
    private function __clone()
    {
    }

    // Prevent unserializing of the instance
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
} 