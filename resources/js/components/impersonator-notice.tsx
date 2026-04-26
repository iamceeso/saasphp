import { usePage } from '@inertiajs/react';

type Impersonator = {
    name: string;
    email: string;
};

export default function ImpersonatorNotice() {
    const { props } = usePage<{
        isImpersonating?: boolean;
        impersonator?: Impersonator;
    }>();

    const isImpersonating = props.isImpersonating;
    const impersonator = props.impersonator;

    if (!isImpersonating || !impersonator) return null;

    return (
        <div className="mb-4 rounded-lg bg-yellow-100 p-4 text-sm text-yellow-800 shadow-sm dark:bg-yellow-900 dark:text-yellow-100">
            <strong>Impersonating:</strong> {impersonator.name} ({impersonator.email}) {' '}
            <a href="/impersonate/leave" className="underline hover:text-yellow-900 dark:hover:text-yellow-300">
                Leave impersonation
            </a>
        </div>
    );
}
