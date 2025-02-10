<?php

namespace App\Framework\CLI;

use App\Framework\Logger;

class CommandRunner
{
    private string $jobsDir;
    private string $templatePath;
    private Logger $logger;

    public function __construct()
    {
        $this->jobsDir = __DIR__ . '/../../../jobs';
        $this->templatePath = __DIR__ . '/templates/job.template.php';
        $this->logger = Logger::getInstance();
    }

    public function createJob(string $jobName): void
    {
        // Validate job name
        if (!preg_match('/^[A-Z][A-Za-z0-9]+$/', $jobName)) {
            throw new \InvalidArgumentException("Job name must start with uppercase letter and contain only alphanumeric characters");
        }

        $jobFile = "{$this->jobsDir}/{$jobName}.php";
        if (file_exists($jobFile)) {
            throw new \RuntimeException("Job {$jobName} already exists");
        }

        // Create jobs directory if it doesn't exist
        if (!is_dir($this->jobsDir)) {
            mkdir($this->jobsDir, 0755, true);
        }

        // Create template directory if it doesn't exist
        $templateDir = dirname($this->templatePath);
        if (!is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }

        // Create template if it doesn't exist
        if (!file_exists($this->templatePath)) {
            $this->createTemplate();
        }

        // Read template and replace placeholders
        $template = file_get_contents($this->templatePath);
        $jobContent = str_replace('{{JobName}}', $jobName, $template);

        // Create the job file
        file_put_contents($jobFile, $jobContent);
        $this->logger->info("Created new job: {$jobName}");
        echo "Created new job: {$jobFile}\n";
    }

    public function disableJob(string $jobName): void
    {
        $jobFile = "{$this->jobsDir}/{$jobName}.php";
        $disabledFile = "{$this->jobsDir}/{$jobName}.disable.php";

        if (!file_exists($jobFile)) {
            throw new \RuntimeException("Job {$jobName} not found");
        }

        rename($jobFile, $disabledFile);
        $this->logger->info("Disabled job: {$jobName}");
        echo "Disabled job: {$jobName}\n";
    }

    public function enableJob(string $jobName): void
    {
        $baseFile = "{$this->jobsDir}/{$jobName}";
        $disabledFile1 = $baseFile . '.disable.php';
        $disabledFile2 = $baseFile . '-disable.php';
        $enabledFile = $baseFile . '.php';

        if (file_exists($disabledFile1)) {
            rename($disabledFile1, $enabledFile);
        } elseif (file_exists($disabledFile2)) {
            rename($disabledFile2, $enabledFile);
        } else {
            throw new \RuntimeException("Disabled job {$jobName} not found");
        }

        $this->logger->info("Enabled job: {$jobName}");
        echo "Enabled job: {$jobName}\n";
    }

    public function listJobs(): void
    {
        if (!is_dir($this->jobsDir)) {
            echo "No jobs directory found\n";
            return;
        }

        $files = glob($this->jobsDir . '/*.php');
        if (empty($files)) {
            echo "No jobs found\n";
            return;
        }

        echo "\nJobs List:\n";
        echo str_repeat('-', 50) . "\n";
        echo sprintf("%-30s %-20s\n", 'Job Name', 'Status');
        echo str_repeat('-', 50) . "\n";

        foreach ($files as $file) {
            $fileName = basename($file);
            $jobName = preg_replace('/\.(?:disable-?)?php$/', '', $fileName);
            $status = preg_match('/([-.]disable\.php$)/', $fileName) ? 'Disabled' : 'Enabled';
            
            echo sprintf("%-30s %-20s\n", $jobName, $status);
        }
        echo str_repeat('-', 50) . "\n";
    }

    public function invokeJob(string $jobName, bool $sync = false): void
    {
        $jobFile = "{$this->jobsDir}/{$jobName}.php";
        $disabledFile1 = "{$this->jobsDir}/{$jobName}.disable.php";
        $disabledFile2 = "{$this->jobsDir}/{$jobName}-disable.php";

        $actualFile = null;
        if (file_exists($jobFile)) {
            $actualFile = $jobFile;
        } elseif (file_exists($disabledFile1)) {
            $actualFile = $disabledFile1;
        } elseif (file_exists($disabledFile2)) {
            $actualFile = $disabledFile2;
        }

        if (!$actualFile) {
            throw new \RuntimeException("Job {$jobName} not found");
        }

        $fullyQualifiedClassName = "\\Jobs\\{$jobName}";

        if ($sync) {
            // Synchronous execution for testing - output directly
            \App\Framework\JobRunner::invoke($fullyQualifiedClassName, false);
        } else {
            // Asynchronous execution - run in background
            $logDir = __DIR__ . "/../../../storage/logs";
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . "/job-{$jobName}-" . date('Y-m-d-H-i-s') . ".log";

            // Run the job in background using PHP binary
            $cmd = sprintf(
                'nohup %s -r %s > %s 2>&1 & echo $!',
                PHP_BINARY,
                escapeshellarg(sprintf(
                    'require "%s"; \App\Framework\JobRunner::invoke("%s", true);',
                    dirname(dirname(dirname(__DIR__))) . '/preload.php',
                    $fullyQualifiedClassName
                )),
                escapeshellarg($logFile)
            );
            
            exec($cmd, $output);
            if (!empty($output)) {
                $pid = $output[0];
                echo "Job {$jobName} started in background with PID {$pid}. Log file: {$logFile}\n";
            }
        }
    }

