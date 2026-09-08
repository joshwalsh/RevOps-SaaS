<?php

namespace Database\Factories;

use App\Models\CanonicalEvent;
use App\Models\EventNameMapping;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventNameMapping>
 */
class EventNameMappingFactory extends Factory
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
            'event_name' => fake()->unique()->word(),
            'canonical_event_id' => CanonicalEvent::factory(),
        ];
    }
}
