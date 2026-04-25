<?php

namespace Database\Factories;

use App\Models\PlanPrice;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanPriceFactory extends Factory
{
    protected $model = PlanPrice::class;

    public function definition(): array
    {
        return [
            'plan_id' => SubscriptionPlan::factory(),
            'interval' => $this->faker->randomElement(['monthly', 'annually']),
            'amount' => $this->faker->randomElement([999, 2999, 9999]),
            'trial_days' => $this->faker->randomElement([0, 7, 14, 30]),
            'stripe_price_id' => 'price_test_'.$this->faker->unique()->word(),
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => 'monthly',
        ]);
    }

    public function annually(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => 'annually',
        ]);
    }
}
