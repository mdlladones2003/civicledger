import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { object, rows, text, date, hours } from '@/lib/app';

import { Status, Metric, MetricGroup, Panel, PageHeading, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function Reports({ props }: { props: Data }) {
    const summary = object(props.summary);
    const events = rows(props.events);
    const claims = object(summary.claims_by_status);
    const proofs = object(summary.proofs_by_status);

    return (
        <AppShell title="Service reports">
            <PageHeading eyebrow="Scoped reporting" title="Service summary" description="Summary totals include approved service only and are limited to your assigned scope." />
            <MetricGroup>
                <Metric label="Approved service hours" value={hours(summary.official_minutes)} />
                <Metric label="Completed attendance" value={text(summary.completed_attendance, '0')} />
                <Metric label="Pending claims" value={text(claims.pending, '0')} />
                <Metric label="Confirmed proofs" value={text(proofs.confirmed, '0')} />
            </MetricGroup>
            <Panel title="Event activity">
                {events.length === 0 ? <Empty>No event activity is available in your scope.</Empty> : (
                    <Table headers={['Event', 'Date', 'Status', 'Registered', 'Approved hours']}>
                        {events.map((event) => (
                            <TableRow key={text(event.id)}>
                                <Cell>{text(event.title)}</Cell>
                                <Cell>{date(event.starts_at)}</Cell>
                                <Cell><Status value={event.status} /></Cell>
                                <Cell>{text(event.registered, '0')}</Cell>
                                <Cell>{hours(event.approved_minutes)}</Cell>
                            </TableRow>
                        ))}
                    </Table>
                )}
            </Panel>
        </AppShell>
    );
}
