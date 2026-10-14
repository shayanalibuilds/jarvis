<?php

declare(strict_types=1);

use App\Ai\Agents\Jarvis;
use App\Enums\JarvisSessionStatus;
use App\Models\JarvisSession;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\get;

test('the jarvis console requires authentication', function (): void {
    get('/jarvis')->assertRedirect(route('login'));
});

test('the jarvis console renders for a signed in user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/jarvis')->assertOk();
});

test('the connections screen renders and lists the catalog', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/jarvis/connections')
        ->assertOk()
        ->assertSee('Gmail')
        ->assertSee('Notion')
        ->assertSee('Todoist');
});

test('a prompt is required before jarvis runs', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::jarvis')
        ->set('prompt', '')
        ->call('run')
        ->assertHasErrors(['prompt' => 'required']);
});

test('jarvis replies to a prompt and the session is recorded', function (): void {
    $user = User::factory()->create();

    Jarvis::fake(['All done, sir.']);

    Livewire::actingAs($user)
        ->test('pages::jarvis')
        ->set('prompt', 'Check the disk space')
        ->call('run')
        ->assertHasNoErrors();

    $session = JarvisSession::query()->where('user_id', $user->getKey())->latest('created_at')->first();

    expect($session)->not->toBeNull()
        ->and($session->status)->toBe(JarvisSessionStatus::Running)
        ->and($session->conversation_id)->not->toBeNull();
});

test('the take over shortcut stops jarvis from the console', function (): void {
    $user = User::factory()->create();
    $session = JarvisSession::factory()->running()->create(['user_id' => $user->getKey()]);

    Livewire::actingAs($user)
        ->test('pages::jarvis')
        ->call('shortcut', 'take_over');

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::TakenOver);
});

test('the pause shortcut pauses and resumes jarvis', function (): void {
    $user = User::factory()->create();
    $session = JarvisSession::factory()->running()->create(['user_id' => $user->getKey()]);

    Livewire::actingAs($user)->test('pages::jarvis')->call('shortcut', 'pause');

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::Paused);

    Livewire::actingAs($user)->test('pages::jarvis')->call('shortcut', 'pause');

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::Running);
});

test('the stop button ends the session', function (): void {
    $user = User::factory()->create();
    $session = JarvisSession::factory()->running()->create(['user_id' => $user->getKey()]);

    Livewire::actingAs($user)->test('pages::jarvis')->call('stop');

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::Stopped);
});
