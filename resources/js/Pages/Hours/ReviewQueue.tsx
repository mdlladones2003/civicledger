import { Link } from '@inertiajs/react';

import { buttonVariants } from '@/components/ui/button';
import { TableRow } from '@/components/ui/table';

import { object, rows, text, date, person } from '@/lib/app';

import { Status, Panel, PageHeading, PageNav, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

const statusFilters = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'revision_requested', label: 'Revision requested' },
];

export default function ReviewQueue({ props }: { props: Data }) {
    const records = rows(props.records);
    const currentStatus = text(props.status, 'pending');

    return (
        <AppShell title="Hours review">
            <PageHeading eyebrow="Coordinator workspace" title="Hours review" description="Review submitted service records. Approval, proof status, and certificate validity are separate decisions." />
            <Panel title="Submitted claims">
                <nav className="mb-4 flex flex-wrap gap-2" aria-label="Filter claims by status">
                    {statusFilters.map((filter) => (
                        <Link
                            key={filter.value}
                            href={`/hours/review?status=${filter.value}`}
                            aria-current={filter.value === currentStatus ? 'page' : undefined}
                            className={buttonVariants({ variant: filter.value === currentStatus ? 'secondary' : 'ghost', className: 'min-h-11' })}
                        >
                            {filter.label}
                        </Link>
                    ))}
                </nav>
                {records.length === 0 ? <Empty>No claims match this review queue.</Empty> : (
                    <Table headers={['Student', 'Event', 'Submitted', 'Approval', 'Actions']}>
                        {records.map((record) => {
                            const attendance = object(record.attendance);
                            const event = object(object(attendance.session).event);

                            return (
                                <TableRow key={text(record.id)}>
                                    <Cell>{person(record.student)}</Cell>
                                    <Cell>{text(event.title)}</Cell>
                                    <Cell>{date(record.updated_at)}</Cell>
                                    <Cell><Status value={record.status} /></Cell>
                                    <Cell><Link className="font-semibold text-civic underline" href={`/hours/review/${text(record.id)}`}>Review</Link></Cell>
                                </TableRow>
                            );
                        })}
                    </Table>
                )}
                <PageNav page={object(props.records)} label="Claim pages" />
            </Panel>
        </AppShell>
    );
}
