<?php

declare(strict_types=1);

namespace App\Exceptions\Jarvis;

use RuntimeException;

final class BlockedCommandException extends RuntimeException
{
    /**
     * Create an exception for a command that is never allowed to run.
     */
    public static function forCommand(string $command, string $pattern): self
    {
        return new self(sprintf(
            'The command [%s] is permanently blocked because it matches the pattern [%s].',
            $command,
            $pattern,
        ));
    }
}
