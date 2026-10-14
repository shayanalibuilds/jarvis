<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Exceptions\Jarvis\JarvisInterruptedException;
use App\Models\JarvisSession;
use App\Models\User;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Base class for every Jarvis tool.
 *
 * Tools are always bound to the session that is driving them, which is what
 * lets the user take over: the moment the session is no longer "running",
 * every tool refuses to execute.
 */
abstract class JarvisTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(protected readonly JarvisSession $session) {}

    /**
     * Determine whether the tool requires approval before it may run.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        return (bool) config('jarvis.control.require_approval', true);
    }

    /**
     * Ensure Jarvis is still allowed to act.
     *
     * @throws JarvisInterruptedException
     */
    protected function ensureJarvisMayAct(): void
    {
        if (! $this->session->refresh()->allowsExecution()) {
            throw JarvisInterruptedException::forSession($this->session);
        }
    }

    /**
     * Get the user that owns the session.
     */
    protected function user(): User
    {
        /** @var User $user */
        $user = $this->session->user;

        return $user;
    }

    /**
     * Record the tool call on the session so the UI can show what Jarvis is doing.
     */
    protected function markActivity(string $tool, ?string $summary = null): void
    {
        $this->session->forceFill([
            'current_tool' => $tool,
            'last_activity_at' => now(),
        ])->save();

        if ($summary !== null) {
            app(\App\Actions\Jarvis\RecordJarvisEvent::class)($this->session, 'tool.invoked', $summary, ['tool' => $tool]);
        }
    }
}
