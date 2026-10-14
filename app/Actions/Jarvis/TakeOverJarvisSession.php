<?php

declare(strict_types=1);

namespace App\Actions\Jarvis;

use App\Enums\JarvisSessionStatus;
use App\Models\JarvisSession;
use App\Services\Jarvis\DeviceBridge;

/**
 * Take control away from Jarvis immediately, without ending the session.
 *
 * Jarvis stops acting on the next tool call, every in flight device tool call
 * is cancelled, and the session can be handed back with "resume".
 */
final class TakeOverJarvisSession
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
            'status' => JarvisSessionStatus::TakenOver,
            'taken_over_at' => now(),
            'last_activity_at' => now(),
        ]);

        ($this->record)(
            $session,
            'session.taken_over',
            $reason ?? 'You took over. Jarvis will not run another action until you resume.',
        );

        $this->devices->cancelOutstanding($session, 'The user took over.');

        return $session;
    }
}
