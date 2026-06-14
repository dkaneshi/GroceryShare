<?php

namespace Database\Factories;

use App\Models\Sister;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sister>
 */
class SisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
        ];
    }

    public function withPhone(): static
    {
        return $this->state(['phone' => '+15551234567']);
    }
}
