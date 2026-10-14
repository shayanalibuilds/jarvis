<?php

declare(strict_types=1);

namespace App\Services\Jarvis;

use App\Models\JarvisSession;
use Illuminate\Support\Collection;
use Laravel\Ai\Models\ConversationMessage;

/**
 * Reads the stored conversation for a session so the UI can show what Jarvis
 * and the user said, and which tool calls are waiting for approval.
 */
final class SessionTranscript
{
    /**
     * Get the stored messages for the session, oldest first.
     *
     * @return Collection<int, ConversationMessage>
     */
    public function messages(JarvisSession $session, int $limit = 60): Collection
    {
        if (! $this->hasConversation($session)) {
            return new Collection;
        }

        return ConversationMessage::query()
            ->where('conversation_id', $session->conversation_id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the tool calls that are waiting for the user's approval.
     *
     * @return list<array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>
     */
    public function pendingApprovals(JarvisSession $session): array
    {
        if (! $this->hasConversation($session)) {
            return [];
        }

        $row = ConversationMessage::query()
            ->where('conversation_id', $session->conversation_id)
            ->where('role', 'assistant')
            ->whereNotNull('approval_state')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $row instanceof ConversationMessage) {
            return [];
        }

        /** @var array<string, mixed> $pending */
        $pending = (array) ($row->approval_state['pending'] ?? []);

        /** @var Collection<int, array<string, mixed>> $toolCalls */
        $toolCalls = collect((array) ($row->tool_calls ?? []))->keyBy('id');

        $approvals = [];

        foreach ($pending as $id => $reason) {
            /** @var array<string, mixed>|null $call */
            $call = $toolCalls->get((string) $id);

            $approvals[] = [
                'id' => (string) $id,
                'tool' => is_string($call['name'] ?? null) ? $call['name'] : 'unknown',
                'arguments' => is_array($call['arguments'] ?? null) ? $call['arguments'] : [],
                'reason' => is_string($reason) ? $reason : null,
            ];
        }

        return $approvals;
    }

    /**
     * Determine whether the session has a stored conversation.
     */
    private function hasConversation(JarvisSession $session): bool
    {
        return is_string($session->conversation_id) && $session->conversation_id !== '';
    }
}
