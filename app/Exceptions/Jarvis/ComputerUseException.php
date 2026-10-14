<?php

declare(strict_types=1);

namespace App\Exceptions\Jarvis;

use RuntimeException;

final class ComputerUseException extends RuntimeException
{
    /**
     * Create an exception for a computer use action that is not supported.
     */
    public static function unsupported(string $action, string $driver): self
    {
        return new self(sprintf('The [%s] action is not supported by the [%s] computer use driver.', $action, $driver));
    }

    /**
     * Create an exception for a computer use action that failed to execute.
     */
    public static function failed(string $action, string $reason): self
    {
        return new self(sprintf('The [%s] computer use action failed: %s', $action, $reason));
    }
}
