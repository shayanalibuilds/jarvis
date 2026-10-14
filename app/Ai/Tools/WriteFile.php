<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\JarvisSession;
use App\Services\Jarvis\CapabilityExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Writes a file to the user's computer.
 */
final class WriteFile extends JarvisTool
{
    public function __construct(
        private readonly CapabilityExecutor $executor,
        JarvisSession $session,
    ) {
        parent::__construct($session);
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Create or overwrite a file on the user\'s computer with the given contents. Missing parent directories are created automatically.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $this->ensureJarvisMayAct();

        $path = (string) $request->string('path');

        $this->markActivity('write_file', 'Writing: '.$path);

        return $this->executor->execute($this->session, 'write_file', [
            'path' => $path,
            'contents' => (string) $request->string('contents'),
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
                ->description('The absolute path of the file to write.')
                ->required(),
            'contents' => $schema->string()
                ->description('The full contents to write to the file.')
                ->required(),
        ];
    }
}
