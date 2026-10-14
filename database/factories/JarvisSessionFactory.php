<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JarvisSessionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\JarvisSession>
 */
final class JarvisSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'status' => JarvisSessionStatus::Idle,
            'agent' => 'jarvis',
            'last_activity_at' => now(),
        ];
    }

    /**
     * Indicate that Jarvis is running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JarvisSessionStatus::Running,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Indicate that the user took over the session.
     */
    public function takenOver(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JarvisSessionStatus::TakenOver,
            'taken_over_at' => now(),
        ]);
    }

    /**
     * Indicate that the session was stopped.
     */
    public function stopped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JarvisSessionStatus::Stopped,
            'stopped_at' => now(),
        ]);
    }
}
