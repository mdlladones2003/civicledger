import { object, text, hours, statusLabel } from '@/lib/app';

import { Metric, MetricGroup, Panel, PageHeading, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function Dashboard({ coordinator, signer, admin, summary }: { coordinator?: boolean; signer?: boolean; admin?: boolean; summary: Data }) {
    if (admin) {
        return (
            <AppShell title="Administrator overview">
                <PageHeading eyebrow="Administration" title="Dashboard" description="A system-wide view of CivicLedger accounts, events, and certificates." />
                <MetricGroup columns={3}>
                    <Metric label="Accounts" value={text(summary.users, '0')} />
                    <Metric label="Events" value={text(summary.events, '0')} />
                    <Metric label="Certificates" value={text(summary.certificates, '0')} />
                </MetricGroup>
            </AppShell>
        );
    }

    if (signer) {
        return (
            <AppShell title="Signer overview">
                <PageHeading eyebrow="Certificate signing" title="Dashboard" description="A snapshot of events and certificate activity in CivicLedger." />
                <MetricGroup columns={3}>
                    <Metric label="Events" value={text(summary.events, '0')} />
                    <Metric label="Eligible students" value={text(summary.eligible_candidates, '0')} />
                    <Metric label="Issued certificates" value={text(summary.issued_certificates, '0')} />
                </MetricGroup>
            </AppShell>
        );
    }

    if (coordinator) {
        const events = object(summary.events_by_status);
        const claims = object(summary.claims_by_status);
        const proofs = object(summary.proofs_by_status);
        const totalEvents = Object.values(events).reduce<number>((total, count) => total + Number(count || 0), 0);

        return (
            <AppShell title="Coordinator overview">
                <PageHeading eyebrow="Coordinator workspace" title="Overview" description="A scoped snapshot of your events, hour approvals, and proof processing." />
                <MetricGroup>
                    <Metric label="Managed events" value={totalEvents} />
                    <Metric label="Completed check-ins" value={text(summary.completed_attendance, '0')} />
                    <Metric label="Hours awaiting review" value={text(claims.pending, '0')} />
                    <Metric label="Approved service hours" value={hours(summary.official_minutes)} />
                </MetricGroup>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel title="Event status">
                        <div className="grid gap-3">
                            {Object.entries(events).length === 0 ? <Empty /> : Object.entries(events).map(([label, value]) => (
                                <div key={label} className="flex items-center justify-between border-b border-border py-2 last:border-b-0">
                                    <span className="text-sm text-muted-foreground">{statusLabel(label)}</span>
                                    <span className="text-sm font-medium tabular-nums">{text(value, '0')}</span>
                                </div>
                            ))}
                        </div>
                    </Panel>
                    <Panel title="Proof status">
                        <div className="grid gap-3">
                            {Object.entries(proofs).length === 0 ? <Empty /> : Object.entries(proofs).map(([label, value]) => (
                                <div key={label} className="flex items-center justify-between border-b border-border py-2 last:border-b-0">
                                    <span className="text-sm text-muted-foreground">{statusLabel(label)}</span>
                                    <span className="text-sm font-medium tabular-nums">{text(value, '0')}</span>
                                </div>
                            ))}
                        </div>
                    </Panel>
                </div>
            </AppShell>
        );
    }

    const claims = object(summary.claims);

    return (
        <AppShell title="Student overview">
            <PageHeading eyebrow="Your service" title="Overview" description="Follow your upcoming opportunities, approved hours, and certificate status." />
            <MetricGroup>
                <Metric label="Approved service hours" value={hours(summary.official_minutes)} detail="Only approved time counts toward your total." />
                <Metric label="Registered events" value={text(summary.registered_events, '0')} />
                <Metric label="Active certificates" value={text(summary.active_certificates, '0')} />
                <Metric label="Claims to follow up" value={text(claims.pending, '0')} detail="Pending review" />
            </MetricGroup>
        </AppShell>
    );
}
