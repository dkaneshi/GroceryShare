<?php

namespace Database\Factories;

use App\Models\GroceryWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroceryWeek>
 */
class GroceryWeekFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 60, 300);

        return [
            'week_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'total_amount' => $total,
            'share_amount' => (int) floor($total / 3),
            'notes' => null,
        ];
    }
}
