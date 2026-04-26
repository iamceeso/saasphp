import InputError from '@/components/input-error';
import SocialLogins from '@/components/social-logins';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEventHandler } from 'react';

interface SocialIds {
    google_id: string;
    microsoft_id: string;
    yahoo_id: string;
    github_id: string;
    twitter_id: string;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    social_ids: SocialIds;
}

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const { status, canResetPassword } = usePage().props as unknown as LoginProps;
    const { twoFactorEnabled, social_ids } = usePage().props as unknown as { twoFactorEnabled: boolean; social_ids: SocialIds };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Log in to your account" description="Enter your email/phone and password below to log in">
            <Head title="Log in" />
            
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password">Email address / Phone number</Label>
                            {canResetPassword && (
                                <TextLink href={route('magic.login')} className="ml-auto text-sm" tabIndex={5}>
                                    Magic link login?
                                </TextLink>
                            )}
                        </div>
                        <Input
                            id="login"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="username"
                            value={data.login}
                            onChange={(e) => setData('login', e.target.value)}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.login} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password">Password</Label>
                            {canResetPassword && (
                                <TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={5}>
                                    Forgot password?
                                </TextLink>
                            )}
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="Password"
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" tabIndex={3} />
                            <Label htmlFor="remember">Remember me</Label>
                        </div>
                        <div className="text-muted-foreground text-center text-sm">
                            Don't have an account?{' '}
                            <TextLink href={route('register')} tabIndex={5}>
                                Sign up
                            </TextLink>
                        </div>
                    </div>

                    <Button type="submit" className="mt-4 w-full" tabIndex={4} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Log in
                    </Button>
                </div>
                {twoFactorEnabled && (
                    <SocialLogins
                        google_id={social_ids.google_id}
                        microsoft_id={social_ids.microsoft_id}
                        yahoo_id={social_ids.yahoo_id}
                        github_id={social_ids.github_id}
                        twitter_id={social_ids.twitter_id}
                    />
                )}
            </form>

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
