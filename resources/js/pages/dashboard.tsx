import ImpersonatorNotice from '@/components/impersonator-notice';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BillingNav } from '@/modules/billing/components/BillingNav';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    const { modules } = usePage<SharedData>().props;
    const billingEnabled = modules.billing.enabled;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ImpersonatorNotice/>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Welcome back</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Manage your account, subscriptions, and billing in one place.
                    </p>
                </div>

                {billingEnabled && <BillingNav />}

                {billingEnabled && (
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Plans & Pricing</CardTitle>
                                <CardDescription>Choose or compare subscription plans.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Link href={route('pricing.show')}>
                                    <Button className="w-full">Open Pricing</Button>
                                </Link>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Subscriptions</CardTitle>
                                <CardDescription>Manage your active subscriptions and updates.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Link href={route('subscriptions.index')}>
                                    <Button className="w-full" variant="outline">Manage Subscriptions</Button>
                                </Link>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Billing History</CardTitle>
                                <CardDescription>View invoices and billing events.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Link href={route('subscriptions.index')}>
                                    <Button className="w-full" variant="secondary">View Billing</Button>
                                </Link>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
