<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\JarvisSession;
use App\Services\Jarvis\CapabilityExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Reads a file from the user's computer.
 */
final class ReadFile extends JarvisTool
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
        return 'Read the contents of a file on the user\'s computer. Returns the file contents as a string.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $this->ensureJarvisMayAct();

        return $this->executor->execute($this->session, 'read_file', [
            'path' => (string) $request->string('path'),
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
                ->description('The absolute path of the file to read.')
                ->required(),
        ];
    }
}
