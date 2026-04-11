import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Head } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { CardElement, Elements, useElements, useStripe } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';

interface Plan {
    id: number;
    name: string;
    description: string;
    stripe_product_id: string;
}

interface Price {
    id: number;
    amount: number;
    interval: string;
    trial_days: number;
}

interface Props {
    plan: Plan;
    price: Price;
    interval: string;
    clientSecret: string | null;
    publishableKey: string;
}

interface CheckoutFormProps {
    plan: Plan;
    price: Price;
    interval: string;
    publishableKey: string;
}

function CheckoutForm({ plan, price, interval, publishableKey }: CheckoutFormProps) {
    const stripe = useStripe();
    const elements = useElements();
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [cardholderName, setCardholderName] = useState('');

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setIsLoading(true);
        setError(null);

        try {
            if (!stripe || !elements) {
                setError('Stripe has not loaded yet. Please wait a moment and try again.');
                return;
            }

            if (!publishableKey) {
                setError('Payment processing is not configured. Please contact support.');
                return;
            }

            const cardElement = elements.getElement(CardElement);

            if (!cardElement) {
                setError('Card form is not ready. Please refresh and try again.');
                return;
            }

            const paymentMethodResult = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    name: cardholderName,
                },
            });

            if (paymentMethodResult.error || !paymentMethodResult.paymentMethod) {
                setError(paymentMethodResult.error?.message || 'Unable to verify card details.');
                return;
            }

            const response = await fetch(route('subscribe'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    plan_id: plan.id,
                    interval: interval,
                    payment_method: paymentMethodResult.paymentMethod.id,
                }),
            });

            const contentType = response.headers.get('content-type') || '';

            if (!contentType.includes('application/json')) {
                if (response.status === 401 || response.status === 403) {
                    setError('Your session has expired or access is denied. Please sign in again and retry.');
                    return;
                }

                if (response.status === 419) {
                    setError('Your session token expired. Refresh the page and try again.');
                    return;
                }

                setError('Unexpected server response. Please refresh the page and try again.');
                return;
            }

            const data = await response.json();

            if (data.success) {
                window.location.href = data.redirect;
            } else {
                const backendError =
                    data.error ||
                    data.message ||
                    (data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : null);

                setError(backendError || 'An error occurred during payment processing');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setIsLoading(false);
        }
    };

    const formatPrice = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount / 100);
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-12 px-4">
            <div className="max-w-2xl mx-auto">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">
                        Complete Your Subscription
                    </h1>
                    <p className="text-gray-600">
                        Secure payment powered by Stripe
                    </p>
                </div>

                <div className="grid md:grid-cols-3 gap-8">
                    <div className="md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Payment Method</CardTitle>
                                <CardDescription>
                                    Enter your card details to complete the subscription
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    {error && (
                                        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                                            <p className="text-red-800">{error}</p>
                                        </div>
                                    )}

                                    <div className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div className="flex justify-between mb-2">
                                            <span className="text-gray-600">{plan.name}</span>
                                            <span className="font-semibold">{formatPrice(price.amount)}</span>
                                        </div>
                                        <p className="text-sm text-gray-500">
                                            Billed {interval === 'monthly' ? 'monthly' : 'annually'}
                                            {price.trial_days > 0 && ` • ${price.trial_days} days free`}
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Cardholder Name
                                        </label>
                                        <input
                                            type="text"
                                            value={cardholderName}
                                            onChange={(e) => setCardholderName(e.target.value)}
                                            placeholder="John Doe"
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            required
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Card Details
                                        </label>
                                        <div className="w-full px-4 py-3 border border-gray-300 rounded-lg focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-transparent">
                                            <CardElement
                                                options={{
                                                    hidePostalCode: true,
                                                    style: {
                                                        base: {
                                                            fontSize: '16px',
                                                            color: '#111827',
                                                            '::placeholder': {
                                                                color: '#6B7280',
                                                            },
                                                        },
                                                        invalid: {
                                                            color: '#DC2626',
                                                        },
                                                    },
                                                }}
                                            />
                                        </div>
                                        <p className="text-xs text-gray-500 mt-1">
                                            Test: 4242 4242 4242 4242
                                        </p>
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={isLoading || !stripe || !elements}
                                        className="w-full"
                                    >
                                        {isLoading ? 'Processing...' : `Subscribe - ${formatPrice(price.amount)}/${interval === 'monthly' ? 'mo' : 'yr'}`}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    <div>
                        <Card className="sticky top-4">
                            <CardHeader>
                                <CardTitle>Order Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <h3 className="font-semibold mb-2">{plan.name}</h3>
                                    <p className="text-sm text-gray-600">{plan.description}</p>
                                </div>

                                <div className="border-t border-gray-200 pt-4 space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span>Price</span>
                                        <span>{formatPrice(price.amount)}</span>
                                    </div>
                                    {price.trial_days > 0 && (
                                        <div className="flex justify-between text-sm text-green-600">
                                            <span>Trial period</span>
                                            <span>{price.trial_days} days free</span>
                                        </div>
                                    )}
                                </div>

                                <div className="border-t border-gray-200 pt-4">
                                    <div className="flex justify-between font-semibold">
                                        <span>Total</span>
                                        <span>{formatPrice(price.amount)}</span>
                                    </div>
                                    <p className="text-xs text-gray-500 mt-2">
                                        Billed {interval === 'monthly' ? 'monthly' : 'annually'}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function CheckoutPage({ plan, price, interval, publishableKey }: Props) {
    const stripePromise = useMemo(
        () => (publishableKey ? loadStripe(publishableKey) : null),
        [publishableKey]
    );

    return (
        <>
            <Head title="Checkout" />
            {stripePromise ? (
                <Elements stripe={stripePromise}>
                    <CheckoutForm
                        plan={plan}
                        price={price}
                        interval={interval}
                        publishableKey={publishableKey}
                    />
                </Elements>
            ) : (
                <CheckoutForm
                    plan={plan}
                    price={price}
                    interval={interval}
                    publishableKey={publishableKey}
                />
            )}
        </>
    );
}
