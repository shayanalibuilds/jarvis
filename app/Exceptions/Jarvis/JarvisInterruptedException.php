<?php

declare(strict_types=1);

namespace App\Exceptions\Jarvis;

use App\Models\JarvisSession;
use RuntimeException;

final class JarvisInterruptedException extends RuntimeException
{
    /**
     * Create an exception for a session that is no longer allowed to act.
     */
    public static function forSession(JarvisSession $session): self
    {
        return new self(sprintf(
            'Jarvis stopped: the session is "%s". The user is in control.',
            $session->status->value,
        ));
    }
}
