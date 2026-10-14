<?php

declare(strict_types=1);

use App\Actions\Jarvis\ConnectJarvisToolkit;
use App\Actions\Jarvis\SyncJarvisConnections;
use App\Ai\Tools\RunConnectionTool;
use App\Enums\JarvisConnectionStatus;
use App\Models\JarvisConnection;
use App\Models\JarvisSession;
use App\Models\User;
use App\Services\Composio\ComposioClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as ToolRequest;

beforeEach(function (): void {
    config()->set('jarvis.composio.key', 'test-key');
    config()->set('jarvis.composio.base_url', 'https://backend.composio.dev/api/v3');
    Cache::flush();
});

test('the composio client is not configured without an api key', function (): void {
    config()->set('jarvis.composio.key', null);

    expect(app(ComposioClient::class)->configured())->toBeFalse();
});

test('connecting a toolkit creates a connect link and stores it', function (): void {
    Http::fake([
        '*/auth_configs' => Http::response(['toolkit' => ['slug' => 'gmail'], 'auth_config' => ['id' => 'ac_123']], 201),
        '*/connected_accounts/link' => Http::response([
            'redirect_url' => 'https://backend.composio.dev/link/abc',
            'connected_account_id' => 'ca_abc',
        ], 201),
    ]);

    $user = User::factory()->create();

    $connection = app(ConnectJarvisToolkit::class)($user, 'gmail', 'https://jarvis.test/jarvis/connections/callback');

    expect($connection->toolkit)->toBe('gmail')
        ->and($connection->name)->toBe('Gmail')
        ->and($connection->status)->toBe(JarvisConnectionStatus::Pending)
        ->and($connection->auth_config_id)->toBe('ac_123')
        ->and($connection->meta['redirect_url'])->toBe('https://backend.composio.dev/link/abc');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/connected_accounts/link')
        && $request['user_id'] === $user->getKey());
});

test('connections are marked active when composio reports them as active', function (): void {
    Http::fake([
        '*/connected_accounts*' => Http::response([
            'items' => [[
                'id' => 'ca_active',
                'toolkit' => ['slug' => 'gmail'],
                'status' => 'ACTIVE',
            ]],
        ]),
    ]);

    $user = User::factory()->create();
    JarvisConnection::factory()->create(['user_id' => $user->getKey(), 'toolkit' => 'gmail']);

    app(SyncJarvisConnections::class)($user);

    $connection = JarvisConnection::query()->where('user_id', $user->getKey())->where('toolkit', 'gmail')->first();

    expect($connection->status)->toBe(JarvisConnectionStatus::Active)
        ->and($connection->connected_account_id)->toBe('ca_active')
        ->and($connection->connected_at)->not->toBeNull();
});

test('the connection tool refuses actions for products that are not connected', function (): void {
    Http::fake();

    $session = JarvisSession::factory()->running()->create();

    $tool = new RunConnectionTool(app(ComposioClient::class), $session);

    $result = json_decode($tool->handle(new ToolRequest([
        'tool' => 'GMAIL_SEND_EMAIL',
        'arguments' => ['to' => 'someone@example.com'],
    ])), true);

    expect($result['successful'])->toBeFalse()
        ->and($result['error'])->toContain('not connected');

    Http::assertNothingSent();
});

test('the connection tool executes an action for a connected product', function (): void {
    Http::fake([
        '*/tools/execute/*' => Http::response([
            'successful' => true,
            'data' => ['messageId' => 'msg_1'],
            'error' => null,
        ]),
    ]);

    $session = JarvisSession::factory()->running()->create();
    JarvisConnection::factory()->active()->create([
        'user_id' => $session->user_id,
        'toolkit' => 'gmail',
    ]);

    $tool = new RunConnectionTool(app(ComposioClient::class), $session);

    $result = json_decode($tool->handle(new ToolRequest([
        'tool' => 'GMAIL_SEND_EMAIL',
        'arguments' => ['to' => 'someone@example.com', 'body' => 'Hello'],
    ])), true);

    expect($result['successful'])->toBeTrue()
        ->and($result['data']['messageId'])->toBe('msg_1');
});
