<?php

declare(strict_types=1);

use App\Actions\Jarvis\PauseJarvisSession;
use App\Actions\Jarvis\ResumeJarvisSession;
use App\Actions\Jarvis\StartJarvisSession;
use App\Actions\Jarvis\StopJarvisSession;
use App\Actions\Jarvis\TakeOverJarvisSession;
use App\Enums\JarvisSessionStatus;
use App\Enums\JarvisToolCallStatus;
use App\Models\JarvisSession;
use App\Models\JarvisToolCall;
use App\Models\User;

test('starting a session marks it running and records an event', function (): void {
    $user = User::factory()->create();

    $session = app(StartJarvisSession::class)($user, 'Open my inbox');

    expect($session->status)->toBe(JarvisSessionStatus::Running)
        ->and($session->title)->toBe('Open my inbox')
        ->and($session->user_id)->toBe($user->getKey())
        ->and($session->events()->count())->toBe(1);
});

test('starting a new session stops the previous one', function (): void {
    $user = User::factory()->create();

    $first = app(StartJarvisSession::class)($user, 'First task');
    $second = app(StartJarvisSession::class)($user, 'Second task');

    expect($first->refresh()->status)->toBe(JarvisSessionStatus::Stopped)
        ->and($second->status)->toBe(JarvisSessionStatus::Running);
});

test('taking over stops jarvis from acting and cancels in flight tool calls', function (): void {
    $session = JarvisSession::factory()->running()->create();

    $call = JarvisToolCall::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $session->user_id,
    ]);

    app(TakeOverJarvisSession::class)($session, 'User pressed the shortcut.');

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::TakenOver)
        ->and($session->allowsExecution())->toBeFalse()
        ->and($call->refresh()->status)->toBe(JarvisToolCallStatus::Rejected)
        ->and($session->events()->where('type', 'session.taken_over')->exists())->toBeTrue();
});

test('a taken over session can be resumed', function (): void {
    $session = JarvisSession::factory()->takenOver()->create();

    app(ResumeJarvisSession::class)($session);

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::Running)
        ->and($session->allowsExecution())->toBeTrue()
        ->and($session->taken_over_at)->toBeNull();
});

test('a running session can be paused and resumed', function (): void {
    $session = JarvisSession::factory()->running()->create();

    app(PauseJarvisSession::class)($session);

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::Paused)
        ->and($session->allowsExecution())->toBeFalse();

    app(ResumeJarvisSession::class)($session);

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::Running);
});

test('stopping a session ends it and records why', function (): void {
    $session = JarvisSession::factory()->running()->create();

    app(StopJarvisSession::class)($session, 'Stopped by the user.');

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::Stopped)
        ->and($session->stopped_at)->not->toBeNull()
        ->and($session->isActive())->toBeFalse()
        ->and($session->events()->where('type', 'session.stopped')->exists())->toBeTrue();
});

test('a stopped session can no longer be taken over or paused', function (): void {
    $session = JarvisSession::factory()->stopped()->create();

    app(TakeOverJarvisSession::class)($session);
    app(PauseJarvisSession::class)($session);

    expect($session->refresh()->status)->toBe(JarvisSessionStatus::Stopped);
});
