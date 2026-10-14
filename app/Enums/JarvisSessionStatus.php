<?php

declare(strict_types=1);

namespace App\Enums;

enum JarvisSessionStatus: string
{
    case Idle = 'idle';
    case Running = 'running';
    case Paused = 'paused';
    case TakenOver = 'taken_over';
    case Stopped = 'stopped';

    /**
     * Get a human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Idle',
            self::Running => 'Running',
            self::Paused => 'Paused',
            self::TakenOver => 'You have taken over',
            self::Stopped => 'Stopped',
        };
    }

    /**
     * Determine whether Jarvis may still act in this status.
     */
    public function allowsExecution(): bool
    {
        return $this === self::Running;
    }

    /**
     * Determine whether the session can be resumed by the user.
     */
    public function isResumable(): bool
    {
        return in_array($this, [self::Paused, self::TakenOver, self::Idle], true);
    }

    /**
     * Determine whether the session has ended.
     */
    public function hasEnded(): bool
    {
        return $this === self::Stopped;
    }
}
