<?php

declare(strict_types=1);

namespace App\Services\Jarvis\Shell;

final readonly class CommandResult
{
    public function __construct(
        public string $command,
        public string $output,
        public ?int $exitCode,
        public int $durationMs,
        public bool $timedOut = false,
    ) {}

    /**
     * Determine whether the command completed successfully.
     */
    public function isSuccessful(): bool
    {
        return ! $this->timedOut && $this->exitCode === 0;
    }

    /**
     * Get a short, model friendly summary of the result.
     */
    public function summary(): string
    {
        if ($this->timedOut) {
            return sprintf('The command timed out after %dms and was terminated.', $this->durationMs);
        }

        return sprintf('Exit code: %s (in %dms)', $this->exitCode ?? 'unknown', $this->durationMs);
    }

    /**
     * Get the result as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'output' => $this->output,
            'exit_code' => $this->exitCode,
            'duration_ms' => $this->durationMs,
            'timed_out' => $this->timedOut,
            'successful' => $this->isSuccessful(),
        ];
    }
}
