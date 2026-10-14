<?php

declare(strict_types=1);

namespace App\Actions\Jarvis;

use App\Enums\JarvisConnectionStatus;
use App\Models\JarvisConnection;
use App\Services\Composio\ComposioClient;
use Throwable;

/**
 * Revoke a connection and remove it locally.
 */
final class DisconnectJarvisToolkit
{
    public function __construct(
        private readonly ComposioClient $composio,
    ) {}

    public function __invoke(JarvisConnection $connection): void
    {
        if (is_string($connection->connected_account_id) && $connection->connected_account_id !== '') {
            try {
                $this->composio->disconnect($connection->connected_account_id);
            } catch (Throwable) {
                // The account may already be gone on the provider side.
            }
        }

        $connection->update([
            'status' => JarvisConnectionStatus::Revoked,
            'connected_at' => null,
            'meta' => null,
        ]);

        $connection->delete();
    }
}
