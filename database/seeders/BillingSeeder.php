<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\PlanPrice;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'Perfect for individuals and small projects',
                'sort_order' => 1,
                'is_most_popular' => false,
                'cta_type' => 'subscribe',
                'prices' => [
                    ['interval' => 'monthly', 'amount' => 999, 'trial_days' => 14],
                    ['interval' => 'annually', 'amount' => 9990, 'trial_days' => 14],
                ],
                'features' => [
                    'users' => ['name' => 'Team Members', 'value' => '3'],
                    'storage' => ['name' => 'Storage', 'value' => '5GB'],
                    'api_calls' => ['name' => 'API Calls/Month', 'value' => '10000'],
                    'support' => ['name' => 'Email Support', 'description' => '48 hour response time'],
                ],
            ],
            [
                'slug' => 'professional',
                'name' => 'Professional',
                'description' => 'For growing teams and businesses',
                'sort_order' => 2,
                'is_most_popular' => true,
                'cta_type' => 'subscribe',
                'prices' => [
                    ['interval' => 'monthly', 'amount' => 2999, 'trial_days' => 14],
                    ['interval' => 'annually', 'amount' => 29990, 'trial_days' => 14],
                ],
                'features' => [
                    'users' => ['name' => 'Team Members', 'value' => '25'],
                    'storage' => ['name' => 'Storage', 'value' => '100GB'],
                    'api_calls' => ['name' => 'API Calls/Month', 'value' => '100000'],
                    'support' => ['name' => 'Priority Support', 'description' => '4 hour response time'],
                    'sso' => ['name' => 'SSO Integration'],
                    'analytics' => ['name' => 'Advanced Analytics'],
                ],
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For large-scale operations',
                'sort_order' => 3,
                'is_most_popular' => false,
                'cta_type' => 'contact',
                'contact_url' => 'mailto:sales@saasphp.com',
                'contact_button_text' => 'Contact Sales',
                'prices' => [
                    ['interval' => 'monthly', 'amount' => 9999, 'trial_days' => 30],
                    ['interval' => 'annually', 'amount' => 99990, 'trial_days' => 30],
                ],
                'features' => [
                    'users' => ['name' => 'Team Members', 'value' => 'unlimited'],
                    'storage' => ['name' => 'Storage', 'value' => 'unlimited'],
                    'api_calls' => ['name' => 'API Calls/Month', 'value' => 'unlimited'],
                    'support' => ['name' => '24/7 Priority Support', 'description' => '1 hour response time'],
                    'sso' => ['name' => 'SSO Integration'],
                    'analytics' => ['name' => 'Advanced Analytics'],
                    'custom_branding' => ['name' => 'Custom Branding'],
                    'dedicated_account' => ['name' => 'Dedicated Account Manager'],
                    'compliance' => ['name' => 'SOC2 & HIPAA Compliance'],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $pricesData = $planData['prices'];
            $featuresData = $planData['features'];
            unset($planData['prices'], $planData['features']);

            $plan = SubscriptionPlan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );

            foreach ($pricesData as $priceData) {
                PlanPrice::updateOrCreate(
                    ['plan_id' => $plan->id, 'interval' => $priceData['interval']],
                    [
                        'amount' => $priceData['amount'],
                        'trial_days' => $priceData['trial_days'] ?? 0,
                        'is_active' => true,
                    ]
                );
            }

            foreach ($featuresData as $featureKey => $featureData) {
                PlanFeature::updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_key' => $featureKey],
                    [
                        'feature_name' => $featureData['name'],
                        'description' => $featureData['description'] ?? null,
                        'value' => $featureData['value'] ?? null,
                    ]
                );
            }
        }

        $this->command->info('Billing plans seeded successfully.');
    }
}
