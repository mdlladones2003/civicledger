import { Button } from '@/components/ui/button';
import { AuthField, AuthLayout } from '@/components/auth/AuthLayout';
import { csrfToken } from '@/components/app/Primitives';

export default function ChangePassword() {
    return (
        <AuthLayout title="Choose a new password">
            <p className="text-sm leading-6 text-muted-foreground">
                Your administrator created this account. Choose a new password to continue.
            </p>
            <form action="/password/change" method="post" className="grid gap-4">
                <input type="hidden" name="_token" value={csrfToken()} />
                <input type="hidden" name="_method" value="put" />
                <AuthField label="New password" name="password" type="password" autoComplete="new-password" minLength={12} />
                <AuthField label="Confirm new password" name="password_confirmation" type="password" autoComplete="new-password" minLength={12} />
                <Button type="submit" size="lg" className="w-full">Update password</Button>
            </form>
        </AuthLayout>
    );
}
