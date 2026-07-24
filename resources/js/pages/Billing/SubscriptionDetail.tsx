import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BillingNav } from '@/modules/billing/components/BillingNav';
import { BillingPageHeader } from '@/modules/billing/components/BillingPageHeader';
import { BillingStatusBadge } from '@/modules/billing/components/BillingStatusBadge';
import { PlanFeatureList } from '@/modules/billing/components/PlanFeatureList';
import { formatBillingDate, formatBillingPrice } from '@/modules/billing/lib/format';
import { billingJsonRequest } from '@/modules/billing/lib/request';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Subscription {
    id: number;
    plan_id: number;
    status: string;
    interval: string;
    amount: number;
    current_period_start: string;
    current_period_end: string;
    canceled_at: string | null;
    ended_at?: string | null;
    trial_ends_at: string | null;
    metadata?: {
        provider?: string;
        free_tier?: boolean;
    };
    plan: {
        id: number;
        name: string;
        description: string;
        features: Feature[];
    };
    billingEvents: BillingEvent[];
}

interface Feature {
    id: number;
    feature_key: string;
    feature_name: string;
    description: string | null;
}

interface BillingEvent {
    id: number;
    event_type: string;
    created_at: string;
}

interface Plan {
    id: number;
    name: string;
}

interface AvailablePlan extends Plan {
    prices: Price[];
    features: Feature[];
}

interface Price {
    id: number;
    amount: number;
    interval: string;
}

interface Props {
    subscription: Subscription;
    availablePlans: AvailablePlan[];
}

interface BillingActionResponse {
    success?: boolean;
    error?: string;
    message?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Subscriptions', href: '/subscriptions' },
];

