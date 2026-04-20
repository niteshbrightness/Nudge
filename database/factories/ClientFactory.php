<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Timezone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->e164PhoneNumber(),
            'timezone_id' => Timezone::inRandomOrder()->first()?->id ?? 1,
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
            'sms_consent' => true,
        ];
    }
}
