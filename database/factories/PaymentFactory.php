<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Sister;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sister_id' => Sister::factory(),
            'amount' => fake()->randomFloat(2, 10, 100),
            'paid_on' => now()->toDateString(),
            'notes' => null,
        ];
    }
}
