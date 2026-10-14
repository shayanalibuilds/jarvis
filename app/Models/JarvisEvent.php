<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property string $session_id
 * @property string|null $user_id
 * @property string $type
 * @property string $summary
 * @property array<string, mixed>|null $payload
 */
final class JarvisEvent extends Model
{
    /** @use HasFactory<\Database\Factories\JarvisEventFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Get the session the event belongs to.
     *
     * @return BelongsTo<JarvisSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(JarvisSession::class, 'session_id');
    }

    /**
     * Get the user the event belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'payload' => 'array',
        ];
    }
}
