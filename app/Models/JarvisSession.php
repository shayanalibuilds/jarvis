<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JarvisSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

final class JarvisSession extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $guarded = [];

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
            'status' => JarvisSessionStatus::class,
            'meta' => 'array',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'taken_over_at' => 'datetime',
            'stopped_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the session.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the events recorded for the session.
     *
     * @return HasMany<JarvisEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(JarvisEvent::class, 'session_id')->latest();
    }

    /**
     * Get the tool calls made during the session.
     *
     * @return HasMany<JarvisToolCall, $this>
     */
    public function toolCalls(): HasMany
    {
        return $this->hasMany(JarvisToolCall::class, 'session_id');
    }

    /**
     * Determine whether Jarvis is allowed to act in this session.
     */
    public function allowsExecution(): bool
    {
        return $this->status->allowsExecution();
    }

    /**
     * Determine whether the session is still open.
     */
    public function isActive(): bool
    {
        return ! $this->status->hasEnded();
    }

    /**
     * Record activity on the session.
     */
    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }
}
