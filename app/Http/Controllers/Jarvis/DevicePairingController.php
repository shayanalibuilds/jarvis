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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);

        /** @var JarvisDevice $device */
        $device = JarvisDevice::query()->create([
            'user_id' => $request->user()->getKey(),
            'name' => $validated['name'],
            'platform' => $validated['platform'] ?? 'unknown',
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
