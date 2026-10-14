<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JarvisConnectionStatus;
use App\Models\JarvisConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JarvisConnection>
 */
final class JarvisConnectionFactory extends Factory
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
            'toolkit' => 'gmail',
            'name' => 'Gmail',
            'status' => JarvisConnectionStatus::Pending,
        ];
    }

    /**
     * Indicate that the connection is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JarvisConnectionStatus::Active,
            'connected_account_id' => 'ca_'.Str::random(12),
            'connected_at' => now(),
        ]);
    }
}
