<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\JarvisSession;
use App\Services\Jarvis\CapabilityExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Lists a directory on the user's computer.
 */
final class ListDirectory extends JarvisTool
{
    public function __construct(
        private readonly CapabilityExecutor $executor,
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
        return 'List the files and folders inside a directory on the user\'s computer.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $this->ensureJarvisMayAct();

        return $this->executor->execute($this->session, 'list_directory', [
            'path' => (string) $request->string('path') ?: '.',
        ]);
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('The absolute path of the directory to list.')
                ->required(),
        ];
    }
}
