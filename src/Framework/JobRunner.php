<?php

namespace App\Framework;

use App\Framework\Job\BaseJob;

class JobRunner
{
    private array $jobs = [];
    private Logger $logger;

    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->discoverJobs();
    }

    private function discoverJobs(): void
    {
        $jobsDir = __DIR__ . '/../../jobs';
        if (!is_dir($jobsDir)) {
            throw new \RuntimeException("Jobs directory not found: {$jobsDir}");
        }

        $files = glob($jobsDir . '/*.php');
        foreach ($files as $file) {
            // Skip disabled jobs
            if (preg_match('/([-.]disable\.php$)/', $file)) {
                continue;
            }

            // Get the class name from the file
            $className = basename($file, '.php');
            $fullyQualifiedClassName = "\\Jobs\\{$className}";

            // Require the file first
            require_once $file;

            // Check if the class exists and is a valid job
            if (!class_exists($fullyQualifiedClassName)) {
                $this->logger->warning("Class {$fullyQualifiedClassName} not found in {$file}");
                continue;
            }

            if (!is_subclass_of($fullyQualifiedClassName, BaseJob::class)) {
                $this->logger->warning("Class {$fullyQualifiedClassName} must extend BaseJob");
                continue;
            }

            $this->jobs[$className] = $fullyQualifiedClassName;
        }
    }

    public static function handleException(\Throwable $e, string $jobName = "Unknown"): void
    {
        Notifier::send("@vhnvn Error in job {$jobName}: " . $e->getMessage());
    }

    public function onException(\Throwable $e, string $jobName = "Unknown"): void
    {
        self::handleException($e, $jobName);
        $this->logger->error("Error in job {$jobName}: " . $e->getMessage(), [
            'exception' => $e,
            'trace' => $e->getTraceAsString()
        ]);
    }

    public static function invoke(string $jobClass, bool $disableLogging = false): void
    {
        try {
            /** @var BaseJob $job */
            $job = new $jobClass();
            if ($disableLogging) {
                $job->disableLogging();
            }
            $job->run();
        } catch (\Throwable $e) {
            self::handleException($e, $jobClass);
            throw $e;
        }
    }

    public function run(bool $runInBackground = false): void
    {
        if (empty($this->jobs)) {
            $this->logger->warning("No jobs found to run");
            return;
        }

        foreach ($this->jobs as $jobName => $jobClass) {
            try {
                $this->logger->info("Processing job: {$jobName}");

                if ($runInBackground) {
                    // Get the job file path from the class name
                    $jobFile = __DIR__ . '/../../jobs/' . $jobName . '.php';
                    if (!file_exists($jobFile)) {
                        throw new \RuntimeException("Job file not found: {$jobFile}");
                    }

                    // Run the job in background using PHP binary
                    $cmd = sprintf(
                        'nohup %s -r %s > /dev/null 2>&1 & echo $!',
                        PHP_BINARY,
                        escapeshellarg(sprintf(
                            'require "%s"; \App\Framework\JobRunner::invoke("%s", true);',
                            __DIR__ . '/../../preload.php',
                            $jobClass
                        ))
                    );
                    
                    exec($cmd, $output);
                    if (!empty($output)) {
                        $pid = $output[0];
                        $this->logger->info("Started job {$jobName} in background with PID {$pid}");
                    }
                } else {
                    /** @var BaseJob $job */
                    $job = new $jobClass();
                    $job->run();
                }
            } catch (\Throwable $e) {
                $this->onException($e, $jobName);
            }
        }
    }

    /**
     * Get list of discovered jobs
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }
}
