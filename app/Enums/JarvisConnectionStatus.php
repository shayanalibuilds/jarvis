<?php

declare(strict_types=1);

namespace App\Enums;

enum JarvisConnectionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Failed = 'failed';
    case Expired = 'expired';
    case Revoked = 'revoked';

    /**
     * Resolve a status from a Composio connected account status.
     */
    public static function fromComposio(?string $status): self
    {
        return match (mb_strtoupper((string) $status)) {
            'ACTIVE' => self::Active,
            'FAILED' => self::Failed,
            'EXPIRED' => self::Expired,
            'REVOKED', 'INACTIVE' => self::Revoked,
            default => self::Pending,
        };
    }

    /**
     * Get a human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for authorization',
            self::Active => 'Connected',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
            self::Revoked => 'Disconnected',
        };
    }

    /**
     * Determine whether the connection is usable.
     */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
