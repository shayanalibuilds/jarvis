<?php

declare(strict_types=1);

use App\Http\Controllers\Jarvis\DevicePairingController;
use App\Http\Controllers\Jarvis\JarvisConnectionCallbackController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::livewire('/', 'pages::dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Jarvis routes
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('/jarvis', 'pages::jarvis')->name('jarvis.index');
    Route::livewire('/jarvis/connections', 'pages::connections.index')->name('jarvis.connections');
    Route::get('/jarvis/connections/callback', JarvisConnectionCallbackController::class)->name('jarvis.connections.callback');
    Route::post('/jarvis/devices', [DevicePairingController::class, 'store'])->name('jarvis.devices.store');
});

// Auth routes
Route::middleware('guest')->group(function () {
    Route::livewire('/auth/login', 'pages::auth.login')->name('login');
    Route::livewire('/auth/register', 'pages::auth.register')->name('register');
    Route::livewire('/auth/forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/auth/reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
});

// Profile routes
Route::middleware(['auth'])->group(function () {
    Route::livewire('/profile', 'pages::profile.index')->name('profile.update');
});

// Email verification notice route
Route::get('/verify-email', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// Email verification handler route
Route::get('/verify-email/{id}/{hash}', function () {
    // This will be handled by Laravel's built-in email verification
})->middleware(['auth', 'signed'])->name('verification.verify');
