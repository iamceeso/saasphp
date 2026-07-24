export class BillingRequestError extends Error {
    constructor(
        message: string,
        public readonly response?: Response,
        public readonly data?: unknown,
    ) {
        super(message);
        this.name = 'BillingRequestError';
    }
}

type BillingRequestOptions = Omit<RequestInit, 'body' | 'headers'> & {
    body?: unknown;
    headers?: HeadersInit;
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export async function billingJsonRequest<T>(url: string, options: BillingRequestOptions = {}): Promise<T> {
    const response = await fetch(url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-Token': csrfToken(),
            ...options.headers,
        },
        credentials: options.credentials ?? 'same-origin',
        body: options.body === undefined ? undefined : JSON.stringify(options.body),
    });

    const contentType = response.headers.get('content-type') || '';

    if (!contentType.includes('application/json')) {
        if (response.status === 401 || response.status === 403) {
            throw new BillingRequestError('Your session has expired or access is denied. Please sign in again and retry.', response);
        }

        if (response.status === 419) {
            throw new BillingRequestError('Your session token expired. Refresh the page and try again.', response);
        }

        throw new BillingRequestError('Unexpected server response. Please refresh the page and try again.', response);
    }

    const data = (await response.json()) as T & {
        error?: string;
        message?: string;
        errors?: Record<string, string[]>;
        success?: boolean;
    };

    if (!response.ok) {
        const backendError = data.error || data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : null);

        throw new BillingRequestError(backendError || 'Billing request failed.', response, data);
    }

    return data;
}
