<?php

declare(strict_types=1);

namespace App\Actions\Jarvis;

use App\Enums\JarvisSessionStatus;
use App\Models\JarvisSession;
use App\Models\User;
use App\Services\Jarvis\DeviceBridge;
use Illuminate\Support\Str;

/**
 * Open a new Jarvis session for the user.
 */
final class StartJarvisSession
{
    public function __construct(
        private readonly RecordJarvisEvent $record,
        private readonly DeviceBridge $devices,
    ) {}

    public function __invoke(User $user, string $prompt, ?string $conversationId = null): JarvisSession
    {
        $this->closeOpenSessions($user);

        /** @var JarvisSession $session */
        $session = JarvisSession::query()->create([
            'user_id' => $user->getKey(),
            'title' => Str::limit($prompt, 60, preserveWords: true),
            'status' => JarvisSessionStatus::Running,
            'agent' => 'jarvis',
            'conversation_id' => $conversationId,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        ($this->record)($session, 'session.started', 'Session started', ['prompt' => $prompt]);

        return $session;
    }

    /**
     * Stop any session the user left open.
     */
    private function closeOpenSessions(User $user): void
    {
        $open = JarvisSession::query()
            ->where('user_id', $user->getKey())
            ->where('status', '!=', JarvisSessionStatus::Stopped)
            ->get();

        foreach ($open as $session) {
            $session->update([
                'status' => JarvisSessionStatus::Stopped,
                'stopped_at' => now(),
            ]);

            $this->devices->cancelOutstanding($session, 'A new session was started.');
        }
    }
}
