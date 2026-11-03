import React, { useState, type ChangeEventHandler, type ReactNode } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft, Eye, EyeOff } from 'lucide-react';

import { BrandMark } from '@/components/app/BrandMark';

import { ThemeToggle } from '@/components/app/ThemeToggle';

import type { SharedProps } from '@/types/app';

export function AuthLayout({ title, children, errors: formErrors, showErrorSummary = true }: {
    title: string;
    children: ReactNode;
    errors?: Record<string, string>;
    showErrorSummary?: boolean;
}) {
    const { errors: pageErrors } = usePage<SharedProps>().props;
    const errors = formErrors ?? pageErrors;

    return (
        <div className="grid min-h-screen lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <Head title={title} />
            <aside className="hidden min-h-screen grid-rows-[auto_1fr] gap-12 bg-sidebar p-10 text-sidebar-foreground lg:grid xl:p-14">
                <Link className="flex w-fit items-center gap-3 text-lg font-semibold" href="/">
                    <BrandMark />CivicLedger
                </Link>
                <div className="grid w-full gap-4 self-center text-left">
                    <h2 className="max-w-lg text-4xl font-semibold tracking-tight xl:text-5xl">Service recorded. Impact trusted.</h2>
                    <p className="max-w-[42ch] leading-7 opacity-85">Use CivicLedger to register for events, record attendance, review service hours, and verify issued certificates.</p>
                </div>
            </aside>
            <main className="flex items-center justify-center px-4 py-10 sm:px-8">
                <div className="grid w-full max-w-md gap-6">
                    <div>
                        <div className="mb-6 flex items-center justify-between gap-3">
                            <Link className={buttonVariants({ variant: 'ghost', size: 'sm' })} href="/">
                                <ArrowLeft /> Back to home
                            </Link>
                            <ThemeToggle />
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                    </div>
                    {showErrorSummary && Object.keys(errors ?? {}).length > 0 && (
                        <Alert variant="destructive">
                            <AlertTitle>Please correct the highlighted fields</AlertTitle>
                            <AlertDescription>
                                <ul className="grid gap-1">
                                    {Object.entries(errors ?? {}).map(([field, message]) => (
                                        <li key={field}>
                                            <a className="underline underline-offset-2" href={`#${field}`}>
                                                {message}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    )}
                    {children}
                </div>
            </main>
        </div>
    );
}

export function AuthField({ label, name, type = 'text', autoComplete, required = true, minLength, value, onChange, error: fieldError }: {
    label: string;
    name: string;
    type?: string;
    autoComplete?: string;
    required?: boolean;
    minLength?: number;
    value?: string;
    onChange?: ChangeEventHandler<HTMLInputElement>;
    error?: string | null;
}) {
    const pageError = usePage<SharedProps>().props.errors?.[name];
    const error = fieldError === undefined ? pageError : fieldError;
    const errorId = `${name}-error`;
    const [passwordVisible, setPasswordVisible] = useState(false);
    const isPassword = type === 'password';

    return (
        <div className="grid gap-2">
            <Label htmlFor={name} className="text-sm font-medium text-foreground">{label}</Label>
            <div className="relative">
                <Input
                    id={name}
                    name={name}
                    type={isPassword && passwordVisible ? 'text' : type}
                    autoComplete={autoComplete}
                    required={required}
                    minLength={minLength}
                    value={value}
                    onChange={onChange}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? errorId : undefined}
                    className={`h-10 rounded-lg px-3 text-sm font-normal ${isPassword ? 'pr-11' : ''}`}
                />
                {isPassword && (
                    <button
                        type="button"
                        onClick={() => setPasswordVisible((visible) => !visible)}
                        aria-label={passwordVisible ? 'Hide password' : 'Show password'}
                        aria-pressed={passwordVisible}
                        aria-controls={name}
                        className="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {passwordVisible ? <EyeOff className="size-4" aria-hidden="true" /> : <Eye className="size-4" aria-hidden="true" />}
                    </button>
                )}
            </div>
            {error && <p id={errorId} className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
