import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
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

const StatusBadge = ({ status }: { status: string }) => {
    const statusMap: Record<string, { bg: string; text: string }> = {
        active: { bg: 'bg-green-100', text: 'text-green-800' },
        trialing: { bg: 'bg-blue-100', text: 'text-blue-800' },
        past_due: { bg: 'bg-yellow-100', text: 'text-yellow-800' },
        canceled: { bg: 'bg-red-100', text: 'text-red-800' },
        unpaid: { bg: 'bg-red-100', text: 'text-red-800' },
    };

    const config = statusMap[status] || statusMap.active;

    return (
        <Badge className={`${config.bg} ${config.text}`}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </Badge>
    );
};

export default function SubscriptionsPage({ subscriptions }: Props) {
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subscriptions" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Your Subscriptions
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Manage your active subscriptions and billing information.
                    </p>
                </div>

                {subscriptions.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                No subscriptions yet
                            </h3>
                            <p className="text-gray-600 mb-4">
                                Get started by choosing a plan that fits your needs.
                            </p>
                            <Link href={route('pricing.show')}>
                                <Button>Browse Plans</Button>
                            </Link>
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
                                        <StatusBadge status={subscription.status} />
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid md:grid-cols-4 gap-6 mb-6">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 mb-1">
                                                Price
                                            </p>
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
                                        <Link href={route('subscriptions.show', subscription.id)}>
                                            <Button variant="outline">
                                                View Details
                                            </Button>
                                        </Link>
                                        <Link href={route('subscriptions.invoices', subscription.id)}>
                                            <Button variant="outline">
                                                Invoices
                                            </Button>
                                        </Link>
                                        {subscription.status === 'active' && !subscription.canceled_at && (
                                            <Link href={route('subscriptions.cancel', subscription.id)} method="post">
                                                <Button variant="destructive">
                                                    Cancel
                                                </Button>
                                            </Link>
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
