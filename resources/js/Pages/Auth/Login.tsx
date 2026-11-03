import { useForm, usePage } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { AuthLayout, AuthField } from '@/components/auth/AuthLayout';
import type { SharedProps } from '@/types/app';

export default function Login() {
    const { errors: pageErrors } = usePage<SharedProps>().props;
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        if (pageErrors && Object.keys(pageErrors).length > 0) {
            form.setError(pageErrors);
        }
    }, [pageErrors]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/login', {
            onError: () => form.reset('password'),
        });
    }

    return (
        <AuthLayout title="Sign in" errors={form.errors} showErrorSummary={false}>
            <form onSubmit={submit} className="grid gap-4">
                <AuthField
                    label="Email address"
                    name="email"
                    type="email"
                    autoComplete="email"
                    value={form.data.email}
                    error={form.errors.email ?? null}
                    onChange={(event) => {
                        form.setData('email', event.target.value);
                        form.clearErrors();
                    }}
                />
                <AuthField
                    label="Password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    value={form.data.password}
                    error={form.errors.password ?? null}
                    onChange={(event) => {
                        form.setData('password', event.target.value);
                        form.clearErrors();
                    }}
                />
                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={form.data.remember}
                        onChange={(event) => form.setData('remember', event.target.checked)}
                        className="size-4 rounded border-input accent-primary"
                    />
                    Remember me
                </label>
                <Button type="submit" size="lg" className="w-full" disabled={form.processing}>
                    {form.processing ? 'Signing in…' : 'Sign in'}
                </Button>
            </form>
            <p className="text-sm text-muted-foreground">
                Need an account? <Link href="/register" className="text-link underline underline-offset-4">Register</Link>
            </p>
        </AuthLayout>
    );
}
