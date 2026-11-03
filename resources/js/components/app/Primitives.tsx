import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { AlertCircle, Check, Circle, Clock3, FileQuestion, RefreshCw, Send, ShieldCheck, ShieldX, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { dateTimeLocal, text, statusLabel, statusClass, statusIconClass } from '@/lib/app';

export function Status({ value }: { value: unknown }) {
    const status = text(value).toLowerCase().replaceAll('_', ' ').replaceAll('-', ' ');
    const Icon = ['approved', 'confirmed', 'current', 'valid'].includes(status)
        ? Check
        : status === 'verified'
            ? ShieldCheck
            : ['pending', 'unavailable'].includes(status)
            ? Clock3
            : ['submitted'].includes(status)
                ? Send
                : ['queued', 'superseded', 'draft'].includes(status)
                    ? Circle
                    : ['revision requested'].includes(status)
                        ? RefreshCw
                        : ['rejected', 'failed', 'mismatch', 'revoked', 'blocked'].includes(status)
                            ? ['mismatch', 'revoked'].includes(status) ? ShieldX : X
                            : status === 'not found'
                                ? FileQuestion
                                        : AlertCircle;

    return (
        <Badge aria-label={statusLabel(value)} className={`h-auto min-h-7 gap-1.5 border px-2.5 py-1 ${statusClass(value)}`}>
            <Icon className={`size-3.5 shrink-0 ${statusIconClass(value)}`} aria-hidden="true" />
            {statusLabel(value)}
        </Badge>
    );
}

export function MetricGroup({ children, columns = 4 }: { children: ReactNode; columns?: 3 | 4 }) {
    return (
        <div className={`grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-border bg-border ${columns === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-4'}`}>
            {children}
        </div>
    );
}

export function Metric({ label, value, detail }: { label: string; value: ReactNode; detail?: string }) {
    return (
        <div className="min-w-0 bg-card p-4 sm:p-5 [&:last-child:nth-child(odd)]:col-span-2 sm:[&:last-child:nth-child(odd)]:col-span-1">
            <div className="grid gap-1">
                <p className="text-sm font-medium text-muted-foreground">{label}</p>
                <p className="text-2xl font-medium tabular-nums tracking-tight text-foreground">{value}</p>
                {detail && <p className="text-xs leading-5 text-muted-foreground">{detail}</p>}
            </div>
        </div>
    );
}

export function Panel({ title, children, action }: { title: string; children: ReactNode; action?: ReactNode }) {
    return (
        <Card>
            <CardHeader className="grid-cols-[1fr_auto] items-center border-b border-border py-4">
                <CardTitle className="font-semibold text-foreground">{title}</CardTitle>
                {action}
            </CardHeader>
            <CardContent className="p-5">{children}</CardContent>
        </Card>
    );
}

export function PageHeading({ eyebrow, title, description, action }: {
    eyebrow?: string;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div className="space-y-1">
                {eyebrow && <p className="text-sm font-medium text-muted-foreground">{eyebrow}</p>}
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
                {description && <p className="max-w-2xl text-sm leading-6 text-muted-foreground">{description}</p>}
            </div>
            {action}
        </div>
    );
}

export function ButtonLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Link href={href} className={buttonVariants({ size: 'lg', className: 'min-h-11' })}>
            {children}
        </Link>
    );
}

export function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function ActionForm({ action, children, button = 'Submit', method = 'post' }: {
    action: string;
    children?: ReactNode;
    button?: string;
    method?: 'post' | 'delete' | 'put' | 'patch';
}) {
    const token = csrfToken();

    return (
        <form action={action} method="post" className="grid gap-3">
            <input type="hidden" name="_token" value={token} />
            {method !== 'post' && <input type="hidden" name="_method" value={method} />}
            {children}
            <Button type="submit" size="lg" className="min-h-11 w-fit">
                {button}
            </Button>
        </form>
    );
}

export function Field({ label, name, type = 'text', value, required = false }: {
    label: string;
    name: string;
    type?: string;
    value?: unknown;
    required?: boolean;
}) {
    return (
        <Label className="grid gap-1.5 text-sm font-medium text-foreground">
            <span>{label}</span>
            <Input
                name={name}
                type={type}
                defaultValue={type === 'datetime-local' ? dateTimeLocal(value) : typeof value === 'string' ? value : undefined}
                required={required}
                className="h-10 rounded-lg px-3 text-sm font-normal"
            />
        </Label>
    );
}

export function Table({ headers, children }: { headers: string[]; children: ReactNode }) {
    return (
        <div className="w-full overflow-x-auto">
            <ShadcnTable className="min-w-[640px] md:min-w-0">
                <TableHeader>
                    <TableRow>
                        {headers.map((header) => <TableHead key={header} className="px-3 py-3 text-xs font-normal text-muted-foreground">{header}</TableHead>)}
                    </TableRow>
                </TableHeader>
                <TableBody>{children}</TableBody>
            </ShadcnTable>
        </div>
    );
}

export function Cell({ children }: { children: ReactNode }) {
    return <TableCell className="px-3 py-3.5 align-middle text-foreground">{children}</TableCell>;
}

export function Empty({ children = 'Nothing to show yet.' }: { children?: string }) {
    return <p className="rounded-xl bg-muted px-4 py-8 text-center text-sm text-muted-foreground">{children}</p>;
}
