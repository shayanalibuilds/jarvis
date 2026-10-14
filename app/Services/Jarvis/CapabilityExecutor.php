<?php

declare(strict_types=1);

namespace App\Services\Jarvis;

use App\Exceptions\Jarvis\BlockedCommandException;
use App\Exceptions\Jarvis\JarvisInterruptedException;
use App\Models\JarvisSession;
use App\Services\Jarvis\ComputerUse\ComputerUseManager;
use App\Services\Jarvis\Shell\CommandRunner;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Executes a capability either on the connected desktop device or locally.
 *
 * Every action is routed through here so the desktop application and the
 * server behave identically, and so the session guard is always applied.
 */
final class CapabilityExecutor
{
    public function __construct(
        private readonly DeviceBridge $devices,
        private readonly CommandRunner $runner,
        private readonly ComputerUseManager $computer,
    ) {}

    /**
     * Determine where the next capability should run.
     */
    public function targetFor(JarvisSession $session): string
    {
        $configured = (string) config('jarvis.shell.target', 'auto');

        return match ($configured) {
            'device' => 'device',
            'local' => 'local',
            default => $this->devices->hasActiveDevice($session->user) ? 'device' : 'local',
        };
    }

    /**
     * Execute a capability for the session and return a JSON result string.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function execute(JarvisSession $session, string $capability, array $arguments): string
    {
        if (! $session->refresh()->allowsExecution()) {
            throw JarvisInterruptedException::forSession($session);
        }

        if ($this->targetFor($session) === 'device') {
            return $this->devices->dispatch($session, $capability, $arguments);
        }

        return $this->runLocally($capability, $arguments);
    }

    /**
     * Run a capability on the machine hosting the application.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function runLocally(string $capability, array $arguments): string
    {
        return match ($capability) {
            'run_shell_command' => $this->runShellCommand($arguments),
            'computer_use' => $this->runComputerUse($arguments),
            'read_file' => $this->readFile($arguments),
            'write_file' => $this->writeFile($arguments),
            'list_directory' => $this->listDirectory($arguments),
            default => $this->error("Unknown capability [{$capability}]."),
        };
    }

    /**
     * Run a shell command on this machine.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function runShellCommand(array $arguments): string
    {
        if (! (bool) config('jarvis.shell.enabled', true)) {
            return $this->error('Shell execution is disabled.');
        }

        $command = (string) ($arguments['command'] ?? '');

        if (mb_trim($command) === '') {
            return $this->error('A command is required.');
        }

        try {
            $result = $this->runner->run(
                command: $command,
                workingDirectory: $this->stringOrNull($arguments['working_directory'] ?? null)
                    ?? $this->stringOrNull(config('jarvis.shell.working_directory')),
                timeout: is_numeric($arguments['timeout'] ?? null) ? (int) $arguments['timeout'] : null,
            );
        } catch (BlockedCommandException $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->json([
            'capability' => 'run_shell_command',
            'target' => 'local',
            ...$result->toArray(),
        ]);
    }

    /**
     * Run a computer use action on this machine.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function runComputerUse(array $arguments): string
    {
        if (! (bool) config('jarvis.computer_use.enabled', true)) {
            return $this->error('Computer use is disabled.');
        }

        $action = (string) ($arguments['action'] ?? '');
        $driver = $this->computer->driver();

        try {
            $output = match ($action) {
                'screenshot' => ['path' => $this->computer->screenshot()],
                'move' => $this->nullify($driver->move($this->int($arguments, 'x'), $this->int($arguments, 'y'))),
                'click' => $this->nullify($driver->click(
                    $this->int($arguments, 'x'),
                    $this->int($arguments, 'y'),
                    $this->stringOrNull($arguments['button'] ?? null) ?? 'left',
                    $this->int($arguments, 'clicks', 1),
                )),
                'type' => $this->nullify($driver->type((string) ($arguments['text'] ?? ''))),
                'key' => $this->nullify($driver->pressKey((string) ($arguments['key'] ?? ''))),
                'scroll' => $this->nullify($driver->scroll($this->int($arguments, 'amount'))),
                'open_app' => $this->nullify($driver->openApplication((string) ($arguments['application'] ?? ''))),
                default => throw new RuntimeException("Unknown computer use action [{$action}]."),
            };
        } catch (Throwable $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->json([
            'capability' => 'computer_use',
            'target' => 'local',
            'driver' => $driver->name(),
            'action' => $action,
            'output' => $output,
        ]);
    }

    /**
     * Read a file from this machine.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function readFile(array $arguments): string
    {
        $path = (string) ($arguments['path'] ?? '');

        if (! File::exists($path)) {
            return $this->error("The file [{$path}] does not exist.");
        }

        return $this->json([
            'capability' => 'read_file',
            'target' => 'local',
            'path' => $path,
            'contents' => File::get($path),
        ]);
    }

    /**
     * Write a file to this machine.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function writeFile(array $arguments): string
    {
        $path = (string) ($arguments['path'] ?? '');
        $contents = (string) ($arguments['contents'] ?? '');

        if ($this->isProtectedPath($path)) {
            return $this->error("Writing to [{$path}] is blocked by the Jarvis configuration.");
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return $this->json([
            'capability' => 'write_file',
            'target' => 'local',
            'path' => $path,
            'bytes' => mb_strlen($contents),
        ]);
    }

    /**
     * List a directory on this machine.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function listDirectory(array $arguments): string
    {
        $path = (string) ($arguments['path'] ?? '.');

        if (! File::isDirectory($path)) {
            return $this->error("The directory [{$path}] does not exist.");
        }

        $entries = File::glob(mb_rtrim($path, '/').'/*');

        return $this->json([
            'capability' => 'list_directory',
            'target' => 'local',
            'path' => $path,
            'entries' => array_map(
                fn (string $entry): array => [
                    'name' => basename($entry),
                    'path' => $entry,
                    'type' => is_dir($entry) ? 'directory' : 'file',
                ],
                $entries === false ? [] : $entries,
            ),
        ]);
    }

    /**
     * Determine whether a path is protected from writes.
     */
    private function isProtectedPath(string $path): bool
    {
        /** @var list<string> $protected */
        $protected = config('jarvis.filesystem.protected_paths', []);

        foreach ($protected as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function int(array $arguments, string $key, int $default = 0): int
    {
        $value = $arguments[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Convert a void return into something JSON serialisable.
     */
    private function nullify(mixed $value): mixed
    {
        return $value ?? ['ok' => true];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function error(string $message): string
    {
        return $this->json([
            'successful' => false,
            'error' => $message,
        ]);
    }
}
