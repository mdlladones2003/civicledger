import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { csrfToken } from '@/components/app/Primitives';
import { AuthLayout, AuthField } from '@/components/auth/AuthLayout';
import { object, rows, text } from '@/lib/app';
import type { Data, SharedProps } from '@/types/app';

export default function Register({ props }: { props: Data }) {
    const { errors } = usePage<SharedProps>().props;
    const sections = rows(props.sections);

    return (
        <AuthLayout title="Student registration">
            <p className="text-sm leading-6 text-muted-foreground">
                Select your program section. This page creates student accounts only; coordinator and signer accounts are created by an administrator.
            </p>
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
                <div className="grid gap-2">
                    <Label htmlFor="section_id">Program section</Label>
                    <select
                        id="section_id"
                        name="section_id"
                        required
                        defaultValue=""
                        aria-invalid={Boolean(errors?.section_id)}
                        className="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option value="" disabled>Select your section</option>
                        {sections.map((section) => (
                            <option key={text(section.id)} value={text(section.id)}>
                                {text(object(section.program).code)} · {text(section.code)} · {text(object(section.term).code)}
                            </option>
                        ))}
                    </select>
                    {errors?.section_id && (
                        <p className="text-sm text-destructive">{errors.section_id}</p>
                    )}
                    {sections.length === 0 && (
                        <p className="text-sm text-muted-foreground">No sections are available yet. Contact your coordinator.</p>
                    )}
                </div>
                <Button type="submit" size="lg" className="w-full">Create student account</Button>
            </form>
            <p className="text-sm text-muted-foreground">
                Already registered? <Link href="/login" className="text-link underline underline-offset-4">Sign in</Link>
            </p>
        </AuthLayout>
    );
}
