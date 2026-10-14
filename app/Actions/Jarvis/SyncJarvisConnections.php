<?php

declare(strict_types=1);

namespace App\Actions\Jarvis;

use App\Enums\JarvisConnectionStatus;
use App\Models\JarvisConnection;
use App\Models\User;
use App\Services\Composio\ComposioClient;

/**
 * Refresh local connection statuses from Composio.
 */
final class SyncJarvisConnections
{
    public function __construct(
        private readonly ComposioClient $composio,
    ) {}

    public function __invoke(User $user): int
    {
        if (! $this->composio->configured()) {
            return 0;
        }

        $accounts = $this->composio->connectedAccountsOrEmpty($user);

        if ($accounts === []) {
            return 0;
        }

        /** @var array<string, array{id: string|null, status: string|null}> $byToolkit */
        $byToolkit = [];

        foreach ($accounts as $account) {
            $toolkit = $account['toolkit'];

            if (! is_string($toolkit) || $toolkit === '') {
                continue;
            }

            $status = JarvisConnectionStatus::fromComposio($account['status']);

            if (isset($byToolkit[$toolkit]) && ! $status->isUsable()) {
                continue;
            }

            $byToolkit[$toolkit] = ['id' => $account['id'], 'status' => $account['status']];
        }

        $updated = 0;

        foreach ($byToolkit as $toolkit => $account) {
            $status = JarvisConnectionStatus::fromComposio($account['status']);
            $definition = $this->composio->findToolkit($toolkit);

            /** @var JarvisConnection $connection */
            $connection = JarvisConnection::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'toolkit' => $toolkit,
                ],
                [
                    'name' => $definition['name'] ?? $toolkit,
                    'status' => $status,
                    'connected_account_id' => $account['id'],
                    'connected_at' => $status->isUsable() ? now() : null,
                ],
            );

            if ($connection->wasRecentlyCreated || $connection->wasChanged()) {
                $updated++;
            }
        }

        return $updated;
    }
}
