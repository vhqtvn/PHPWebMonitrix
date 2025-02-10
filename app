#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use App\Framework\JobRunner;
use App\Framework\Logger;
use App\Framework\CLI\CommandRunner;

try {
    // Load environment variables
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $logger = Logger::getInstance();

    if ($argc < 2) {
        echo "Usage: ./app <command> [options]\n";
        echo "\nAvailable commands:\n";
        echo "  cron                    Run the job scheduler\n";
        echo "  create <job-name>       Create a new job from template\n";
        echo "  disable <job-name>      Disable a job\n";
        echo "  enable <job-name>       Enable a disabled job\n";
        echo "  list                    List all jobs and their status\n";
        echo "  invoke <job-name>       Run a specific job once (for testing)\n";
        echo "  run <job-name>          Run a job in background\n";
        echo "  test <job-name>         Run a job synchronously for testing\n";
        echo "  install                 Install shell completion and add to PATH\n";
        exit(1);
    }

    $command = $argv[1];
    $args = array_slice($argv, 2);

    switch ($command) {
        case 'cron':
            $runner = new JobRunner();
            $runner->run(true);
            break;

        case 'create':
            if (empty($args)) {
                throw new \InvalidArgumentException("Job name is required");
            }
            $cmd = new CommandRunner();
            $cmd->createJob($args[0]);
            break;

        case 'disable':
            if (empty($args)) {
                throw new \InvalidArgumentException("Job name is required");
            }
            $cmd = new CommandRunner();
            $cmd->disableJob($args[0]);
            break;

        case 'enable':
            if (empty($args)) {
                throw new \InvalidArgumentException("Job name is required");
            }
            $cmd = new CommandRunner();
            $cmd->enableJob($args[0]);
            break;

        case 'list':
            $cmd = new CommandRunner();
            $cmd->listJobs();
            break;

        case 'invoke':
        case 'test':
            if (empty($args)) {
                throw new \InvalidArgumentException("Job name is required");
            }
            $cmd = new CommandRunner();
            $cmd->invokeJob($args[0], true); // Synchronous for testing
            break;

        case 'run':
            if (empty($args)) {
                throw new \InvalidArgumentException("Job name is required");
            }
            $cmd = new CommandRunner();
            $cmd->invokeJob($args[0], false); // Asynchronous
            break;

        case 'install':
            $cmd = new CommandRunner();
            $cmd->installShellCompletion();
            break;

        default:
            throw new \InvalidArgumentException("Unknown command: {$command}");
    }
} catch (\Throwable $e) {
    Logger::getInstance()->error('Command execution failed: ' . $e->getMessage(), [
        'exception' => $e,
        'trace' => $e->getTraceAsString()
    ]);
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 