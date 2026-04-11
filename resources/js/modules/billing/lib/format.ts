export function formatBillingPrice(amount: number, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency.toUpperCase(),
    }).format(amount / 100);
}

export function formatBillingDate(value: string | number) {
    const date = typeof value === 'number' ? new Date(value * 1000) : new Date(value);

    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(date);
}
