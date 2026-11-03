import { Head, Link, usePage } from '@inertiajs/react';
import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { object, rows, text, date, hours, person } from '@/lib/app';

import { Status, Panel, PageHeading, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function ReviewQueue({ props }: { props: Data }) {
    const records = rows(props.records);

    return (
        <AppShell title="Hours review">
            <PageHeading eyebrow="Coordinator workspace" title="Hours review" description="Review submitted service records. Approval, proof status, and certificate validity are separate decisions." />
            <Panel title="Submitted claims">
                {records.length === 0 ? <Empty>No claims match this review queue.</Empty> : (
                    <Table headers={['Student', 'Event', 'Submitted', 'Approval', '']}>
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
            </Panel>
        </AppShell>
    );
}
