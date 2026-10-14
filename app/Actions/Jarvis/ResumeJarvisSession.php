<?php

declare(strict_types=1);

namespace App\Actions\Jarvis;

use App\Enums\JarvisSessionStatus;
use App\Models\JarvisSession;

/**
 * Resume a paused or taken over session.
 */
final class ResumeJarvisSession
{
    public function __construct(
        private readonly RecordJarvisEvent $record,
    ) {}

    public function __invoke(JarvisSession $session, ?string $reason = null): JarvisSession
    {
        if ($session->status->hasEnded()) {
            return $session;
        }

        $session->update([
            'status' => JarvisSessionStatus::Running,
            'paused_at' => null,
            'taken_over_at' => null,
            'last_activity_at' => now(),
        ]);

        ($this->record)(
            $session,
            'session.resumed',
            $reason ?? 'Resumed. Jarvis is in control again.',
        );

        return $session;
    }
}
