import { ShieldCheck } from 'lucide-react';

export function BrandMark({ variant = 'default' }: { variant?: 'default' | 'sidebar' }) {
    return (
        <span
            className={`flex size-9 items-center justify-center rounded-[10px] ${variant === 'sidebar' ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'bg-background text-foreground'}`}
            aria-hidden="true"
        >
            <ShieldCheck className="size-5" />
        </span>
    );
}
