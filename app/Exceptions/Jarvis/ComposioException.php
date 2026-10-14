<?php

declare(strict_types=1);

namespace App\Exceptions\Jarvis;

use RuntimeException;

final class ComposioException extends RuntimeException
{
    /**
     * Create an exception for a request that failed on the Composio API.
     */
    public static function requestFailed(string $endpoint, int $status, string $body): self
    {
        return new self(sprintf(
            'The Composio request to [%s] failed with status [%d]: %s',
            $endpoint,
            $status,
            $body,
        ));
    }

    /**
     * Create an exception for a missing API key.
     */
    public static function notConfigured(): self
    {
        return new self('Composio is not configured. Add COMPOSIO_API_KEY to your environment file to enable one-step connections.');
    }
}
