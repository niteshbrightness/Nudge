<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = ['task_created', 'task_updated', 'task_completed', 'comment_created', 'project_updated'];

        return [
            'event_type' => fake()->randomElement($eventTypes),
            'raw_payload' => ['event' => fake()->word(), 'data' => ['id' => fake()->randomNumber(5)]],
            'parsed_data' => ['title' => fake()->sentence(), 'project_id' => fake()->randomNumber(5)],
            'activecollab_url' => fake()->url(),
            'short_url' => 'https://bit.ly/'.fake()->bothify('????##'),
            'received_at' => now(),
        ];
    }
}
