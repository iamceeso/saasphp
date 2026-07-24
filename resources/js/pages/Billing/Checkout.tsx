import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { billingJsonRequest } from '@/modules/billing/lib/request';
import { Head } from '@inertiajs/react';
import { CardElement, Elements, useElements, useStripe } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import React, { useMemo, useState } from 'react';

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
    publishableKey: string;
}

interface CheckoutFormProps {
    plan: Plan;
    price: Price;
    interval: string;
    publishableKey: string;
}

interface SubscribeResponse {
    success?: boolean;
    redirect?: string;
    error?: string;
    message?: string;
    errors?: Record<string, string[]>;
    requiresAction?: boolean;
    clientSecret?: string;
    paymentIntentStatus?: string;
}

function CheckoutForm({ plan, price, interval, publishableKey }: CheckoutFormProps) {
    const stripe = useStripe();
    const elements = useElements();
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [cardholderName, setCardholderName] = useState('');
    const isFreePlan = price.amount === 0;

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setIsLoading(true);
        setError(null);

        try {
            if (isFreePlan) {
                const data = await billingJsonRequest<SubscribeResponse>(route('subscribe'), {
                    method: 'POST',
                    body: {
                        plan_id: plan.id,
                        interval,
                    },
                });

                if (!data.success) {
                    setError(data.error || 'Unable to start the free plan.');
                    return;
                }

                if (!data.redirect) {
                    setError('Subscription completed, but no redirect was provided. Please refresh the page.');
                    return;
                }

                window.location.href = data.redirect;
                return;
            }

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

            const data = await billingJsonRequest<SubscribeResponse>(route('subscribe'), {
                method: 'POST',
                body: {
                    plan_id: plan.id,
                    interval: interval,
                    payment_method: paymentMethodResult.paymentMethod.id,
                },
            });

            if (data.success) {
                const requiresConfirmation =
                    Boolean(data.requiresAction) || ['requires_action', 'requires_confirmation'].includes(data.paymentIntentStatus ?? '');

                if (requiresConfirmation && data.clientSecret) {
                    const confirmation = await stripe.confirmCardPayment(data.clientSecret);

                    if (confirmation.error) {
                        setError(confirmation.error.message || 'Payment confirmation failed.');
                        return;
                    }

                    const paymentIntentStatus = confirmation.paymentIntent?.status;

                    if (paymentIntentStatus && !['succeeded', 'processing', 'requires_capture'].includes(paymentIntentStatus)) {
                        setError(`Payment requires attention. Current status: ${paymentIntentStatus}.`);
                        return;
                    }
                }

                if (!data.redirect) {
                    setError('Subscription completed, but no redirect was provided. Please refresh the page.');
                    return;
                }

                window.location.href = data.redirect;
            } else {
                const backendError = data.error || data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : null);

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
        <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 px-4 py-12">
            <div className="mx-auto max-w-2xl">
                <div className="mb-8">
                    <h1 className="mb-2 text-3xl font-bold text-gray-900">{isFreePlan ? 'Start Your Free Plan' : 'Complete Your Subscription'}</h1>
                    <p className="text-gray-600">{isFreePlan ? 'No card required for this plan.' : 'Secure payment powered by Stripe'}</p>
                </div>

                <div className="grid gap-8 md:grid-cols-3">
                    <div className="md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>{isFreePlan ? 'Free Activation' : 'Payment Method'}</CardTitle>
                                <CardDescription>
                                    {isFreePlan
                                        ? 'Confirm your free plan and activate it instantly.'
                                        : 'Enter your card details to complete the subscription'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    {error && (
                                        <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                                            <p className="text-red-800">{error}</p>
                                        </div>
                                    )}

                                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                        <div className="mb-2 flex justify-between">
                                            <span className="text-gray-600">{plan.name}</span>
                                            <span className="font-semibold">{formatPrice(price.amount)}</span>
                                        </div>
                                        <p className="text-sm text-gray-500">
                                            Billed {interval === 'monthly' ? 'monthly' : 'annually'}
                                            {price.trial_days > 0 && ` • ${price.trial_days} days free`}
                                        </p>
                                    </div>

                                    {!isFreePlan && (
                                        <>
                                            <div>
                                                <label className="mb-2 block text-sm font-medium text-gray-700">Cardholder Name</label>
                                                <input
                                                    type="text"
                                                    value={cardholderName}
                                                    onChange={(e) => setCardholderName(e.target.value)}
                                                    placeholder="John Doe"
                                                    className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                                                    required
                                                />
                                            </div>

                                            <div>
                                                <label className="mb-2 block text-sm font-medium text-gray-700">Card Details</label>
                                                <div className="w-full rounded-lg border border-gray-300 px-4 py-3 focus-within:border-transparent focus-within:ring-2 focus-within:ring-blue-500">
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
                                                <p className="mt-1 text-xs text-gray-500">Test: 4242 4242 4242 4242</p>
                                            </div>
                                        </>
                                    )}

                                    <Button type="submit" disabled={isLoading || (!isFreePlan && (!stripe || !elements))} className="w-full">
                                        {isLoading
                                            ? isFreePlan
                                                ? 'Starting...'
                                                : 'Processing...'
                                            : isFreePlan
                                              ? 'Start Free Plan'
                                              : `Subscribe - ${formatPrice(price.amount)}/${interval === 'monthly' ? 'mo' : 'yr'}`}
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
                                    <h3 className="mb-2 font-semibold">{plan.name}</h3>
                                    <p className="text-sm text-gray-600">{plan.description}</p>
                                </div>

                                <div className="space-y-2 border-t border-gray-200 pt-4">
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
                                    <p className="mt-2 text-xs text-gray-500">Billed {interval === 'monthly' ? 'monthly' : 'annually'}</p>
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
    const stripePromise = useMemo(() => (publishableKey ? loadStripe(publishableKey) : null), [publishableKey]);

    return (
        <>
            <Head title="Checkout" />
            {stripePromise ? (
                <Elements stripe={stripePromise}>
                    <CheckoutForm plan={plan} price={price} interval={interval} publishableKey={publishableKey} />
                </Elements>
            ) : (
                <CheckoutForm plan={plan} price={price} interval={interval} publishableKey={publishableKey} />
            )}
        </>
    );
}
