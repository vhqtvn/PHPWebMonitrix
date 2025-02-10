<?php

namespace Jobs;

use App\Framework\Job\BaseJob;
use App\Framework\Logger;

class {{JobName}} extends BaseJob
{
    protected function configure(): void
    {
        $this->config = array_merge($this->config, [
            'schedule' => '* * * * *',     // Run every minute
            'timeout' => 300,              // 5 minutes timeout
            'allow_overlapping' => false,  // Don't allow multiple instances
            'retry_attempts' => 3,         // Retry 3 times
            'retry_delay' => 60,          // Wait 60 seconds between retries
            'notify_on_error' => true,    // Notify on errors
            'notify_on_success' => false, // Don't notify on success
        ]);
    }

    protected function execute(): void
    {
        // Implement your job logic here
        $this->logger->info("{{JobName}} is running");
    }
}