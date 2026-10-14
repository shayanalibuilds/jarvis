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
 * Runs an action on one of the user's connected products.
 */
final class RunConnectionTool extends JarvisTool
{
    public function __construct(
        private readonly ComposioClient $composio,
        JarvisSession $session,
    ) {
        parent::__construct($session);
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Run an action on one of the products the user has connected, for example GMAIL_SEND_EMAIL, GOOGLEDRIVE_FIND_FILE, NOTION_SEARCH_NOTION_PAGE, or TODOIST_CREATE_TASK. Use list_connections first to discover the exact tool slug and the toolkit it belongs to.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $this->ensureJarvisMayAct();

        $tool = (string) $request->string('tool');

        if ($tool === '') {
            return $this->error('A tool slug is required.');
        }

        $arguments = $request->array('arguments');

        /** @var array<string, mixed> $arguments */
        if (! $this->toolkitIsConnected($tool)) {
            return $this->error(sprintf(
                'The [%s] action is not available because that product is not connected. Ask the user to connect it from the Connections screen.',
                $tool,
            ));
        }

        $this->markActivity('run_connection_tool', 'Using connected app action: '.$tool);

        try {
            $result = $this->composio->execute($tool, $this->user(), $arguments);
        } catch (Throwable $exception) {
            return $this->error($exception->getMessage());
        }

        return (string) json_encode([
            'tool' => $tool,
            'successful' => $result['successful'],
            'data' => $result['data'],
            'error' => $result['error'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tool' => $schema->string()
                ->description('The Composio tool slug to run, for example GMAIL_SEND_EMAIL.')
                ->required(),
            'arguments' => $schema->object()
                ->description('The arguments for the action, matching the schema returned by list_connections.'),
        ];
    }

    /**
     * Determine whether the toolkit that owns the given tool is connected.
     */
    private function toolkitIsConnected(string $tool): bool
    {
        $prefix = mb_strtolower((string) str($tool)->before('_'));

        if ($prefix === '') {
            return false;
        }

        return JarvisConnection::query()
            ->where('user_id', $this->user()->getKey())
            ->where('toolkit', $prefix)
            ->where('status', 'active')
            ->exists();
    }

    private function error(string $message): string
    {
        return (string) json_encode(['successful' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    }
}
