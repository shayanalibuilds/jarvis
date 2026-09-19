<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('password reset link can be requested', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test('pages::auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('email field is required', function (): void {
    Livewire::test('pages::auth.forgot-password')
        ->set('email', '')
        ->call('sendPasswordResetLink')
        ->assertHasErrors(['email' => 'required']);
});

test('email must be valid', function (): void {
    Livewire::test('pages::auth.forgot-password')
        ->set('email', 'not-an-email')
        ->call('sendPasswordResetLink')
        ->assertHasErrors(['email']);
});
