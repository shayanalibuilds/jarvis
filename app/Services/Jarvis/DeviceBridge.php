<?php

declare(strict_types=1);

namespace App\Services\Jarvis;

use App\Enums\JarvisToolCallStatus;
use App\Exceptions\Jarvis\DeviceBridgeException;
use App\Exceptions\Jarvis\JarvisInterruptedException;
use App\Models\JarvisDevice;
use App\Models\JarvisSession;
use App\Models\JarvisToolCall;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Hands tool calls to the desktop application and waits for the result.
 *
 * The desktop app polls for work, executes it locally (shell, computer use),
 * and reports back. If the user stops or takes over the session while a tool
 * call is in flight, the call is cancelled immediately.
 */
final class DeviceBridge
{
    /**
     * Get the most recently seen online device for the user.
     */
    public function activeDeviceFor(User $user): ?JarvisDevice
    {
        $ttl = (int) config('jarvis.devices.heartbeat_ttl', 60);

        return JarvisDevice::query()
            ->where('user_id', $user->getKey())
            ->where('last_seen_at', '>=', now()->subSeconds($ttl))
            ->latest('last_seen_at')
            ->first();
    }

    /**
     * Determine whether the user has an online desktop device.
     */
    public function hasActiveDevice(User $user): bool
    {
        return $this->activeDeviceFor($user) instanceof JarvisDevice;
    }

    /**
     * Dispatch a tool call to the user's desktop device and wait for the result.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws DeviceBridgeException
     * @throws JarvisInterruptedException
     */
    public function dispatch(JarvisSession $session, string $tool, array $arguments, ?int $timeout = null): string
    {
        $device = $this->activeDeviceFor($session->user);

        if (! $device instanceof JarvisDevice) {
            throw DeviceBridgeException::noDeviceOnline();
        }

        $call = JarvisToolCall::query()->create([
            'session_id' => $session->getKey(),
            'user_id' => $session->user_id,
            'device_id' => $device->getKey(),
            'tool' => $tool,
            'target' => 'device',
            'status' => JarvisToolCallStatus::Pending,
            'arguments' => $arguments,
        ]);

        $timeout ??= (int) config('jarvis.devices.result_timeout', 90);
        $deadline = now()->addSeconds($timeout);

        while (now()->lt($deadline)) {
            $call->refresh();

            if ($call->status === JarvisToolCallStatus::Completed) {
                return (string) $call->result;
            }

            if ($call->status === JarvisToolCallStatus::Failed) {
                throw DeviceBridgeException::failed($tool, (string) $call->error);
            }

            if ($call->status === JarvisToolCallStatus::Rejected) {
                throw JarvisInterruptedException::forSession($session->refresh());
            }

            if (! $session->refresh()->allowsExecution()) {
                $call->update([
                    'status' => JarvisToolCallStatus::Rejected,
                    'error' => 'Cancelled because the user took over.',
                    'completed_at' => now(),
                ]);

                throw JarvisInterruptedException::forSession($session);
            }

            usleep(250_000);
        }

        $call->update([
            'status' => JarvisToolCallStatus::Failed,
            'error' => 'Timed out waiting for the device.',
            'completed_at' => now(),
        ]);

        throw DeviceBridgeException::timedOut($tool);
    }

    /**
     * Claim the next pending tool call for the given device.
     */
    public function claimNext(JarvisDevice $device): ?JarvisToolCall
    {
        return DB::transaction(function () use ($device): ?JarvisToolCall {
            /** @var Builder<JarvisToolCall> $query */
            $query = JarvisToolCall::query()
                ->pending()
                ->where('user_id', $device->user_id)
                ->where(function (Builder $query) use ($device): void {
                    $query->whereNull('device_id')->orWhere('device_id', $device->getKey());
                })
                ->oldest();

            $call = $query->first();

            if (! $call instanceof JarvisToolCall) {
                return null;
            }

            $call->update([
                'status' => JarvisToolCallStatus::Running,
                'device_id' => $device->getKey(),
                'claimed_at' => now(),
            ]);

            return $call;
        });
    }

    /**
     * Record the outcome of a tool call executed by a device.
     */
    public function complete(JarvisToolCall $call, ?string $result = null, ?string $error = null): JarvisToolCall
    {
        $call->update([
            'status' => $error === null ? JarvisToolCallStatus::Completed : JarvisToolCallStatus::Failed,
            'result' => $result,
            'error' => $error,
            'completed_at' => now(),
        ]);

        return $call;
    }

    /**
     * Cancel every outstanding tool call for a session.
     */
    public function cancelOutstanding(JarvisSession $session, string $reason): int
    {
        return JarvisToolCall::query()
            ->where('session_id', $session->getKey())
            ->whereIn('status', [JarvisToolCallStatus::Pending, JarvisToolCallStatus::Running])
            ->update([
                'status' => JarvisToolCallStatus::Rejected,
                'error' => $reason,
                'completed_at' => now(),
            ]);
    }
}
