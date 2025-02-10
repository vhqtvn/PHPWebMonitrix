<?php

namespace App\Framework\Job;

use App\Framework\Logger;

abstract class BaseJob
{
    protected array $config = [
        'schedule' => '* * * * *',      // Cron expression for scheduling
        'timeout' => 300,               // Maximum execution time in seconds
        'allow_overlapping' => false,   // Whether to allow multiple instances
        'retry_attempts' => 3,          // Number of retry attempts
        'retry_delay' => 60,            // Delay between retries in seconds
        'enabled' => true,              // Whether the job is enabled
        'notify_on_error' => true,      // Whether to send notifications on error
        'notify_on_success' => false,   // Whether to send notifications on success
    ];

    protected Logger $logger;
    protected bool $testMode = false;
    protected bool $loggingEnabled = true;
    protected JobState $state;

    public function __construct(bool $testMode = false)
    {
        $this->testMode = $testMode;
        $this->state = new JobState(static::class);
        $className = basename(str_replace('\\', '/', static::class));
        $this->logger = Logger::getInstance()->scope($className);
        $this->configure();
    }

    /**
     * Disable logging for this job instance
     */
    public function disableLogging(): void
    {
        $this->loggingEnabled = false;
    }

    /**
     * Override this method to configure the job
     */
    protected function configure(): void
    {
    }

    /**
     * The main job logic to be implemented by concrete classes
     */
    abstract protected function execute(): void;

    /**
     * Get job configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if the job should run based on schedule
     */
    public function shouldRun(): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        // Always run in test mode
        if ($this->testMode) {
            return true;
        }

        // Parse cron expression and check if it matches current time
        return $this->matchesCronExpression();
    }

    /**
     * Make the job invokable for easy testing
     */
    public function __invoke(bool $force = false): void
    {
        $oldTestMode = $this->testMode;
        if ($force) {
            $this->testMode = true;
        }

        try {
            $this->run();
        } finally {
            $this->testMode = $oldTestMode;
        }
    }

    /**
     * Run the job with all configured behaviors
     */
    public function run(): void
    {
        if (!$this->shouldRun()) {
            if ($this->loggingEnabled) {
                $this->logger->info("Job skipped: not scheduled to run now");
            }
            return;
        }

        $lockFile = $this->getLockFile();
        if (!$this->testMode && !$this->config['allow_overlapping'] && file_exists($lockFile)) {
            if ($this->loggingEnabled) {
                $this->logger->warning("Job is already running");
            }
            return;
        }

        try {
            if (!$this->testMode) {
                $this->acquireLock();
                $this->setTimeout();
            }
            
            $attempt = 0;
            do {
                try {
                    $this->execute();
                    if ($this->config['notify_on_success'] && $this->loggingEnabled) {
                        // Implement success notification
                    }
                    break;
                } catch (\Throwable $e) {
                    $attempt++;
                    if ($attempt >= $this->config['retry_attempts']) {
                        throw $e;
                    }
                    if ($this->loggingEnabled) {
                        $this->logger->warning("Retry attempt {$attempt} after error: " . $e->getMessage());
                    }
                    sleep($this->config['retry_delay']);
                }
            } while ($attempt < $this->config['retry_attempts']);

        } catch (\Throwable $e) {
            if ($this->loggingEnabled) {
                $this->logger->error("Job failed: " . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ]);
                if ($this->config['notify_on_error']) {
                    // Implement error notification
                }
            }
            throw $e;
        } finally {
            if (!$this->testMode) {
                $this->releaseLock();
            }
        }
    }

    private function getLockFile(): string
    {
        $class = str_replace('\\', '_', static::class);
        return sys_get_temp_dir() . "/job_lock_{$class}.lock";
    }

    private function acquireLock(): void
    {
        if (!$this->config['allow_overlapping']) {
            file_put_contents($this->getLockFile(), getmypid());
        }
    }

    private function releaseLock(): void
    {
        $lockFile = $this->getLockFile();
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    private function setTimeout(): void
    {
        set_time_limit($this->config['timeout']);
    }

    private function matchesCronExpression(): bool
    {
        // Simple cron expression matching
        // In a real implementation, you'd want to use a proper cron parser library
        $now = new \DateTime();
        $schedule = $this->config['schedule'];
        
        // For now, just return true if it's "* * * * *" (every minute)
        // You should implement proper cron expression parsing here
        return $schedule === '* * * * *' || $this->parseCronExpression($schedule, $now);
    }

    private function parseCronExpression(string $schedule, \DateTime $now): bool
    {
        // TODO: Implement proper cron expression parsing
        // For now, we'll just handle basic expressions
        $parts = explode(' ', $schedule);
        if (count($parts) !== 5) {
            return false;
        }

        $minute = $now->format('i');
        $hour = $now->format('H');
        $dayOfMonth = $now->format('d');
        $month = $now->format('m');
        $dayOfWeek = $now->format('w');

        return $this->matchesCronPart($parts[0], $minute) &&
               $this->matchesCronPart($parts[1], $hour) &&
               $this->matchesCronPart($parts[2], $dayOfMonth) &&
               $this->matchesCronPart($parts[3], $month) &&
               $this->matchesCronPart($parts[4], $dayOfWeek);
    }

    private function matchesCronPart(string $pattern, string $value): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (strpos($pattern, '/') !== false) {
            [$_, $step] = explode('/', $pattern);
            return (int)$value % (int)$step === 0;
        }

        if (strpos($pattern, ',') !== false) {
            $values = explode(',', $pattern);
            return in_array($value, $values);
        }

        if (strpos($pattern, '-') !== false) {
            [$start, $end] = explode('-', $pattern);
            return (int)$value >= (int)$start && (int)$value <= (int)$end;
        }

        return $pattern === $value;
    }

    /**
     * Get a state value
     */
    protected function getState(string $key, $default = null)
    {
        return $this->state->get($key, $default);
    }

    /**
     * Set a state value
     */
    protected function setState(string $key, $value, callable $onChange = null): void
    {
        $this->state->set($key, $value, $onChange);
    }

    /**
     * Check and update state if closure returns true
     * @param string $key The state key to check
     * @param mixed $newValue The new value to potentially set
     * @param callable $closure Function that takes ($newValue, $oldValue) and returns bool
     * @return bool True if state was updated, false otherwise
     */
    protected function checkStateUpdate(string $key, $newValue, callable $closure): bool
    {
        return $this->state->checkUpdate($key, $newValue, $closure);
    }
} 