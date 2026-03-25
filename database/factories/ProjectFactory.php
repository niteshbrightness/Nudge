<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'activecollab',
            'external_id' => fake()->optional()->randomNumber(6),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(['active', 'completed', 'on_hold']),
            'url' => fake()->optional()->url(),
        ];
    }
}
