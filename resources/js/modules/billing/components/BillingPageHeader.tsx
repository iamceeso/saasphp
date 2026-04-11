interface BillingPageHeaderProps {
    title: string;
    description: string;
}

export function BillingPageHeader({ title, description }: BillingPageHeaderProps) {
    return (
        <div>
            <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
            <p className="text-muted-foreground mt-1 text-sm">{description}</p>
        </div>
    );
}
