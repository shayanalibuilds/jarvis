<?php

declare(strict_types=1);

namespace App\Actions\Jarvis;

use App\Models\JarvisEvent;
use App\Models\JarvisSession;

/**
 * Record an auditable event for a Jarvis session.
 */
final class RecordJarvisEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(?JarvisSession $session, string $type, string $summary, array $payload = []): JarvisEvent
    {
        /** @var JarvisEvent $event */
        $event = JarvisEvent::query()->create([
            'session_id' => $session?->getKey(),
            'user_id' => $session?->user_id,
            'type' => $type,
            'summary' => $summary,
            'payload' => $payload === [] ? null : $payload,
        ]);

        $session?->touchActivity();

        return $event;
    }
}
