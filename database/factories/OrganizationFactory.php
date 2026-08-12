<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
        ];
    }

    /**
     * Mark the organization as the platform's super-admin organization.
     *
     * `is_super_admin` isn't fillable, so it's forced after creation rather
     * than passed through the definition.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (Organization $organization) {
            $organization->forceFill(['is_super_admin' => true])->save();
        });
    }
}
