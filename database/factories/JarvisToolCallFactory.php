<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JarvisToolCallStatus;
use App\Models\JarvisSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\JarvisToolCall>
 */
final class JarvisToolCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => JarvisSession::factory(),
            'user_id' => User::factory(),
            'tool' => 'run_shell_command',
            'target' => 'device',
            'status' => JarvisToolCallStatus::Pending,
            'arguments' => ['command' => 'echo hello'],
        ];
    }

    /**
     * Indicate that the tool call completed successfully.
     */
    public function completed(string $result = 'ok'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JarvisToolCallStatus::Completed,
            'result' => $result,
            'completed_at' => now(),
        ]);
    }
}
