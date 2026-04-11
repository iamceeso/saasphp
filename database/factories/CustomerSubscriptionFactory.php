<?php

namespace Database\Factories;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerSubscriptionFactory extends Factory
{
    protected $model = CustomerSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => SubscriptionPlan::factory(),
            'stripe_subscription_id' => 'sub_test_' . $this->faker->unique()->word(),
            'stripe_customer_id' => 'cus_test_' . $this->faker->unique()->word(),
            'status' => 'active',
            'interval' => 'monthly',
            'amount' => 2999,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'trial_ends_at' => null,
            'canceled_at' => null,
            'ended_at' => null,
            'metadata' => json_encode(['currency' => 'USD']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'canceled_at' => null,
        ]);
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'canceled_at' => now(),
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
            'amount' => 29990,
        ]);
    }
}
