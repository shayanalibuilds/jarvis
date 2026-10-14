<?php

declare(strict_types=1);

use App\Enums\JarvisSessionStatus;
use App\Models\JarvisDevice;
use App\Models\JarvisSession;
use App\Models\JarvisToolCall;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

test('the device api rejects requests without a token', function (): void {
    postJson('/api/jarvis/devices/heartbeat')->assertUnauthorized();
});

test('a device can pair with a signed in user', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/jarvis/devices', [
        'name' => 'Shayan laptop',
        'platform' => 'macos',
    ]);

    $response->assertCreated();

    $token = $response->json('device.token');

    expect($token)->toBeString()->not->toBeEmpty();

    assertDatabaseHas('jarvis_devices', [
        'user_id' => $user->getKey(),
        'name' => 'Shayan laptop',
        'token' => $token,
    ]);
});

test('the heartbeat returns the session state and the shortcuts', function (): void {
    $user = User::factory()->create();
    $device = JarvisDevice::factory()->create(['user_id' => $user->getKey()]);
    $session = JarvisSession::factory()->running()->create(['user_id' => $user->getKey()]);

    $response = $this->withHeader('X-Jarvis-Device-Token', $device->token)
        ->postJson('/api/jarvis/devices/heartbeat');

    $response->assertOk()
        ->assertJsonPath('session.id', $session->getKey())
        ->assertJsonPath('session.status', 'running')
        ->assertJsonPath('session.allows_execution', true);

    expect($response->json('shortcuts.take_over.accelerator'))->toBe('CommandOrControl+Shift+J')
        ->and($device->refresh()->last_seen_at)->not->toBeNull();
});

test('a device claims the next pending tool call and reports the result', function (): void {
    $user = User::factory()->create();
    $device = JarvisDevice::factory()->create(['user_id' => $user->getKey()]);
    $session = JarvisSession::factory()->running()->create(['user_id' => $user->getKey()]);

    $call = JarvisToolCall::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $user->getKey(),
    ]);

    $claimed = $this->withHeader('X-Jarvis-Device-Token', $device->token)
        ->getJson('/api/jarvis/devices/next');

    $claimed->assertOk()
        ->assertJsonPath('call.id', $call->getKey())
        ->assertJsonPath('call.capability', 'run_shell_command');

    $this->withHeader('X-Jarvis-Device-Token', $device->token)
        ->postJson('/api/jarvis/devices/calls/'.$call->getKey(), ['result' => '{"output":"ok"}'])
        ->assertOk();

    expect($call->refresh()->result)->toBe('{"output":"ok"}')
        ->and($call->status->value)->toBe('completed');
});

test('a device cannot report on another device tool call', function (): void {
    $device = JarvisDevice::factory()->create();
    $other = JarvisToolCall::factory()->create();

    $this->withHeader('X-Jarvis-Device-Token', $device->token)
        ->postJson('/api/jarvis/devices/calls/'.$other->getKey(), ['result' => 'nope'])
        ->assertForbidden();
});

test('the desktop shortcut can take over and stop the active session', function (): void {
    $user = User::factory()->create();
    $device = JarvisDevice::factory()->create(['user_id' => $user->getKey()]);
    $session = JarvisSession::factory()->running()->create(['user_id' => $user->getKey()]);

    $this->withHeader('X-Jarvis-Device-Token', $device->token)
        ->postJson('/api/jarvis/control', ['action' => 'take_over'])
        ->assertOk()
        ->assertJsonPath('session.status', 'taken_over');

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::TakenOver);

    $this->withHeader('X-Jarvis-Device-Token', $device->token)
        ->postJson('/api/jarvis/control', ['action' => 'resume'])
        ->assertOk()
        ->assertJsonPath('session.status', 'running');

    $this->withHeader('X-Jarvis-Device-Token', $device->token)
        ->postJson('/api/jarvis/control', ['action' => 'stop'])
        ->assertOk();

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::Stopped);
});

test('the control endpoint validates the requested action', function (): void {
    $device = JarvisDevice::factory()->create();

    $this->withHeader('X-Jarvis-Device-Token', $device->token)
        ->postJson('/api/jarvis/control', ['action' => 'launch-missiles'])
        ->assertStatus(422);
});

test('control reports when there is no active session', function (): void {
    $device = JarvisDevice::factory()->create();

    $this->withHeader('X-Jarvis-Device-Token', $device->token)
        ->postJson('/api/jarvis/control', ['action' => 'take_over'])
        ->assertOk()
        ->assertJsonPath('session', null);
});
