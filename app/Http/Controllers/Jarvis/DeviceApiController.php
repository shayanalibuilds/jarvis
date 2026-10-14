<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jarvis;

use App\Actions\Jarvis\PauseJarvisSession;
use App\Actions\Jarvis\ResumeJarvisSession;
use App\Actions\Jarvis\StopJarvisSession;
use App\Actions\Jarvis\TakeOverJarvisSession;
use App\Enums\JarvisSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\JarvisDevice;
use App\Models\JarvisSession;
use App\Models\JarvisToolCall;
use App\Services\Jarvis\DeviceBridge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints used by the Jarvis desktop application.
 */
final class DeviceApiController extends Controller
{
    /**
     * Report that the device is alive and fetch the current state.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $device = $this->device($request);

        return response()->json([
            'device' => [
                'id' => $device->getKey(),
                'name' => $device->name,
                'platform' => $device->platform,
            ],
            'server_time' => now()->toIso8601String(),
            'session' => $this->sessionPayload($device),
            'shortcuts' => config('jarvis.control.shortcuts'),
            'capabilities' => ['run_shell_command', 'computer_use', 'read_file', 'write_file', 'list_directory'],
        ]);
    }

    /**
     * Claim the next tool call the device should execute.
     */
    public function next(Request $request, DeviceBridge $bridge): JsonResponse
    {
        $device = $this->device($request);
        $call = $bridge->claimNext($device);

        if (! $call instanceof JarvisToolCall) {
            return response()->json(['call' => null]);
        }

        return response()->json([
            'call' => [
                'id' => $call->getKey(),
                'capability' => $call->tool,
                'arguments' => $call->arguments ?? [],
                'timeout' => Config::integer('jarvis.shell.timeout', 60),
            ],
        ]);
    }

    /**
     * Report the result of a tool call.
     */
    public function report(Request $request, JarvisToolCall $call, DeviceBridge $bridge): JsonResponse
    {
        $device = $this->device($request);

        abort_unless($call->device_id === $device->getKey(), 403, 'This tool call belongs to another device.');

        $result = $request->input('result');
        $error = $request->input('error');

        $bridge->complete(
            $call,
            is_string($result) ? $result : null,
            is_string($error) ? $error : null,
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Get the current session state for the device's owner.
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'session' => $this->sessionPayload($this->device($request)),
        ]);
    }

    /**
     * Take over, pause, resume, or stop the active session.
     */
    public function control(
        Request $request,
        PauseJarvisSession $pause,
        ResumeJarvisSession $resume,
        StopJarvisSession $stop,
        TakeOverJarvisSession $takeOver,
    ): JsonResponse {
        $action = $request->string('action')->value();

        if (! in_array($action, ['take_over', 'pause', 'resume', 'stop'], true)) {
            throw ValidationException::withMessages([
                'action' => 'The action must be one of: take_over, pause, resume, stop.',
            ]);
        }

        $device = $this->device($request);

        $session = JarvisSession::query()
            ->where('user_id', $device->user_id)
            ->where('status', '!=', JarvisSessionStatus::Stopped)
            ->latest('created_at')
            ->first();

        if (! $session instanceof JarvisSession) {
            return response()->json([
                'session' => null,
                'message' => 'There is no active Jarvis session.',
            ]);
        }

        match ($action) {
            'take_over' => $takeOver($session, 'Taken over from the desktop shortcut.'),
            'pause' => $pause($session, 'Paused from the desktop shortcut.'),
            'resume' => $resume($session, 'Resumed from the desktop shortcut.'),
            default => $stop($session, 'Stopped from the desktop shortcut.'),
        };

        return response()->json([
            'session' => $this->sessionPayload($device),
            'message' => 'Jarvis is now '.$session->refresh()->status->label().'.',
        ]);
    }

    /**
     * Resolve the authenticated device.
     */
    private function device(Request $request): JarvisDevice
    {
        $device = $request->attributes->get('jarvis_device');

        abort_unless($device instanceof JarvisDevice, 401, 'A Jarvis device token is required.');

        return $device;
    }

    /**
     * Build the session payload the desktop application consumes.
     *
     * @return array<string, mixed>|null
     */
    private function sessionPayload(JarvisDevice $device): ?array
    {
        $session = JarvisSession::query()
            ->where('user_id', $device->user_id)
            ->where('status', '!=', JarvisSessionStatus::Stopped)
            ->latest('created_at')
            ->first();

        if (! $session instanceof JarvisSession) {
            return null;
        }

        return [
            'id' => $session->getKey(),
            'title' => $session->title,
            'status' => $session->status->value,
            'status_label' => $session->status->label(),
            'current_tool' => $session->current_tool,
            'allows_execution' => $session->allowsExecution(),
            'last_activity_at' => $session->last_activity_at?->toIso8601String(),
        ];
    }
}
