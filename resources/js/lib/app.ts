import type { Data } from '@/types/app';

export function object(value: unknown): Data {
    return value !== null && typeof value === 'object' ? value as Data : {};
}

export function dateTimeLocal(value: unknown): string | undefined {
    if (typeof value !== 'string') {
        return undefined;
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return undefined;
    }

    return new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

/** Converts a datetime-local value (browser time) to an unambiguous UTC timestamp for the server. */
export function isoFromLocal(value: string): string | null {
    const parsed = new Date(value);

    return value === '' || Number.isNaN(parsed.getTime()) ? null : parsed.toISOString();
}

export function rows(value: unknown): Data[] {
    const data = Array.isArray(value) ? value : object(value).data;

    return Array.isArray(data) ? data.map(object) : [];
}

export function text(value: unknown, fallback = '—'): string {
    if (typeof value === 'string' || typeof value === 'number') {
        return String(value);
    }

    return fallback;
}

export function displayEventTitle(value: unknown): string {
    return text(value, 'Service event').replace(/\s+\(Demo\)$/i, '');
}

export function date(value: unknown, options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' }): string {
    if (typeof value !== 'string' && typeof value !== 'number') {
        return '—';
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, options).format(parsed);
}

export function hours(value: unknown): string {
    const minutes = Number(value);

    return Number.isFinite(minutes) ? `${(minutes / 60).toFixed(1)} hrs` : '0.0 hrs';
}

export function person(value: unknown): string {
    const user = object(value);

    return [user.first_name, user.middle_name, user.last_name]
        .filter((part): part is string => typeof part === 'string' && part.length > 0)
        .join(' ') || text(user.name);
}

export function statusLabel(value: unknown): string {
    const label = text(value).replaceAll('_', ' ').replaceAll('-', ' ');

    return label.charAt(0).toUpperCase() + label.slice(1);
}

export function statusClass(value: unknown): string {
    const status = text(value).toLowerCase().replaceAll('_', ' ').replaceAll('-', ' ');

    if (['active', 'approved', 'confirmed', 'current', 'verified', 'valid'].includes(status)) {
        return 'border-status-success-border bg-status-success text-status-success-foreground';
    }

    if (['pending', 'submitted', 'revision requested', 'unavailable'].includes(status)) {
        return 'border-status-pending-border bg-status-pending text-status-pending-foreground';
    }

    if (['rejected', 'failed', 'mismatch', 'revoked', 'blocked', 'suspended'].includes(status)) {
        return 'border-status-danger-border bg-status-danger text-status-danger-foreground';
    }

    return 'border-status-neutral-border bg-status-neutral text-status-neutral-foreground';
}

export function statusIconClass(value: unknown): string {
    const status = text(value).toLowerCase().replaceAll('_', ' ').replaceAll('-', ' ');

    if (['active', 'approved', 'confirmed', 'current', 'verified', 'valid'].includes(status)) {
        return 'text-status-success-border';
    }

    if (['pending', 'submitted', 'revision requested', 'unavailable'].includes(status)) {
        return 'text-status-pending-border';
    }

    if (['rejected', 'failed', 'mismatch', 'revoked', 'blocked', 'suspended'].includes(status)) {
        return 'text-status-danger-border';
    }

    return 'text-status-neutral-border';
}