    private function createTemplate(): void
    {
        $template = <<<'PHP'
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
PHP;
        file_put_contents($this->templatePath, $template);
    }

    public function installShellCompletion(): void
    {
        $appRoot = dirname(dirname(dirname(__DIR__)));
        $shellDir = $appRoot . '/shell';
        $binDir = $appRoot . '/bin';

        // Create directories if they don't exist
        foreach ([$shellDir, $binDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Create shell initialization script
        $this->createShellInit($shellDir);

        // Create completion scripts
        $this->createBashCompletionScript($shellDir . '/app.completion.bash');
        $this->createZshCompletionScript($shellDir . '/app.completion.zsh');

        // Create symlink in local bin directory
        $binPath = $binDir . '/app';
        if (!file_exists($binPath)) {
            symlink($appRoot . '/app', $binPath);
        }

        echo "\nInstallation completed!\n";
        echo "\nTo use the app, add the following line to your shell's rc file:\n";
        echo "source " . $shellDir . "/init.sh\n\n";
        echo "Or for temporary use in current shell, run:\n";
        echo "source " . $shellDir . "/init.sh\n\n";
    }

    private function createShellInit(string $shellDir): void
    {
        $initScript = <<<'SHELL'
#!/bin/bash

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Add project bin to PATH if not already there
if [[ ":$PATH:" != *":$PROJECT_ROOT/bin:"* ]]; then
    export PATH="$PROJECT_ROOT/bin:$PATH"
fi

# Detect shell type
SHELL_TYPE=$(basename "$SHELL")

# Source appropriate completion file
case "$SHELL_TYPE" in
    "zsh")
        source "$SCRIPT_DIR/app.completion.zsh"
        ;;
    "bash"|*)
        source "$SCRIPT_DIR/app.completion.bash"
        ;;
esac

# Add alias for convenience
alias app="$PROJECT_ROOT/app"
SHELL;

        file_put_contents($shellDir . '/init.sh', $initScript);
        chmod($shellDir . '/init.sh', 0755);
    }

    private function createBashCompletionScript(string $path): void
    {
        $script = <<<'BASH'
# bash completion for app monitor tool

_app_completion()
{
    local cur prev words cword
    _init_completion || return

    # List of all available commands
    local commands="cron create disable enable list invoke run"

    case $prev in
        create|disable|enable|invoke|run)
            # Get list of jobs (excluding .disable.php and -disable.php files)
            local jobs_dir="$(dirname $(dirname ${BASH_SOURCE[0]}))/jobs"
            COMPREPLY=( $(compgen -W "$(ls $jobs_dir/*.php 2>/dev/null | grep -v '[.-]disable\.php$' | xargs -n1 basename -s .php)" -- "$cur") )
            return 0
            ;;
        app)
            # Complete command names
            COMPREPLY=( $(compgen -W "$commands" -- "$cur") )
            return 0
            ;;
    esac

    # Default to command names if no specific completion
    COMPREPLY=( $(compgen -W "$commands" -- "$cur") )
} &&
complete -F _app_completion app
BASH;
        file_put_contents($path, $script);
        chmod($path, 0644);
    }

    private function createZshCompletionScript(string $path): void
    {
        $script = <<<'ZSH'
#compdef app

_app() {
    local curcontext="$curcontext" state line
    typeset -A opt_args

    _arguments -C \
        '1: :->command' \
        '2: :->argument' \
        '*: :->args'

    case $state in
        command)
            local commands
            commands=(
                'cron:Run the job scheduler'
                'create:Create a new job from template'
                'disable:Disable a job'
                'enable:Enable a disabled job'
                'list:List all jobs and their status'
                'invoke:Run a specific job once (for testing)'
                'run:Alias for invoke'
            )
            _describe -t commands 'app commands' commands
            ;;
        argument)
            case $line[1] in
                create|disable|enable|invoke|run)
                    local jobs_dir="$(dirname $(dirname $0))/jobs"
                    local -a jobs
                    jobs=( ${jobs_dir}/*.php(N:t:r) )
                    # Filter out disabled jobs
                    jobs=( ${jobs:#*disable} )
                    _wanted jobs expl 'jobs' compadd -a jobs
                    ;;
            esac
            ;;
    esac
}
ZSH;
        file_put_contents($path, $script);
        chmod($path, 0644);
    }
} 