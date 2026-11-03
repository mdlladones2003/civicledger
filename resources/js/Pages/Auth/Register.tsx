import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { csrfToken } from '@/components/app/Primitives';
import { AuthLayout, AuthField } from '@/components/auth/AuthLayout';

export default function Register() {
    return (
        <AuthLayout title="Create an account">
            <form action="/register" method="post" className="grid gap-4">
                <input type="hidden" name="_token" value={csrfToken()} />
                <div className="grid gap-4 sm:grid-cols-2">
                    <AuthField label="First name" name="first_name" autoComplete="given-name" />
                    <AuthField label="Middle name (optional)" name="middle_name" autoComplete="additional-name" required={false} />
                    <AuthField label="Last name" name="last_name" autoComplete="family-name" />
                    <AuthField label="Email address" name="email" type="email" autoComplete="email" />
                    <AuthField label="Password" name="password" type="password" autoComplete="new-password" minLength={12} />
                    <AuthField label="Confirm password" name="password_confirmation" type="password" autoComplete="new-password" minLength={12} />
                </div>
                <Button type="submit" size="lg" className="w-full">Create account</Button>
            </form>
            <p className="text-sm text-muted-foreground">
                Already registered? <Link href="/login" className="text-link underline underline-offset-4">Sign in</Link>
            </p>
        </AuthLayout>
    );
}
