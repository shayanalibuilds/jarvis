<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jarvis;

use App\Http\Controllers\Controller;
use App\Models\JarvisDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pairs a desktop application with the signed in user.
 */
final class DevicePairingController extends Controller
{
    /**
     * Register a new desktop device and return its token.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $request->user();

        abort_if($user === null, 401, 'You must be signed in to pair a device.');

        /** @var JarvisDevice $device */
        $device = JarvisDevice::query()->create([
            'user_id' => $user->getKey(),
            'name' => $request->string('name')->value(),
            'platform' => $request->string('platform')->value() ?: 'unknown',
            'token' => Str::random(64),
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'device' => [
                'id' => $device->getKey(),
                'name' => $device->name,
                'platform' => $device->platform,
                'token' => $device->token,
            ],
        ], 201);
    }
}
