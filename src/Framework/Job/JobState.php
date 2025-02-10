<?php

namespace App\Framework\Job;

use InvalidArgumentException;

class JobState
{
    private string $jobClass;
    private string $stateFile;

    public function __construct(string $jobClass)
    {
        $this->jobClass = $jobClass;
        $this->stateFile = $this->getStateFile();
    }

    public function get(string $key, $default = null)
    {
        $state = $this->loadState();
        return $state[$key] ?? $default;
    }

    /**
     * Set a state value
     * @param string $key The key to set
     * @param mixed $value The value to set
     * @param null|callable $onChanged Function that takes ()|($newValue)|($newValue, $oldValue), which will be called if the value was set
     * @return bool True if state was updated, false otherwise
     * @throws InvalidArgumentException If closure has invalid number of parameters
     */
    public function set(string $key, $value, ?callable $onChanged = null): bool
    {
        $state = $this->loadState();
        $oldValue = $state[$key] ?? null;

        if ($value !== $oldValue) {
            $state[$key] = $value;
            $this->saveState($state);

            if ($onChanged) {
                $reflection = new \ReflectionFunction($onChanged);
                $paramCount = $reflection->getNumberOfParameters();

                match ($paramCount) {
                    0 => $onChanged(),
                    1 => $onChanged($value),
                    2 => $onChanged($value, $oldValue),
                    default => throw new \InvalidArgumentException("Closure must have 0-2 parameters")
                };
            }
            return true;
        }
        return false;
    }

    /**
     * Check if the new value should be set and if so, set it
     * @param string $key The key to check
     * @param mixed $newValue The new value to potentially set
     * @param callable $closure Function that takes ()|($oldValue)|($newValue, $oldValue) and returns bool
     * @return bool True if state was updated, false otherwise
     * @throws InvalidArgumentException If closure has invalid number of parameters
     */
    public function checkUpdate(string $key, $newValue, callable $closure): bool
    {
        $oldValue = $this->get($key);

        // Use reflection to check closure parameters
        $reflection = new \ReflectionFunction($closure);
        $paramCount = $reflection->getNumberOfParameters();

        // Call closure based on parameter count
        $shouldUpdate = match ($paramCount) {
            0 => $closure(),
            1 => $closure($oldValue),
            2 => $closure($newValue, $oldValue),
            default => throw new \InvalidArgumentException("Closure must have 0-2 parameters")
        };

        if ($shouldUpdate) {
            $this->set($key, $newValue);
        }

        return $shouldUpdate;
    }

    private function loadState(): array
    {
        if (!file_exists($this->stateFile)) {
            return [];
        }
        $content = file_get_contents($this->stateFile);
        if (!$content) {
            return [];
        }
        return json_decode($content, true) ?? [];
    }

    private function saveState(array $state): void
    {
        file_put_contents($this->stateFile, json_encode($state));
    }

    private function getStateFile(): string
    {
        $class = str_replace('\\', '_', $this->jobClass);
        return sys_get_temp_dir() . "/job_state_{$class}.json";
    }
}
