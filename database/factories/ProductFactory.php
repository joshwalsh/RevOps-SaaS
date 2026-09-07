<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
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
            'name' => fake()->unique()->words(3, true),
            'price_cents' => fake()->randomElement([0, 900, 2900, 9900]),
            'currency' => 'USD',
        ];
    }

    /**
     * Indicate that the product is free to sign up for.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_cents' => 0,
        ]);
    }
}
