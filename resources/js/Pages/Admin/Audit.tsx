import { TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

import { rows, text, date, person, statusLabel } from '@/lib/app';

import { Panel, PageHeading, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function Audit({ props }: { props: Data }) {
    const logs = rows(props.logs);

    return (
        <AppShell title="Audit log">
            <PageHeading eyebrow="Administration" title="Audit log" description="A read-only record of consequential actions." />
            <Panel title="Recent activity">
                {logs.length === 0 ? <Empty>No audit events found.</Empty> : (
                    <Table headers={['When', 'Actor', 'Action']}>
                        {logs.map((log) => (
                            <TableRow key={text(log.id)}>
                                <Cell>{date(log.created_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                <Cell>{person(log.actor)}</Cell>
                                <Cell>
                                    <Badge variant="outline">{statusLabel(text(log.action).replaceAll('.', ' '))}</Badge>
                                </Cell>
                            </TableRow>
                        ))}
                    </Table>
                )}
            </Panel>
        </AppShell>
    );
}
