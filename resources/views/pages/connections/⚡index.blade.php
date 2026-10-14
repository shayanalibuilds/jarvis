<?php

use App\Actions\Jarvis\ConnectJarvisToolkit;
use App\Actions\Jarvis\DisconnectJarvisToolkit;
use App\Actions\Jarvis\SyncJarvisConnections;
use App\Exceptions\Jarvis\ComposioException;
use App\Models\JarvisConnection;
use App\Models\User;
use App\Services\Composio\ComposioClient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public ?string $error = null;

    public function mount(SyncJarvisConnections $sync): void
    {
        $client = app(ComposioClient::class);

        if (! $client->configured()) {
            $this->error = 'Composio is not configured yet. Add COMPOSIO_API_KEY to your environment to enable one-step connections.';

            return;
        }

        try {
            $sync($this->user());
        } catch (ComposioException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    /**
     * Connect a product in one step: create the link and send the user to it.
     */
    public function connect(string $toolkit, ConnectJarvisToolkit $connect): void
    {
        $this->error = null;

        try {
            $connection = $connect($this->user(), $toolkit, route('jarvis.connections.callback'));
        } catch (ComposioException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $redirect = $connection->meta['redirect_url'] ?? null;

        if (! is_string($redirect) || $redirect === '') {
            $this->error = 'Composio did not return an authorization link for ' . $toolkit . '.';

            return;
        }

        $this->redirect($redirect);
    }

    /**
     * Disconnect a product.
     */
    public function disconnect(string $toolkit, DisconnectJarvisToolkit $disconnect): void
    {
        $connection = JarvisConnection::query()
            ->where('user_id', $this->user()->getKey())
            ->where('toolkit', $toolkit)
            ->first();

        if ($connection instanceof JarvisConnection) {
            $disconnect($connection);
        }

        Flux::toast('Disconnected ' . $toolkit . '.', variant: 'success');
    }

    /**
     * Refresh every connection status from Composio.
     */
    public function refresh(SyncJarvisConnections $sync): void
    {
        try {
            $updated = $sync($this->user());
        } catch (ComposioException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        Flux::toast($updated > 0 ? 'Connections refreshed.' : 'No connection changes.', variant: 'success');
    }

    /**
     * Get the user that owns the connections.
     */
    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Get the data the view needs.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $client = app(ComposioClient::class);

        return [
            'catalog' => $client->catalog(),
            'connections' => JarvisConnection::query()
                ->where('user_id', $this->user()->getKey())
                ->get()
                ->keyBy('toolkit'),
            'configured' => $client->configured(),
        ];
    }
};
?>

<div class="space-y-6">
    <flux:card class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <flux:heading size="lg">Connections</flux:heading>
            <flux:spacer />
            <flux:button size="sm" icon="arrow-path" wire:click="refresh">Refresh status</flux:button>
            <flux:link href="{{ route('jarvis.index') }}" wire:navigate>Back to Jarvis</flux:link>
        </div>

        <flux:text>
            Connect a product once and Jarvis can use it right away. Authorization is brokered for you, so there is nothing to configure: click
            connect, approve access, and you are done.
        </flux:text>

        @if ($error)
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $error }}</flux:callout>
        @endif
    </flux:card>

    @foreach ($catalog as $group)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ $group['label'] }}</flux:heading>

            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($group['toolkits'] as $toolkit)
                    @php($connection = $connections->get($toolkit['slug']))
                    @php($status = $connection?->status)
                    <div
                        wire:key="{{ $toolkit['slug'] }}"
                        class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <flux:text class="font-medium">{{ $toolkit['name'] }}</flux:text>
                                @if ($status === null)
                                    <flux:badge size="sm" color="zinc">Not connected</flux:badge>
                                @elseif ($status === \App\Enums\JarvisConnectionStatus::Active)
                                    <flux:badge size="sm" color="green">Connected</flux:badge>
                                @else
                                    <flux:badge size="sm" color="amber">{{ $status->label() }}</flux:badge>
                                @endif
                            </div>
                            <flux:text class="text-sm text-zinc-500">{{ $toolkit['description'] }}</flux:text>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            @if ($status === \App\Enums\JarvisConnectionStatus::Active)
                                <flux:button size="sm" variant="ghost" wire:click="disconnect('{{ $toolkit['slug'] }}')">Disconnect</flux:button>
                            @else
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="link"
                                    wire:click="connect('{{ $toolkit['slug'] }}')"
                                    wire:loading.attr="disabled"
                                    :disabled="! $configured"
                                >
                                    Connect
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endforeach
</div>
