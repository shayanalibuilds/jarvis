<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\JarvisConnection;
use App\Models\JarvisSession;
use App\Services\Composio\ComposioClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Lists the products the user has connected and the tools they expose.
 */
final class ListConnections extends JarvisTool
{
    public function __construct(
        private readonly ComposioClient $composio,
        JarvisSession $session,
    ) {
        parent::__construct($session);

        $this->withoutApproval();
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'List the products the user has connected to Jarvis (Gmail, Google Drive, Notion, Todoist, and more). Pass a toolkit slug to also list the actions available for it.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $this->ensureJarvisMayAct();

        $connections = JarvisConnection::query()
            ->where('user_id', $this->user()->getKey())
            ->orderBy('toolkit')
            ->get()
            ->map(fn (JarvisConnection $connection): array => [
                'toolkit' => $connection->toolkit,
                'name' => $connection->displayName(),
                'status' => $connection->status->value,
            ])
            ->all();

        $payload = [
            'connected' => $connections,
            'composio_configured' => $this->composio->configured(),
        ];

        $toolkit = (string) $request->string('toolkit');

        if ($toolkit !== '') {
            try {
                $payload['toolkit'] = $toolkit;
                $payload['tools'] = array_map(
                    fn (array $tool): array => [
                        'slug' => $tool['slug'],
                        'description' => $tool['description'],
                    ],
                    $this->composio->tools($toolkit, 25),
                );
            } catch (Throwable $exception) {
                $payload['tools_error'] = $exception->getMessage();
            }
        }

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'toolkit' => $schema->string()
                ->description('Optional Composio toolkit slug, for example "gmail" or "notion", to list its available actions.'),
        ];
    }
}
