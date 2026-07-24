// Components
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

export default function MagicConfirm({ email, code }: { email: string; code: string }) {
    const { data, post, processing, errors } = useForm({
        email,
        code,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('magic.confirm'));
    };

    return (
        <AuthLayout title="Confirm login" description="Confirm you'd like to sign in as this account">
            <Head title="Confirm login" />

            <div className="space-y-6">
                <form onSubmit={submit}>
                    <p className="text-muted-foreground text-sm">
                        Signing in as <span className="text-foreground font-medium">{data.email}</span>.
                    </p>

                    <InputError message={errors.code || errors.email} className="mt-2" />

                    <div className="my-6 flex items-center justify-start">
                        <Button className="w-full" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Confirm login
                        </Button>
                    </div>
                </form>

                <div className="text-muted-foreground space-x-1 text-center text-sm">
                    <span>Not you?</span>
                    <TextLink href={route('login')}>Go back</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
