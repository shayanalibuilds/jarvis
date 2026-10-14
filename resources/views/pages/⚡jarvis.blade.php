<?php

use App\Actions\Jarvis\PauseJarvisSession;
use App\Actions\Jarvis\ResumeJarvisSession;
use App\Actions\Jarvis\RunJarvisTurn;
use App\Actions\Jarvis\StopJarvisSession;
use App\Actions\Jarvis\TakeOverJarvisSession;
use App\Models\JarvisSession;
use App\Models\User;
use App\Services\Jarvis\DeviceBridge;
use App\Services\Jarvis\SessionTranscript;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public string $prompt = '';

    public ?string $error = null;

    public bool $busy = false;

    public function mount(): void
    {
        $this->error = null;
    }

    /**
     * Send a prompt to Jarvis.
     */
    public function run(RunJarvisTurn $turn): void
    {
        $this->error = null;

        $validated = $this->validate([
            'prompt' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        $this->busy = true;

        try {
            $result = $turn->start($this->user(), $validated['prompt']);

            if (! $result->succeeded()) {
                $this->error = $result->error;
            }
        } finally {
            $this->busy = false;
        }

        $this->prompt = '';
    }

    /**
     * Approve a pending tool call and let Jarvis continue.
     */
    public function approve(string $toolCallId, RunJarvisTurn $turn): void
    {
        $this->decide($turn, Decisions::from([$toolCallId => Decision::approve()])->approveRemaining());
    }

    /**
     * Reject a pending tool call.
     */
    public function reject(string $toolCallId, RunJarvisTurn $turn): void
    {
        $this->decide($turn, Decisions::from([$toolCallId => Decision::reject('Rejected by the user.')])->rejectRemaining('Rejected by the user.'));
    }

    /**
     * Take control away from Jarvis.
     */
    public function takeOver(TakeOverJarvisSession $action): void
    {
        $session = $this->session();

        if ($session instanceof JarvisSession) {
            $action($session);

            Flux::toast('You took over. Jarvis is paused.', variant: 'warning');
        }
    }

    /**
     * Pause or resume Jarvis.
     */
    public function togglePause(PauseJarvisSession $pause, ResumeJarvisSession $resume): void
    {
        $session = $this->session();

        if (! $session instanceof JarvisSession) {
            return;
        }

        if ($session->status->allowsExecution()) {
            $pause($session);

            Flux::toast('Jarvis paused.', variant: 'warning');

            return;
        }

        $resume($session);

        Flux::toast('Jarvis resumed.', variant: 'success');
    }

    /**
     * Stop the session.
     */
    public function stop(StopJarvisSession $action): void
    {
        $session = $this->session();

        if ($session instanceof JarvisSession) {
            $action($session);

            Flux::toast('Session stopped.', variant: 'success');
        }
    }

    /**
     * End the current session so the next prompt starts a fresh conversation.
     */
    public function newSession(StopJarvisSession $action): void
    {
        $session = $this->session();

        if ($session instanceof JarvisSession) {
            $action($session, 'Session closed by the user.');
        }

        Flux::toast('Started a new session.', variant: 'success');
    }

    /**
     * Handle a global keyboard shortcut from the web or desktop application.
     */
    #[On('jarvis-shortcut')]
    public function shortcut(string $action, TakeOverJarvisSession $takeOver, StopJarvisSession $stop, PauseJarvisSession $pause, ResumeJarvisSession $resume): void
    {
        match ($action) {
            'take_over' => $this->takeOver($takeOver),
            'stop' => $this->stop($stop),
            'pause' => $this->togglePause($pause, $resume),
            default => null,
        };
    }

    /**
     * Apply approval decisions and continue the paused turn.
     */
    private function decide(RunJarvisTurn $turn, Decisions $decisions): void
    {
        $session = $this->session();

        if (! $session instanceof JarvisSession) {
            return;
        }

        $this->busy = true;

        try {
            $result = $turn->resume($session, $decisions->all());

            if (! $result->succeeded()) {
                $this->error = $result->error;
            }
        } finally {
            $this->busy = false;
        }
    }

    /**
     * Get the user driving Jarvis.
     */
    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Get the user's current session, if any.
     */
    private function session(): ?JarvisSession
    {
        return app(RunJarvisTurn::class)->activeSessionFor($this->user());
    }

    /**
     * Get the data the view needs.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $session = $this->session();
        $transcript = app(SessionTranscript::class);

        return [
            'session' => $session,
            'messages' => $session instanceof JarvisSession ? $transcript->messages($session) : collect(),
            'pendingApprovals' => $session instanceof JarvisSession ? $transcript->pendingApprovals($session) : [],
            'events' => $session instanceof JarvisSession ? $session->events()->limit(15)->get() : collect(),
            'deviceOnline' => app(DeviceBridge::class)->hasActiveDevice($this->user()),
            'shortcuts' => config('jarvis.control.shortcuts'),
            'shellTarget' => (string) config('jarvis.shell.target', 'auto'),
        ];
    }
};
?>

@php
    $status = $session?->status;
    $statusColor = match ($status) {
        \App\Enums\JarvisSessionStatus::Running => 'green',
        \App\Enums\JarvisSessionStatus::Paused => 'amber',
        \App\Enums\JarvisSessionStatus::TakenOver => 'sky',
        default => 'zinc',
    };
@endphp

<div class="space-y-6" x-data x-on:keydown.window.ctrl.shift.j.prevent="$wire.shortcut('take_over')"
    x-on:keydown.window.meta.shift.j.prevent="$wire.shortcut('take_over')"
    x-on:keydown.window.ctrl.shift.space.prevent="$wire.shortcut('pause')"
    x-on:keydown.window.meta.shift.space.prevent="$wire.shortcut('pause')"
    x-on:keydown.window.escape="$wire.shortcut('take_over')">

    <flux:card class="space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <flux:heading size="lg">{{ config('jarvis.name') }}</flux:heading>
            <flux:badge :color="$statusColor">{{ $status?->label() ?? 'No session' }}</flux:badge>
            <flux:badge :color="$deviceOnline ? 'green' : 'zinc'">
                {{ $deviceOnline ? 'Desktop connected' : 'No desktop connected' }}
            </flux:badge>
            <flux:spacer />
            <flux:link href="{{ route('jarvis.connections') }}" wire:navigate>Connections</flux:link>
        </div>

        <flux:text>
            Jarvis runs on your computer and acts through your connected apps. You are always in control: press
            <flux:badge size="sm">{{ $shortcuts['take_over']['accelerator'] }}</flux:badge> to take over, or
            <flux:badge size="sm">{{ $shortcuts['pause']['accelerator'] }}</flux:badge> to pause and resume. Escape
            also takes over.
        </flux:text>

        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="takeOver" icon="hand-raised" variant="filled" :disabled="$session === null">
                Take over
            </flux:button>
            <flux:button wire:click="togglePause" icon="pause" :disabled="$session === null">
                {{ $status?->allowsExecution() ? 'Pause' : 'Resume' }}
            </flux:button>
            <flux:button wire:click="stop" icon="stop" variant="danger" :disabled="$session === null">
                Stop
            </flux:button>
            <flux:button wire:click="newSession" icon="plus" variant="ghost">New session</flux:button>
        </div>

        @if ($session !== null)
            <flux:text class="text-xs text-zinc-500">
                Session {{ \Illuminate\Support\Str::limit($session->id, 8, '') }} &middot; last activity
                {{ $session->last_activity_at?->diffForHumans() ?? 'never' }}
                @if ($session->current_tool)
                    &middot; doing <span class="font-medium">{{ $session->current_tool }}</span>
                @endif
            </flux:text>
        @endif
    </flux:card>

    @if (count($pendingApprovals) > 0)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Jarvis is waiting for you</flux:heading>
                <flux:subheading>Approve or reject these actions before Jarvis continues.</flux:subheading>
            </div>

            @foreach ($pendingApprovals as $approval)
                <div wire:key="{{ $approval['id'] }}"
                    class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="font-medium">{{ $approval['tool'] }}</flux:text>
                    @if ($approval['reason'])
                        <flux:text>{{ $approval['reason'] }}</flux:text>
                    @endif
                    <pre class="overflow-x-auto rounded bg-zinc-50 p-2 text-xs dark:bg-zinc-900">{{ json_encode($approval['arguments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="primary" wire:click="approve('{{ $approval['id'] }}')"
                            wire:loading.attr="disabled">
                            Approve
                        </flux:button>
                        <flux:button size="sm" variant="danger" wire:click="reject('{{ $approval['id'] }}')"
                            wire:loading.attr="disabled">
                            Reject
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </flux:card>
    @endif

    <flux:card class="space-y-4">
        <form wire:submit="run" class="space-y-3">
            <flux:textarea wire:model="prompt" rows="3" :disabled="$busy"
                placeholder="Tell Jarvis what to do on your computer, for example: find last week's invoices in Drive and email me a summary." />
            <flux:error name="prompt" />

            <div class="flex flex-wrap items-center gap-3">
                <flux:button type="submit" variant="primary" icon="play" :disabled="$busy">
                    {{ $busy ? 'Jarvis is working...' : 'Run' }}
                </flux:button>
                <flux:button type="button" variant="ghost" icon="hand-raised" wire:click="takeOver">
                    Take over now
                </flux:button>
                @if ($error)
                    <flux:text class="text-red-600 dark:text-red-400">{{ $error }}</flux:text>
                @endif
            </div>
        </form>
    </flux:card>

    <flux:card class="space-y-4">
        <div class="flex items-center gap-3">
            <flux:heading size="lg">Transcript</flux:heading>
            <flux:spacer />
            <flux:text class="text-xs text-zinc-500">shell target: {{ $shellTarget }}</flux:text>
        </div>

        @forelse ($messages as $message)
            <div wire:key="{{ $message->id }}"
                class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    {{ $message->role }}
                </flux:text>

                @if (filled($message->content))
                    <flux:text class="whitespace-pre-wrap">{{ $message->content }}</flux:text>
                @endif

                @foreach ($message->tool_calls ?? [] as $call)
                    <flux:text class="text-xs text-zinc-500">
                        tool call &middot; {{ $call['name'] ?? 'unknown' }}
                    </flux:text>
                @endforeach

                @foreach ($message->tool_results ?? [] as $result)
                    <pre class="overflow-x-auto rounded bg-zinc-50 p-2 text-xs dark:bg-zinc-900">{{ \Illuminate\Support\Str::limit(json_encode($result, JSON_UNESCAPED_SLASHES), 600) }}</pre>
                @endforeach
            </div>
        @empty
            <flux:text>Nothing yet. Tell Jarvis what you want it to do.</flux:text>
        @endforelse
    </flux:card>

    @if ($events->isNotEmpty())
        <flux:card class="space-y-3">
            <flux:heading size="lg">Activity</flux:heading>

            @foreach ($events as $event)
                <div wire:key="{{ $event->id }}" class="flex items-start gap-3">
                    <flux:badge size="sm">{{ $event->type }}</flux:badge>
                    <div>
                        <flux:text>{{ $event->summary }}</flux:text>
                        <flux:text class="text-xs text-zinc-500">{{ $event->created_at?->diffForHumans() }}</flux:text>
                    </div>
                </div>
            @endforeach
        </flux:card>
    @endif
</div>
