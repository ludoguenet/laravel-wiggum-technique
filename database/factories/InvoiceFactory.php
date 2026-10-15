<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'number' => fake()->unique()->bothify('INV-####'),
            'amount_cents' => fake()->numberBetween(1_000, 50_000),
            'billing_address' => fake()->address(),
            'iban' => fake()->iban('BE'),
            'refunded_at' => null,
        ];
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'refunded_at' => now(),
        ]);
    }
}
