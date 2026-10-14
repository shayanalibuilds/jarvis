<?php

declare(strict_types=1);

namespace App\Enums;

enum JarvisToolCallStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    /**
     * Get a human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for a device',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Determine whether the tool call has finished.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Rejected], true);
    }
}
