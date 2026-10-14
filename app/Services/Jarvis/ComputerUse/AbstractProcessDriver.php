<?php

declare(strict_types=1);

namespace App\Services\Jarvis\ComputerUse;

use App\Exceptions\Jarvis\ComputerUseException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Base class for drivers that shell out to operating system tools.
 */
abstract class AbstractProcessDriver implements ComputerUseDriver
{
    /**
     * Run the given command, returning its standard output.
     *
     * @param  list<string>  $command
     */
    protected function run(array $command, string $action): string
    {
        $process = new Process($command, timeout: 30);

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw ComputerUseException::failed($action, $exception->getMessage());
        }

        if (! $process->isSuccessful()) {
            throw ComputerUseException::failed($action, mb_trim($process->getErrorOutput()) ?: 'the command was not successful');
        }

        return $process->getOutput();
    }

    /**
     * Determine whether a binary is available on the current machine.
     */
    protected function which(string $binary): bool
    {
        $paths = explode(PATH_SEPARATOR, (string) getenv('PATH'));

        foreach ($paths as $path) {
            if ($path !== '' && is_executable($path.DIRECTORY_SEPARATOR.$binary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether any of the given binaries is available.
     *
     * @param  list<string>  $binaries
     */
    protected function whichAny(array $binaries): bool
    {
        foreach ($binaries as $binary) {
            if ($this->which($binary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the first available binary from the given list.
     *
     * @param  list<string>  $binaries
     */
    protected function firstAvailable(array $binaries): ?string
    {
        foreach ($binaries as $binary) {
            if ($this->which($binary)) {
                return $binary;
            }
        }

        return null;
    }
}
