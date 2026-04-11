import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import React, { useState } from 'react';

interface Subscription {
    id: number;
    plan_id: number;
    status: string;
    interval: string;
    amount: number;
    current_period_start: string;
    current_period_end: string;
    canceled_at: string | null;
    trial_ends_at: string | null;
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Subscriptions', href: '/subscriptions' },
];

const StatusBadge = ({ status }: { status: string }) => {
    const statusMap: Record<string, { bg: string; text: string }> = {
        active: { bg: 'bg-green-100', text: 'text-green-800' },
        trialing: { bg: 'bg-blue-100', text: 'text-blue-800' },
        past_due: { bg: 'bg-yellow-100', text: 'text-yellow-800' },
        canceled: { bg: 'bg-red-100', text: 'text-red-800' },
    };

    const config = statusMap[status] || statusMap.active;

    return (
        <Badge className={`${config.bg} ${config.text}`}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </Badge>
    );
};

export default function SubscriptionDetailPage({ subscription, availablePlans }: Props) {
    const [selectedPlan, setSelectedPlan] = useState<number | null>(null);
    const [selectedInterval, setSelectedInterval] = useState<'monthly' | 'annually'>('monthly');
    const [isUpdating, setIsUpdating] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);

    const formatPrice = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount / 100);
    };

    const formatDate = (date: string) => {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        }).format(new Date(date));
    };

    const handleSwapPlan = async () => {
        if (!selectedPlan) return;

        setIsUpdating(true);
        setActionError(null);
        try {
            const response = await fetch(route('subscriptions.swap-plan', subscription.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    plan_id: selectedPlan,
                    interval: selectedInterval,
                    prorate: true,
                }),
            });

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                setActionError('Unexpected server response. Please refresh and try again.');
                return;
            }

            const data = await response.json();
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
            const response = await fetch(route('subscriptions.cancel', subscription.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data = await response.json();
            if (data.success) {
                window.location.href = route('subscriptions.index');
            }
        } catch (error) {
            console.error('Error canceling subscription:', error);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                ...breadcrumbs,
                { title: subscription.plan.name, href: route('subscriptions.show', subscription.id) },
            ]}
        >
            <Head title={`Subscription - ${subscription.plan.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {subscription.plan.name}
                    </h1>
                    <StatusBadge status={subscription.status} />
                </div>

                <div className="grid md:grid-cols-3 gap-8 mb-8">
                    <div className="md:col-span-2">
                        <Card className="mb-6">
                            <CardHeader>
                                <CardTitle>Subscription Details</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 mb-1">Plan</p>
                                        <p className="text-lg font-semibold text-gray-900">
                                            {subscription.plan.name}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 mb-1">Price</p>
                                        <p className="text-lg font-semibold text-gray-900">
                                            {formatPrice(subscription.amount)}
                                            <span className="text-sm font-normal text-gray-600">
                                                /{subscription.interval === 'monthly' ? 'mo' : 'yr'}
                                            </span>
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 mb-1">
                                            Current Period
                                        </p>
                                        <p className="text-sm text-gray-900">
                                            {formatDate(subscription.current_period_start)} -{' '}
                                            {formatDate(subscription.current_period_end)}
                                        </p>
                                    </div>
                                    {subscription.trial_ends_at && (
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 mb-1">
                                                Trial Ends
                                            </p>
                                            <p className="text-sm text-green-600 font-medium">
                                                {formatDate(subscription.trial_ends_at)}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className="border-t pt-4 flex gap-3">
                                    <Link href={route('subscriptions.invoices', subscription.id)}>
                                        <Button variant="outline">
                                            View Invoices
                                        </Button>
                                    </Link>
                                    {subscription.status === 'active' && !subscription.canceled_at && (
                                        <Button
                                            variant="destructive"
                                            onClick={handleCancel}
                                        >
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
                                <ul className="space-y-3">
                                    {subscription.plan.features.map((feature) => (
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
                            </CardContent>
                        </Card>
                    </div>

                    <div>
                        {availablePlans.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">Change Plan</CardTitle>
                                    <CardDescription>
                                        Switch to a different plan
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <select
                                        value={selectedPlan || ''}
                                        onChange={(e) => {
                                            const value = e.target.value;
                                            setSelectedPlan(value ? parseInt(value, 10) : null);
                                        }}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg"
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
                                                onChange={(e) =>
                                                    setSelectedInterval(e.target.value as 'monthly' | 'annually')
                                                }
                                                className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                            >
                                                <option value="monthly">Monthly</option>
                                                <option value="annually">Annually</option>
                                            </select>

                                            <Button
                                                onClick={handleSwapPlan}
                                                disabled={isUpdating}
                                                className="w-full"
                                            >
                                                {isUpdating ? 'Updating...' : 'Update Plan'}
                                            </Button>

                                            {actionError && (
                                                <p className="text-sm text-red-600">
                                                    {actionError}
                                                </p>
                                            )}
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
