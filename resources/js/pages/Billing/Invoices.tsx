import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/react';
import React from 'react';

interface Invoice {
    id: string;
    amount: number;
    status: string;
    created: number;
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

const StatusBadge = ({ status }: { status: string }) => {
    const statusMap: Record<string, { bg: string; text: string }> = {
        paid: { bg: 'bg-green-100', text: 'text-green-800' },
        open: { bg: 'bg-yellow-100', text: 'text-yellow-800' },
        uncollectible: { bg: 'bg-red-100', text: 'text-red-800' },
        draft: { bg: 'bg-gray-100', text: 'text-gray-800' },
        void: { bg: 'bg-gray-100', text: 'text-gray-800' },
    };

    const config = statusMap[status] || statusMap.draft;

    return (
        <span className={`inline-block px-2 py-1 rounded text-xs font-semibold ${config.bg} ${config.text}`}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

export default function InvoicesPage({ subscription, invoices, upcomingInvoice }: Props) {
    const formatPrice = (amount: number, currency = 'USD') => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency.toUpperCase(),
        }).format(amount / 100);
    };

    const formatDate = (timestamp: number) => {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        }).format(new Date(timestamp * 1000));
    };

    return (
        <>
            <Head title="Invoices" />
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-12 px-4 sm:px-6 lg:px-8">
                <div className="max-w-4xl mx-auto">
                    <div className="mb-8">
                        <Link href={route('subscriptions.show', subscription.id)} className="text-blue-600 hover:text-blue-800 mb-4 inline-block">
                            ← Back to Subscription
                        </Link>
                        <h1 className="text-3xl font-bold text-gray-900">
                            Invoices
                        </h1>
                        <p className="text-gray-600 mt-2">
                            {subscription.plan.name}
                        </p>
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
                                                    {formatPrice(upcomingInvoice.amount, upcomingInvoice.currency)}
                                                </span>
                                            </p>
                                            <p className="mt-1 text-gray-500">
                                                Expected billing date:{' '}
                                                {formatDate(
                                                    (upcomingInvoice.next_payment_attempt ?? upcomingInvoice.period_end ?? Math.floor(Date.now() / 1000))
                                                )}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b border-gray-200">
                                                <th className="text-left py-3 px-4 font-semibold text-gray-700">
                                                    Date
                                                </th>
                                                <th className="text-left py-3 px-4 font-semibold text-gray-700">
                                                    Invoice
                                                </th>
                                                <th className="text-left py-3 px-4 font-semibold text-gray-700">
                                                    Amount
                                                </th>
                                                <th className="text-left py-3 px-4 font-semibold text-gray-700">
                                                    Status
                                                </th>
                                                <th className="text-left py-3 px-4 font-semibold text-gray-700">
                                                    Action
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {invoices.map((invoice) => (
                                                <tr
                                                    key={invoice.id}
                                                    className="border-b border-gray-100 hover:bg-gray-50 transition"
                                                >
                                                    <td className="py-3 px-4 text-sm text-gray-900">
                                                        {formatDate(invoice.created)}
                                                    </td>
                                                    <td className="py-3 px-4 text-sm font-mono text-gray-600">
                                                        {invoice.id}
                                                    </td>
                                                    <td className="py-3 px-4 text-sm font-semibold text-gray-900">
                                                        {formatPrice(invoice.amount, invoice.currency)}
                                                    </td>
                                                    <td className="py-3 px-4 text-sm">
                                                        <StatusBadge status={invoice.status} />
                                                    </td>
                                                    <td className="py-3 px-4 text-sm space-x-3">
                                                        {invoice.hosted_invoice_url && (
                                                            <a
                                                                href={invoice.hosted_invoice_url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-blue-600 hover:text-blue-800 font-medium"
                                                            >
                                                                View
                                                            </a>
                                                        )}
                                                        {invoice.invoice_pdf && (
                                                            <a
                                                                href={invoice.invoice_pdf}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-blue-600 hover:text-blue-800 font-medium"
                                                            >
                                                                PDF
                                                            </a>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
