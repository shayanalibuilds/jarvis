<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jarvis;

use App\Actions\Jarvis\SyncJarvisConnections;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles the redirect back from a one-step connection.
 */
final class JarvisConnectionCallbackController extends Controller
{
    /**
     * Refresh the user's connections and return them to the connections screen.
     */
    public function __invoke(Request $request, SyncJarvisConnections $sync): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $sync($user);
        }

        return redirect()
            ->route('jarvis.connections')
            ->with('status', 'Connection updated.');
    }
}
