interface PlanFeature {
    id: number;
    feature_name: string;
    description: string | null;
    value?: string | null;
}

export function PlanFeatureList({ features }: { features: PlanFeature[] }) {
    return (
        <ul className="space-y-3">
            {features.map((feature) => (
                <li key={feature.id} className="flex items-start gap-3">
                    <svg
                        className="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                    <div>
                        <p className="text-sm font-medium text-gray-900">
                            {feature.feature_name}
                            {feature.value && (
                                <span className="ml-2 text-xs font-semibold text-blue-700">
                                    {feature.value}
                                </span>
                            )}
                        </p>
                        {feature.description && (
                            <p className="text-xs text-gray-500">{feature.description}</p>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}
