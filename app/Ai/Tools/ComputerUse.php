<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\JarvisSession;
use App\Services\Jarvis\CapabilityExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Sees and controls the user's screen: screenshots, mouse, and keyboard.
 */
final class ComputerUse extends JarvisTool
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
        return 'Control the user\'s screen: take a screenshot, move or click the mouse, type text, press keys, scroll, or open an application. Always take a screenshot first to see the current state before clicking or typing.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $this->ensureJarvisMayAct();

        $action = (string) $request->string('action');

        $this->markActivity('computer_use', 'Computer use: '.$action);

        return $this->executor->execute($this->session, 'computer_use', array_filter([
            'action' => $action,
            'x' => $request->has('x') ? $request->integer('x') : null,
            'y' => $request->has('y') ? $request->integer('y') : null,
            'button' => (string) $request->string('button') ?: null,
            'clicks' => $request->has('clicks') ? $request->integer('clicks') : null,
            'text' => (string) $request->string('text') ?: null,
            'key' => (string) $request->string('key') ?: null,
            'amount' => $request->has('amount') ? $request->integer('amount') : null,
            'application' => (string) $request->string('application') ?: null,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('The action to perform: screenshot, move, click, type, key, scroll, or open_app.')
                ->enum(['screenshot', 'move', 'click', 'type', 'key', 'scroll', 'open_app'])
                ->required(),
            'x' => $schema->integer()->description('Screen x coordinate, for move and click.'),
            'y' => $schema->integer()->description('Screen y coordinate, for move and click.'),
            'button' => $schema->string()
                ->description('Mouse button for click.')
                ->enum(['left', 'right', 'middle']),
            'clicks' => $schema->integer()->description('Number of clicks, for click.'),
            'text' => $schema->string()->description('Text to type, for type.'),
            'key' => $schema->string()->description('Key or key combination to press, for example "enter" or "cmd+s".'),
            'amount' => $schema->integer()->description('Scroll amount. Positive scrolls up, negative scrolls down.'),
            'application' => $schema->string()->description('Application name or path to open, for open_app.'),
        ];
    }
}
