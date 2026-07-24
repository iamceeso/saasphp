import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BillingPageHeader } from '@/modules/billing/components/BillingPageHeader';
import { PlanFeatureList } from '@/modules/billing/components/PlanFeatureList';
import { formatBillingPrice } from '@/modules/billing/lib/format';
import { billingJsonRequest } from '@/modules/billing/lib/request';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import React from 'react';

interface Plan {
    id: number;
    slug: string;
    name: string;
    description: string;
    sort_order: number;
    is_active: boolean;
    is_most_popular: boolean;
    cta_type: 'subscribe' | 'contact';
    contact_url: string | null;
    contact_button_text: string | null;
    prices: Price[];
    features: Feature[];
}

interface Price {
    id: number;
    plan_id: number;
    interval: 'monthly' | 'annually';
    amount: number;
    trial_days: number;
    stripe_price_id: string | null;
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

interface SubscribeResponse {
    success?: boolean;
    redirect?: string;
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pricing', href: '/billing/pricing' },
];

export default function PricingPage({ plans, userSubscription }: Props) {
    const { auth } = usePage<SharedData>().props;
    const isAuthenticated = Boolean(auth?.user);
    const [billingInterval, setBillingInterval] = React.useState<'monthly' | 'annually'>('monthly');
    const [subscribingPlanId, setSubscribingPlanId] = React.useState<number | null>(null);
    const [subscribeError, setSubscribeError] = React.useState<string | null>(null);

    const handleSubscribe = async (plan: Plan) => {
        const price = plan.prices.find((p) => p.interval === billingInterval);

        if (!price) {
            setSubscribeError('This plan is not available for the selected billing interval.');
            return;
        }

        if (!isAuthenticated || price.amount > 0) {
            window.location.href = route('checkout.show', {
                plan_id: plan.id,
                interval: billingInterval,
            });
            return;
        }

        setSubscribingPlanId(plan.id);
        setSubscribeError(null);

        try {
            const data = await billingJsonRequest<SubscribeResponse>(route('subscribe'), {
                method: 'POST',
                body: {
                    plan_id: plan.id,
                    interval: billingInterval,
                },
            });

            if (!data.success) {
                setSubscribeError(data.error || 'Unable to activate the free plan.');
                return;
            }

            if (!data.redirect) {
                setSubscribeError('Subscription completed, but no redirect was provided. Please refresh the page.');
                return;
            }

            window.location.href = data.redirect;
        } catch (error) {
            setSubscribeError(error instanceof Error ? error.message : 'Unable to activate the free plan.');
        } finally {
            setSubscribingPlanId(null);
        }
    };

    const sortedPlans = [...plans].sort((a, b) => a.sort_order - b.sort_order);
    const highlightedPlanId = sortedPlans.find((plan) => plan.is_most_popular)?.id;

    const pricingContent = (
        <div
            className={
                isAuthenticated
                    ? 'flex h-full flex-1 flex-col gap-6 rounded-xl p-4'
                    : 'min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 px-4 py-12 sm:px-6 lg:px-8'
            }
        >
            <div className={isAuthenticated ? '' : 'mx-auto max-w-7xl'}>
                <div className={isAuthenticated ? '' : 'mb-12 text-center'}>
                    {isAuthenticated ? (
                        <BillingPageHeader title="Simple, Transparent Pricing" description="Choose the perfect plan for your needs." />
                    ) : (
                        <>
                            <h1 className="mb-4 text-4xl font-bold text-gray-900">Simple, Transparent Pricing</h1>
                            <p className="mb-8 text-xl text-gray-600">Choose the perfect plan for your needs</p>
                        </>
                    )}

                    <div
                        className={`border-border bg-background/80 mt-6 inline-flex items-center rounded-xl border p-1 ${isAuthenticated ? '' : ''}`}
                    >
                        <span className={`text-sm font-medium ${billingInterval === 'monthly' ? 'text-gray-900' : 'text-gray-600'}`}>
                            <button
                                onClick={() => setBillingInterval('monthly')}
                                className={`rounded-lg px-4 py-2 transition ${billingInterval === 'monthly' ? 'bg-foreground text-background shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                            >
                                Monthly
                            </button>
                        </span>
                        <span className={`text-sm font-medium ${billingInterval === 'annually' ? 'text-gray-900' : 'text-gray-600'}`}>
                            <button
                                onClick={() => setBillingInterval('annually')}
                                className={`rounded-lg px-4 py-2 transition ${billingInterval === 'annually' ? 'bg-foreground text-background shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                            >
                                Annually
                            </button>
                        </span>
                        <Badge className="ml-2 bg-emerald-100 text-emerald-800">Save 20%</Badge>
                    </div>
                </div>

                <div className={`grid gap-8 md:grid-cols-3 ${isAuthenticated ? '' : 'mx-auto max-w-6xl'}`}>
                    {sortedPlans.map((plan) => {
                        const price = plan.prices.find((p) => p.interval === billingInterval);
                        const isCurrentPlan = userSubscription?.plan_id === plan.id;
                        const isHighlighted = plan.id === highlightedPlanId;
                        const isFreePlan = (price?.amount ?? 0) === 0;
                        const isContactPlan = plan.cta_type === 'contact';
                        const contactButtonLabel = plan.contact_button_text || 'Contact Sales';

                        return (
                            <Card
                                key={plan.id}
                                className={`relative flex flex-col border transition-all ${
                                    isCurrentPlan
                                        ? 'shadow-lg ring-2 ring-blue-500'
                                        : isHighlighted
                                          ? 'border-blue-300 shadow-md'
                                          : 'hover:border-slate-300 hover:shadow-sm'
                                }`}
                            >
                                {isHighlighted && !isCurrentPlan && (
                                    <Badge className="absolute -top-3 right-4 bg-blue-600 text-white">Most Popular</Badge>
                                )}
                                <CardHeader>
                                    <div className="mb-2 flex items-center justify-between">
                                        <CardTitle className="text-2xl">{plan.name}</CardTitle>
                                        {isCurrentPlan && <Badge className="bg-blue-600">Current Plan</Badge>}
                                    </div>
                                    <CardDescription>{plan.description}</CardDescription>
                                </CardHeader>
                                <CardContent className="flex-grow">
                                    <div className="mb-6">
                                        {isContactPlan && !price ? (
                                            <>
                                                <div className="mb-2">
                                                    <span className="text-4xl font-bold tracking-tight">Custom</span>
                                                </div>
                                                <p className="text-sm font-medium text-amber-700">
                                                    Talk to our team for tailored pricing and onboarding.
                                                </p>
                                            </>
                                        ) : price ? (
                                            <>
                                                <div className="mb-2 flex items-end gap-2">
                                                    <span className="text-4xl font-bold tracking-tight">{formatBillingPrice(price.amount)}</span>
                                                    <span className="mb-1 text-sm text-gray-600">
                                                        /{price.interval === 'monthly' ? 'month' : 'year'}
                                                    </span>
                                                </div>
                                                {price.trial_days > 0 && (
                                                    <p className="text-sm font-medium text-emerald-600">{price.trial_days} days free trial</p>
                                                )}
                                            </>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">Price unavailable for this interval.</p>
                                        )}
                                    </div>

                                    <div className="mb-6">
                                        <h3 className="mb-4 text-sm font-semibold">Features included:</h3>
                                        <PlanFeatureList features={plan.features} />
                                    </div>

                                    {isContactPlan ? (
                                        <Button asChild className="w-full" variant={isHighlighted ? 'default' : 'secondary'}>
                                            <a href={plan.contact_url || '/contact'}>{contactButtonLabel}</a>
                                        </Button>
                                    ) : (
                                        <Button
                                            onClick={() => void handleSubscribe(plan)}
                                            disabled={isCurrentPlan || subscribingPlanId === plan.id}
                                            className="w-full"
                                            variant={isCurrentPlan ? 'outline' : isHighlighted ? 'default' : 'secondary'}
                                        >
                                            {isCurrentPlan
                                                ? 'Current Plan'
                                                : subscribingPlanId === plan.id
                                                  ? isFreePlan
                                                      ? 'Starting Free Plan...'
                                                      : 'Loading...'
                                                  : isFreePlan
                                                    ? 'Start Free'
                                                    : 'Subscribe Now'}
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {subscribeError && (
                    <div className="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{subscribeError}</div>
                )}

                <div className={`${isAuthenticated ? '' : 'mx-auto mt-16 max-w-4xl'} bg-card mt-6 rounded-xl border p-8`}>
                    <h2 className="mb-4 text-2xl font-bold">Frequently Asked Questions</h2>
                    <div className="space-y-6">
                        <div>
                            <h3 className="mb-2 text-lg font-semibold">Can I change plans?</h3>
                            <p className="text-gray-600">Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately.</p>
                        </div>
                        <div>
                            <h3 className="mb-2 text-lg font-semibold">Is there a contract?</h3>
                            <p className="text-gray-600">No, there is no long-term contract. You can cancel your subscription at any time.</p>
                        </div>
                        <div>
                            <h3 className="mb-2 text-lg font-semibold">What payment methods do you accept?</h3>
                            <p className="text-gray-600">We accept all major credit and debit cards through Stripe.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return (
        <>
            <Head title="Pricing" />
            {isAuthenticated ? <AppLayout breadcrumbs={breadcrumbs}>{pricingContent}</AppLayout> : pricingContent}
        </>
    );
}