export default function SubscriptionDetailPage({ subscription, availablePlans }: Props) {
    const [selectedPlan, setSelectedPlan] = useState<number | null>(null);
    const [selectedInterval, setSelectedInterval] = useState<'monthly' | 'annually'>('monthly');
    const [isUpdating, setIsUpdating] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const isFreeSubscription = subscription.metadata?.provider === 'free';
    const selectedPlanRecord = availablePlans.find((plan) => plan.id === selectedPlan);
    const selectedPlanPrice = selectedPlanRecord?.prices.find((price) => price.interval === selectedInterval);
    const upgradingFromFreeToPaid = isFreeSubscription && (selectedPlanPrice?.amount ?? 0) > 0;

    const handleSwapPlan = async () => {
        if (!selectedPlan) return;

        setIsUpdating(true);
        setActionError(null);
        try {
            const data = await billingJsonRequest<BillingActionResponse>(route('subscriptions.swap-plan', subscription.id), {
                method: 'POST',
                body: {
                    plan_id: selectedPlan,
                    interval: selectedInterval,
                    prorate: true,
                },
            });

            if (data.success) {
                window.location.reload();
                return;
            }
            setActionError(data.error || data.message || 'Unable to update subscription plan.');
        } catch (error) {
            setActionError(error instanceof Error ? error.message : 'Unable to update subscription plan.');
        } finally {
            setIsUpdating(false);
        }
    };

    const handleCancel = async () => {
        if (!confirm('Are you sure you want to cancel this subscription?')) return;

        try {
            const data = await billingJsonRequest<BillingActionResponse>(route('subscriptions.cancel', subscription.id), {
                method: 'POST',
            });

            if (data.success) {
                window.location.href = route('subscriptions.index');
            }
        } catch (error) {
            console.error('Error canceling subscription:', error);
        }
    };

    return (
        <AppLayout breadcrumbs={[...breadcrumbs, { title: subscription.plan.name, href: route('subscriptions.show', subscription.id) }]}>
            <Head title={`Subscription - ${subscription.plan.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <BillingPageHeader
                        title={subscription.plan.name}
                        description="Review your plan details, billing cycle, and subscription actions."
                    />
                    <BillingStatusBadge status={subscription.status} />
                </div>
                <BillingNav />

                <div className="mb-8 grid gap-8 md:grid-cols-3">
                    <div className="md:col-span-2">
                        <Card className="mb-6">
                            <CardHeader>
                                <CardTitle>Subscription Details</CardTitle>
                                <CardDescription>{subscription.plan.description}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="mb-1 text-sm font-medium text-gray-600">Plan</p>
                                        <p className="text-lg font-semibold text-gray-900">{subscription.plan.name}</p>
                                    </div>
                                    <div>
                                        <p className="mb-1 text-sm font-medium text-gray-600">Price</p>
                                        <p className="text-lg font-semibold text-gray-900">
                                            {isFreeSubscription ? (
                                                'Free'
                                            ) : (
                                                <>
                                                    {formatBillingPrice(subscription.amount)}
                                                    <span className="text-sm font-normal text-gray-600">
                                                        /{subscription.interval === 'monthly' ? 'mo' : 'yr'}
                                                    </span>
                                                </>
                                            )}
                                        </p>
                                    </div>
                                    {isFreeSubscription ? (
                                        <div>
                                            <p className="mb-1 text-sm font-medium text-gray-600">Access</p>
                                            <p className="text-sm text-gray-900">Active free tier</p>
                                        </div>
                                    ) : (
                                        <div>
                                            <p className="mb-1 text-sm font-medium text-gray-600">Current Period</p>
                                            <p className="text-sm text-gray-900">
                                                {formatBillingDate(subscription.current_period_start)} -{' '}
                                                {formatBillingDate(subscription.current_period_end)}
                                            </p>
                                        </div>
                                    )}
                                    {subscription.trial_ends_at && (
                                        <div>
                                            <p className="mb-1 text-sm font-medium text-gray-600">Trial Ends</p>
                                            <p className="text-sm font-medium text-green-600">{formatBillingDate(subscription.trial_ends_at)}</p>
                                        </div>
                                    )}
                                    {subscription.canceled_at && (
                                        <div>
                                            <p className="mb-1 text-sm font-medium text-gray-600">Canceled At</p>
                                            <p className="text-sm font-medium text-red-600">{formatBillingDate(subscription.canceled_at)}</p>
                                        </div>
                                    )}
                                    {subscription.ended_at && (
                                        <div>
                                            <p className="mb-1 text-sm font-medium text-gray-600">Ended At</p>
                                            <p className="text-sm text-gray-900">{formatBillingDate(subscription.ended_at)}</p>
                                        </div>
                                    )}
                                </div>

                                <div className="flex gap-3 border-t pt-4">
                                    <Button asChild variant="outline">
                                        <Link href={route('subscriptions.invoices', subscription.id)}>View Invoices</Link>
                                    </Button>
                                    {(subscription.status === 'active' || subscription.status === 'trialing') && !subscription.canceled_at && (
                                        <Button variant="destructive" onClick={handleCancel}>
                                            Cancel Subscription
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Plan Features</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PlanFeatureList features={subscription.plan.features} />
                            </CardContent>
                        </Card>
                    </div>

                    <div>
                        {availablePlans.length > 0 && subscription.status !== 'canceled' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">Change Plan</CardTitle>
                                    <CardDescription>Switch to a different plan</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <select
                                        value={selectedPlan || ''}
                                        onChange={(e) => {
                                            const value = e.target.value;
                                            setSelectedPlan(value ? parseInt(value, 10) : null);
                                        }}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2"
                                    >
                                        <option value="">Select a plan</option>
                                        {availablePlans.map((plan) => (
                                            <option key={plan.id} value={plan.id}>
                                                {plan.name}
                                            </option>
                                        ))}
                                    </select>

                                    {selectedPlan && (
                                        <>
                                            <select
                                                value={selectedInterval}
                                                onChange={(e) => setSelectedInterval(e.target.value as 'monthly' | 'annually')}
                                                className="w-full rounded-lg border border-gray-300 px-3 py-2"
                                            >
                                                <option value="monthly">Monthly</option>
                                                <option value="annually">Annually</option>
                                            </select>

                                            <Button onClick={handleSwapPlan} disabled={isUpdating || upgradingFromFreeToPaid} className="w-full">
                                                {isUpdating ? 'Updating...' : upgradingFromFreeToPaid ? 'Use Pricing Page to Upgrade' : 'Update Plan'}
                                            </Button>

                                            {upgradingFromFreeToPaid && (
                                                <p className="text-muted-foreground text-sm">
                                                    Paid upgrades from a free plan require checkout so a payment method can be collected.
                                                </p>
                                            )}

                                            {actionError && <p className="text-sm text-red-600">{actionError}</p>}
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
