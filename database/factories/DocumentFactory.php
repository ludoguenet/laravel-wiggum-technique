<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(3, true),
            'is_confidential' => false,
        ];
    }

    /**
     * Indicate that the document is reserved to administrators.
     */
    public function confidential(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_confidential' => true,
        ]);
    }
}
