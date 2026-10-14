<?php

declare(strict_types=1);

use App\Http\Controllers\Jarvis\DeviceApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Jarvis Device API
|--------------------------------------------------------------------------
|
| These endpoints are used by the Jarvis desktop application, which executes
| tool calls (shell, computer use, files) on the user's own machine and lets
| the user take over with a global shortcut.
|
*/

Route::middleware('jarvis.device')->prefix('jarvis')->group(function (): void {
    Route::post('/devices/heartbeat', [DeviceApiController::class, 'heartbeat'])->name('api.jarvis.heartbeat');
    Route::get('/devices/next', [DeviceApiController::class, 'next'])->name('api.jarvis.next');
    Route::post('/devices/calls/{call}', [DeviceApiController::class, 'report'])->name('api.jarvis.calls.report');
    Route::get('/status', [DeviceApiController::class, 'status'])->name('api.jarvis.status');
    Route::post('/control', [DeviceApiController::class, 'control'])->name('api.jarvis.control');
});
