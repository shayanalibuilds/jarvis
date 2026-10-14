<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\JarvisSession;
use App\Services\Jarvis\CapabilityExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Runs a shell command on the user's computer.
 */
final class RunShellCommand extends JarvisTool
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
        return 'Run a shell command on the user\'s computer and return its combined output and exit code. Use this for anything the command line can do: inspecting files, running scripts, installing packages, or driving other tools. Prefer short, single purpose commands.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $this->ensureJarvisMayAct();

        $command = (string) $request->string('command');
        $timeout = $request->integer('timeout');

        $this->markActivity('run_shell_command', 'Running: '.$command);

        return $this->executor->execute($this->session, 'run_shell_command', [
            'command' => $command,
            'working_directory' => (string) $request->string('working_directory') ?: null,
            'timeout' => $timeout > 0 ? $timeout : null,
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
            'command' => $schema->string()
                ->description('The shell command to run, exactly as you would type it in a terminal.')
                ->required(),
            'working_directory' => $schema->string()
                ->description('The absolute directory to run the command in. Defaults to the Jarvis working directory.'),
            'timeout' => $schema->integer()
                ->description('How many seconds to wait before the command is terminated.'),
        ];
    }
}
