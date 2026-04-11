import { Badge } from '@/components/ui/badge';

const statusMap: Record<string, string> = {
    active: 'bg-green-100 text-green-800',
    trialing: 'bg-blue-100 text-blue-800',
    past_due: 'bg-yellow-100 text-yellow-800',
    canceled: 'bg-red-100 text-red-800',
    unpaid: 'bg-red-100 text-red-800',
    paid: 'bg-green-100 text-green-800',
    open: 'bg-yellow-100 text-yellow-800',
    uncollectible: 'bg-red-100 text-red-800',
    draft: 'bg-gray-100 text-gray-800',
    void: 'bg-gray-100 text-gray-800',
};

export function BillingStatusBadge({ status }: { status: string }) {
    const classes = statusMap[status] ?? 'bg-gray-100 text-gray-800';

    return (
        <Badge className={classes}>
            {status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ')}
        </Badge>
    );
}
