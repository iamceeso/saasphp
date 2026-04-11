import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { BillingNav } from '@/modules/billing/components/BillingNav';
import { BillingPageHeader } from '@/modules/billing/components/BillingPageHeader';
import { BillingStatusBadge } from '@/modules/billing/components/BillingStatusBadge';
import { formatBillingDate, formatBillingPrice } from '@/modules/billing/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import React from 'react';

interface Subscription {
    id: number;
    plan_id: number;
    stripe_subscription_id: string;
    status: string;
    interval: string;
    amount: number;
    current_period_start: string;
    current_period_end: string;
    trial_ends_at: string | null;
    canceled_at: string | null;
    ended_at?: string | null;
    plan: {
        name: string;
        description: string;
    };
}

interface Props {
    subscriptions: {
        data: Subscription[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Subscriptions', href: '/subscriptions' },
];

export default function SubscriptionsPage({ subscriptions }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subscriptions" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <BillingPageHeader
                    title="Your Subscriptions"
                    description="Manage your active subscriptions and billing information."
                />
                <BillingNav />

                {subscriptions.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                No subscriptions yet
                            </h3>
                            <p className="text-gray-600 mb-4">
                                Get started by choosing a plan that fits your needs.
                            </p>
                            <Button asChild>
                                <Link href={route('pricing.show')}>Browse Plans</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        {subscriptions.data.map((subscription) => (
                            <Card key={subscription.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="text-xl">
                                                {subscription.plan.name}
                                            </CardTitle>
                                            <CardDescription>
                                                {subscription.plan.description}
                                            </CardDescription>
                                        </div>
                                        <BillingStatusBadge status={subscription.status} />
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid md:grid-cols-4 gap-6 mb-6">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 mb-1">
                                                Price
                                            </p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {formatBillingPrice(subscription.amount)}
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
                                                {formatBillingDate(subscription.current_period_start)} -{' '}
                                                {formatBillingDate(subscription.current_period_end)}
                                            </p>
                                        </div>
                                        {subscription.trial_ends_at && (
                                            <div>
                                                <p className="text-sm font-medium text-gray-600 mb-1">
                                                    Trial Ends
                                                </p>
                                                <p className="text-sm text-green-600 font-medium">
                                                    {formatBillingDate(subscription.trial_ends_at)}
                                                </p>
                                            </div>
                                        )}
                                        {subscription.canceled_at && (
                                            <div>
                                                <p className="text-sm font-medium text-gray-600 mb-1">
                                                    Canceled At
                                                </p>
                                                <p className="text-sm text-red-600 font-medium">
                                                    {formatBillingDate(subscription.canceled_at)}
                                                </p>
                                            </div>
                                        )}
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 mb-1">
                                                Subscription ID
                                            </p>
                                            <p className="text-xs text-gray-600 font-mono">
                                                {subscription.stripe_subscription_id.slice(-8)}...
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex gap-3 pt-4 border-t">
                                        <Button asChild variant="outline">
                                            <Link href={route('subscriptions.show', subscription.id)}>
                                                View Details
                                            </Link>
                                        </Button>
                                        <Button asChild variant="outline">
                                            <Link href={route('subscriptions.invoices', subscription.id)}>
                                                Invoices
                                            </Link>
                                        </Button>
                                        {(subscription.status === 'active' || subscription.status === 'trialing') && !subscription.canceled_at && (
                                            <Button asChild variant="destructive">
                                                <Link href={route('subscriptions.cancel', subscription.id)} method="post">
                                                    Cancel
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                </div>
        </AppLayout>
    );
}
