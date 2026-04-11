import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { BillingNav } from '@/modules/billing/components/BillingNav';
import { BillingPageHeader } from '@/modules/billing/components/BillingPageHeader';
import { BillingStatusBadge } from '@/modules/billing/components/BillingStatusBadge';
import { formatBillingDate, formatBillingPrice } from '@/modules/billing/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import React from 'react';

interface Invoice {
    id: string;
    number: string | null;
    amount: number;
    subtotal: number;
    total: number;
    amount_due: number;
    amount_paid: number;
    status: string;
    created: number;
    paid_at: number | null;
    period_start: number | null;
    period_end: number | null;
    attempt_count: number;
    description: string | null;
    invoice_pdf: string | null;
    hosted_invoice_url: string | null;
    currency: string;
}

interface Subscription {
    id: number;
    plan: {
        name: string;
    };
}

interface Props {
    subscription: Subscription;
    invoices: Invoice[];
    upcomingInvoice: {
        amount: number;
        currency: string;
        next_payment_attempt: number | null;
        period_end: number | null;
    } | null;
}

const baseBreadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Subscriptions', href: '/subscriptions' },
];

export default function InvoicesPage({ subscription, invoices, upcomingInvoice }: Props) {
    const totalCollected = invoices.reduce((sum, invoice) => sum + invoice.amount_paid, 0);
    const openInvoices = invoices.filter((invoice) => invoice.status === 'open').length;

    return (
        <AppLayout
            breadcrumbs={[
                ...baseBreadcrumbs,
                { title: subscription.plan.name, href: route('subscriptions.show', subscription.id) },
                { title: 'Invoices', href: route('subscriptions.invoices', subscription.id) },
            ]}
        >
            <Head title="Invoices" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <BillingPageHeader
                    title="Invoices"
                    description={`Billing history for ${subscription.plan.name}.`}
                />
                <BillingNav />

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="gap-0">
                            <CardDescription>Total Invoices</CardDescription>
                            <CardTitle className="text-2xl">{invoices.length}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="gap-0">
                            <CardDescription>Total Collected</CardDescription>
                            <CardTitle className="text-2xl">
                                {formatBillingPrice(totalCollected, invoices[0]?.currency ?? upcomingInvoice?.currency ?? 'USD')}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="gap-0">
                            <CardDescription>Open Invoices</CardDescription>
                            <CardTitle className="text-2xl">{openInvoices}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Billing History</CardTitle>
                        <CardDescription>
                            {invoices.length} invoice{invoices.length !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {invoices.length === 0 ? (
                            <div className="text-center py-8">
                                <p className="text-gray-600">No invoices yet</p>
                                {upcomingInvoice && (
                                        <div className="mt-4 text-sm text-gray-700">
                                            <p>
                                                Upcoming charge: {' '}
                                                <span className="font-semibold">
                                                    {formatBillingPrice(upcomingInvoice.amount, upcomingInvoice.currency)}
                                                </span>
                                            </p>
                                            <p className="mt-1 text-gray-500">
                                                Expected billing date:{' '}
                                                {formatBillingDate(
                                                    (upcomingInvoice.next_payment_attempt ?? upcomingInvoice.period_end ?? Math.floor(Date.now() / 1000))
                                                )}
                                            </p>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {invoices.map((invoice) => (
                                    <div
                                        key={invoice.id}
                                        className="rounded-xl border border-border bg-background p-4"
                                    >
                                        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                            <div className="space-y-2">
                                                <div className="flex items-center gap-3">
                                                    <h3 className="font-semibold text-foreground">
                                                        {invoice.number || invoice.id}
                                                    </h3>
                                                    <BillingStatusBadge status={invoice.status} />
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    Created {formatBillingDate(invoice.created)}
                                                    {invoice.paid_at && ` • Paid ${formatBillingDate(invoice.paid_at)}`}
                                                </p>
                                                {invoice.description && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {invoice.description}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="flex flex-wrap items-center gap-3">
                                                {invoice.hosted_invoice_url && (
                                                    <a
                                                        href={invoice.hosted_invoice_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-blue-600 hover:text-blue-800 font-medium"
                                                    >
                                                        View Invoice
                                                    </a>
                                                )}
                                                {invoice.invoice_pdf && (
                                                    <a
                                                        href={invoice.invoice_pdf}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-blue-600 hover:text-blue-800 font-medium"
                                                    >
                                                        Download PDF
                                                    </a>
                                                )}
                                            </div>
                                        </div>

                                        <div className="mt-4 grid gap-4 border-t pt-4 text-sm md:grid-cols-4">
                                            <div>
                                                <p className="text-muted-foreground">Total</p>
                                                <p className="font-semibold text-foreground">
                                                    {formatBillingPrice(invoice.total || invoice.amount, invoice.currency)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Paid</p>
                                                <p className="font-semibold text-foreground">
                                                    {formatBillingPrice(invoice.amount_paid, invoice.currency)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Amount Due</p>
                                                <p className="font-semibold text-foreground">
                                                    {formatBillingPrice(invoice.amount_due, invoice.currency)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Billing Period</p>
                                                <p className="font-semibold text-foreground">
                                                    {invoice.period_start && invoice.period_end
                                                        ? `${formatBillingDate(invoice.period_start)} - ${formatBillingDate(invoice.period_end)}`
                                                        : 'Not available'}
                                                </p>
                                            </div>
                                        </div>

                                        {invoice.attempt_count > 0 && (
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                Payment attempts: {invoice.attempt_count}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
                </div>
        </AppLayout>
    );
}
