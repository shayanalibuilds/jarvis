<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @property string $user_id
 * @property string $name
 * @property string $platform
 * @property string $token
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 */
final class JarvisDevice extends Model
{
    /** @use HasFactory<\Database\Factories\JarvisDeviceFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Get the user that owns the device.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tool calls executed by the device.
     *
     * @return HasMany<JarvisToolCall, $this>
     */
    public function toolCalls(): HasMany
    {
        return $this->hasMany(JarvisToolCall::class, 'device_id');
    }

    /**
     * Determine whether the device has sent a heartbeat recently.
     */
    public function isOnline(): bool
    {
        $ttl = Config::integer('jarvis.devices.heartbeat_ttl', 60);

        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subSeconds($ttl));
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
            'meta' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }
}
