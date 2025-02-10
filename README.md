# PHP Website Monitoring & Scraper Framework

A robust PHP framework for monitoring websites and running scheduled scraping jobs with advanced features like job scheduling, retry mechanisms, and notifications.

## Features

- **Flexible Job Scheduling**: Schedule jobs using cron expressions
- **Job Management**:
  - Timeout control
  - Overlapping job prevention
  - Retry mechanisms with configurable attempts and delays
  - Job state persistence
- **Logging & Monitoring**:
  - Comprehensive logging system using Monolog
  - Job status tracking
  - Success/Error notifications
- **Scraping Capabilities**:
  - Built-in DOM crawling using Symfony components
  - HTTP client integration with Guzzle
  - CSS selector support

## Requirements

- PHP >= 7.4
- Composer

## Installation

1. Clone the repository:
```bash
git clone https://github.com/vhqtvn/PHPWebMonitrix.git monitors
cd monitors
```

2. Install dependencies:
```bash
composer install
```

3. Configure environment:
```bash
cp .env.example .env
# Edit .env with your configuration
```

## Project Structure

- `src/` - Core framework source code
- `jobs/` - Job implementations
- `config/` - Configuration files
- `storage/logs/` - Log files
- `shell/` - Shell scripts and utilities
- `vendor/` - Composer dependencies

## Creating a New Job

1. Create a new job class in the `jobs/` directory
2. Extend the `App\Framework\Job\BaseJob` class
3. Implement the required `execute()` method
4. Configure job settings using the `$config` property

Example:
```php
<?php

namespace Jobs;

use App\Framework\Job\BaseJob;

class MyMonitorJob extends BaseJob
{
    protected array $config = [
        'schedule' => '*/5 * * * *',    // Run every 5 minutes
        'timeout' => 300,               // 5 minutes timeout
        'retry_attempts' => 3,
        'notify_on_error' => true
    ];

    protected function execute(): void
    {
        // Your monitoring/scraping logic here
    }
}
```

## Running Jobs

The framework provides several command-line tools for managing jobs:

```bash
# List all jobs and their status
./app list

# Create a new job from template
./app create MyNewJob

# Run a job in background
./app run MyMonitorJob

# Run a job synchronously for testing
./app test MyMonitorJob

# Run a specific job once (for testing)
./app invoke MyMonitorJob

# Enable/Disable jobs
./app enable MyMonitorJob
./app disable MyMonitorJob

# Run the job scheduler (for cron)
./app cron
```

### Monitor Shell Environment

The framework provides a dedicated shell environment for easier job management. To enter the monitor shell:

```bash
./x
```

Features of the monitor shell:
- Custom prompt showing `(monitor)` prefix
- Automatic PATH configuration
- Pre-loaded shell completions
- Convenient `app` alias for the command-line tool
- Prevention of nested shell instances
- Support for both Bash and Zsh

Example usage in monitor shell:
```bash
$ ./x
(monitor) $ app list          # List all jobs
(monitor) $ app create MyJob  # Create new job
(monitor) $ app test MyJob    # Test run a job
```

The monitor shell maintains your current working directory and inherits your existing shell configuration while adding the monitoring-specific enhancements.

## Setting Up Cron Jobs

The framework is designed to run as a cron-based job scheduler, where the scheduler itself runs every minute to check and execute jobs according to their individual schedules.

To set up the scheduler:

1. Open your crontab for editing:
```bash
crontab -e
```

2. Add the following line to run the job scheduler every minute:
```bash
* * * * * cd /path/to/your/project && ./app cron >> storage/logs/cron.log 2>&1
```

Replace `/path/to/your/project` with the absolute path to your project directory.

The scheduler will:
- Check for jobs due to run based on their cron expressions
- Handle job timeouts and overlapping prevention
- Manage retries for failed jobs
- Log execution status and errors
- Send notifications based on job configuration

Each job can have its own schedule configured via the `schedule` option in its config:
```php
protected array $config = [
    'schedule' => '*/5 * * * *',    // Run every 5 minutes
    'timeout' => 300,               // 5 minutes timeout
    'retry_attempts' => 3,
    'notify_on_error' => true
];
```

## Configuration

Jobs can be configured with the following options:- `schedule`: Cron expression for scheduling
- `timeout`: Maximum execution time in seconds
- `allow_overlapping`: Whether to allow multiple instances
- `retry_attempts`: Number of retry attempts
- `retry_delay`: Delay between retries in seconds
- `enabled`: Whether the job is enabled
- `notify_on_error`: Send notifications on error
- `notify_on_success`: Send notifications on success

## State Management

Jobs can maintain persistent state between runs using the built-in state management system. Each job has its own state file stored in the system's temporary directory.

### Basic State Operations

```php
class MyMonitorJob extends BaseJob
{
    protected function execute(): void
    {
        // Get a state value (with optional default)
        $lastValue = $this->state->get('last_value', 0);

        // Set a state value
        $this->state->set('last_value', 42);

        // Set with change callback
        $this->state->set('status', 'completed', function($newValue, $oldValue) {
            $this->logger->info("Status changed from {$oldValue} to {$newValue}");
        });
    }
}
```

### Conditional State Updates

The `checkUpdate` method allows for conditional state updates based on the old and new values:

```php
class PriceMonitorJob extends BaseJob
{
    protected function execute(): void
    {
        $newPrice = $this->fetchCurrentPrice();
        
        // Update only if price has increased
        $this->state->checkUpdate('price', $newPrice, function($newValue, $oldValue) {
            return $newValue > $oldValue;
        });

        // Or with single parameter (old value only)
        $this->state->checkUpdate('last_check', time(), function($oldValue) {
            return time() - $oldValue > 3600; // Update if more than 1 hour old
        });
    }
}
```

State values are automatically persisted between job runs and can be used to:
- Track the last processed item
- Store historical data
- Implement incremental processing
- Maintain job progress
- Implement rate limiting
- Track changes and trigger notifications

## License

MIT License

Copyright (c) 2024 vhnvn

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## Author

- vhnvn (Developer) 

