<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JarvisConnectionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property JarvisConnectionStatus $status
 * @property string $user_id
 * @property string $toolkit
 * @property string|null $name
 * @property string|null $auth_config_id
 * @property string|null $connected_account_id
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $connected_at
 */
final class JarvisConnection extends Model
{
    /** @use HasFactory<\Database\Factories\JarvisConnectionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Get the user that owns the connection.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether the connection can be used by Jarvis.
     */
    public function isUsable(): bool
    {
        return $this->status->isUsable();
    }

    /**
     * Get the human readable name for the toolkit.
     */
    public function displayName(): string
    {
        return $this->name ?? $this->toolkit;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'status' => JarvisConnectionStatus::class,
            'meta' => 'array',
            'connected_at' => 'datetime',
        ];
    }
}
