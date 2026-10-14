<?php

declare(strict_types=1);

namespace App\Actions\Jarvis;

use App\Enums\JarvisSessionStatus;
use App\Models\JarvisSession;
use App\Services\Jarvis\DeviceBridge;

/**
 * Pause Jarvis without ending the session.
 */
final class PauseJarvisSession
{
    public function __construct(
        private readonly RecordJarvisEvent $record,
        private readonly DeviceBridge $devices,
    ) {}

    public function __invoke(JarvisSession $session, ?string $reason = null): JarvisSession
    {
        if ($session->status->hasEnded()) {
            return $session;
        }

        $session->update([
            'status' => JarvisSessionStatus::Paused,
            'paused_at' => now(),
            'last_activity_at' => now(),
        ]);

        ($this->record)(
            $session,
            'session.paused',
            $reason ?? 'Paused. Jarvis will wait until you resume.',
        );

        $this->devices->cancelOutstanding($session, 'The session was paused.');

        return $session;
    }
}
