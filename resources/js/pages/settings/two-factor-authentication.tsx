import HeadingSmall from '@/components/heading-small';
import ImpersonatorNotice from '@/components/impersonator-notice';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { FormEventHandler } from 'react';

interface TwoFactorAuthenticationProps extends Record<string, unknown> {
    twoFactorQRCode?: string;
    twoFactorSecret?: string;
    twoFactorRecoveryCodes?: string[];
    twoFactorConfirmation?: boolean;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two Factor Authentication',
        href: '/settings/security',
    },
];

export default function TwoFactorAuthentication() {
    const { data, setData, post, delete: del, processing, reset } = useForm({ code: '' });
    const { errors: { confirmTwoFactorAuthentication = {} } = {} } = usePage().props as {
        errors?: {
            confirmTwoFactorAuthentication?: {
                code?: string;
            };
        };
    };

    const { twoFactorSecret, twoFactorQRCode, twoFactorRecoveryCodes, twoFactorConfirmation } = usePage<TwoFactorAuthenticationProps>().props;
    const twoFactorQRCodeSource =
        typeof twoFactorQRCode === 'string' && twoFactorQRCode.length > 0 ? `data:image/svg+xml;utf8,${encodeURIComponent(twoFactorQRCode)}` : null;

    const twoFactorEnable: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('two-factor.enable'), {
            preserveScroll: true,
            onFinish: () => reset('code'),
        });
    };

    const twoFactorConfirm: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => {
                console.log('2FA confirmed');
            },
            onFinish: () => reset('code'),
        });
    };

    const twoFactorDisable: FormEventHandler = (e) => {
        e.preventDefault();

        del(route('two-factor.disable'), {
            preserveScroll: true,
            onError: (errors) => {
                console.log('Unable to disable 2FA', errors);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ImpersonatorNotice/>
            <SettingsLayout>
                <div className="space-y-6">
                    {twoFactorSecret && !twoFactorConfirmation && twoFactorQRCodeSource && (
                        <img src={twoFactorQRCodeSource} alt="Two-factor authentication QR code" className="h-48 w-48 rounded bg-white p-2" />
                    )}

                    <HeadingSmall title="2FA Details" description="Enable and disable two-factor authentication" />

                    {twoFactorSecret ? (
                        <>
                            {!twoFactorConfirmation ? (
                                // Show confirmation step
                                <form onSubmit={twoFactorConfirm} className="space-y-4">
                                    <p className="text-sm text-gray-400">
                                        Please enter the verification code from your authenticator app to complete setup.
                                    </p>
                                    <input
                                        type="text"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        className="block w-full rounded border-gray-300 p-2 shadow-sm focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                                        placeholder="Enter 6-digit code"
                                        maxLength={6}
                                    />
                                    <InputError message={confirmTwoFactorAuthentication.code} />
                                    <Button type="submit" disabled={processing}>
                                        Confirm 2FA
                                    </Button>
                                </form>
                            ) : (
                                // Show Disable 2FA option if confirmed
                                <form onSubmit={twoFactorDisable} className="space-y-6">
                                    <div className="flex items-center gap-4">
                                        <InputError message={confirmTwoFactorAuthentication.code} />
                                        <Button className="cursor-pointer bg-red-500 hover:bg-red-400" disabled={processing}>
                                            Disable 2FA
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </>
                    ) : (
                        <form onSubmit={twoFactorEnable} className="space-y-6">
                            <div className="flex items-center gap-4">
                                <Button className="cursor-pointer hover:bg-gray-500" disabled={processing}>
                                    Enable 2FA
                                </Button>
                            </div>
                            <InputError message={confirmTwoFactorAuthentication.code} />
                        </form>
                    )}

                    {twoFactorSecret && typeof twoFactorQRCode === 'string' && !twoFactorConfirmation && twoFactorRecoveryCodes && (
                        <div className="w-72 rounded-lg bg-white p-6 shadow-lg dark:bg-gray-800">
                            <h2 className="mb-2 text-lg font-bold text-gray-700 dark:text-gray-300">Recovery Codes</h2>
                            <p className="mb-2 text-xs text-red-400">
                                These codes will disapppear after confirmation. Please save them in a secure location.
                            </p>

                            <div className="rounded bg-gray-100 p-2 font-mono text-sm leading-6 dark:bg-gray-800">
                                <ul>
                                    {twoFactorRecoveryCodes.map((code) => (
                                        <li key={code}>{code}</li>
                                    ))}
                                </ul>
                                <button
                                    onClick={() => {
                                        const codesToCopy = twoFactorRecoveryCodes.join('\n');
                                        navigator.clipboard
                                            .writeText(codesToCopy)
                                            .then(() => {})
                                            .catch(() => {});
                                    }}
                                    className="mb-4 flex items-center py-2 text-xs text-blue-400 hover:cursor-pointer hover:underline"
                                >
                                    <Copy size={12} className="mr-1" /> Copy Recovery Codes
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
