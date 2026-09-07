<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Person;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'product_id' => Product::factory(),
            'product_name' => fake()->unique()->words(3, true),
            'amount_cents' => fake()->randomElement([0, 900, 2900, 9900]),
            'currency' => 'USD',
        ];
    }

    /**
     * Indicate that this was a free signup.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_cents' => 0,
        ]);
    }

    /**
     * Indicate that the signup couldn't be matched to a catalog Product,
     * keeping only the recorded product_name.
     */
    public function withoutMatchedProduct(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => null,
        ]);
    }
}
