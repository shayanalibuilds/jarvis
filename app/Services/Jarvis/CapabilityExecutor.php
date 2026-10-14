<?php

declare(strict_types=1);

namespace App\Services\Jarvis;

use App\Exceptions\Jarvis\BlockedCommandException;
use App\Exceptions\Jarvis\JarvisInterruptedException;
use App\Models\JarvisSession;
use App\Models\User;
use App\Services\Jarvis\ComputerUse\ComputerUseManager;
use App\Services\Jarvis\Shell\CommandRunner;
use Illuminate\Support\Facades\Config;
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
        $configured = Config::string('jarvis.shell.target', 'auto');

        return match ($configured) {
            'device' => 'device',
            'local' => 'local',
            default => $this->devices->hasActiveDevice($this->userFor($session)) ? 'device' : 'local',
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
        if (! Config::boolean('jarvis.shell.enabled', true)) {
            return $this->error('Shell execution is disabled.');
        }

        $command = $this->stringArgument($arguments, 'command');

        if (mb_trim($command) === '') {
            return $this->error('A command is required.');
        }

        $configuredWorkingDirectory = config('jarvis.shell.working_directory');

        try {
            $result = $this->runner->run(
                command: $command,
                workingDirectory: $this->stringArgument($arguments, 'working_directory')
                    ?: (is_string($configuredWorkingDirectory) && $configuredWorkingDirectory !== '' ? $configuredWorkingDirectory : null),
                timeout: $this->intArgument($arguments, 'timeout') ?: null,
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
        if (! Config::boolean('jarvis.computer_use.enabled', true)) {
            return $this->error('Computer use is disabled.');
        }

        $action = $this->stringArgument($arguments, 'action');
        $driver = $this->computer->driver();

        try {
            $output = ['ok' => true];

            switch ($action) {
                case 'screenshot':
                    $output = ['path' => $this->computer->screenshot()];
                    break;
                case 'move':
                    $driver->move($this->intArgument($arguments, 'x'), $this->intArgument($arguments, 'y'));
                    break;
                case 'click':
                    $driver->click(
                        $this->intArgument($arguments, 'x'),
                        $this->intArgument($arguments, 'y'),
                        $this->stringArgument($arguments, 'button') ?: 'left',
                        max(1, $this->intArgument($arguments, 'clicks', 1)),
                    );
                    break;
                case 'type':
                    $driver->type($this->stringArgument($arguments, 'text'));
                    break;
                case 'key':
                    $driver->pressKey($this->stringArgument($arguments, 'key'));
                    break;
                case 'scroll':
                    $driver->scroll($this->intArgument($arguments, 'amount'));
                    break;
                case 'open_app':
                    $driver->openApplication($this->stringArgument($arguments, 'application'));
                    break;
                default:
                    throw new RuntimeException("Unknown computer use action [{$action}].");
            }
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
        $path = $this->stringArgument($arguments, 'path');

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
        $path = $this->stringArgument($arguments, 'path');
        $contents = $this->stringArgument($arguments, 'contents');

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
        $path = $this->stringArgument($arguments, 'path') ?: '.';

        if (! File::isDirectory($path)) {
            return $this->error("The directory [{$path}] does not exist.");
        }

        $entries = [];

        /** @var list<string> $directories */
        $directories = File::directories($path);

        foreach ($directories as $directory) {
            $entries[] = [
                'name' => basename($directory),
                'path' => $directory,
                'type' => 'directory',
            ];
        }

        foreach (File::files($path) as $file) {
            $entries[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'type' => 'file',
            ];
        }

        return $this->json([
            'capability' => 'list_directory',
            'target' => 'local',
            'path' => $path,
            'entries' => $entries,
        ]);
    }

    /**
     * Determine whether a path is protected from writes.
     */
    private function isProtectedPath(string $path): bool
    {
        /** @var list<string> $protected */
        $protected = Config::array('jarvis.filesystem.protected_paths', []);

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
    private function stringArgument(array $arguments, string $key, string $default = ''): string
    {
        $value = $arguments[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function intArgument(array $arguments, string $key, int $default = 0): int
    {
        $value = $arguments[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get the user that owns the session.
     */
    private function userFor(JarvisSession $session): User
    {
        /** @var User $user */
        $user = $session->user;

        return $user;
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
