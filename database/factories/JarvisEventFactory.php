<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JarvisEvent;
use App\Models\JarvisSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JarvisEvent>
 */
final class JarvisEventFactory extends Factory
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
            'type' => 'session.started',
            'summary' => 'Session started',
        ];
    }
}
