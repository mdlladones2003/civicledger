import type { ReactNode } from 'react';
import { AlertCircle, CheckCircle2, Clock3, Info } from 'lucide-react';
import { cn } from 'cn';

type ToastVariant = 'success' | 'pending' | 'danger' | 'neutral';

const toastStyles: Record<ToastVariant, string> = {
    success: 'border-status-success-border bg-status-success text-status-success-foreground',
    pending: 'border-status-pending-border bg-status-pending text-status-pending-foreground',
    danger: 'border-status-danger-border bg-status-danger text-status-danger-foreground',
    neutral: 'border-status-neutral-border bg-status-neutral text-status-neutral-foreground',
};

const toastIcons = {
    success: CheckCircle2,
    pending: Clock3,
    danger: AlertCircle,
    neutral: Info,
};

export function Toast({
    title,
    children,
    variant = 'neutral',
    className,
}: {
    title: string;
    children: ReactNode;
    variant?: ToastVariant;
    className?: string;
}) {
    const Icon = toastIcons[variant];

    return (
        <div
            data-slot="toast"
            role={variant === 'danger' ? 'alert' : 'status'}
            aria-live={variant === 'danger' ? 'assertive' : 'polite'}
            className={cn('grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-[10px] border p-4 text-sm', toastStyles[variant], className)}
        >
            <Icon className="row-span-2 mt-0.5 size-4" aria-hidden="true" />
            <p>{title}</p>
            <div className="text-sm leading-5">{children}</div>
        </div>
    );
}
