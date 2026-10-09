<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'reference' => fake()->unique()->bothify('ORD-####'),
            'product' => fake()->words(3, true),
            'total_cents' => fake()->numberBetween(1_000, 50_000),
            'status' => fake()->randomElement(['paid', 'shipped', 'delivered']),
        ];
    }
}
