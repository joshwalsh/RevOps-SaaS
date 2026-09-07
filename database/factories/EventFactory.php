<?php

namespace Database\Factories;

use App\Models\AnonIdentity;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'anon_identity_id' => AnonIdentity::factory(),
            'person_id' => null,
            'event_name' => fake()->randomElement(['page_view', 'signup_started', 'purchase']),
            'properties' => [],
        ];
    }
}
