<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JarvisToolCallStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property JarvisToolCallStatus $status
 * @property string $session_id
 * @property string $user_id
 * @property string|null $device_id
 * @property string $tool
 * @property string $target
 * @property array<string, mixed>|null $arguments
 * @property string|null $result
 * @property string|null $error
 */
final class JarvisToolCall extends Model
{
    /** @use HasFactory<\Database\Factories\JarvisToolCallFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Get the session the tool call belongs to.
     *
     * @return BelongsTo<JarvisSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(JarvisSession::class, 'session_id');
    }

    /**
     * Get the user the tool call belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the device that executed the tool call.
     *
     * @return BelongsTo<JarvisDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(JarvisDevice::class, 'device_id');
    }

    /**
     * Scope the query to tool calls that are waiting to be executed.
     *
     * @param  Builder<JarvisToolCall>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', JarvisToolCallStatus::Pending);
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
            'status' => JarvisToolCallStatus::class,
            'arguments' => 'array',
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
