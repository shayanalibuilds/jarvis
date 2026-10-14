<?php

declare(strict_types=1);

namespace App\Services\Jarvis\Shell;

use App\Exceptions\Jarvis\BlockedCommandException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final class CommandRunner
{
    public function __construct(
        private readonly int $timeout = 60,
        private readonly int $maxOutputLength = 20000,
    ) {}

    /**
     * Run a shell command and capture its output.
     *
     * @throws BlockedCommandException
     */
    public function run(string $command, ?string $workingDirectory = null, ?int $timeout = null): CommandResult
    {
        $this->ensureCommandIsAllowed($command);

        $timeout ??= $this->timeout;
        $startedAt = hrtime(true);

        $process = Process::fromShellCommandline($command, $workingDirectory, timeout: $timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $process->stop(0);

            return new CommandResult(
                command: $command,
                output: $this->truncate($this->combine($process)),
                exitCode: null,
                durationMs: $this->elapsedMilliseconds($startedAt),
                timedOut: true,
            );
        } catch (Throwable $exception) {
            return new CommandResult(
                command: $command,
                output: $exception->getMessage(),
                exitCode: null,
                durationMs: $this->elapsedMilliseconds($startedAt),
            );
        }

        return new CommandResult(
            command: $command,
            output: $this->truncate($this->combine($process)),
            exitCode: $process->getExitCode(),
            durationMs: $this->elapsedMilliseconds($startedAt),
        );
    }

    /**
     * Ensure the command is not permanently blocked.
     *
     * @throws BlockedCommandException
     */
    public function ensureCommandIsAllowed(string $command): void
    {
        /** @var list<string> $patterns */
        $patterns = config('jarvis.shell.blocked_patterns', []);

        foreach ($patterns as $pattern) {
            if ($pattern !== '' && mb_stripos($command, $pattern) !== false) {
                throw BlockedCommandException::forCommand($command, $pattern);
            }
        }
    }

    /**
     * Combine the standard output and error output of the process.
     */
    private function combine(Process $process): string
    {
        return mb_trim($process->getOutput().$process->getErrorOutput());
    }

    /**
     * Truncate output that exceeds the configured maximum length.
     */
    private function truncate(string $output): string
    {
        if (mb_strlen($output) <= $this->maxOutputLength) {
            return $output;
        }

        return mb_substr($output, 0, $this->maxOutputLength)."\n\n[output truncated]";
    }

    /**
     * Get the number of milliseconds since the given start time.
     */
    private function elapsedMilliseconds(int|float $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
