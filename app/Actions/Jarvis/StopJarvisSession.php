<?php

declare(strict_types=1);

namespace App\Actions\Jarvis;

use App\Enums\JarvisSessionStatus;
use App\Models\JarvisSession;
use App\Services\Jarvis\DeviceBridge;

/**
 * End the session for good.
 */
final class StopJarvisSession
{
    public function __construct(
        private readonly RecordJarvisEvent $record,
        private readonly DeviceBridge $devices,
    ) {}

    public function __invoke(JarvisSession $session, ?string $reason = null): JarvisSession
    {
        $session->update([
            'status' => JarvisSessionStatus::Stopped,
            'stopped_at' => now(),
            'current_tool' => null,
            'last_activity_at' => now(),
        ]);

        ($this->record)(
            $session,
            'session.stopped',
            $reason ?? 'Session stopped.',
        );

        $this->devices->cancelOutstanding($session, 'The session was stopped.');

        return $session;
    }
}
