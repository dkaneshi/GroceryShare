<?php

namespace Database\Factories;

use App\Models\GroceryShare;
use App\Models\GroceryWeek;
use App\Models\Sister;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroceryShare>
 */
class GroceryShareFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grocery_week_id' => GroceryWeek::factory(),
            'sister_id' => Sister::factory(),
            'amount' => fake()->randomFloat(2, 10, 100),
        ];
    }
}
