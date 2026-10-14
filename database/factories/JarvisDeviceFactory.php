<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JarvisDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JarvisDevice>
 */
final class JarvisDeviceFactory extends Factory
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
            'name' => fake()->domainWord().' laptop',
            'platform' => fake()->randomElement(['macos', 'linux', 'windows']),
            'token' => Str::random(64),
            'last_seen_at' => now(),
        ];
    }

    /**
     * Indicate that the device is offline.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_seen_at' => now()->subMinutes(10),
        ]);
    }
}
