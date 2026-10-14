<?php

declare(strict_types=1);

namespace App\Exceptions\Jarvis;

use RuntimeException;

final class DeviceBridgeException extends RuntimeException
{
    /**
     * Create an exception for a user with no online device.
     */
    public static function noDeviceOnline(): self
    {
        return new self('No desktop device is online. Open the Jarvis desktop app, or enable local execution to run this on the server.');
    }

    /**
     * Create an exception for a device that never reported a result.
     */
    public static function timedOut(string $tool): self
    {
        return new self(sprintf('The desktop device did not return a result for [%s] in time.', $tool));
    }

    /**
     * Create an exception for a device that reported a failure.
     */
    public static function failed(string $tool, string $reason): self
    {
        return new self(sprintf('The desktop device failed to run [%s]: %s', $tool, $reason));
    }
}
