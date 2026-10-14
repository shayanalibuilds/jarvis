<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\JarvisDevice;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the desktop application using its device token.
 */
final class AuthenticateJarvisDevice
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Jarvis-Device-Token');

        $device = is_string($token) && $token !== ''
            ? JarvisDevice::query()->where('token', $token)->first()
            : null;

        if (! $device instanceof JarvisDevice) {
            throw new AuthenticationException('A valid Jarvis device token is required.');
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        $request->attributes->set('jarvis_device', $device);

        return $next($request);
    }
}
