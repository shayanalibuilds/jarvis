<?php

declare(strict_types=1);

namespace App\Actions\Jarvis;

use App\Ai\Agents\Jarvis;
use App\Ai\JarvisTurnResult;
use App\Enums\JarvisSessionStatus;
use App\Exceptions\Jarvis\JarvisInterruptedException;
use App\Models\JarvisSession;
use App\Models\User;
use Closure;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Throwable;

/**
 * Run one Jarvis turn: prompt the agent, persist the outcome, and record what
 * happened so the user can follow along.
 */
final class RunJarvisTurn
{
    public function __construct(
        private readonly StartJarvisSession $startSession,
        private readonly RecordJarvisEvent $record,
    ) {}

    /**
     * Start (or continue) a session with the given prompt.
     */
    public function start(User $user, string $prompt): JarvisTurnResult
    {
        $session = $this->activeSessionFor($user);

        if (! $session instanceof JarvisSession) {
            $session = ($this->startSession)($user, $prompt);
        } else {
            $session->update([
                'status' => JarvisSessionStatus::Running,
                'last_activity_at' => now(),
            ]);
        }

        return $this->prompt($session, $prompt);
    }

    /**
     * Send a prompt to an existing session.
     */
    public function prompt(JarvisSession $session, string $prompt): JarvisTurnResult
    {
        return $this->run($session, fn (Jarvis $agent): JarvisTurnResult => new JarvisTurnResult(
            $session,
            $agent->prompt(
                $prompt,
                provider: $this->provider(),
                model: $this->model(),
                timeout: $this->timeout(),
            ),
        ));
    }

    /**
     * Resume a paused session with the user's approval decisions.
     *
     * @param  array<string, Decision|bool>  $decisions
     */
    public function resume(JarvisSession $session, array $decisions): JarvisTurnResult
    {
        return $this->run($session, fn (Jarvis $agent): JarvisTurnResult => new JarvisTurnResult(
            $session,
            $agent->prompt(
                Decisions::from($decisions),
                provider: $this->provider(),
                model: $this->model(),
                timeout: $this->timeout(),
            ),
        ));
    }

    /**
     * Get the session the user is currently working in.
     */
    public function activeSessionFor(User $user): ?JarvisSession
    {
        return JarvisSession::query()
            ->where('user_id', $user->getKey())
            ->where('status', '!=', JarvisSessionStatus::Stopped)
            ->latest('created_at')
            ->first();
    }

    /**
     * Run the given agent interaction inside the session guard.
     *
     * @param  Closure(Jarvis): JarvisTurnResult  $callback
     */
    private function run(JarvisSession $session, Closure $callback): JarvisTurnResult
    {
        if (! $session->refresh()->allowsExecution()) {
            return new JarvisTurnResult($session, error: JarvisInterruptedException::forSession($session)->getMessage());
        }

        $agent = (new Jarvis($session))->forUser($session->user);

        if (is_string($session->conversation_id) && $session->conversation_id !== '') {
            $agent->continue($session->conversation_id, $session->user);
        }

        try {
            $result = $callback($agent);
        } catch (JarvisInterruptedException $exception) {
            ($this->record)($session, 'session.interrupted', $exception->getMessage());

            return new JarvisTurnResult($session, error: $exception->getMessage());
        } catch (Throwable $exception) {
            $session->update(['last_error' => $exception->getMessage(), 'current_tool' => null]);

            ($this->record)($session, 'session.failed', 'Jarvis hit an error: '.$exception->getMessage());

            return new JarvisTurnResult($session, error: $exception->getMessage());
        }

        $response = $result->response;

        $session->update([
            'conversation_id' => $response?->conversationId ?? $session->conversation_id,
            'current_tool' => null,
            'last_activity_at' => now(),
        ]);

        if ($result->isAwaitingApproval()) {
            ($this->record)(
                $session,
                'approval.requested',
                'Jarvis is waiting for your approval.',
                ['pending' => $result->pendingApprovals()->map->toArray()->all()],
            );
        } else {
            ($this->record)($session, 'turn.completed', 'Jarvis replied.', ['text' => $result->text()]);
        }

        return $result;
    }

    /**
     * Resolve the configured provider.
     */
    private function provider(): ?string
    {
        $provider = config('jarvis.agent.provider');

        return is_string($provider) && $provider !== '' ? $provider : null;
    }

    /**
     * Resolve the configured model.
     */
    private function model(): ?string
    {
        $model = config('jarvis.agent.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    /**
     * Resolve the configured timeout.
     */
    private function timeout(): int
    {
        return max(1, (int) config('jarvis.agent.timeout', 120));
    }
}
