<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Person;
use App\Models\TenantPeople;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantPeople>
 */
class TenantPeopleFactory extends Factory
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
            'person_id' => Person::factory(),
            'first_seen_at' => now(),
        ];
    }
}
