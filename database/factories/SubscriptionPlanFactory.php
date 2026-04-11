<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        $names = ['Free', 'Pro', 'Enterprise'];
        $name = $this->faker->randomElement($names);

        return [
            'slug' => strtolower($name),
            'name' => $name,
            'description' => $this->faker->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
            'stripe_product_id' => 'prod_test_' . $this->faker->unique()->word(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
