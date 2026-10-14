<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\JarvisSession;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;

/**
 * The outcome of a single Jarvis turn.
 */
final readonly class JarvisTurnResult
{
    public function __construct(
        public JarvisSession $session,
        public ?AgentResponse $response = null,
        public ?string $error = null,
    ) {}

    /**
     * Get the assistant text for the turn.
     */
    public function text(): string
    {
        return $this->response?->text ?? '';
    }

    /**
     * Get the approvals the user still has to make a decision on.
     *
     * @return Collection<int, PendingApproval>
     */
    public function pendingApprovals(): Collection
    {
        return $this->response?->pendingApprovals ?? new Collection;
    }

    /**
     * Determine whether Jarvis is waiting for the user to approve a tool call.
     */
    public function isAwaitingApproval(): bool
    {
        return $this->response?->hasPendingApprovals() ?? false;
    }

    /**
     * Determine whether the turn completed without an error.
     */
    public function succeeded(): bool
    {
        return $this->error === null;
    }
}
