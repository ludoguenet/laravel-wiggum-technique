<?php

namespace Database\Factories;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Visite de la chocolaterie Wonka',
            'tickets_left' => fake()->numberBetween(2, 5),
            'tickets_sold' => 0,
        ];
    }

    /**
     * Indicate that only the last golden ticket remains.
     */
    public function lastTicket(): static
    {
        return $this->state(fn (array $attributes) => [
            'tickets_left' => 1,
        ]);
    }
}
