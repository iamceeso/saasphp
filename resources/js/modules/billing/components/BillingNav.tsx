import { Link, usePage } from '@inertiajs/react';
import { CreditCard, ReceiptText } from 'lucide-react';
import { SharedData } from '@/types';

const links = [
    {
        title: 'Pricing',
        href: '/billing/pricing',
        icon: CreditCard,
        key: 'show_pricing' as const,
    },
    {
        title: 'Subscriptions',
        href: '/subscriptions',
        icon: ReceiptText,
        key: 'show_subscriptions' as const,
    },
];

export function BillingNav() {
    const page = usePage<SharedData>();
    const billing = page.props.modules.billing;

    if (!billing.enabled || !billing.navigation.enabled) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            {links
                .filter((link) => billing.navigation[link.key])
                .map((link) => {
                    const isActive = page.url === link.href || page.url.startsWith(`${link.href}/`);
                    const Icon = link.icon;

                    return (
                        <Link
                            key={link.href}
                            href={link.href}
                            className={`inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition ${
                                isActive
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-border bg-background hover:border-foreground/20 hover:bg-muted'
                            }`}
                        >
                            <Icon className="h-4 w-4" />
                            <span>{link.title}</span>
                        </Link>
                    );
                })}
        </div>
    );
}
