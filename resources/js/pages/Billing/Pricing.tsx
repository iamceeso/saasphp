import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';
import React from 'react';

interface Plan {
    id: number;
    slug: string;
    name: string;
    description: string;
    sort_order: number;
    is_active: boolean;
    prices: Price[];
    features: Feature[];
}

interface Price {
    id: number;
    plan_id: number;
    interval: 'monthly' | 'annually';
    amount: number;
    trial_days: number;
    stripe_price_id: string;
    is_active: boolean;
}

interface Feature {
    id: number;
    plan_id: number;
    feature_key: string;
    feature_name: string;
    description: string | null;
    value: string | null;
}

interface Subscription {
    id: number;
    plan_id: number;
    status: string;
}

interface Props {
    plans: Plan[];
    userSubscription: Subscription | null;
}

export default function PricingPage({ plans, userSubscription }: Props) {
    const [billingInterval, setBillingInterval] = React.useState<'monthly' | 'annually'>('monthly');

    const formatPrice = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount / 100);
    };

    const handleSubscribe = (planId: number) => {
        window.location.href = route('checkout.show', {
            plan_id: planId,
            interval: billingInterval,
        });
    };

    return (
        <>
            <Head title="Pricing" />
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-12 px-4 sm:px-6 lg:px-8">
                <div className="max-w-7xl mx-auto">
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-gray-900 mb-4">
                            Simple, Transparent Pricing
                        </h1>
                        <p className="text-xl text-gray-600 mb-8">
                            Choose the perfect plan for your needs
                        </p>

                        <div className="flex justify-center items-center gap-4">
                            <span className={`text-sm font-medium ${billingInterval === 'monthly' ? 'text-gray-900' : 'text-gray-600'}`}>
                                Monthly
                            </span>
                            <button
                                onClick={() => setBillingInterval(billingInterval === 'monthly' ? 'annually' : 'monthly')}
                                className="relative inline-flex h-8 w-14 items-center rounded-full bg-gray-300"
                            >
                                <span
                                    className={`inline-block h-6 w-6 transform rounded-full bg-white transition ${
                                        billingInterval === 'annually' ? 'translate-x-7' : 'translate-x-1'
                                    }`}
                                />
                            </button>
                            <span className={`text-sm font-medium ${billingInterval === 'annually' ? 'text-gray-900' : 'text-gray-600'}`}>
                                Annually
                                <Badge className="ml-2 bg-green-100 text-green-800">Save 20%</Badge>
                            </span>
                        </div>
                    </div>

                    <div className="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                        {plans.map((plan) => {
                            const price = plan.prices.find((p) => p.interval === billingInterval);
                            const isCurrentPlan = userSubscription?.plan_id === plan.id;

                            return (
                                <Card
                                    key={plan.id}
                                    className={`flex flex-col ${isCurrentPlan ? 'ring-2 ring-blue-500 shadow-lg' : ''}`}
                                >
                                    <CardHeader>
                                        <div className="flex items-center justify-between mb-2">
                                            <CardTitle className="text-2xl">{plan.name}</CardTitle>
                                            {isCurrentPlan && (
                                                <Badge className="bg-blue-600">Current Plan</Badge>
                                            )}
                                        </div>
                                        <CardDescription>{plan.description}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex-grow">
                                        <div className="mb-6">
                                            {price ? (
                                                <>
                                                    <div className="flex items-baseline gap-2 mb-2">
                                                        <span className="text-4xl font-bold">
                                                            {formatPrice(price.amount)}
                                                        </span>
                                                        <span className="text-gray-600">
                                                            /{price.interval === 'monthly' ? 'month' : 'year'}
                                                        </span>
                                                    </div>
                                                    {price.trial_days > 0 && (
                                                        <p className="text-sm text-green-600 font-medium">
                                                            {price.trial_days} days free trial
                                                        </p>
                                                    )}
                                                </>
                                            ) : null}
                                        </div>

                                        <div className="mb-6">
                                            <h3 className="font-semibold text-sm mb-4">Features included:</h3>
                                            <ul className="space-y-3">
                                                {plan.features.map((feature) => (
                                                    <li key={feature.id} className="flex items-start gap-3">
                                                        <svg
                                                            className="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                strokeWidth={2}
                                                                d="M5 13l4 4L19 7"
                                                            />
                                                        </svg>
                                                        <div>
                                                            <p className="text-sm font-medium text-gray-900">
                                                                {feature.feature_name}
                                                            </p>
                                                            {feature.description && (
                                                                <p className="text-xs text-gray-500">
                                                                    {feature.description}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>

                                        <Button
                                            onClick={() => handleSubscribe(plan.id)}
                                            disabled={isCurrentPlan}
                                            className="w-full"
                                            variant={isCurrentPlan ? 'outline' : 'default'}
                                        >
                                            {isCurrentPlan ? 'Current Plan' : 'Subscribe Now'}
                                        </Button>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>

                    <div className="max-w-4xl mx-auto mt-16 bg-white rounded-lg shadow p-8">
                        <h2 className="text-2xl font-bold mb-4">Frequently Asked Questions</h2>
                        <div className="space-y-6">
                            <div>
                                <h3 className="font-semibold text-lg mb-2">Can I change plans?</h3>
                                <p className="text-gray-600">
                                    Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately.
                                </p>
                            </div>
                            <div>
                                <h3 className="font-semibold text-lg mb-2">Is there a contract?</h3>
                                <p className="text-gray-600">
                                    No, there is no long-term contract. You can cancel your subscription at any time.
                                </p>
                            </div>
                            <div>
                                <h3 className="font-semibold text-lg mb-2">What payment methods do you accept?</h3>
                                <p className="text-gray-600">
                                    We accept all major credit and debit cards through Stripe.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
